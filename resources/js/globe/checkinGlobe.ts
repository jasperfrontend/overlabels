/**
 * The 3D checkin globe: a dotted continent sphere (see landmask.ts for where
 * the continents come from) with one pin and one HTML label per checkin.
 *
 * Loaded as a lazy chunk - three.js only ever downloads when a template
 * actually contains [[[checkin_globe]]] (the emote-library discipline).
 *
 * Styling is CSS, not settings: the renderer reads custom properties off the
 * placeholder element, and the labels/stalks are ordinary DOM the template's
 * own CSS can style. Recognised properties, all optional:
 *
 *   --globe-dot-color        land dot color            (default #8f8f8f)
 *   --globe-dot-size         land dot size in px       (default 2.2)
 *   --globe-pin-color        pin head + stalk color    (default #ffd166)
 *   --globe-rotation-seconds seconds per revolution    (default 90, 0 = still)
 *   --globe-tilt-degrees     axis tilt toward viewer   (default 18)
 *
 * Labels carry class `ol-globe-label` (+ `is-hidden` on the far side) and a
 * `data-login` attribute; the default look is intentionally minimal so
 * template CSS owns it.
 */

import type { CheckinPin } from '@/utils/checkinSlots';
import * as THREE from 'three';
import { landDots, latLngToUnitVector } from './landmask';

export interface GlobeInstance {
  update(pins: CheckinPin[]): void;
  destroy(): void;
}

interface PinObject {
  pin: CheckinPin;
  anchor: THREE.Vector3;
  label: HTMLDivElement;
}

const SPHERE_RADIUS = 1;
const PIN_TIP_RADIUS = 1.14;

function cssNumber(styles: CSSStyleDeclaration, name: string, fallback: number): number {
  const raw = styles.getPropertyValue(name).trim();
  const n = Number(raw);
  return raw !== '' && Number.isFinite(n) ? n : fallback;
}

function cssColor(styles: CSSStyleDeclaration, name: string, fallback: string): string {
  const raw = styles.getPropertyValue(name).trim();
  return raw !== '' ? raw : fallback;
}

/** A soft round sprite so points render as dots instead of squares. */
function dotTexture(): THREE.Texture {
  const size = 32;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d')!;
  const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
  gradient.addColorStop(0, 'rgba(255,255,255,1)');
  gradient.addColorStop(0.7, 'rgba(255,255,255,1)');
  gradient.addColorStop(1, 'rgba(255,255,255,0)');
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, size, size);
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  return texture;
}

