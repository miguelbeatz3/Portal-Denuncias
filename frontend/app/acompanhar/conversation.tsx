'use client';

import { useRef, useState, type FormEvent } from 'react';
import { submit } from '../request';

export type ReportMessage = { id: number; sender: 'admin' | 'entity' | 'citizen'; message: string; created_at: string };

const senderLabels = { admin: 'Administração', entity: 'Entidade', citizen: 'A sua mensagem' };
const timestamp = new Intl.DateTimeFormat('pt-AO', { dateStyle: 'long', timeStyle: 'short', timeZone: 'Africa/Luanda' });

export function Conversation({ credentials, initialMessages }: { credentials: FormData; initialMessages: ReportMessage[] }) {
  const [messages, setMessages] = useState(initialMessages);
  const [pending, setPending] = useState<'send' | 'refresh'>();
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const attempt = useRef<{ text: string; id: string } | null>(null);

  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending) return;
    const form = event.currentTarget;
    const data = new FormData(form);
    const text = String(data.get('message')).trim();
    setNotice('');
    if (!text) {
      setError('Escreva uma mensagem.');
      return;
    }
    setError('');
    setPending('send');
    try {
      if (attempt.current?.text !== text) attempt.current = { text, id: crypto.randomUUID() };
      credentials.forEach((value, key) => data.set(key, value));
      data.set('message', text);
      data.set('message_id', attempt.current.id);
      const sent: ReportMessage = await submit('tracking/messages', data);
      setMessages(current => current.some(message => message.id === sent.id) ? current
        : [...current, sent].sort((first, second) => first.id - second.id));
      attempt.current = null;
      form.reset();
      setNotice('Mensagem enviada.');
    } catch (error) {
      setError(error instanceof TypeError ? 'Falha de ligação. Verifique a rede e tente novamente.' : (error as Error).message);
    } finally {
      setPending(undefined);
    }
  }

  async function refresh() {
    if (pending) return;
    setPending('refresh');
    setError('');
    setNotice('');
    try {
      const result = await submit('tracking', credentials);
      setMessages(result.messages ?? []);
      setNotice('Conversa actualizada.');
    } catch (error) {
      setError(error instanceof TypeError ? 'Falha de ligação. Verifique a rede e tente novamente.' : (error as Error).message);
    } finally {
      setPending(undefined);
    }
  }

  return <section className="tracking-conversation" aria-labelledby="conversation-title">
    <div className="tracking-conversation-heading">
      <h2 id="conversation-title">Mensagens da denúncia</h2>
      <button type="button" className="secondary" onClick={refresh} disabled={!!pending}>
        {pending === 'refresh' ? 'A actualizar…' : 'Actualizar conversa'}
      </button>
    </div>
    {messages.length ? <ol className="tracking-messages">
      {messages.map(entry => <li key={entry.id} data-sender={entry.sender}>
        <header><strong>{senderLabels[entry.sender]}</strong>
          <time dateTime={entry.created_at}>{timestamp.format(new Date(entry.created_at))}</time></header>
        <p>{entry.message}</p>
      </li>)}
    </ol> : <p className="tracking-hint">Ainda não há mensagens nesta denúncia.</p>}
    <form onSubmit={send}>
      <label>A sua mensagem<textarea name="message" rows={3} maxLength={2000} required readOnly={pending === 'send'} /></label>
      {error && <p role="alert" className="tracking-error">{error}</p>}
      <div className="tracking-message-actions">
        <p role="status" className="tracking-hint">{notice}</p>
        <button disabled={!!pending}>{pending === 'send' ? 'A enviar…' : 'Enviar mensagem'}</button>
      </div>
    </form>
  </section>;
}
