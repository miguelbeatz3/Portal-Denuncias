import Link from 'next/link';
import Image from 'next/image';
import './inicio.css';

export default function Home() {
  return <div className="citizen-home">
    <section className="intro home-hero" aria-labelledby="home-title">
      <Image src="/baia-de-luanda.jpg" alt="Baía de Luanda vista da Ilha de Luanda" width={960} height={370} preload className="home-photo" />
      <div className="home-message">
    <p className="home-region">Província de Luanda</p>
    <h1 id="home-title">O que aconteceu no seu bairro?</h1>
    <p>Foi vítima ou testemunha de um furto, de uma ameaça ou de um acto de vandalismo? Conte o que aconteceu. Também pode comunicar problemas como uma rua sem iluminação ou uma vala entupida. A denúncia pode ser anónima ou identificada.</p>
    <div className="buttons">
      <Link className="primary" href="/registar">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="m15 5 4 4M4 20l4-1 12-12a2.8 2.8 0 0 0-4-4L4 15z" />
        </svg>
        Registar denúncia
      </Link>
      <Link className="secondary" href="/acompanhar">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <circle cx="10" cy="10" r="6.5" /><path d="m15 15 5.5 5.5" />
        </svg>
        Acompanhar denúncia
      </Link>
    </div>
      </div>
    </section>
    <aside className="emergency-notice" aria-label="Aviso de emergência">
      Se o crime estiver a acontecer ou houver perigo imediato, <a href="tel:111">ligue para o 111</a>.
    </aside>
    <section className="home-guide" aria-labelledby="home-guide-title">
      <h2 id="home-guide-title">Como funciona a sua denúncia?</h2>
      <ol>
        <li><h3>Conte o que aconteceu</h3><p>Indique o município e um ponto de referência. Se tiver provas, pode anexar fotografias ou documentos, ou descrever o que viu.</p></li>
        <li><h3>Guarde o comprovativo</h3><p>No fim do registo, recebe uma referência e um código secreto. Guarde ambos para poder voltar a consultar a denúncia.</p></li>
        <li><h3>Acompanhe a resposta</h3><p>A Administração analisa o relato e pode encaminhá-lo à entidade responsável. Em «Acompanhar denúncia», consulte o estado e as actualizações publicadas.</p></li>
      </ol>
    </section>
  </div>;
}
