/**
 * Landing — "Cielo Estrellado" (spec 6.6.2)
 * Fondo 3D decorativo: estrellas titilando + constelaciones doradas + rotación imperceptible.
 * Solo modo oscuro — en modo claro las estrellas blancas son invisibles.
 */
import { Canvas, useFrame } from '@react-three/fiber';
import { Stars, Line } from '@react-three/drei';
import { Suspense, useRef } from 'react';
import * as THREE from 'three';
import { hasWebGL, isMobileDevice, prefersReducedMotion } from './webgl';
import { useThemeStore } from '../../stores/themeStore';

const CONSTELLATION_A: THREE.Vector3[] = [
  new THREE.Vector3(-10, 4,  -30),
  new THREE.Vector3(-7,  7,  -28),
  new THREE.Vector3(-5,  5,  -32),
  new THREE.Vector3(-3,  8,  -29),
  new THREE.Vector3(-7,  7,  -28),
  new THREE.Vector3(-8,  2,  -31),
];

const CONSTELLATION_B: THREE.Vector3[] = [
  new THREE.Vector3(6,  -3, -35),
  new THREE.Vector3(9,   1, -33),
  new THREE.Vector3(12,  0, -36),
  new THREE.Vector3(11, -4, -34),
  new THREE.Vector3(8,  -5, -32),
  new THREE.Vector3(6,  -3, -35),
];

function Scene({ mobile }: { mobile: boolean }) {
  const groupRef = useRef<THREE.Group>(null);

  useFrame(() => {
    if (groupRef.current) {
      groupRef.current.rotation.y += 0.0005;
    }
  });

  return (
    <group ref={groupRef}>
      <Stars
        radius={120}
        depth={55}
        count={mobile ? 300 : 1400}
        factor={7}
        saturation={0}
        fade
        speed={0.4}
      />
      <Line points={CONSTELLATION_A} color="#C9A84C" lineWidth={1} transparent opacity={0.22} />
      <Line points={CONSTELLATION_B} color="#C9A84C" lineWidth={1} transparent opacity={0.22} />
    </group>
  );
}

export default function StarField() {
  const { theme } = useThemeStore();

  // Estrellas blancas son invisibles en modo claro — el hero tiene su propio fondo CSS
  if (theme === 'light' || !hasWebGL() || prefersReducedMotion()) return null;

  const mobile = isMobileDevice();

  return (
    <div
      aria-hidden="true"
      style={{
        position: 'absolute',
        inset: 0,
        zIndex: 0,
        pointerEvents: 'none',
        overflow: 'hidden',
        borderRadius: 'inherit',
      }}
    >
      <Suspense fallback={null}>
        <Canvas
          camera={{ position: [0, 0, 1], fov: 60 }}
          gl={{ alpha: true, antialias: false, powerPreference: 'low-power' }}
          style={{ background: 'transparent', width: '100%', height: '100%' }}
          dpr={Math.min(typeof window !== 'undefined' ? window.devicePixelRatio : 1, 1.5)}
        >
          <Scene mobile={mobile} />
        </Canvas>
      </Suspense>
    </div>
  );
}
