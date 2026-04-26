import { NavLink, Outlet } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';

const BASE_ITEMS = [
  { to: '/app/admin',                label: 'Resumen',        end: true,  superAdminOnly: false },
  { to: '/app/admin/reembolsos',     label: 'Reembolsos',     end: false, superAdminOnly: false },
  { to: '/app/admin/comprobantes',   label: 'Comprobantes',   end: false, superAdminOnly: false },
  { to: '/app/admin/citas',          label: 'Citas',          end: false, superAdminOnly: false },
  { to: '/app/admin/servicios',      label: 'Servicios',      end: false, superAdminOnly: false },
  { to: '/app/admin/disponibilidad', label: 'Disponibilidad', end: false, superAdminOnly: false },
  { to: '/app/admin/especialistas',  label: 'Especialistas',  end: false, superAdminOnly: true  },
  { to: '/app/admin/settings',       label: 'Ajustes',        end: false, superAdminOnly: false },
];

export function AdminLayout() {
  const user         = useAuthStore((s) => s.user);
  const isSuperAdmin = Array.isArray(user?.roles) && user.roles.includes('super_admin');

  const navItems = BASE_ITEMS.filter((i) => !i.superAdminOnly || isSuperAdmin);

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
