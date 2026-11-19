/**
 * The angle knob: a number control that turns a two-color gradient.
 *
 * A number control declares the two color controls it turns in
 * `config.angle_colors` (`["bubble_color", "bubble_color_2"]`). The knob then
 * draws a circle painted with those two live colors and a line through it,
 * and the streamer drags the line instead of reading degrees off a slider.
 *
 * The convention is the overlay's, not CSS's. An angle names where the FIRST
 * color sits, clockwise from twelve o'clock: 0 puts it at the top and the
 * second color at the bottom, 90 puts it at the right. CSS's own 0deg points
 * the other way (toward the top, so the first color starts at the bottom),
 * which is why the overlay writes `calc(180deg + angle)` and why
 * `gradientCss()` does the same. That offset lives here and in the overlay
 * CSS only; `angleKnob.test.ts` reads the recipe to hold the two together.
 */

/** The two color keys a config names, or null when it names anything else. */
export function angleColorKeys(config: unknown): [string, string] | null {
  if (!config || typeof config !== 'object') return null;

  const raw = (config as Record<string, unknown>).angle_colors;
  if (!Array.isArray(raw) || raw.length !== 2) return null;

  const [first, second] = raw;
  if (typeof first !== 'string' || typeof second !== 'string' || first === '' || second === '') return null;

  return [first, second];
}

/** A whole degree in [0, 360). */
export function normaliseAngle(degrees: number): number {
  if (!Number.isFinite(degrees)) return 0;

  return ((Math.round(degrees) % 360) + 360) % 360;
}

/**
 * The angle that puts the first color at a pointer offset from the centre.
 * Screen coordinates: y grows downward. `snap` rounds to that many degrees.
 */
export function angleFromPointer(dx: number, dy: number, snap = 1): number {
  if (dx === 0 && dy === 0) return 0;

  const degrees = (Math.atan2(dy, dx) * 180) / Math.PI + 90;
  const step = snap > 1 ? snap : 1;

  return normaliseAngle(Math.round(degrees / step) * step);
}

/** The CSS the overlay paints for this angle, so the circle and the stream agree. */
export function gradientCss(angle: number, first: string, second: string): string {
  return `linear-gradient(${normaliseAngle(180 + angle)}deg, ${first}, ${second})`;
}
