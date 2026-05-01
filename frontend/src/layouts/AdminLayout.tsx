import { NavLink, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

const BASE_ITEMS = [
  { to: '/app/admin',                label: 'Resumen',        end: true,  superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/mis-citas',      label: 'Mi agenda',      end: false, superAdminOnly: false, especialistaOnly: true  },
  { to: '/app/admin/reembolsos',     label: 'Reembolsos',     end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/comprobantes',        label: 'Comprobantes',     end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/cuentas-bancarias',   label: 'Cuentas bancarias',end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/citas',          label: 'Citas',          end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/clientes',       label: 'Clientes',       end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/cupones',        label: 'Cupones',        end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/paquetes',       label: 'Paquetes',       end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/plantillas',     label: 'Plantillas',     end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/notificaciones', label: 'Notificaciones', end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/audit-log',      label: 'Audit log',      end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/agente/metrics', label: 'IA · Métricas',  end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/api-usage',      label: 'APIs · Consumo', end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/resenas',        label: 'Reseñas',        end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/servicios',      label: 'Servicios',      end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/disponibilidad', label: 'Disponibilidad', end: false, superAdminOnly: false, especialistaOnly: false },
  { to: '/app/admin/especialistas',  label: 'Especialistas',  end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/reportes',       label: 'Reportes',       end: false, superAdminOnly: true,  especialistaOnly: false },
  { to: '/app/admin/settings',       label: 'Ajustes',        end: false, superAdminOnly: false, especialistaOnly: false },
];

export function AdminLayout() {
  const user          = useAuthStore((s) => s.user);
  const isSuperAdmin  = Array.isArray(user?.roles) && user.roles.includes('super_admin');
  const isEspecialista = Array.isArray(user?.roles) && user.roles.includes('admin_especialista');

  const navItems = BASE_ITEMS.filter((i) => {
    if (i.superAdminOnly && !isSuperAdmin) return false;
    if (i.especialistaOnly && !isEspecialista && !isSuperAdmin) return false;
    return true;
  });

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <h3 className="admin-sidebar-title">Administración</h3>
        <nav>
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `admin-nav-link${isActive ? ' is-active' : ''}`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <section className="admin-content">
        <Outlet />
      </section>
    </div>
  );
}
