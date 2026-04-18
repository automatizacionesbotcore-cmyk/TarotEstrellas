/**
 * Sala — "Portal de entrada" (spec 6.6.2)
 * Constelación de puntos orbitando un centro que forma un portal dorado.
 * Aparece en la fase 'loading' de la SalaVideoPage.
 */
import { Canvas, useFrame } from '@react-three/fiber';
import { Suspense, useRef } from 'react';
import * as THREE from 'three';
import { hasWebGL, prefersReducedMotion } from './webgl';

const RING_POINTS = 18;
const INNER_RING  = 12;

function portalPoint(index: number, total: number, radius: number, z: number): THREE.Vector3 {
  const angle = (index / total) * Math.PI * 2;
  return new THREE.Vector3(Math.cos(angle) * radius, Math.sin(angle) * radius, z);
}

function PortalScene() {
  const outerRef = useRef<THREE.Group>(null);
  const innerRef = useRef<THREE.Group>(null);

  useFrame(({ clock }) => {
    const t = clock.getElapsedTime();
    if (outerRef.current) outerRef.current.rotation.z = t * 0.4;
    if (innerRef.current) innerRef.current.rotation.z = -t * 0.65;
  });

  const outerPoints = Array.from({ length: RING_POINTS }, (_, i) =>
    portalPoint(i, RING_POINTS, 2.2, 0),
  );
  const innerPoints = Array.from({ length: INNER_RING }, (_, i) =>
    portalPoint(i, INNER_RING, 1.1, 0),
  );

  return (
    <>
      <ambientLight intensity={0.2} />
      <pointLight position={[0, 0, 3]} intensity={1.8} color="#c9a84c" />
      <pointLight position={[0, 0, -3]} intensity={0.6} color="#6b3fa0" />

      {/* Outer ring */}
      <group ref={outerRef}>
        {outerPoints.map((pos, i) => (
          <mesh key={`o${i}`} position={pos}>
            <sphereGeometry args={[0.07, 8, 8]} />
            <meshStandardMaterial color="#c9a84c" emissive="#c9a84c" emissiveIntensity={0.8} />
          </mesh>
        ))}
      </group>

      {/* Inner ring */}
      <group ref={innerRef}>
        {innerPoints.map((pos, i) => (
          <mesh key={`i${i}`} position={pos}>
            <sphereGeometry args={[0.05, 8, 8]} />
            <meshStandardMaterial color="#e8d5a0" emissive="#e8d5a0" emissiveIntensity={0.6} />
          </mesh>
        ))}
      </group>

      {/* Center glow */}
      <mesh position={[0, 0, 0]}>
        <sphereGeometry args={[0.28, 16, 16]} />
        <meshStandardMaterial
          color="#c9a84c"
          emissive="#c9a84c"
          emissiveIntensity={1.2}
          transparent
          opacity={0.55}
        />
      </mesh>
    </>
  );
}

export default function ConstellationPortal() {
  if (!hasWebGL() || prefersReducedMotion()) return null;

  return (
    <div
      aria-hidden="true"
      style={{ width: 180, height: 180, margin: '0 auto 1rem' }}
    >
      <Suspense fallback={null}>
        <Canvas
          camera={{ position: [0, 0, 5], fov: 45 }}
          gl={{ alpha: true, antialias: false, powerPreference: 'low-power' }}
          style={{ background: 'transparent' }}
          dpr={Math.min(typeof window !== 'undefined' ? window.devicePixelRatio : 1, 1.5)}
        >
          <PortalScene />
        </Canvas>
      </Suspense>
    </div>
  );
}
