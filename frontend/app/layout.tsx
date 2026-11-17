import Link from 'next/link';
import Image from 'next/image';
import { ThemePicker } from './theme-picker';
import './portal.css';

export const metadata = { title: 'Portal de Denúncias · Luanda' };

export default function Layout({ children }: { children: React.ReactNode }) {
  return <html lang="pt-AO" suppressHydrationWarning><head>
    <script dangerouslySetInnerHTML={{ __html: `try { const theme = localStorage.getItem('portal-cidadao-tema'); if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme; } catch {}` }} />
  </head><body>
    <header><nav><Link className="brand" href="/">
      <Image className="brand-light" src="/brand/portal-denuncias.svg" alt="Portal de Denúncias" width={195} height={58} unoptimized />
      <Image className="brand-dark" src="/brand/portal-denuncias-secundario.svg" alt="Portal de Denúncias" width={195} height={58} unoptimized />
    </Link>
      <Link className="track-link" href="/acompanhar">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <circle cx="10" cy="10" r="6.5" /><path d="m15 15 5.5 5.5" />
        </svg>
        Acompanhar
      </Link>
      <ThemePicker /></nav></header>
    <main>{children}</main>
    <footer>Portal de Denúncias · Luanda</footer>
  </body></html>;
}
