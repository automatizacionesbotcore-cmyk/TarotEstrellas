import { NavLink, Outlet } from 'react-router-dom';

const navItems = [
  { to: '/app/admin',              label: 'Resumen',      end: true  },
  { to: '/app/admin/reembolsos',   label: 'Reembolsos',   end: false },
  { to: '/app/admin/comprobantes', label: 'Comprobantes', end: false },
  { to: '/app/admin/citas',        label: 'Citas',        end: false },
  { to: '/app/admin/settings',     label: 'Ajustes',      end: false },
];

export function AdminLayout() {
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
