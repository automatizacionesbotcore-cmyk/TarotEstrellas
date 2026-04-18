import { motion } from 'framer-motion';
import { Link, useParams } from 'react-router-dom';
import { useEffect, useState } from 'react';

type LegalDoc = {
  title: string;
  version: string;
  paragraphs: string[];
};

type Slug = 'terminos' | 'privacidad' | 'cookies' | 'reembolsos';

const META: Record<Slug, { eyebrow: string; pageTitle: string }> = {
  terminos:   { eyebrow: '✦ Marco legal',    pageTitle: 'Términos y condiciones | TarotEstrellas' },
  privacidad: { eyebrow: '✦ Tu privacidad',  pageTitle: 'Política de privacidad | TarotEstrellas' },
  cookies:    { eyebrow: '✦ Transparencia',  pageTitle: 'Política de cookies | TarotEstrellas'    },
  reembolsos: { eyebrow: '✦ Tu protección',  pageTitle: 'Política de reembolsos | TarotEstrellas' },
};

const VALID_SLUGS: Slug[] = ['terminos', 'privacidad', 'cookies', 'reembolsos'];

const BASE = import.meta.env.PROD
  ? '/tarotEstrella/tarotestrellas/frontend/dist/'
  : '/';

export function LegalPage() {
  const { slug = '' } = useParams<{ slug: string }>();
  const [doc, setDoc]         = useState<LegalDoc | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState(false);

  const validSlug = VALID_SLUGS.includes(slug as Slug) ? (slug as Slug) : null;
  const meta      = validSlug ? META[validSlug] : null;

  useEffect(() => {
    if (!validSlug) { setLoading(false); setError(true); return; }
    setLoading(true);
    setError(false);
    fetch(`${BASE}legal/${validSlug}.json`)
      .then((r) => { if (!r.ok) throw new Error(); return r.json() as Promise<LegalDoc>; })
      .then(setDoc)
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, [validSlug]);

  useEffect(() => {
    document.title = meta?.pageTitle ?? 'Legal | TarotEstrellas';
  }, [meta]);

  return (
    <main className="legal-page">
      <motion.div
        className="legal-page-inner"
        initial={{ opacity: 0, y: 18 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
      >
        <Link to="/" className="legal-back">← Volver al inicio</Link>

        {loading ? (
          <p className="legal-loading">Cargando documento…</p>
        ) : error || !doc ? (
          <div className="dash-empty">
            <span className="dash-empty-icon">🌙</span>
            <p>Documento no encontrado.</p>
            <Link className="btn-secondary" to="/">Ir al inicio</Link>
          </div>
        ) : (
          <>
            {meta && <p className="dash-eyebrow">{meta.eyebrow}</p>}
            <h1 className="dash-title">{doc.title}</h1>
            <p className="legal-version">Versión {doc.version}</p>

            <div className="legal-content">
              {doc.paragraphs.map((p, i) => (
                <motion.p
                  key={i}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.35, delay: i * 0.05 }}
                >
                  {p}
                </motion.p>
              ))}
            </div>

            <div className="legal-footer-nav">
              <p className="legal-also">Ver también:</p>
              <div className="legal-links">
                {VALID_SLUGS.filter((s) => s !== validSlug).map((s) => (
                  <Link key={s} to={`/legal/${s}`} className="legal-link">
                    {META[s].pageTitle.split(' | ')[0]}
                  </Link>
                ))}
              </div>
            </div>
          </>
        )}
      </motion.div>
    </main>
  );
}
