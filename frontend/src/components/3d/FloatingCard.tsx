/**
 * Catálogo — "Carta Flotante" (spec 6.6.2)
 * Carta de tarot 3D flotando suavemente. Reacciona a hover.
 * Paleta adaptada al tema activo (oscuro: púrpura profundo + dorado / claro: lavanda + rosa).
 */
import { Canvas, useFrame } from '@react-three/fiber';
import { Float } from '@react-three/drei';
import { Suspense, useRef, useState } from 'react';
import * as THREE from 'three';
import { hasWebGL, prefersReducedMotion } from './webgl';
import { useThemeStore } from '../../stores/themeStore';

// Paletas por tema
const PALETTE = {
  dark: {
    cardBody:         '#1a0f2e',
    cardEmissive:     '#2e1a4a',
    emissiveIntensity: 0.4,
    metalness:        0.55,
    roughness:        0.3,
    frame:            '#c9a84c',
    frameOpacity:     0.38,
    star:             '#c9a84c',
    starOpacity:      0.55,
    ring:             '#c9a84c',
    ringOpacity:      0.35,
    ambient:          '#1a0f2e',
    ambientIntensity:  0.35,
    mainLight:        '#c9a84c',
    mainLightBase:     2,
    mainLightHover:    3,
    backLight:        '#6b3fa0',
    backLightIntensity: 0.9,
  },
  light: {
    cardBody:         '#f0e8f8',
    cardEmissive:     '#d4a0b0',
    emissiveIntensity: 0.12,
    metalness:        0.15,
    roughness:        0.55,
    frame:            '#d4a0b0',
    frameOpacity:     0.5,
    star:             '#8b6bae',
    starOpacity:      0.65,
    ring:             '#d4a0b0',
    ringOpacity:      0.45,
    ambient:          '#e8d4f0',
    ambientIntensity:  0.9,
    mainLight:        '#d4a0b0',
    mainLightBase:     1.8,
    mainLightHover:    2.8,
    backLight:        '#8b6bae',
    backLightIntensity: 0.6,
  },
};

type Palette = {
  cardBody: string; cardEmissive: string; emissiveIntensity: number;
  metalness: number; roughness: number;
  frame: string; frameOpacity: number;
  star: string; starOpacity: number;
  ring: string; ringOpacity: number;
  ambient: string; ambientIntensity: number;
  mainLight: string; mainLightBase: number; mainLightHover: number;
  backLight: string; backLightIntensity: number;
};

function CardPlaceholder() {
  return <div className="canvas-loading" aria-hidden="true" />;
}

function TarotCard({ onHover, p }: { onHover: (v: boolean) => void; p: Palette }) {
  const meshRef = useRef<THREE.Mesh>(null);
  const scaleRef = useRef(new THREE.Vector3(1, 1, 1));

  useFrame(({ clock }) => {
    if (!meshRef.current) return;
    meshRef.current.rotation.y = Math.sin(clock.elapsedTime * 0.35) * 0.3;
    meshRef.current.scale.lerp(scaleRef.current, 0.08);
  });

  return (
    <Float speed={1.6} rotationIntensity={0.12} floatIntensity={0.55}>
      <group>
        {/* Marco */}
        <mesh position={[0, 0, -0.02]}>
          <boxGeometry args={[1.62, 2.54, 0.04]} />
          <meshBasicMaterial color={p.frame} transparent opacity={p.frameOpacity} />
        </mesh>

        {/* Cuerpo */}
        <mesh
          ref={meshRef}
          onPointerEnter={() => { scaleRef.current.set(1.06, 1.06, 1.06); onHover(true); }}
          onPointerLeave={() => { scaleRef.current.set(1, 1, 1); onHover(false); }}
        >
          <boxGeometry args={[1.52, 2.45, 0.07]} />
          <meshStandardMaterial
            color={p.cardBody}
            metalness={p.metalness}
            roughness={p.roughness}
            emissive={p.cardEmissive}
            emissiveIntensity={p.emissiveIntensity}
          />
        </mesh>

        {/* Estrella central */}
        <mesh position={[0, 0, 0.045]}>
          <circleGeometry args={[0.28, 8]} />
          <meshBasicMaterial color={p.star} transparent opacity={p.starOpacity} />
        </mesh>

        {/* Anillo ornamental */}
        <mesh position={[0, 0, 0.044]}>
          <ringGeometry args={[0.35, 0.4, 32]} />
          <meshBasicMaterial color={p.ring} transparent opacity={p.ringOpacity} />
        </mesh>
      </group>
    </Float>
  );
}

function Scene({ p }: { p: Palette }) {
  const [hovered, setHovered] = useState(false);

  return (
    <>
      <ambientLight intensity={p.ambientIntensity} color={p.ambient} />
      <pointLight
        position={[2, 3, 4]}
        intensity={hovered ? p.mainLightHover : p.mainLightBase}
        color={p.mainLight}
      />
      <pointLight position={[-2, -1, 3]} intensity={p.backLightIntensity} color={p.backLight} />
      <TarotCard onHover={setHovered} p={p} />
    </>
  );
}

export default function FloatingCard() {
  const { theme } = useThemeStore();

  if (!hasWebGL() || prefersReducedMotion()) return null;

  const p = PALETTE[theme];

  return (
    <div className="floating-card-canvas" aria-hidden="true">
      <Suspense fallback={<CardPlaceholder />}>
        <Canvas
          camera={{ position: [0, 0, 5], fov: 42 }}
          gl={{ alpha: true, antialias: true }}
          style={{ background: 'transparent', width: '100%', height: '100%' }}
          dpr={Math.min(typeof window !== 'undefined' ? window.devicePixelRatio : 1, 2)}
        >
          <Scene p={p} />
        </Canvas>
      </Suspense>
    </div>
  );
}
