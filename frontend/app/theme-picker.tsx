'use client';

import { useEffect, useState } from 'react';

const storageKey = 'portal-cidadao-tema';

export function ThemePicker() {
  const [dark, setDark] = useState(false);

  useEffect(() => {
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    function sync() {
      const saved = document.documentElement.dataset.theme;
      setDark(saved ? saved === 'dark' : system.matches);
    }
    sync();
    system.addEventListener('change', sync);
    return () => system.removeEventListener('change', sync);
  }, []);

  function toggle() {
    const value = dark ? 'light' : 'dark';
    setDark(!dark);
    document.documentElement.dataset.theme = value;
    try {
      localStorage.setItem(storageKey, value);
    } catch {
      // A escolha continua activa quando o navegador impede a gravação.
    }
  }

  return <button type="button" className="theme-picker" aria-label="Modo escuro"
    aria-pressed={dark} title={dark ? 'Activar modo claro' : 'Activar modo escuro'} onClick={toggle}>
    <svg aria-hidden="true" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5" />
    </svg>
    <svg aria-hidden="true" viewBox="0 0 24 24">
      <path d="M20.5 14A8.5 8.5 0 0 1 10 3.5 8.5 8.5 0 1 0 20.5 14Z" />
    </svg>
  </button>;
}
