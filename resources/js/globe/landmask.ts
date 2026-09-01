/**
 * A 180x90 (2 degree) equirectangular land bitmask, derived from the app's
 * own GeoNames gazetteer: a cell is "land" when at least one city in
 * geo_places sits inside it. Cities only exist on land, so the checkin
 * database doubles as the continent silhouette the globe draws - no map
 * texture, no extra download, ~2.7 KB of base64.
 *
 * Row-major, bit 7 of byte 0 = the cell at (lat 90..88, lng -180..-178).
 * Regenerate after a gazetteer refresh with the tinker script recorded in
 * the OL-2609-007 claim (docs/changelog/claims/2026/09/OL-2609-007/):
 * bucket geo_places lat/lng into the grid, pack bits, base64.
 *
 * Pure module: no three.js, no DOM - fully unit-testable.
 */

export const MASK_COLS = 180;
export const MASK_ROWS = 90;

const LANDMASK_B64 =
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABEAQAAAAAAAAAACAAAAAAAAAFEAAAAAAQEAAAQAD8AIABAEECYQAA' +
  'AgADAAQgQAGAAAAA//QAggYAIAEQJRhsBCQIACAEGAAsAA//CL9sYAIRxCAAGQHIKQBAkAGBA/AB//eL48QIQABkACCcNmAYAQCQ' +
  'BAA4IP//f+t4AAP31fBBA+PGTaAgMgBwAAJP/////4Gkkn4MRAAXIKC2AgIUAAAAPP////+Lj0MNBEUAAAQDByAgMBAAAAPD////' +
  '/9///oEAcAAcAB//dgEJAAAAfH///////+5YQBSAAgAB//9A4NAAAA/v//////+3/9vAwAAAAAP//YZa4AAA//////v//////AgA' +
  'AAAAP////88AAAP/////33////7AAAAAAAH/////8AAAH////9l///9/3AAAAAAAH/////gAAAB////+dv////7wAAAAAAH////8' +
  'AAAAf//7f53/fP//3wAAAAAAH////wAAAAfy///9//19/+GAAAAAAAD///+AAAOAf6///vf9///+GAAAAAAAD///+AAABAf/7///' +
  '//H///uAAAAAAAB///+AAAAAP/xz///6B//n+AAAAAAAA///8EAAAEf/zB////3//n8AAAAAAAAf//wAAAAAefu/7+//v//xAAAA' +
  'AAAAAP/9wAAAAH9gevv//////yAAAAAAAAAP/A8AAAAPxywX+//////mBAAAAAAAAH/AeAAAADgDzP3//////5AAAAACAAAD/B7A' +
  'AAAHCIAF/PD////gAAAAADgAAA/O/gAAAHggIE+AD///4gAAAAAAgAAAf+f+AAAHy/mGuCA/z/wwAAAAAAAAAAP/EvAABn7OHC38' +
  'A/B/pwAAAAAAAAAAB/gBAABn//7j/4A+A/h4AgAAAAAAAAAPwxgAAH/////MAeCfh4AgAAAAAAAAADj/AAAH///9/wA8CXh8AAFw' +
  'AAAAAAAB//gAAB/////wAuAfD8euDwAAAAAAAAf/wAAB//+d7gAmCcD8R+YwAAAAAAAAH58AAA/v///gAiB9vsgCkgADAAAAAAPN' +
  'qAAAAD///AAgB/+PABAIABAAAAAAfpmAAAAH//+AAgA+++ABAIAAAAAAAMfePwAAAL+P8AAgAf3/8ABGAAAAAAAAf//+QAAB9/4A' +
  'AAAf///WAGAAAAAAAAf7p/gAAB//wEAAAHNw/nAAAAAAAAAAf+7/gCAA7/wAAgAD8hH5gDGAAYAAAAP/7/gAAA//wAAAAAf8H64B' +
  'CAAAAAAAPf//AAAA//8CAAACA5BEKAWAAAAAAAH3//AAAA//+gAAAgAB7ABCnACAAAAAD+/+AAEB//5wAAAAACygBAAANAAAAAB/' +
  '/+AAAA//zhAAAAAaOwBjtAAUAAAAA//+AAAA//jiQAAAA/UQEjICAAAAAAA+/8AAAAf/HmAAAAHwX4DgAAMCAAAAA//8AAAAf9Hg' +
  'AAAALBh8BAAAAAgAAAAv/gAAAAP/HAAAAAIBwuAAAAAAABAAAv/AAAAAf+AAAAAAFkg+AAAAAAAAAAA//AAAAAP+AAAAAAGwQ+BA' +
  'AAAAAAAAA/+AAAAAP8AAAAAAHgL+AAAAAAAAAAI/8AAAAAP4AAAAAAHg9+AAAAAAAAAAB/8AAAAAHgAAAAAAHAf8AMAAAAAAAAB/' +
  'wAABAAAAAAAAAAAAH8AHAAAAAAAAB/wAAAAAAAAAAAAAAADwAPAAAAAAAAB+AAAAAAAAAAAAAAAAA4AeQAAAAAAAB8AAAAAAAAAA' +
  'AAAAAAA4A4AAAAAAAAB8AAAAAAAAAAAAAAAAAABwAAAAAAAAB8AAAAAAAAAAAAAAAAAABgAAAAAAAAB4AAAAAAAAAABAAAAAAAAA' +
  'AAAAAAAABQQAAAAAAAAAAAAAAAAAAAAAAAAAAAA4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAYABAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' +
  'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

let decoded: Uint8Array | null = null;

function maskBytes(): Uint8Array {
  if (decoded) return decoded;
  const binary = atob(LANDMASK_B64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return (decoded = bytes);
}

export function isLandCell(row: number, col: number): boolean {
  if (row < 0 || row >= MASK_ROWS || col < 0 || col >= MASK_COLS) return false;
  const index = row * MASK_COLS + col;
  const byte = maskBytes()[index >> 3];
  return ((byte >> (7 - (index & 7))) & 1) === 1;
}

export interface LandDot {
  lat: number;
  lng: number;
}

/** Every land cell's centre as lat/lng, ready for the dot sphere. */
export function landDots(): LandDot[] {
  const dots: LandDot[] = [];
  for (let row = 0; row < MASK_ROWS; row++) {
    for (let col = 0; col < MASK_COLS; col++) {
      if (!isLandCell(row, col)) continue;
      dots.push({
        lat: 90 - (row + 0.5) * (180 / MASK_ROWS),
        lng: -180 + (col + 0.5) * (360 / MASK_COLS),
      });
    }
  }
  return dots;
}

/**
 * lat/lng in degrees to a unit vector in three.js coordinates (y up, camera
 * looking down -z). lng 0 faces the camera at rotation 0.
 */
export function latLngToUnitVector(lat: number, lng: number): [number, number, number] {
  const phi = ((90 - lat) * Math.PI) / 180;
  const theta = ((lng + 90) * Math.PI) / 180;
  return [-Math.sin(phi) * Math.cos(theta), Math.cos(phi), Math.sin(phi) * Math.sin(theta)];
}
