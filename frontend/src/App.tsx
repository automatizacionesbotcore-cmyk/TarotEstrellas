import React, { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { Toaster } from './components/ui/Toaster';
import { PublicLayout } from './layouts/PublicLayout';
import { AuthLayout } from './layouts/AuthLayout';
import { AppLayout } from './layouts/AppLayout';
import { AdminOnly } from './components/guards/AdminOnly';
import { AdminLayout } from './layouts/AdminLayout';
import { LandingPage } from './pages/public/LandingPage';
import { ServiciosPage } from './pages/public/ServiciosPage';
import { ServicioDetallePage } from './pages/public/ServicioDetallePage';
import { EspecialistaDetallePage } from './pages/public/EspecialistaDetallePage';
import { LoginPage } from './pages/auth/LoginPage';
import { RegisterPage } from './pages/auth/RegisterPage';
import { ForgotPasswordPage } from './pages/auth/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/auth/ResetPasswordPage';
import { VerifyEmailPage } from './pages/auth/VerifyEmailPage';
import { NotFoundPage } from './pages/public/NotFoundPage';
import { GoogleCallbackPage } from './pages/auth/GoogleCallbackPage';
import { DashboardPage } from './pages/app/DashboardPage';
import { MiCuentaPage } from './pages/app/MiCuentaPage';
import { MisConsultasPage } from './pages/app/MisConsultasPage';
import { MembresiaPage } from './pages/app/MembresiaPage';
import { useAuthStore } from './stores/authStore';
import { AuthCardModal } from './components/ui/AuthCardModal';

// Lazy-load heavy pages (Stripe, Daily.co, R3F) para reducir el bundle inicial
const PagarCitaPage  = lazy(() => import('./pages/app/PagarCitaPage').then((m) => ({ default: m.PagarCitaPage })));
const PagarSaldoPage = lazy(() => import('./pages/app/PagarSaldoPage').then((m) => ({ default: m.PagarSaldoPage })));
const SalaVideoPage  = lazy(() => import('./pages/app/SalaVideoPage').then((m) => ({ default: m.SalaVideoPage })));
const DetalleCitaPage = lazy(() => import('./pages/app/DetalleCitaPage').then((m) => ({ default: m.DetalleCitaPage })));
const LegalPage      = lazy(() => import('./pages/public/LegalPage').then((m) => ({ default: m.LegalPage })));

const AdminDashboardPage        = lazy(() => import('./pages/app/admin/AdminDashboardPage').then((m) => ({ default: m.AdminDashboardPage })));
const AdminReembolsosPage       = lazy(() => import('./pages/app/admin/AdminReembolsosPage').then((m) => ({ default: m.AdminReembolsosPage })));
const AdminReembolsoDetallePage = lazy(() => import('./pages/app/admin/AdminReembolsoDetallePage').then((m) => ({ default: m.AdminReembolsoDetallePage })));
const AdminComprobantesPage     = lazy(() => import('./pages/app/admin/AdminComprobantesPage').then((m) => ({ default: m.AdminComprobantesPage })));
const AdminCitasPage            = lazy(() => import('./pages/app/admin/AdminCitasPage').then((m) => ({ default: m.AdminCitasPage })));
const AdminServiciosPage        = lazy(() => import('./pages/app/admin/AdminServiciosPage').then((m) => ({ default: m.AdminServiciosPage })));
const AdminSettingsPage         = lazy(() => import('./pages/app/admin/AdminSettingsPage').then((m) => ({ default: m.AdminSettingsPage })));
const AdminDisponibilidadPage   = lazy(() => import('./pages/app/admin/AdminDisponibilidadPage').then((m) => ({ default: m.AdminDisponibilidadPage })));
const AdminEspecialistasPage    = lazy(() => import('./pages/app/admin/AdminEspecialistasPage').then((m) => ({ default: m.AdminEspecialistasPage })));

function PageLoader() {
  return (
    <main className="page-content" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '60vh' }}>
      <p style={{ color: 'var(--text-muted)' }}>Cargando…</p>
    </main>
  );
}

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
    <Toaster />
    <Routes>
      <Route element={<PublicLayout />}>
        <Route path="/" element={<LandingPage />} />
        <Route path="/servicios" element={<ServiciosPage />} />
        <Route path="/servicios/:slug" element={<ServicioDetallePage />} />
        <Route path="/especialistas/:slug" element={<EspecialistaDetallePage />} />
        <Route
          path="/legal/:slug"
          element={
            <Suspense fallback={<PageLoader />}>
              <LegalPage />
            </Suspense>
          }
        />
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
        <Route
          path="citas/:id/pagar"
          element={<Suspense fallback={<PageLoader />}><PagarCitaPage /></Suspense>}
        />
        <Route
          path="citas/:id/pagar-saldo"
          element={<Suspense fallback={<PageLoader />}><PagarSaldoPage /></Suspense>}
        />
        <Route path="membresia" element={<MembresiaPage />} />
        <Route
          path="sala/:uuid"
          element={<Suspense fallback={<PageLoader />}><SalaVideoPage /></Suspense>}
        />
        <Route
          path="citas/:id"
          element={<Suspense fallback={<PageLoader />}><DetalleCitaPage /></Suspense>}
        />
        <Route path="mi-cuenta" element={<MiCuentaPage />} />
        <Route
          path="admin"
          element={
            <AdminOnly>
              <AdminLayout />
            </AdminOnly>
          }
        >
          <Route index                       element={<Suspense fallback={<PageLoader />}><AdminDashboardPage        /></Suspense>} />
          <Route path="reembolsos"           element={<Suspense fallback={<PageLoader />}><AdminReembolsosPage       /></Suspense>} />
          <Route path="reembolsos/:uuid"     element={<Suspense fallback={<PageLoader />}><AdminReembolsoDetallePage /></Suspense>} />
          <Route path="comprobantes"         element={<Suspense fallback={<PageLoader />}><AdminComprobantesPage     /></Suspense>} />
          <Route path="citas"                element={<Suspense fallback={<PageLoader />}><AdminCitasPage            /></Suspense>} />
          <Route path="servicios"            element={<Suspense fallback={<PageLoader />}><AdminServiciosPage        /></Suspense>} />
          <Route path="disponibilidad"       element={<Suspense fallback={<PageLoader />}><AdminDisponibilidadPage   /></Suspense>} />
          <Route path="especialistas"        element={<Suspense fallback={<PageLoader />}><AdminEspecialistasPage    /></Suspense>} />
          <Route path="settings"             element={<Suspense fallback={<PageLoader />}><AdminSettingsPage         /></Suspense>} />
        </Route>
      </Route>

      <Route path="/auth/verify-email" element={<VerifyEmailPage />} />
      <Route path="/auth/google/callback" element={<GoogleCallbackPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
    </>
  );
}
