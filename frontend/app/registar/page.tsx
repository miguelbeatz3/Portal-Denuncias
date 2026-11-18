'use client';

import Link from 'next/link';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { submit } from '../request';
import { EvidenceUpload } from './evidence-upload';
import { RegistrationReceipt, type ReceiptData } from './registration-receipt';
import './registo.css';

type Category = { name: string; description: string | null };
type Step = 'details' | 'review' | 'done';
const questions = [
  ['Identificação', 'Como prefere registar a denúncia?'],
  ['Assunto', 'Qual destes assuntos melhor descreve o que aconteceu?'],
  ['Ocorrência', 'O que aconteceu?'],
  ['Local e data', 'Onde e quando aconteceu?'],
  ['Provas', 'Tem alguma prova que queira partilhar?'],
] as const;

export default function Register() {
  const [step, setStep] = useState<Step>('details');
  const [question, setQuestion] = useState(0);
  const [identified, identify] = useState(false);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [municipality, setMunicipality] = useState('');
  const [date, setDate] = useState('');
  const [today, setToday] = useState('');
  const [category, setCategory] = useState<string | null>('');
  const [location, setLocation] = useState('');
  const [description, setDescription] = useState('');
  const [hasEvidence, setHasEvidence] = useState<boolean | null>(null);
  const [evidenceDescription, setEvidenceDescription] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const categoryHint = categories.find(option => option.name === category)?.description;
  const [municipalities, setMunicipalities] = useState<string[]>([]);
  const [loadFailed, setLoadFailed] = useState(false);
  const [attempt, retry] = useState(0);
  const [receipt, setReceipt] = useState<ReceiptData>();
  const [error, setError] = useState('');
  const [copyStatus, setCopyStatus] = useState('');
  const [busy, setBusy] = useState(false);
  const heading = useRef<HTMLHeadingElement>(null);
  const previousStep = useRef(step);
  const questionHeading = useRef<HTMLHeadingElement>(null);
  const previousQuestion = useRef(question);

  useEffect(() => {
    setToday(new Intl.DateTimeFormat('sv-SE', { timeZone: 'Africa/Luanda' }).format(new Date()));
    const controller = new AbortController();
    async function loadOptions<T>(path: string): Promise<T[]> {
      const response = await fetch('/api/' + path, { signal: controller.signal, cache: 'no-store' });
      if (!response.ok) throw new Error();
      const options: T[] = await response.json();
      if (!options.length && path === 'municipalities') throw new Error();
      return options;
    }
    Promise.all([loadOptions<Category>('categories?details=1'), loadOptions<string>('municipalities')]).then(([categories, municipalities]) => {
      setCategories(categories);
      setMunicipalities(municipalities);
    }).catch(() => { if (!controller.signal.aborted) setLoadFailed(true); });
    return () => controller.abort();
  }, [attempt]);

  useEffect(() => {
    if (previousStep.current !== step || previousQuestion.current !== question) {
      (step === 'details' ? questionHeading : heading).current?.focus();
    }
    previousStep.current = step;
    previousQuestion.current = question;
  }, [step, question]);

  function review(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const tel = form.elements.namedItem('phone') as HTMLInputElement | null;
    if (tel && phone.trim() && !/^(?:[0-9]{9}|\+[1-9][0-9]{7,14})$/.test(phone.replace(/[\s()-]/g, ''))) {
      tel.setCustomValidity('Indique 9 dígitos ou um número internacional com o indicativo +.');
      tel.reportValidity();
      return;
    }
    for (const [field, value, minimum, message] of [
      ['name', name, 1, 'Indique o seu nome.'],
      ['location', location, 1, 'Indique o local da ocorrência.'],
      ['description', description, 10, 'Descreva a ocorrência com pelo menos 10 caracteres.'],
    ] as const) {
      const input = form.elements.namedItem(field) as HTMLInputElement | HTMLTextAreaElement | null;
      if (input && value.trim().length < minimum) {
        input.setCustomValidity(message);
        input.reportValidity();
        return;
      }
    }
    if (question === 4 && hasEvidence && !evidenceDescription.trim() && !files.length) {
      setError('Descreva as provas ou anexe pelo menos um ficheiro.');
      form.querySelector<HTMLTextAreaElement>('[name="evidence_description"]')?.focus();
      return;
    }
    setError('');
    if (question === questions.length - 1) setStep('review');
    else setQuestion(question + 1);
  }

  async function register() {
    if (busy) return;
    setBusy(true);
    setError('');
    const payload = new FormData();
    payload.set('mode', identified ? 'identified' : 'anonymous');
    if (identified) payload.set('name', name.trim());
    if (identified && phone.trim()) payload.set('phone', phone.trim());
    payload.set('municipality', municipality);
    if (date) payload.set('occurred_on', date);
    payload.set('category', category ?? '');
    payload.set('location', location.trim());
    payload.set('description', description.trim());
    payload.set('has_evidence', hasEvidence ? '1' : '0');
    if (hasEvidence) {
      if (evidenceDescription.trim()) payload.set('evidence_description', evidenceDescription.trim());
      files.forEach(file => payload.append('evidence_files[]', file));
    }
    try {
      const result = await submit('reports', payload);
      setReceipt({ ...result, issuedAt: new Date().toISOString(), subject: category?.trim() || 'Por classificar na triagem',
        municipality: municipality.trim(), location: location.trim(), occurredOn: date });
      setFiles([]);
      setStep('done');
    } catch (e) {
      setError(e instanceof TypeError ? 'Falha de ligação. Verifique a rede e tente novamente.' : (e as Error).message);
    } finally { setBusy(false); }
  }

  async function copyReceipt() {
    if (!receipt) return;
    try {
      await navigator.clipboard.writeText('Referência: ' + receipt.reference + '\nCódigo secreto: ' + receipt.secret);
      setCopyStatus('Dados copiados.');
    } catch {
      setCopyStatus('Seleccione e copie os dados abaixo, ou imprima o comprovativo.');
    }
  }

  const title = step === 'done' ? 'Denúncia recebida' : step === 'review' ? 'Rever denúncia' : 'Registar denúncia';
  return <section className="registration">
    <aside className="registration-guide">
      <Link className="back-home" href="/">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="m10 6-6 6 6 6M4 12h16" />
        </svg>
        Voltar ao início
      </Link>
      <h1 ref={heading} tabIndex={-1}>{title}</h1>
      <p>{step === 'done' ? 'Guarde a referência e o código secreto. Vai precisar de ambos para acompanhar a resposta.'
        : step === 'review' ? 'Confirme os dados antes de enviar. Pode voltar e alterar qualquer campo.'
        : 'Responda a uma pergunta de cada vez. No fim, pode rever tudo antes de enviar.'}</p>
      {step !== 'done' && <ol className="progress-list" aria-label="Progresso do registo">
        {[...questions.map(([label]) => label), 'Rever e enviar'].map((label, index) => <li key={label} aria-current={(step === 'details' ? question : questions.length) === index ? 'step' : undefined}>
          <span aria-hidden="true">{index + 1}</span>{label}</li>)}
      </ol>}
    </aside>
    {step === 'details' && <form className="register-card" onSubmit={review}>
      <header className="question-heading">
        <small>Passo {question + 1} de {questions.length + 1}</small>
        <h2 id="registration-question" ref={questionHeading} tabIndex={-1}>{questions[question][1]}</h2>
      </header>
      {question === 0 && <>
      <fieldset aria-label="Tipo de registo">
        <div className="mode-grid">{[false, true].map(value => <label key={String(value)} className="mode-option">
          <input type="radio" name="mode" checked={identified === value}
            onChange={() => { identify(value); if (!value) { setName(''); setPhone(''); } }} />
          <span><strong className="mode-title">
            <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
              {value ? <>
                <circle cx="12" cy="8" r="3.5" />
                <path d="M5 21v-2a7 7 0 0 1 14 0v2" />
              </> : <path d="M12 3 4 6v5c0 5 3.5 8.5 8 11 4.5-2.5 8-6 8-11V6z" />}
            </svg>
            {value ? 'Identificada' : 'Anónima'}
          </strong><small>{value ? 'Com o meu nome' : 'Sem indicar o nome'}</small></span>
        </label>)}</div>
      </fieldset>
      {identified ? <><label>Nome<input name="name" required maxLength={150} autoComplete="name" value={name}
        onChange={e => { e.target.setCustomValidity(''); setName(e.target.value); }} /></label>
        <label>Telefone de contacto (opcional)<small>Para contacto pela equipa responsável pela denúncia.</small>
          <input type="tel" name="phone" autoComplete="tel" placeholder="+244 9XX XXX XXX" maxLength={32} value={phone}
            onChange={e => { e.target.setCustomValidity(''); setPhone(e.target.value); }} /></label></>
        : <p className="privacy-note">Para preservar o anonimato, evite incluir o seu nome ou contactos na descrição.</p>}
      </>}
      {question === 1 && <div>
      <select id="category" name="category" required aria-labelledby="registration-question" aria-describedby={categoryHint ? 'category-hint' : undefined} value={category === null ? 'unknown' : category && 'category:' + category}
        onChange={e => setCategory(e.target.value === 'unknown' ? null : e.target.value.slice('category:'.length))}>
        <option disabled value="">Seleccione um assunto</option>
        <option value="unknown">Não sei dizer</option>
        {categories.map(c => <option key={c.name} value={'category:' + c.name}>{c.name}</option>)}</select>
      {categoryHint && <small id="category-hint">{categoryHint}</small>}</div>}
      {question === 2 && <div>
        <small id="description-hint">Conte o que viu e indique se a situação continua.</small>
        <textarea name="description" rows={4} minLength={10} maxLength={5000} required value={description}
          aria-labelledby="registration-question" aria-describedby="description-hint"
          onChange={e => { e.target.setCustomValidity(''); setDescription(e.target.value); }} />
      </div>}
      {(question === 1 || question === 3) && loadFailed && <div role="alert">Não foi possível carregar as opções de registo. <button type="button" className="text-action" onClick={() => { setLoadFailed(false); retry(n => n + 1); }}>Tentar novamente</button></div>}
      {question === 3 && <>
      <label>Município<select name="municipality" required value={municipality} onChange={e => setMunicipality(e.target.value)}>
        <option disabled value="">Seleccione o município da ocorrência</option>
        {municipalities.map(name => <option key={name}>{name}</option>)}</select></label>
      <label>Local da ocorrência<input name="location" placeholder="Bairro, rua ou ponto de referência" maxLength={200} required value={location}
        onChange={e => { e.target.setCustomValidity(''); setLocation(e.target.value); }} /></label>
      <label>Data da ocorrência (opcional)<input type="date" name="occurred_on" max={today} value={date}
        onChange={e => setDate(e.target.value)} /></label>
      </>}
      {question === 4 && <>
      <fieldset aria-label="Tem provas da ocorrência?">
        <div className="mode-grid">{[true, false].map(value => <label className="mode-option" key={String(value)}>
          <input type="radio" name="has_evidence" required checked={hasEvidence === value} onChange={() => {
            setHasEvidence(value); setError('');
            if (!value) { setEvidenceDescription(''); setFiles([]); }
          }} />{value ? 'Sim' : 'Não'}
        </label>)}</div>
      </fieldset>
      {hasEvidence && <>
        <label>Descrição das provas<small>Pode descrever as provas, anexar ficheiros ou fazer ambos.</small>
          <textarea name="evidence_description" rows={3} maxLength={5000} value={evidenceDescription}
            onChange={e => { setEvidenceDescription(e.target.value); setError(''); }} /></label>
        <EvidenceUpload files={files} onChange={setFiles} onError={setError} />
      </>}
      </>}
      {error && <p role="alert">{error}</p>}
      <div className="register-actions question-actions">
        {question > 0 && <button type="button" className="secondary" onClick={() => { setError(''); setQuestion(question - 1); }}>Voltar</button>}
        <button type="submit" disabled={question === 3 && !municipalities.length}>
          {question === questions.length - 1 ? 'Rever denúncia' : 'Continuar'} <span aria-hidden="true">→</span>
        </button>
      </div>
    </form>}
    {step === 'review' && <div className="register-card">
      <h2>Resumo da denúncia</h2>
      <dl className="summary">{[['Identificação', identified ? name.trim() : 'Anónima'], ['Assunto', category ?? 'Não sei dizer — a classificar na triagem'],
        ['Telefone de contacto', identified ? phone.trim() : ''], ['Data da ocorrência', date.split('-').reverse().join('/')],
        ['Município', municipality.trim()], ['Local da ocorrência', location.trim()], ['Descrição', description.trim()],
        ['Tem provas?', hasEvidence ? 'Sim' : 'Não'], ['Descrição das provas', evidenceDescription.trim()],
        ['Ficheiros anexados', files.map(file => file.name).join('\n')]].filter(([, value]) => value).map(([label, value]) =>
        <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
      {error && <p role="alert">{error}</p>}
      <div className="register-actions"><button type="button" className="secondary" disabled={busy} onClick={() => { setError(''); setQuestion(0); setStep('details'); }}>Editar dados</button>
        <button type="button" disabled={busy} onClick={register}>{busy ? 'A enviar…' : 'Submeter denúncia'}</button></div>
    </div>}
    {step === 'done' && receipt && <div className="register-card receipt-card">
      <RegistrationReceipt {...receipt} />
      <div className="register-actions"><button type="button" onClick={copyReceipt}>Copiar dados</button>
        <button type="button" className="secondary" onClick={() => window.print()}>Imprimir comprovativo</button></div>
      <p role="status">{copyStatus}</p>
      <Link href="/acompanhar">Acompanhar denúncia →</Link>
    </div>}
  </section>;
}
