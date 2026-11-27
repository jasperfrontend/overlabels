/**
 * The snowfall: a three.js canvas inside the `[[[snow_scene]]]` placeholder.
 *
 * Loaded as a lazy chunk - three.js only ever downloads when a template
 * contains the tag (the emote-library discipline). Everything runs in the
 * overlay: nothing is fetched and nothing is sent, so it costs Overlabels
 * nothing however long it snows.
 *
 * What a flake is and how it falls lives in snowPhysics.ts; this file only
 * draws it. Every flake is one point of a single THREE.Points, so 3000 flakes
 * are one draw call. The shader cuts each point into a soft dot, a square or
 * one of sixteen crystals from an atlas drawn once at mount, and colours it
 * with the two-colour gradient.
 *
 * Styling is CSS: the settings are custom properties on the placeholder
 * (listed on readSnowSettings), re-read twice a second, so a control bound
 * into one changes the snow live in OBS and in the designer without a reload.
 * The background is not ours: the template's CSS paints the placeholder, and
 * the canvas is transparent.
 */

import * as THREE from 'three';
import {
  createFlake,
  crystal,
  CRYSTAL_VARIANTS,
  DEFAULT_SETTINGS,
  type Flake,
  flakeSize,
  flakeX,
  gradientLine,
  MAX_FLAKES,
  readSnowSettings,
  type SnowSettings,
  type SnowShape,
  stepFlake,
  VIRTUAL_HEIGHT,
} from './snowPhysics';

export interface SnowInstance {
  destroy(): void;
}

/** How often the custom properties are re-read, in ms. */
const LOOK_INTERVAL_MS = 500;
/** Ignore a frame gap longer than this (a hidden tab) instead of fast-forwarding through it. */
const MAX_FRAME_GAP = 0.1;
/** The crystal atlas: a 4 x 4 grid of 128 px cells. */
const ATLAS_CELLS = 4;
const ATLAS_CELL = 128;

const SHAPE_INDEX: Record<SnowShape, number> = { round: 0, square: 1, snowflake: 2 };

/** Sixteen crystals, white on transparent, drawn once. */
function crystalAtlas(): THREE.CanvasTexture {
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = ATLAS_CELLS * ATLAS_CELL;
  const ctx = canvas.getContext('2d');
  if (ctx) {
    ctx.fillStyle = '#ffffff';
    ctx.strokeStyle = '#ffffff';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const radius = ATLAS_CELL * 0.46;
    for (let v = 0; v < CRYSTAL_VARIANTS; v++) {
      const design = crystal(v);
      const cx = (v % ATLAS_CELLS) * ATLAS_CELL + ATLAS_CELL / 2;
      const cy = Math.floor(v / ATLAS_CELLS) * ATLAS_CELL + ATLAS_CELL / 2;
      for (let arm = 0; arm < 6; arm++) {
        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate((arm * Math.PI) / 3);
        ctx.scale(radius, radius);
        for (const s of design.strokes) {
          ctx.lineWidth = s.width;
          ctx.beginPath();
          ctx.moveTo(s.x1, s.y1);
          ctx.lineTo(s.x2, s.y2);
          ctx.stroke();
        }
        for (const p of design.plates) hexagon(ctx, 0, p.y, p.radius, p.filled, design.strokes[0].width * 0.6);
        ctx.restore();
      }
      if (design.core > 0) {
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(radius, radius);
        hexagon(ctx, 0, 0, design.core, design.coreFilled, design.strokes[0].width * 0.7);
        ctx.restore();
      }
    }
  }
  const texture = new THREE.CanvasTexture(canvas);
  // gl_PointCoord runs top-down, so the atlas does too.
  texture.flipY = false;
  return texture;
}

