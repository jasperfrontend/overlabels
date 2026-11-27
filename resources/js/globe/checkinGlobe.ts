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
 *   --globe-dot-size         land tile size 0-10       (default 2.2)
 *   --globe-dot-tones        1 = mixed land tones      (default 0)
 *   --globe-pin-color        pin head + stalk color    (default #ffd166)
 *   --globe-shell-color      sphere body color         (default #000000)
 *   --globe-shell-opacity    sphere body opacity 0-1   (default 0.85)
 *   --globe-rotation-seconds seconds per revolution    (default 90, 0 = still)
 *   --globe-tilt-degrees     axis tilt toward viewer   (default 18)
 *
 * They are re-read twice a second and applied in place, so a control bound
 * to one (the Chat Checkin designer binds all seven) changes the globe live
 * in OBS without a reload. Before that they were read once at mount.
 *
 * Labels carry class `ol-globe-label` (+ `is-hidden` on the far side) and a
 * `data-login` attribute; the default look is intentionally minimal so
 * template CSS owns it.
 */

import type { CheckinPin } from '@/utils/checkinSlots';
import * as THREE from 'three';
import { withBrandPin } from './brandPin';
import { EVEN_DOT_COUNT, evenLandDots, latLngToUnitVector } from './landmask';

export interface GlobeInstance {
  update(pins: CheckinPin[]): void;
  /**
   * Put the render loop to sleep and wake it again. An overlay in OBS never
   * needs this; a page that scrolls the globe out of view does, or the GPU
   * keeps drawing an invisible sphere for the rest of the visit.
   */
  setPaused(paused: boolean): void;
  destroy(): void;
}

interface PinObject {
  pin: CheckinPin;
  anchor: THREE.Vector3;
  label: HTMLDivElement;
  /** Head and stalk, hidden together with the label. */
  parts: THREE.Object3D[];
}

const SPHERE_RADIUS = 1;
/**
 * How far a pin's tip stands from the centre. Three heights, one per pin,
 * so neighbouring names sit at different distances above the globe and a
 * crowded patch spreads out instead of stacking every name on one line.
 */
const PIN_TIP_RADII = [1.08, 1.16, 1.26] as const;

/**
 * Which height a viewer's pin gets: a hash of the login, so it looks random
 * but stays put when the globe reloads or the pins are rebuilt.
 */
function pinTipRadius(login: string): number {
  let h = 0x811c9dc5;
  for (let i = 0; i < login.length; i++) {
    h ^= login.charCodeAt(i);
    h = Math.imul(h, 0x01000193);
  }
  return PIN_TIP_RADII[(h >>> 0) % PIN_TIP_RADII.length];
}

/**
 * Names fade in between these two values of cos(how far the pin has turned
 * round the spin axis from the meridian facing the viewer): fully shown
 * within about 53 degrees of it, gone beyond about 66, so the left and right
 * edges carry pins without names. Latitude does not count (placeLabels).
 */
const NAME_FADE_FROM = 0.4;
const NAME_FADE_TO = 0.6;

/** The pin stalk's radius: about 3px across at 1080p. */
const STALK_RADIUS = 0.002;

/** The core ball's radius as a share of the surface the hexagons lie on. */
const CORE_RADIUS = 0.6;

/** How visible the far side is through the gap around the core. */
const FAR_SIDE_OPACITY = 0.3;

/** --globe-dot-size at which tiles are at their largest (the designer's max). */
const MAX_DOT_SIZE = 10;

/** A largest tile's width as a share of the distance to its neighbours. */
const TILE_FILL = 0.9;

/** How far the lighter and darker tones sit from the land colour (HSL lightness). */
const TONE_STEP = 0.12;

/** How often the custom properties are re-read, in ms. */
const STYLE_POLL_MS = 500;

interface GlobeStyle {
  dotColor: string;
  dotSize: number;
  dotTones: boolean;
  pinColor: string;
  shellColor: string;
  shellOpacity: number;
  rotationSeconds: number;
  tiltDegrees: number;
}

function cssNumber(styles: CSSStyleDeclaration, name: string, fallback: number): number {
  const raw = styles.getPropertyValue(name).trim();
  const n = Number(raw);
  return raw !== '' && Number.isFinite(n) ? n : fallback;
}

function cssColor(styles: CSSStyleDeclaration, name: string, fallback: string): string {
  const raw = styles.getPropertyValue(name).trim();
  return raw !== '' ? raw : fallback;
}

function readStyle(el: HTMLElement): GlobeStyle {
  const styles = getComputedStyle(el);
  return {
    dotColor: cssColor(styles, '--globe-dot-color', '#8f8f8f'),
    dotSize: cssNumber(styles, '--globe-dot-size', 2.2),
    dotTones: cssNumber(styles, '--globe-dot-tones', 0) === 1,
    pinColor: cssColor(styles, '--globe-pin-color', '#ffd166'),
    shellColor: cssColor(styles, '--globe-shell-color', '#000000'),
    shellOpacity: cssNumber(styles, '--globe-shell-opacity', 0.85),
    rotationSeconds: cssNumber(styles, '--globe-rotation-seconds', 90),
    tiltDegrees: cssNumber(styles, '--globe-tilt-degrees', 18),
  };
}

export function mountCheckinGlobe(el: HTMLElement): GlobeInstance {
  // Only when the template gave the placeholder no position of its own: the
  // label layer needs a positioned parent. An inline style here would be
  // stripped by the renderer's next morphdom pass (it resets attributes to
  // the template's), moving the globe the first time any control changed.
  if (getComputedStyle(el).position === 'static') {
    el.style.position = 'relative';
  }

  let style = readStyle(el);

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
  tilted.rotation.x = (style.tiltDegrees * Math.PI) / 180;
  tilted.add(spin);
  scene.add(tilted);

  // The continents are flat hexagons lying ON the sphere, one instance each,
  // turned so their face points outward. Unlike screen-facing sprites they
  // foreshorten toward the rim, which is what makes them read as part of the
  // surface rather than balls sitting on it.
  const dots = evenLandDots();
  const normals = dots.map((dot) => new THREE.Vector3(...latLngToUnitVector(dot.lat, dot.lng)));
  // Radius 1, scaled per instance; CircleGeometry faces +Z.
  const hexGeometry = new THREE.CircleGeometry(1, 6);

  // Colour lives per tile (instance colour, see colorHexes()); the materials
  // stay white so they do not tint it.
  // Near side: the hexagon's front face. Opaque, so it writes depth and hides
  // the far side behind it.
  const frontMaterial = new THREE.MeshBasicMaterial({ side: THREE.FrontSide });
  const frontHexes = new THREE.InstancedMesh(hexGeometry, frontMaterial, dots.length);

  // Far side: the same hexagons seen from behind, through the gap between the
  // surface and the core. Dimmed so the back of the world reads as behind.
  const backMaterial = new THREE.MeshBasicMaterial({
    side: THREE.BackSide,
    transparent: true,
    opacity: FAR_SIDE_OPACITY,
    depthWrite: false,
  });
  const backHexes = new THREE.InstancedMesh(hexGeometry, backMaterial, dots.length);
  backHexes.instanceMatrix = frontHexes.instanceMatrix;
  // After the core (renderOrder 0), so the core's depth hides the far-side
  // hexagons straight behind it and only the ring around it shows.
  backHexes.renderOrder = 1;

  const zAxis = new THREE.Vector3(0, 0, 1);
  const hexMatrix = new THREE.Matrix4();
  const hexQuaternion = new THREE.Quaternion();
  const hexScale = new THREE.Vector3();
  let hexRadius = 0;

  // The distance between neighbouring tiles on the even lattice.
  const spacing = SPHERE_RADIUS * Math.sqrt((4 * Math.PI) / EVEN_DOT_COUNT);

  /**
   * --globe-dot-size is a share of that spacing, not pixels: at
   * MAX_DOT_SIZE a tile is TILE_FILL of the spacing across and the tiles
   * nearly touch, at half of it they are half as wide. Sized on the globe,
   * so a tile grows and shrinks with it.
   */
  function layoutHexes(): void {
    const share = Math.min(Math.max(style.dotSize, 0), MAX_DOT_SIZE) / MAX_DOT_SIZE;
    const radius = (share * TILE_FILL * spacing) / 2;
    if (radius === hexRadius) return;
    hexRadius = radius;
    hexScale.set(radius, radius, radius);
    normals.forEach((normal, i) => {
      hexQuaternion.setFromUnitVectors(zAxis, normal);
      hexMatrix.compose(normal.clone().multiplyScalar(SPHERE_RADIUS), hexQuaternion, hexScale);
      frontHexes.setMatrixAt(i, hexMatrix);
    });
    frontHexes.instanceMatrix.needsUpdate = true;
    frontHexes.computeBoundingSphere();
    backHexes.computeBoundingSphere();
  }

  // Each tile's tone, fixed for the globe's life and the same on every load:
  // 0 the land colour, 1 lighter, 2 darker. A hash of the index rather than
  // Math.random(), so a reload does not reshuffle the continents.
  const tones = dots.map((_, i) => {
    let h = Math.imul(i ^ 0x9e3779b9, 0x85ebca6b);
    h ^= h >>> 13;
    h = Math.imul(h, 0xc2b2ae35);
    h ^= h >>> 16;
    return (h >>> 0) % 3;
  });
  const toneColors = [new THREE.Color(), new THREE.Color(), new THREE.Color()];
  const hsl = { h: 0, s: 0, l: 0 };

  /**
   * Paint every tile. With --globe-dot-tones off, all three tones are the
   * land colour itself. Lightness moves in sRGB, the space the designer's
   * colour is picked in, so "a little lighter" looks like it.
   */
  function colorHexes(): void {
    toneColors[0].set(style.dotColor);
    toneColors[0].getHSL(hsl, THREE.SRGBColorSpace);
    const step = style.dotTones ? TONE_STEP : 0;
    toneColors[1].setHSL(hsl.h, hsl.s, Math.min(1, hsl.l + step), THREE.SRGBColorSpace);
    toneColors[2].setHSL(hsl.h, hsl.s, Math.max(0, hsl.l - step), THREE.SRGBColorSpace);
    tones.forEach((tone, i) => frontHexes.setColorAt(i, toneColors[tone]));
    frontHexes.instanceColor!.needsUpdate = true;
    backHexes.instanceColor = frontHexes.instanceColor;
  }

  layoutHexes();
  colorHexes();
  spin.add(frontHexes);
  spin.add(backHexes);

  // The core: a smaller ball well inside the surface, leaving room between it
  // and the hexagons for the far side to show through. It writes depth, so
  // far-side hexagons and pins straight behind it stay hidden.
  const shellMaterial = new THREE.MeshBasicMaterial({ color: style.shellColor, transparent: true, opacity: style.shellOpacity });
  const shell = new THREE.Mesh(new THREE.SphereGeometry(SPHERE_RADIUS * CORE_RADIUS, 48, 32), shellMaterial);
  spin.add(shell);

  const pinGroup = new THREE.Group();
  spin.add(pinGroup);

  // Shared by every pin and kept for the globe's lifetime, so a pin colour
  // change is one material update rather than a rebuild.
  const headGeometry = new THREE.SphereGeometry(0.014, 12, 8);
  const headMaterial = new THREE.MeshBasicMaterial({ color: style.pinColor });
  // A thin cylinder, not a THREE.Line: WebGL draws lines 1px wide whatever
  // linewidth says. About 3px across on a 1080p canvas, and it scales with the
  // globe like the tiles do. Built along +Y from the surface to the tip.
  // One unit long, scaled per pin to its own height.
  const stalkGeometry = new THREE.CylinderGeometry(STALK_RADIUS, STALK_RADIUS, 1, 6);
  stalkGeometry.translate(0, 0.5, 0);
  const stalkMaterial = new THREE.MeshBasicMaterial({ color: style.pinColor, transparent: true, opacity: 0.8 });
  const yAxis = new THREE.Vector3(0, 1, 0);

  let pinObjects: PinObject[] = [];
  const disposables: Array<{ dispose(): void }> = [
    hexGeometry,
    frontMaterial,
    backMaterial,
    frontHexes,
    backHexes,
    shellMaterial,
    shell.geometry,
    headGeometry,
    headMaterial,
    stalkGeometry,
    stalkMaterial,
  ];
  let pinDisposables: Array<{ dispose(): void }> = [];

  /** Apply whatever changed since the last read. Cheap when nothing did. */
  function applyStyle(next: GlobeStyle): void {
    const recolor = next.dotColor !== style.dotColor || next.dotTones !== style.dotTones;
    if (next.shellColor !== style.shellColor) shellMaterial.color.set(next.shellColor);
    if (next.shellOpacity !== style.shellOpacity) shellMaterial.opacity = next.shellOpacity;
    if (next.pinColor !== style.pinColor) {
      headMaterial.color.set(next.pinColor);
      stalkMaterial.color.set(next.pinColor);
    }
    if (next.tiltDegrees !== style.tiltDegrees) tilted.rotation.x = (next.tiltDegrees * Math.PI) / 180;
    style = next;
    layoutHexes();
    if (recolor) colorHexes();
  }

  function rebuildPins(pins: CheckinPin[]): void {
    pinGroup.clear();
    for (const d of pinDisposables) d.dispose();
    pinDisposables = [];
    for (const obj of pinObjects) obj.label.remove();
    pinObjects = [];

    for (const pin of pins) {
      const lat = Number(pin.lat);
      const lng = Number(pin.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

      const [x, y, z] = latLngToUnitVector(lat, lng);
      const surface = new THREE.Vector3(x, y, z).multiplyScalar(SPHERE_RADIUS);
      const tipRadius = pinTipRadius(pin.login);
      const tip = new THREE.Vector3(x, y, z).multiplyScalar(tipRadius);

      const stalk = new THREE.Mesh(stalkGeometry, stalkMaterial);
      stalk.position.copy(surface);
      stalk.quaternion.setFromUnitVectors(yAxis, new THREE.Vector3(x, y, z));
      stalk.scale.set(1, tipRadius - SPHERE_RADIUS, 1);
      pinGroup.add(stalk);

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

      pinObjects.push({ pin, anchor: tip, label, parts: [stalk, head] });
    }
  }

  const worldPos = new THREE.Vector3();
  const spinPos = new THREE.Vector3();
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
      // The name fades with how far its pin has turned round the side: at
      // the left and right edges, pins far apart on the globe land a few
      // pixels apart on screen and their names pile up. Measured around the
      // spin axis only, so latitude plays no part: measured against the line
      // of sight instead, a tilted globe never brought far-south (or
      // far-north) pins close enough and their names never showed. In the
      // tilted frame the meridian facing the camera is the one at x = 0,
      // z > 0, so z over the horizontal length is the cosine of that turn.
      spinPos.copy(obj.anchor).applyMatrix4(spin.matrix);
      const across = Math.hypot(spinPos.x, spinPos.z);
      const turn = across < 1e-6 ? 1 : spinPos.z / across;
      const nameAlpha = THREE.MathUtils.smoothstep(turn, NAME_FADE_FROM, NAME_FADE_TO);
      obj.label.classList.toggle('is-hidden', nameAlpha === 0);
      obj.label.style.opacity = nameAlpha.toFixed(2);
      // The pin itself stays on the whole near side, and goes with the far
      // side: the core no longer covers it all, so a pin round the back
      // would show through the gap around it at full brightness, among tiles
      // dimmed to the far side.
      for (const part of obj.parts) part.visible = facing;
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
  let lastStyleRead = last;
  let destroyed = false;
  let paused = false;

  function frame(now: number): void {
    if (destroyed || paused) return;
    const delta = Math.min(0.1, (now - last) / 1000);
    last = now;

    if (now - lastStyleRead >= STYLE_POLL_MS) {
      lastStyleRead = now;
      applyStyle(readStyle(el));
    }

    if (style.rotationSeconds > 0) {
      spin.rotation.y += (delta * Math.PI * 2) / style.rotationSeconds;
    }

    spin.updateMatrixWorld();
    renderer.render(scene, camera);
    placeLabels();
    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  // What the pins were last built from. update() is called on every render
  // the overlay makes, and anything ticking in the render data renders four
  // times a second (a timer, or a List expiry countdown, which ticks every
  // 250 ms even long after it ran out); rebuilding then recreated every
  // label, and a fresh label starts visible and fades out over the label
  // transition, so far-side names flickered. Same pins, nothing to rebuild.
  let builtFrom = '';

  return {
    update(pins: CheckinPin[]): void {
      // Every globe carries the maker's mark - see brandPin.ts.
      const next = withBrandPin(pins);
      const key = JSON.stringify(next.map((p) => [p.login, p.name, p.place, p.lat, p.lng]));
      if (key === builtFrom) return;
      builtFrom = key;
      rebuildPins(next);
    },
    setPaused(next: boolean): void {
      if (destroyed || paused === next) return;
      paused = next;
      if (paused) {
        cancelAnimationFrame(raf);
      } else {
        // Reset the clock so the first frame back is not one huge delta.
        last = performance.now();
        raf = requestAnimationFrame(frame);
      }
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
