import { useEffect, useRef, useState } from 'react';

function EvidencePreview({ file, remove }: { file: File; remove: () => void }) {
  const [url, setUrl] = useState('');
  const pdf = /\.pdf$/i.test(file.name);
  useEffect(() => {
    const type = pdf ? 'application/pdf' : /\.png$/i.test(file.name) ? 'image/png' : 'image/jpeg';
    const preview = URL.createObjectURL(file.slice(0, file.size, type));
    setUrl(preview);
    return () => URL.revokeObjectURL(preview);
  }, [file, pdf]);

  return <li className="evidence-item">
    <div className="evidence-info">
      <span className="evidence-thumb">{pdf ? 'PDF' : url && <img src={url} alt="" />}</span>
      <div><strong>{file.name}</strong><small>{Math.max(1, Math.round(file.size / 1024))} KB</small></div>
      <button type="button" className="text-action" aria-label={'Remover ' + file.name} onClick={remove}>Remover</button>
    </div>
    {url && <details className="evidence-preview">
      <summary aria-label={'Pré-visualizar ' + file.name}>Pré-visualizar</summary>
      {pdf ? <object type="application/pdf" data={url} aria-label={'Pré-visualização de ' + file.name}>
        <a href={url} target="_blank" rel="noopener noreferrer">Abrir PDF</a>
      </object> : <img src={url} alt={'Pré-visualização de ' + file.name} />}
      {pdf && <a href={url} target="_blank" rel="noopener noreferrer">Abrir PDF num novo separador</a>}
    </details>}
  </li>;
}

type Props = { files: File[]; onChange: (files: File[]) => void; onError: (message: string) => void };

export function EvidenceUpload({ files, onChange, onError }: Props) {
  const picker = useRef<HTMLInputElement>(null);
  const [dragging, setDragging] = useState(false);
  function add(selection: File[]) {
    if (!selection.length) return;
    if (files.length + selection.length > 3) return onError('Pode anexar até 3 ficheiros. Remova um anexo para o substituir.');
    for (const file of selection) {
      if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) return onError(file.name + ' tem um formato não suportado. Escolha PDF, JPG ou PNG.');
      if (file.size > 2 * 1024 * 1024) return onError(file.name + ' excede o limite de 2 MB.');
    }
    onChange([...files, ...selection]);
    onError('');
  }

  return <fieldset className="evidence-upload"><legend>Anexar provas</legend>
    <input ref={picker} type="file" multiple hidden accept=".pdf,.jpg,.jpeg,.png" onChange={event => {
      add(Array.from(event.target.files ?? []));
      event.target.value = '';
    }} />
    <button className="evidence-drop" type="button" data-dragging={dragging}
      aria-describedby="evidence-limits" onClick={() => picker.current?.click()}
      onDragOver={event => { event.preventDefault(); setDragging(true); }}
      onDragLeave={() => setDragging(false)}
      onDrop={event => { event.preventDefault(); setDragging(false); add(Array.from(event.dataTransfer.files)); }}>
      <svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 15V3m-5 5 5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
      </svg>
      <strong>Seleccionar ficheiros</strong><span>ou arraste-os para aqui</span>
    </button>
    <small id="evidence-limits">Até 3 ficheiros PDF, JPG ou PNG · 2 MB por ficheiro</small>
    {files.length > 0 && <ul className="evidence-list">{files.map((file, index) =>
      <EvidencePreview key={file.name + file.lastModified + ':' + index} file={file} remove={() => {
        onChange(files.filter((_, i) => i !== index)); onError('');
      }} />)}</ul>}
  </fieldset>;
}
