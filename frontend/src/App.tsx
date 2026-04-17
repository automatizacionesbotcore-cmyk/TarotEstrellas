import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { PublicLayout } from './layouts/PublicLayout';
import { AuthLayout } from './layouts/AuthLayout';
import { AppLayout } from './layouts/AppLayout';
import { LandingPage } from './pages/public/LandingPage';
import { ServiciosPage } from './pages/public/ServiciosPage';
import { ServicioDetallePage } from './pages/public/ServicioDetallePage';
import { LoginPage } from './pages/auth/LoginPage';
import { RegisterPage } from './pages/auth/RegisterPage';
import { ForgotPasswordPage } from './pages/auth/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/auth/ResetPasswordPage';
import { DashboardPage } from './pages/app/DashboardPage';
import { MiCuentaPage } from './pages/app/MiCuentaPage';
import { MisConsultasPage } from './pages/app/MisConsultasPage';
import { useAuthStore } from './stores/authStore';
import { AuthCardModal } from './components/ui/AuthCardModal';

function AuthOnly({ children }: { children: React.ReactElement }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  return isAuthenticated ? children : <Navigate to="/auth/login" replace />;
}

function GuestOnly({ children }: { children: React.ReactElement }) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  return isAuthenticated ? <Navigate to="/app" replace /> : children;
}

export function App() {
  return (
    <>
    <AuthCardModal />
    <Routes>
      <Route element={<PublicLayout />}>
        <Route path="/" element={<LandingPage />} />
        <Route path="/servicios" element={<ServiciosPage />} />
        <Route path="/servicios/:slug" element={<ServicioDetallePage />} />
      </Route>

      <Route
        path="/auth"
        element={
          <GuestOnly>
            <AuthLayout />
          </GuestOnly>
        }
      >
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="forgot-password" element={<ForgotPasswordPage />} />
        <Route path="reset-password" element={<ResetPasswordPage />} />
      </Route>

      <Route
        path="/app"
        element={
          <AuthOnly>
            <AppLayout />
          </AuthOnly>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="mis-consultas" element={<MisConsultasPage />} />
        <Route path="mi-cuenta" element={<MiCuentaPage />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </>
  );
}
