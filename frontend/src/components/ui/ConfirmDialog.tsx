type Props = {
  open:     boolean;
  title:    string;
  message:  string;
  onConfirm: () => void;
  onCancel:  () => void;
  confirmLabel?: string;
  loading?:      boolean;
};

export function ConfirmDialog({ open, title, message, onConfirm, onCancel, confirmLabel = 'Confirmar', loading }: Props) {
  if (!open) return null;

  return (
    <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
      <div className="confirm-dialog">
        <h3>{title}</h3>
        <p>{message}</p>
        <div className="confirm-dialog-actions">
          <button type="button" className="btn-secondary" onClick={onCancel} disabled={loading}>Cancelar</button>
          <button type="button" className="btn-primary"   onClick={onConfirm} disabled={loading}>
            {loading ? 'Procesando…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
