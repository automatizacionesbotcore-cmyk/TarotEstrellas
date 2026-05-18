import axios from 'axios';
import { useAuthStore } from '../stores/authStore';
import { useToastStore } from '../stores/toastStore';

function resolveApiBaseUrl() {
  const fromEnv = import.meta.env.VITE_API_URL;

  if (fromEnv) {
    return fromEnv;
  }

  if (typeof window === 'undefined') {
    return 'http://localhost:8000/api';
  }

  const { origin, pathname } = window.location;
  const frontendDistMarker = '/frontend/dist';

  if (pathname.includes(frontendDistMarker)) {
    const projectRoot = pathname.slice(0, pathname.indexOf(frontendDistMarker));
    return `${origin}${projectRoot}/backend/public/index.php/api`;
  }

  return `${origin}/api`;
}

const baseURL = resolveApiBaseUrl();

export const api = axios.create({
  baseURL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const url = String(error.config?.url ?? '');

    if (status === 401) {
      useAuthStore.getState().clearSession();
      if (window.location.pathname !== '/auth/login') {
        window.location.href = '/auth/login';
      }
    } else if (status === 403 && !url.includes('/auth/login')) {
      useToastStore.getState().addToast('error', 'No tienes permisos para realizar esta acción.');
    } else if (status >= 500 || error.code === 'ECONNABORTED') {
      useToastStore.getState().addToast('error', 'Error temporal del servidor. Reintenta en unos segundos.');
    }

    return Promise.reject(error);
  },
);
