type Props = {
  name?: string | null;
  className?: string;
};

export function DefaultSpecialistAvatar({ name, className = '' }: Props) {
  const label = name ? `Imagen por defecto de ${name}` : 'Imagen por defecto de especialista';

  return (
    <div className={`default-specialist-avatar ${className}`.trim()} role="img" aria-label={label}>
      <span className="default-specialist-avatar__halo" aria-hidden="true" />
      <svg viewBox="0 0 120 120" aria-hidden="true" focusable="false">
        <defs>
          <radialGradient id="specialistAvatarGlow" cx="48%" cy="38%" r="62%">
            <stop offset="0%" stopColor="#f8d978" stopOpacity="0.95" />
            <stop offset="55%" stopColor="#d7ad43" stopOpacity="0.72" />
            <stop offset="100%" stopColor="#5b3a8f" stopOpacity="0.18" />
          </radialGradient>
          <linearGradient id="specialistAvatarCard" x1="18" y1="8" x2="96" y2="114">
            <stop stopColor="#241437" />
            <stop offset="1" stopColor="#10091f" />
          </linearGradient>
        </defs>
        <circle className="default-specialist-avatar__base" cx="60" cy="60" r="56" fill="url(#specialistAvatarCard)" />
        <circle cx="60" cy="60" r="50" fill="none" stroke="#d7ad43" strokeOpacity="0.55" strokeWidth="2" />
        <path
          className="default-specialist-avatar__moon"
          d="M73.2 24.5c-12.8 3.2-22.4 14.8-22.4 28.6 0 15.6 12.1 28.4 27.4 29.4-5.2 4.2-11.8 6.7-19 6.7-16.8 0-30.4-13.6-30.4-30.4 0-17.3 14.5-31.2 32-30.3 4.4.2 8.6 1.4 12.4 3.4Z"
          fill="url(#specialistAvatarGlow)"
        />
        <path d="M82 70.4l3.7 7.6 8.4 1.2-6.1 5.9 1.5 8.3-7.5-4-7.5 4 1.4-8.3-6-5.9 8.3-1.2 3.8-7.6Z" fill="#f8d978" />
        <path d="M34.8 25.2l2.2 4.4 4.9.7-3.5 3.4.8 4.8-4.4-2.3-4.3 2.3.8-4.8-3.6-3.4 4.9-.7 2.2-4.4Z" fill="#f8d978" opacity="0.92" />
        <path d="M92.8 35.8l1.5 3.1 3.4.5-2.5 2.4.6 3.4-3-1.6-3.1 1.6.6-3.4-2.5-2.4 3.5-.5 1.5-3.1Z" fill="#f8d978" opacity="0.72" />
      </svg>
    </div>
  );
}
