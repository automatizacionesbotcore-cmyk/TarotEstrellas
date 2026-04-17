import { MoonStar, Sun } from 'lucide-react';
import { useThemeStore } from '../../stores/themeStore';

export function ThemeToggle() {
  const theme = useThemeStore((state) => state.theme);
  const toggleTheme = useThemeStore((state) => state.toggleTheme);

  return (
    <button type="button" className="toggle-btn" onClick={toggleTheme}>
      {theme === 'dark' ? <Sun size={16} /> : <MoonStar size={16} />}
      <span>{theme === 'dark' ? 'Modo claro' : 'Modo oscuro'}</span>
    </button>
  );
}