function hexagon(ctx: CanvasRenderingContext2D, x: number, y: number, r: number, filled: boolean, line: number): void {
  ctx.beginPath();
  for (let i = 0; i < 6; i++) {
    const a = (i * Math.PI) / 3;
    const px = x + Math.sin(a) * r;
    const py = y - Math.cos(a) * r;
    if (i === 0) ctx.moveTo(px, py);
    else ctx.lineTo(px, py);
  }
  ctx.closePath();
  if (filled) {
    ctx.fill();
  } else {
    ctx.lineWidth = line;
    ctx.stroke();
  }
}

const VERTEX = /* glsl */ `
  attribute float aSize;
  attribute float aAlpha;
  attribute float aTurn;
  attribute float aVariant;
  uniform float uScale;
  varying float vAlpha;
  varying float vTurn;
  varying float vVariant;
  void main() {
    vAlpha = aAlpha;
    vTurn = aTurn;
    vVariant = aVariant;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    gl_PointSize = max(1.0, aSize * uScale);
  }
`;

const FRAGMENT = /* glsl */ `
  uniform vec3 uColor;
  uniform vec3 uColor2;
  uniform vec2 uDir;
  uniform float uLength;
  uniform int uShape;
  uniform sampler2D uAtlas;
  uniform float uCells;
  varying float vAlpha;
  varying float vTurn;
  varying float vVariant;
  void main() {
    vec2 p = gl_PointCoord - 0.5;
    float a = 1.0;
    if (uShape == 0) {
      a = 1.0 - smoothstep(0.28, 0.5, length(p));
    } else if (uShape == 2) {
      float c = cos(vTurn);
      float s = sin(vTurn);
      vec2 q = vec2(c * p.x - s * p.y, s * p.x + c * p.y) + 0.5;
      if (q.x < 0.0 || q.y < 0.0 || q.x > 1.0 || q.y > 1.0) discard;
      float col = mod(vVariant, uCells);
      float row = floor(vVariant / uCells);
      a = texture2D(uAtlas, (vec2(col, row) + q) / uCells).a;
    }
    // The gradient stays put on screen while a crystal turns under it.
    float t = clamp(dot(p, uDir) / uLength + 0.5, 0.0, 1.0);
    float alpha = a * vAlpha;
    if (alpha < 0.01) discard;
    gl_FragColor = vec4(mix(uColor, uColor2, t), alpha);
  }
`;

/** A CSS colour as raw sRGB components; the shader writes them straight out. */
function srgb(value: string, fallback: string): THREE.Vector3 {
  const color = new THREE.Color();
  try {
    color.setStyle(value);
  } catch {
    color.setStyle(fallback);
  }
  const out = { r: 0, g: 0, b: 0 };
  color.getRGB(out, THREE.SRGBColorSpace);
  return new THREE.Vector3(out.r, out.g, out.b);
}

