import axios from 'axios';
import { useAuthStore } from '../stores/authStore';

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
    if (error.response?.status === 401) {
      useAuthStore.getState().clearSession();
      window.location.href = '/auth/login';
    }

    return Promise.reject(error);
  },
);
