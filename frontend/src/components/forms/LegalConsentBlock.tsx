import { useEffect, useState } from 'react';

type LegalModalType = 'terminos' | 'privacidad';
type LegalDocument  = { title: string; version: string; paragraphs: string[] };

const LEGAL_URLS: Record<LegalModalType, string> = {
  terminos:   '/legal/terminos.json',
  privacidad: '/legal/privacidad.json',
};

type Props = {
  terminosAceptados: boolean;
  privacidadAceptada: boolean;
  mayor18Aceptado: boolean;
  onTerminosAccepted: (version: string) => void;
  onPrivacidadAccepted: (version: string) => void;
  onMayor18Change: (val: boolean) => void;
  showMayor18?: boolean;
};

export function LegalConsentBlock({
  terminosAceptados,
  privacidadAceptada,
  mayor18Aceptado,
  onTerminosAccepted,
  onPrivacidadAccepted,
  onMayor18Change,
  showMayor18 = true,
}: Props) {
  const [activeLegal,    setActiveLegal]    = useState<LegalModalType | null>(null);
  const [scrolledBottom, setScrolledBottom] = useState(false);
  const [scrollProgress, setScrollProgress] = useState(0);
  const [legalDocs,      setLegalDocs]      = useState<Partial<Record<LegalModalType, LegalDocument>>>({});
  const [legalLoading,   setLegalLoading]   = useState(false);
  const [legalError,     setLegalError]     = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLegalLoading(true);
    setLegalError(null);

    Promise.all(
      (Object.keys(LEGAL_URLS) as LegalModalType[]).map(async (type) => {
        const res = await fetch(LEGAL_URLS[type]);
        if (!res.ok) throw new Error(`No se pudo cargar ${type}`);
        return [type, await res.json() as LegalDocument] as const;
      }),
    )
      .then((entries) => {
        if (cancelled) return;
        const next: Partial<Record<LegalModalType, LegalDocument>> = {};
        entries.forEach(([t, d]) => { next[t] = d; });
        setLegalDocs(next);
      })
      .catch(() => { if (!cancelled) setLegalError('No se pudieron cargar los documentos legales.'); })
      .finally(() => { if (!cancelled) setLegalLoading(false); });

    return () => { cancelled = true; };
  }, []);

  const openLegal  = (type: LegalModalType) => { setActiveLegal(type); setScrolledBottom(false); setScrollProgress(0); };
  const closeLegal = () => { setActiveLegal(null); setScrolledBottom(false); setScrollProgress(0); };

  const acceptLegal = () => {
    if (!activeLegal || !legalConfig) return;
    if (activeLegal === 'terminos')   onTerminosAccepted(legalConfig.version);
    if (activeLegal === 'privacidad') onPrivacidadAccepted(legalConfig.version);
    closeLegal();
  };

  const legalConfig = activeLegal ? legalDocs[activeLegal] ?? null : null;

  return (
    <>
      <div className="checkbox-row checkbox-row-legal">
        <input type="checkbox" checked={terminosAceptados} readOnly required onClick={() => openLegal('terminos')} />
        <button className="legal-trigger" type="button" onClick={() => openLegal('terminos')}>
          {terminosAceptados ? 'Términos aceptados' : 'Leer y aceptar términos y condiciones'}
        </button>
      </div>

      <div className="checkbox-row checkbox-row-legal">
        <input type="checkbox" checked={privacidadAceptada} readOnly required onClick={() => openLegal('privacidad')} />
        <button className="legal-trigger" type="button" onClick={() => openLegal('privacidad')}>
          {privacidadAceptada ? 'Política aceptada' : 'Leer y aceptar política de privacidad'}
        </button>
      </div>

      {showMayor18 && (
        <label className="checkbox-row">
          <input type="checkbox" checked={mayor18Aceptado} required
            onChange={(e) => onMayor18Change(e.target.checked)} />
          Confirmo que soy mayor de 18 años
        </label>
      )}

      {/* Legal modal — loaded document */}
      {legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section className="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
            <header className="legal-modal-header">
              <h2 id="legal-modal-title">{legalConfig.title}</h2>
              <button type="button" className="legal-close" onClick={closeLegal} aria-label="Cerrar">Cerrar</button>
            </header>
            <p className="legal-version">Versión {legalConfig.version}</p>
            <div className="legal-scroll"
              onScroll={(e) => {
                const t = e.currentTarget;
                const max = t.scrollHeight - t.clientHeight;
                if (max <= 0) { setScrollProgress(100); setScrolledBottom(true); return; }
                const pct = Math.min(100, Math.round((t.scrollTop / max) * 100));
                setScrollProgress(pct);
                if (pct >= 99) setScrolledBottom(true);
              }}>
              {legalConfig.paragraphs.map((p) => <p key={p}>{p}</p>)}
            </div>
            <footer className="legal-modal-actions">
              <span className="legal-hint">
                {scrolledBottom
                  ? `Lectura completa (${scrollProgress}%).`
                  : `Progreso: ${scrollProgress}%. Desplázate hasta el final para aceptar.`}
              </span>
              <button type="button" className="btn-primary" disabled={!scrolledBottom} onClick={acceptLegal}>
                Aceptar
              </button>
            </footer>
          </section>
        </div>
      ) : null}

      {/* Legal modal — loading/error state */}
      {activeLegal && !legalConfig ? (
        <div className="legal-modal-backdrop" role="presentation">
          <section className="legal-modal" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title-loading">
            <header className="legal-modal-header">
              <h2 id="legal-modal-title-loading">Documento legal</h2>
              <button type="button" className="legal-close" onClick={closeLegal} aria-label="Cerrar">Cerrar</button>
            </header>
            <p className="legal-hint">
              {legalLoading ? 'Cargando documento...' : legalError ?? 'Documento no disponible.'}
            </p>
          </section>
        </div>
      ) : null}
    </>
  );
}