export function mountSnowScene(el: HTMLElement): SnowInstance {
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: false, premultipliedAlpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setClearColor(0x000000, 0);
  const canvas = renderer.domElement;
  canvas.style.display = 'block';
  canvas.style.width = '100%';
  canvas.style.height = '100%';
  el.appendChild(canvas);

  const scene = new THREE.Scene();
  // x runs 0..virtualWidth left to right, y 0..-1080 top to bottom.
  const camera = new THREE.OrthographicCamera(0, VIRTUAL_HEIGHT, 0, -VIRTUAL_HEIGHT, -1, 1);
  let virtualWidth = VIRTUAL_HEIGHT;

  const random = Math.random;
  const flakes: Flake[] = Array.from({ length: MAX_FLAKES }, () => createFlake(random, true));

  const positions = new Float32Array(MAX_FLAKES * 3);
  const sizes = new Float32Array(MAX_FLAKES);
  const alphas = new Float32Array(MAX_FLAKES);
  const turns = new Float32Array(MAX_FLAKES);
  const variants = new Float32Array(MAX_FLAKES);
  flakes.forEach((f, i) => {
    alphas[i] = f.alpha;
    variants[i] = f.variant;
  });

  const geometry = new THREE.BufferGeometry();
  const positionAttr = new THREE.BufferAttribute(positions, 3).setUsage(THREE.DynamicDrawUsage);
  const sizeAttr = new THREE.BufferAttribute(sizes, 1).setUsage(THREE.DynamicDrawUsage);
  const turnAttr = new THREE.BufferAttribute(turns, 1).setUsage(THREE.DynamicDrawUsage);
  geometry.setAttribute('position', positionAttr);
  geometry.setAttribute('aSize', sizeAttr);
  geometry.setAttribute('aTurn', turnAttr);
  geometry.setAttribute('aAlpha', new THREE.BufferAttribute(alphas, 1));
  geometry.setAttribute('aVariant', new THREE.BufferAttribute(variants, 1));

  const atlas = crystalAtlas();
  const material = new THREE.ShaderMaterial({
    vertexShader: VERTEX,
    fragmentShader: FRAGMENT,
    transparent: true,
    depthTest: false,
    depthWrite: false,
    uniforms: {
      uScale: { value: 1 },
      uColor: { value: srgb(DEFAULT_SETTINGS.color, '#ffffff') },
      uColor2: { value: srgb(DEFAULT_SETTINGS.color2, '#ffffff') },
      uDir: { value: new THREE.Vector2(0, 1) },
      uLength: { value: 1 },
      uShape: { value: 0 },
      uAtlas: { value: atlas },
      uCells: { value: ATLAS_CELLS },
    },
  });

  const points = new THREE.Points(geometry, material);
  points.frustumCulled = false;
  scene.add(points);

  let settings: SnowSettings = { ...DEFAULT_SETTINGS };

  function applyLook(): void {
    settings = readSnowSettings((name) => getComputedStyle(el).getPropertyValue(name));
    const u = material.uniforms;
    u.uColor.value = srgb(settings.color, '#ffffff');
    u.uColor2.value = srgb(settings.color2, settings.color);
    const line = gradientLine(settings.angle);
    (u.uDir.value as THREE.Vector2).set(line.x, line.y);
    u.uLength.value = line.length;
    u.uShape.value = SHAPE_INDEX[settings.shape];
    geometry.setDrawRange(0, settings.count);
  }

  function resize(): void {
    const width = Math.max(1, el.clientWidth);
    const height = Math.max(1, el.clientHeight);
    renderer.setSize(width, height, false);
    virtualWidth = (VIRTUAL_HEIGHT * width) / height;
    camera.right = virtualWidth;
    camera.updateProjectionMatrix();
    material.uniforms.uScale.value = (height / VIRTUAL_HEIGHT) * renderer.getPixelRatio();
  }
  const observer = new ResizeObserver(resize);
  observer.observe(el);
  resize();
  applyLook();

  let raf = 0;
  let destroyed = false;
  let lastFrame: number | null = null;
  let lastLook = 0;

  function frame(now: number): void {
    if (destroyed) return;
    const dt = lastFrame === null ? 0 : Math.min(MAX_FRAME_GAP, (now - lastFrame) / 1000);
    lastFrame = now;
    if (now - lastLook >= LOOK_INTERVAL_MS) {
      lastLook = now;
      applyLook();
    }

    const count = settings.count;
    for (let i = 0; i < count; i++) {
      const f = flakes[i];
      const size = flakeSize(f, settings.shape, settings.size);
      stepFlake(f, dt, settings.speed, size, random);
      positions[i * 3] = flakeX(f, virtualWidth);
      positions[i * 3 + 1] = -f.y;
      sizes[i] = size;
      turns[i] = f.turn;
    }
    positionAttr.needsUpdate = true;
    sizeAttr.needsUpdate = true;
    turnAttr.needsUpdate = true;

    renderer.render(scene, camera);
    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  return {
    destroy(): void {
      destroyed = true;
      cancelAnimationFrame(raf);
      observer.disconnect();
      geometry.dispose();
      material.dispose();
      atlas.dispose();
      renderer.dispose();
      canvas.remove();
    },
  };
}
