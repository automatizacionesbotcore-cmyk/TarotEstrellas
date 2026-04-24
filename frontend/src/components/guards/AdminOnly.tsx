import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';

export function AdminOnly({ children }: { children: React.ReactElement }) {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const isAdmin         = useAuthStore((s) => s.isAdmin());

  if (!isAuthenticated) return <Navigate to="/auth/login" replace />;
  if (!isAdmin)         return <Navigate to="/app" replace />;
  return children;
}
