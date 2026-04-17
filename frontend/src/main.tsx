import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { App } from './App';
import './style.css';
import { useThemeStore } from './stores/themeStore';

useThemeStore.getState().initialize();

const queryClient = new QueryClient();
const routerBaseName = import.meta.env.PROD ? '/tarotEstrella/tarotestrellas/frontend/dist' : '/';

ReactDOM.createRoot(document.getElementById('app') as HTMLElement).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter basename={routerBaseName}>
        <App />
      </BrowserRouter>
    </QueryClientProvider>
  </React.StrictMode>,
);
