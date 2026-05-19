import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

const BASE_ITEMS = [
  { to: '/app/admin',                label: 'Resumen',        end: true,  superAdminOnly: false, especialistaOnly: false, info: 'Vista general con métricas clave del negocio: citas próximas, ingresos del mes, reseñas pendientes y alertas operativas.' },
  { to: '/app/admin/mis-citas',      label: 'Mi agenda',      end: false, superAdminOnly: false, especialistaOnly: true,  info: 'Calendario propio del especialista con sus consultas asignadas, sala de videollamada y notas de cada cita.' },
  { to: '/app/admin/reembolsos',     label: 'Reembolsos',     end: false, superAdminOnly: false, especialistaOnly: false, info: 'Solicitudes de devolución de pagos. Aprueba, rechaza o gestiona reembolsos parciales/totales con motivo registrado.' },
  { to: '/app/admin/comprobantes',   label: 'Comprobantes',   end: false, superAdminOnly: false, especialistaOnly: false, info: 'Comprobantes de pago por transferencia subidos por clientes. Verifica el monto y aprueba o rechaza para liberar la cita.' },
  { to: '/app/admin/cuentas-bancarias', label: 'Cuentas bancarias', end: false, superAdminOnly: false, especialistaOnly: false, info: 'Gestiona las cuentas bancarias visibles a clientes para pagos por transferencia (banco, titular, número, RUT).' },
  { to: '/app/admin/citas',          label: 'Citas',          end: false, superAdminOnly: false, especialistaOnly: false, info: 'Historial completo de todas las consultas: estado (pendiente/pagada/realizada/cancelada), pago, especialista, cliente y resumen IA.' },
  { to: '/app/admin/clientes',       label: 'Clientes',       end: false, superAdminOnly: false, especialistaOnly: false, info: 'Base de clientes con su perfil, sesiones, datos natales, consentimientos y briefing IA pre-consulta.' },
  { to: '/app/admin/cupones',        label: 'Cupones',        end: false, superAdminOnly: false, especialistaOnly: false, info: 'Códigos de descuento (% o monto fijo), límite de usos, fecha de expiración y servicios aplicables.' },
  { to: '/app/admin/paquetes',       label: 'Paquetes',       end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Bundles de varias consultas con precio promocional. Define cantidad, vigencia y servicios incluidos.' },
  { to: '/app/admin/plantillas',     label: 'Plantillas',     end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Plantillas editables de email/WhatsApp para confirmaciones, recordatorios, encuestas, etc. Variables {nombre}, {fecha}…' },
  { to: '/app/admin/notificaciones', label: 'Notificaciones', end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Bitácora de todos los emails/WhatsApps enviados por el sistema con estado (entregado/error/leído) y reintentos.' },
  { to: '/app/admin/audit-log',      label: 'Audit log',      end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Registro de auditoría de acciones sensibles (cambios admin, pagos, reembolsos) con usuario, IP y datos antes/después.' },
  { to: '/app/admin/agente/metrics', label: 'IA · Métricas',  end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Métricas del agente Astrea: conversaciones, tokens consumidos, costo, satisfacción y conversiones.' },
  { to: '/app/admin/api-usage',      label: 'APIs · Consumo', end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Consumo y límites de Anthropic, OpenAI y Daily.co. Configura topes mensuales y emails de alerta.' },
  { to: '/app/admin/resenas',        label: 'Reseñas',        end: false, superAdminOnly: false, especialistaOnly: false, info: 'Calificaciones y comentarios de clientes. Modera, responde y publica/oculta reseñas.' },
  { to: '/app/admin/servicios',      label: 'Servicios',      end: false, superAdminOnly: false, especialistaOnly: false, info: 'Catálogo de tipos de consulta (Tarot, Cartas, Carta Astral): duración, precio multi-moneda, descripción.' },
  { to: '/app/admin/disponibilidad', label: 'Disponibilidad', end: false, superAdminOnly: false, especialistaOnly: false, info: 'Horarios laborales por día, bloqueos puntuales y feriados. Define tu agenda para que clientes puedan agendar.' },
  { to: '/app/admin/especialistas',  label: 'Especialistas',  end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Alta de nuevos especialistas (envía link de reset al email), gestión de perfiles públicos y activación.' },
  { to: '/app/admin/reportes',       label: 'Reportes',       end: false, superAdminOnly: true,  especialistaOnly: false, info: 'Reportes financieros y operativos: ingresos, citas por especialista, conversión, exportable a CSV.' },
  { to: '/app/admin/settings',       label: 'Ajustes',        end: false, superAdminOnly: false, especialistaOnly: false, info: 'Configuración general: datos de la empresa, integraciones, branding, T&C, privacidad.' },
];

export function AdminLayout() {
  const user          = useAuthStore((s) => s.user);
  const isSuperAdmin  = Array.isArray(user?.roles) && user.roles.includes('super_admin');
  const isEspecialista = Array.isArray(user?.roles) && user.roles.includes('admin_especialista');
  const location = useLocation();
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => { setDrawerOpen(false); }, [location.pathname]);

  const navItems = BASE_ITEMS.filter((i) => {
    if (i.superAdminOnly && !isSuperAdmin) return false;
    if (i.especialistaOnly && !isEspecialista && !isSuperAdmin) return false;
    if (i.to === '/app/admin/servicios' && isEspecialista && !isSuperAdmin) return false;
    return true;
  });

  const currentLabel = navItems.find((i) =>
    i.end ? location.pathname === i.to : location.pathname.startsWith(i.to)
  )?.label || 'Administración';

  return (
    <div className="admin-shell">
      <button
        type="button"
        className="admin-mobile-toggle"
        onClick={() => setDrawerOpen(true)}
        aria-label="Abrir menú de administración"
      >
        <span aria-hidden>☰</span>
        <span>{currentLabel}</span>
      </button>

      {drawerOpen && (
        <div
          className="admin-drawer-backdrop"
          onClick={() => setDrawerOpen(false)}
          aria-hidden
        />
      )}

      <aside className={`admin-sidebar${drawerOpen ? ' is-open' : ''}`}>
        <div className="admin-sidebar-header">
          <h3 className="admin-sidebar-title">Administración</h3>
          <button
            type="button"
            className="admin-drawer-close"
            onClick={() => setDrawerOpen(false)}
            aria-label="Cerrar menú"
          >×</button>
        </div>
        <nav>
          {navItems.map((item) => (
            <div key={item.to} className="admin-nav-item">
              <NavLink
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  `admin-nav-link${isActive ? ' is-active' : ''}`
                }
              >
                {item.label}
              </NavLink>
              <button
                type="button"
                className="admin-nav-info"
                aria-label={`Información sobre ${item.label}`}
                title={item.info}
              >
                i
              </button>
            </div>
          ))}
        </nav>
      </aside>
      <section className="admin-content">
        <Outlet />
      </section>
    </div>
  );
}
