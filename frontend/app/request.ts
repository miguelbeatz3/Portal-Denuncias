export async function submit(path: string, values: FormData) {
  const multipart = path === 'reports' && values.has('evidence_files[]');
  const response = await fetch('/api/' + path, {
    method: 'POST', cache: 'no-store',
    headers: multipart ? { Accept: 'application/json' } : { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: multipart ? values : JSON.stringify(Object.fromEntries(values)),
  });
  if (response.status === 429) throw new Error('Demasiados pedidos. Aguarde um minuto e volte a tentar.');
  if (response.status === 404) throw new Error('Verifique a referência e o código secreto.');
  if (response.status === 413) throw new Error('Os anexos excedem o tamanho permitido.');
  if (response.status === 422) {
    const result = await response.json();
    if (Object.keys(result.errors ?? {}).some(field => field.startsWith('evidence_files'))) {
      throw new Error('Verifique os anexos. São permitidos até 3 ficheiros PDF, JPG ou PNG de 2 MB cada.');
    }
    throw new Error(result.errors?.evidence_description?.[0] ?? result.errors?.message?.[0] ?? 'Verifique os campos do formulário.');
  }
  if (!response.ok) throw new Error('O serviço está indisponível. Volte a tentar.');
  return response.json();
}
