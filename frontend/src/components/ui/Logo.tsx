import { useThemeStore } from '../../stores/themeStore';

// 8-pointed star — La Estrella (carta XVII del tarot): esperanza, guía, iluminación
const STAR_PATH =
  'M12,2 L13.5,8.3 L19.1,4.9 L15.7,10.5 L22,12 L15.7,13.5 L19.1,19.1 L13.5,15.7 L12,22 L10.5,15.7 L4.9,19.1 L8.3,13.5 L2,12 L8.3,10.5 L4.9,4.9 L10.5,8.3 Z';

interface LogoProps {
  size?: number;
  showText?: boolean;
}

export function Logo({ size = 26, showText = true }: LogoProps) {
  const theme = useThemeStore((state) => state.theme);
  const isDark = theme === 'dark';

  return (
    <span className="logo-wrap" aria-label="TarotEstrellas">
      <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
        className={isDark ? 'logo-star logo-star--dark' : 'logo-star logo-star--light'}
      >
        <path d={STAR_PATH} fill={isDark ? '#C9A84C' : '#D4A0B0'} />
      </svg>

      {showText && (
        <span className={isDark ? 'logo-text logo-text--dark' : 'logo-text logo-text--light'}>
          TarotEstrellas
        </span>
      )}
    </span>
  );
}
