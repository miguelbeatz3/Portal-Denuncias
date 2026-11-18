import Image from 'next/image';
import './receipt.css';

export type ReceiptData = {
  reference: string; secret: string; issuedAt: string;
  subject: string; municipality: string; location: string; occurredOn: string;
};

export function RegistrationReceipt({ reference, secret, issuedAt, subject, municipality, location, occurredOn }: ReceiptData) {
  const issued = new Intl.DateTimeFormat('pt-AO', {
    dateStyle: 'long', timeStyle: 'short', timeZone: 'Africa/Luanda',
  }).format(new Date(issuedAt));

  return <div className="receipt-document" aria-label="Comprovativo de registo">
    <div className="receipt-brand">
      <Image src="/brand/portal-denuncias.svg" alt="Portal de Denúncias" width={195} height={58} unoptimized />
      <span>Luanda, Angola</span>
    </div>
    <div className="receipt-heading">
      <p className="receipt-eyebrow">Registo de denúncia</p>
      <h2>Comprovativo de registo</h2>
      <p>A sua denúncia foi recebida pelo Portal de Denúncias de Luanda.</p>
      <p className="receipt-date">Comprovativo emitido em <time dateTime={issuedAt}>{issued}</time> (hora de Luanda).</p>
    </div>
    <dl className="receipt-credentials">
      <div><dt>Referência da denúncia</dt><dd>{reference}</dd></div>
      <div><dt>Código secreto de acompanhamento</dt><dd>{secret}</dd></div>
    </dl>
    <div className="receipt-security">
      <strong>Guarde estes dados em segurança</strong>
      <p>A referência e o código secreto são necessários para consultar a denúncia. Quem tiver ambos poderá aceder ao estado e às actualizações. Não partilhe este comprovativo.</p>
    </div>
    <div className="receipt-report">
      <h3>Resumo da denúncia</h3>
      <dl className="receipt-details">
        <div className="receipt-detail-wide"><dt>Assunto</dt><dd>{subject}</dd></div>
        <div><dt>Município</dt><dd>{municipality}</dd></div>
        <div><dt>Data da ocorrência</dt><dd>{occurredOn ? <time dateTime={occurredOn}>{occurredOn.split('-').reverse().join('/')}</time> : 'Não indicada'}</dd></div>
        <div className="receipt-detail-wide"><dt>Local da ocorrência</dt><dd>{location}</dd></div>
      </dl>
    </div>
    <div className="receipt-footnote">
      <p>Este comprovativo confirma a recepção da denúncia. O assunto será analisado e encaminhado para a entidade competente.</p>
      <span>Portal de Denúncias · Luanda</span>
    </div>
  </div>;
}
