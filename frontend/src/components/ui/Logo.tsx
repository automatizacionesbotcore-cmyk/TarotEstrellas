import { useThemeStore } from '../../stores/themeStore';

interface LogoProps {
  size?: number;
  showText?: boolean;
}

export function Logo({ size = 26, showText = true }: LogoProps) {
  const theme = useThemeStore((state) => state.theme);
  const isDark = theme === 'dark';
  const logoSrc = showText
    ? (isDark ? '/brand/logo-dark.png' : '/brand/logo-light.png')
    : (isDark ? '/brand/mark-dark.png' : '/brand/mark-light.png');
  const height = showText ? Math.round(size * 1.75) : size;

  return (
    <span className={showText ? 'logo-wrap logo-wrap--full' : 'logo-wrap logo-wrap--mark'} aria-label="TarotEstrellas">
      <img
        src={logoSrc}
        alt="TarotEstrellas"
        className={isDark ? 'brand-logo brand-logo--dark' : 'brand-logo brand-logo--light'}
        style={{ height }}
      />
    </span>
  );
}
