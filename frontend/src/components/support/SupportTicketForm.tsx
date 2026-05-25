import { useState, type FormEvent } from 'react';
import {
  buildSupportFormData,
  createMySupportTicket,
  createPublicSupportTicket,
  SUPPORT_TYPES,
  type SupportTicket,
} from '../../lib/supportApi';

type Props = {
  mode: 'public' | 'authenticated';
  onCreated?: (ticket: SupportTicket) => void;
};

export function SupportTicketForm({ mode, onCreated }: Props) {
  const [nombre, setNombre] = useState('');
  const [email, setEmail] = useState('');
  const [tipoError, setTipoError] = useState('login');
  const [asunto, setAsunto] = useState('');
  const [descripcion, setDescripcion] = useState('');
  const [imagenes, setImagenes] = useState<FileList | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setSubmitting(true);
    setMessage(null);
    setError(null);

    try {
      const formData = buildSupportFormData({
        nombre,
        email,
        tipo_error: tipoError,
        asunto,
        descripcion,
        imagenes,
      });
      const response = mode === 'public'
        ? await createPublicSupportTicket(formData)
        : await createMySupportTicket(formData);

      setMessage(`${response.message} Código: ${response.data.codigo}`);
      setNombre('');
      setEmail('');
      setAsunto('');
      setDescripcion('');
      setImagenes(null);
      onCreated?.(response.data);
    } catch (err: any) {
      const validation = err?.response?.data?.errors;
      const first = validation ? (Object.values(validation)[0] as string[])?.[0] : null;
      setError(err?.response?.data?.message ?? first ?? 'No se pudo crear la solicitud de soporte.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form className="support-form" onSubmit={handleSubmit}>
      {mode === 'public' ? (
        <div className="auth-grid">
          <label>
            Nombre
            <input className="input-field" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
          </label>
          <label>
            Correo
            <input className="input-field" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </label>
        </div>
      ) : null}

      <div className="auth-grid">
        <label>
          Tipo de problema
          <select className="input-field" value={tipoError} onChange={(e) => setTipoError(e.target.value)}>
            {SUPPORT_TYPES.map((type) => (
              <option key={type.value} value={type.value}>{type.label}</option>
            ))}
          </select>
        </label>
        <label>
          Asunto
          <input className="input-field" value={asunto} onChange={(e) => setAsunto(e.target.value)} required maxLength={160} />
        </label>
      </div>

      <label>
        Descripción
        <textarea
          className="input-field"
          rows={5}
          value={descripcion}
          onChange={(e) => setDescripcion(e.target.value)}
          required
          placeholder="Cuéntanos qué ocurre, en qué pantalla pasa y qué mensaje ves."
        />
      </label>

      <label>
        Capturas del error
        <input
          className="input-field"
          type="file"
          accept="image/*"
          multiple
          onChange={(e) => setImagenes(e.target.files)}
        />
        <span className="field-hint">Puedes adjuntar hasta 3 imágenes.</span>
      </label>

      {error ? <p className="form-error">{error}</p> : null}
      {message ? <p className="form-success">{message}</p> : null}

      <button className="btn-primary btn-shimmer" type="submit" disabled={submitting}>
        {submitting ? 'Enviando solicitud...' : 'Enviar solicitud'}
      </button>
    </form>
  );
}
