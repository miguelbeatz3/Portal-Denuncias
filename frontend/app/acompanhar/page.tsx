'use client';
import Link from 'next/link';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { submit } from '../request';
import './tracking.css';

type History = { reference: string; status: string; description: string; classified_as_false: boolean; events: { message: string; created_at: string }[] };

const timestamp = new Intl.DateTimeFormat('pt-AO', {
  dateStyle: 'long', timeStyle: 'short', timeZone: 'Africa/Luanda',
});

export default function Track() {
  const [history, setHistory] = useState<History>();
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const resultHeading = useRef<HTMLHeadingElement>(null);
  const referenceField = useRef<HTMLInputElement>(null);
  const returning = useRef(false);

  useEffect(() => {
    if (history) resultHeading.current?.focus();
    else if (returning.current) {
      referenceField.current?.focus();
      returning.current = false;
    }
  }, [history]);

  async function track(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy) return;
    const credentials = new FormData(event.currentTarget);
    setBusy(true);
    setError('');
    try { setHistory(await submit('tracking', credentials)); }
    catch (e) { setError(e instanceof TypeError ? 'Falha de ligação. Verifique a rede e volte a tentar.' : (e as Error).message); }
    finally { setBusy(false); }
  }

  return <section className="tracking">
    <Link className="tracking-back" href="/">← Página inicial</Link>
    <h1>Acompanhar denúncia</h1>
    {history ? <div className="tracking-result">
      <div className="tracking-overview" data-status={history.status}>
        <p className="tracking-caption">Estado actual</p>
        <h2 ref={resultHeading} tabIndex={-1}>{history.status}</h2>
        {history.classified_as_false && <aside className="tracking-classification">
          <strong>Denúncia Falsa</strong>
          <p>A Administração classificou esta denúncia como falsa.</p>
          <p>Acusar alguém perante uma autoridade ou em público, sabendo que a acusação é falsa e com a intenção de provocar um processo criminal contra essa pessoa, é crime de denúncia caluniosa.</p>
          <a href="https://sic.gov.ao/wp-content/uploads/2021/10/codigo-penal-angolano-lei-no-3820-de-11-de-novembro_2021-05-08-06-18-59-864.pdf">Código Penal Angolano, artigo 352.º</a>
        </aside>}
        <p className="tracking-reference">Referência <strong>{history.reference}</strong></p>
        <button type="button" className="tracking-again" onClick={() => {
          returning.current = true;
          setHistory(undefined);
        }}>Consultar outra denúncia</button>
      </div>
      <article className="tracking-card" aria-labelledby="history-title">
        <div className="tracking-description">
          <h2>Descrição da denúncia</h2>
          <p>{history.description}</p>
        </div>
        <h2 id="history-title">Histórico da denúncia</h2>
        {history.events.length ? <>
          <p className="tracking-hint">Actualizações da mais recente à mais antiga, em hora de Luanda.</p>
          <ol className="tracking-timeline">{history.events.slice().reverse().map((entry, index) => {
            const date = entry.created_at.replace(' ', 'T') + 'Z';
            return <li key={index}><time dateTime={date}>{timestamp.format(new Date(date))}</time>
              <p>{entry.message}</p></li>;
          })}</ol>
        </> : <p>Ainda não há actualizações públicas para esta denúncia.</p>}
      </article>
    </div> : <div className="tracking-entry">
      <p className="tracking-guide">Use a referência e o código secreto que recebeu ao registar a denúncia para consultar o estado e as actualizações.</p>
      <form className="tracking-card" onSubmit={track} aria-labelledby="credentials-title">
        <h2 id="credentials-title">Dados de acompanhamento</h2>
        <label>Referência da denúncia<input ref={referenceField} required name="reference" placeholder="LD-…" maxLength={40} spellCheck={false} /></label>
        <label>Código secreto<input required name="secret" type="password" maxLength={64} autoComplete="off" /></label>
        {error && <p role="alert" className="tracking-error">{error}</p>}
        <button disabled={busy}>{busy ? 'A consultar…' : 'Consultar estado'}</button>
      </form>
    </div>}
  </section>;
}