export function mountCheckinGlobe(el: HTMLElement): GlobeInstance {
  el.style.position = el.style.position || 'relative';

  const styles = getComputedStyle(el);
  const dotColor = cssColor(styles, '--globe-dot-color', '#8f8f8f');
  const dotSize = cssNumber(styles, '--globe-dot-size', 2.2);
  const pinColor = cssColor(styles, '--globe-pin-color', '#ffd166');
  const rotationSeconds = cssNumber(styles, '--globe-rotation-seconds', 90);
  const tiltDegrees = cssNumber(styles, '--globe-tilt-degrees', 18);

  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.domElement.style.display = 'block';
  renderer.domElement.style.width = '100%';
  renderer.domElement.style.height = '100%';
  el.appendChild(renderer.domElement);

  const labelLayer = document.createElement('div');
  labelLayer.className = 'ol-globe-labels';
  labelLayer.style.position = 'absolute';
  labelLayer.style.inset = '0';
  labelLayer.style.overflow = 'hidden';
  labelLayer.style.pointerEvents = 'none';
  el.appendChild(labelLayer);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 10);
  camera.position.set(0, 0, 3.1);

  // Everything that spins lives in this group; the tilt sits on a parent so
  // rotation stays a clean spin around the (tilted) axis.
  const spin = new THREE.Group();
  const tilted = new THREE.Group();
  tilted.rotation.x = (tiltDegrees * Math.PI) / 180;
  tilted.add(spin);
  scene.add(tilted);

  const sprite = dotTexture();

  const dots = landDots();
  const positions = new Float32Array(dots.length * 3);
  dots.forEach((dot, i) => {
    const [x, y, z] = latLngToUnitVector(dot.lat, dot.lng);
    positions[i * 3] = x * SPHERE_RADIUS;
    positions[i * 3 + 1] = y * SPHERE_RADIUS;
    positions[i * 3 + 2] = z * SPHERE_RADIUS;
  });
  const dotGeometry = new THREE.BufferGeometry();
  dotGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
  const dotMaterial = new THREE.PointsMaterial({
    color: dotColor,
    size: dotSize,
    sizeAttenuation: false,
    map: sprite,
    transparent: true,
    alphaTest: 0.2,
    depthWrite: false,
  });
  spin.add(new THREE.Points(dotGeometry, dotMaterial));

  // An occlusion shell so far-side dots and pins read as "behind" instead of
  // floating in space. Verified visually: 0.55 let far-side pin heads punch
  // through at full brightness.
  const shellMaterial = new THREE.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.85 });
  const shell = new THREE.Mesh(new THREE.SphereGeometry(SPHERE_RADIUS * 0.98, 48, 32), shellMaterial);
  spin.add(shell);

  const pinGroup = new THREE.Group();
  spin.add(pinGroup);

  let pinObjects: PinObject[] = [];
  const disposables: Array<{ dispose(): void }> = [dotGeometry, dotMaterial, shellMaterial, shell.geometry, sprite];
  let pinDisposables: Array<{ dispose(): void }> = [];

  function rebuildPins(pins: CheckinPin[]): void {
    pinGroup.clear();
    for (const d of pinDisposables) d.dispose();
    pinDisposables = [];
    for (const obj of pinObjects) obj.label.remove();
    pinObjects = [];

    const headGeometry = new THREE.SphereGeometry(0.014, 12, 8);
    const headMaterial = new THREE.MeshBasicMaterial({ color: pinColor });
    const stalkMaterial = new THREE.LineBasicMaterial({ color: pinColor, transparent: true, opacity: 0.8 });
    pinDisposables.push(headGeometry, headMaterial, stalkMaterial);

    for (const pin of pins) {
      const lat = Number(pin.lat);
      const lng = Number(pin.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

      const [x, y, z] = latLngToUnitVector(lat, lng);
      const surface = new THREE.Vector3(x, y, z).multiplyScalar(SPHERE_RADIUS);
      const tip = new THREE.Vector3(x, y, z).multiplyScalar(PIN_TIP_RADIUS);

      const stalkGeometry = new THREE.BufferGeometry().setFromPoints([surface, tip]);
      pinDisposables.push(stalkGeometry);
      pinGroup.add(new THREE.Line(stalkGeometry, stalkMaterial));

      const head = new THREE.Mesh(headGeometry, headMaterial);
      head.position.copy(tip);
      pinGroup.add(head);

      const label = document.createElement('div');
      label.className = 'ol-globe-label';
      label.dataset.login = pin.login;
      label.textContent = pin.name || pin.login;
      label.style.position = 'absolute';
      label.style.transform = 'translate(-50%, -100%)';
      label.style.whiteSpace = 'nowrap';
      labelLayer.appendChild(label);

      pinObjects.push({ pin, anchor: tip, label });
    }
  }

  const worldPos = new THREE.Vector3();
  const projected = new THREE.Vector3();

  function placeLabels(): void {
    const width = el.clientWidth;
    const height = el.clientHeight;

    for (const obj of pinObjects) {
      worldPos.copy(obj.anchor).applyMatrix4(spin.matrixWorld);
      const facing = worldPos.z > 0.05;
      projected.copy(worldPos).project(camera);
      const sx = (projected.x * 0.5 + 0.5) * width;
      const sy = (-projected.y * 0.5 + 0.5) * height;
      obj.label.style.left = `${sx.toFixed(1)}px`;
      obj.label.style.top = `${sy.toFixed(1)}px`;
      obj.label.classList.toggle('is-hidden', !facing);
      obj.label.style.opacity = facing ? '' : '0';
    }
  }

  function resize(): void {
    const width = Math.max(1, el.clientWidth);
    const height = Math.max(1, el.clientHeight);
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  }

  const observer = new ResizeObserver(resize);
  observer.observe(el);
  resize();

  let raf = 0;
  let last = performance.now();
  let destroyed = false;

  function frame(now: number): void {
    if (destroyed) return;
    const delta = Math.min(0.1, (now - last) / 1000);
    last = now;

    if (rotationSeconds > 0) {
      spin.rotation.y += (delta * Math.PI * 2) / rotationSeconds;
    }

    spin.updateMatrixWorld();
    renderer.render(scene, camera);
    placeLabels();
    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  return {
    update(pins: CheckinPin[]): void {
      rebuildPins(pins);
    },
    destroy(): void {
      destroyed = true;
      cancelAnimationFrame(raf);
      observer.disconnect();
      rebuildPins([]);
      for (const d of pinDisposables) d.dispose();
      for (const d of disposables) d.dispose();
      renderer.dispose();
      renderer.domElement.remove();
      labelLayer.remove();
    },
  };
}
