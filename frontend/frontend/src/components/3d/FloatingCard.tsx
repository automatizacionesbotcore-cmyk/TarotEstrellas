/**
 * Catálogo — "Carta Flotante" (spec 6.6.2)
 * Carta de tarot 3D flotando suavemente. Reacciona a hover.
 */
import { Canvas, useFrame } from '@react-three/fiber';
import { Float } from '@react-three/drei';
import { Suspense, useRef, useState } from 'react';
import * as THREE from 'three';
import { hasWebGL, prefersReducedMotion } from './webgl';

// Cargando placeholder — brillo dorado
function CardPlaceholder() {
  return <div className="canvas-loading" aria-hidden="true" />;
}

function TarotCard({ onHover }: { onHover: (v: boolean) => void }) {
  const meshRef = useRef<THREE.Mesh>(null);
  const scaleRef = useRef(new THREE.Vector3(1, 1, 1));

  useFrame(({ clock }) => {
    if (!meshRef.current) return;
    // Rotación Y senoidal suave (spec: gira lentamente sobre eje Y)
    meshRef.current.rotation.y = Math.sin(clock.elapsedTime * 0.35) * 0.3;
    // Escala interpolada al hover
    meshRef.current.scale.lerp(scaleRef.current, 0.08);
  });

  return (
    <Float speed={1.6} rotationIntensity={0.12} floatIntensity={0.55}>
      <group>
        {/* Marco dorado (ligeramente más grande, detrás) */}
        <mesh position={[0, 0, -0.02]}>
          <boxGeometry args={[1.62, 2.54, 0.04]} />
          <meshBasicMaterial color="#c9a84c" transparent opacity={0.38} />
        </mesh>

        {/* Cuerpo de la carta */}
        <mesh
          ref={meshRef}
          onPointerEnter={() => { scaleRef.current.set(1.06, 1.06, 1.06); onHover(true); }}
          onPointerLeave={() => { scaleRef.current.set(1, 1, 1); onHover(false); }}
        >
          <boxGeometry args={[1.52, 2.45, 0.07]} />
          <meshStandardMaterial
            color="#1a0f2e"
            metalness={0.55}
            roughness={0.3}
            emissive="#2e1a4a"
            emissiveIntensity={0.4}
          />
        </mesh>

        {/* Estrella central decorativa (cara del dorso) */}
        <mesh position={[0, 0, 0.045]}>
          <circleGeometry args={[0.28, 8]} />
          <meshBasicMaterial color="#c9a84c" transparent opacity={0.55} />
        </mesh>

        {/* Anillo exterior ornamental */}
        <mesh position={[0, 0, 0.044]}>
          <ringGeometry args={[0.35, 0.4, 32]} />
          <meshBasicMaterial color="#c9a84c" transparent opacity={0.35} />
        </mesh>
      </group>
    </Float>
  );
}

function Scene() {
  const [hovered, setHovered] = useState(false);

  return (
    <>
      <ambientLight intensity={0.35} color="#1a0f2e" />
      {/* Luz dorada principal */}
      <pointLight
        position={[2, 3, 4]}
        intensity={hovered ? 3 : 2}
        color="#c9a84c"
      />
      {/* Contraluz violeta */}
      <pointLight position={[-2, -1, 3]} intensity={0.9} color="#6b3fa0" />
      <TarotCard onHover={setHovered} />
    </>
  );
}

export default function FloatingCard() {
  if (!hasWebGL() || prefersReducedMotion()) return null;

  return (
    <div className="floating-card-canvas" aria-hidden="true">
      <Suspense fallback={<CardPlaceholder />}>
        <Canvas
          camera={{ position: [0, 0, 5], fov: 42 }}
          gl={{ alpha: true, antialias: true }}
          style={{ background: 'transparent', width: '100%', height: '100%' }}
          dpr={Math.min(typeof window !== 'undefined' ? window.devicePixelRatio : 1, 2)}
        >
          <Scene />
        </Canvas>
      </Suspense>
    </div>
  );
}
