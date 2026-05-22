type StorageKind = 'local' | 'session';

function getStorage(kind: StorageKind): Storage | null {
  if (typeof window === 'undefined') return null;

  try {
    const storage = kind === 'local' ? window.localStorage : window.sessionStorage;
    const probe = `tarotestrellas-${kind}-probe`;
    storage.setItem(probe, '1');
    storage.removeItem(probe);
    return storage;
  } catch {
    return null;
  }
}

export function storageGet(kind: StorageKind, key: string): string | null {
  try {
    return getStorage(kind)?.getItem(key) ?? null;
  } catch {
    return null;
  }
}

export function storageSet(kind: StorageKind, key: string, value: string): void {
  try {
    getStorage(kind)?.setItem(key, value);
  } catch {
    // El navegador puede bloquear storage en primera visita, modo privado o WebView.
  }
}

export function storageRemove(kind: StorageKind, key: string): void {
  try {
    getStorage(kind)?.removeItem(key);
  } catch {
    // Mantener la app usable aunque el storage no esté disponible.
  }
}
