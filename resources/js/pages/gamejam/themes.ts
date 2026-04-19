export interface RoomLayout {
  grid: string;
  tiles: Record<string, string>;
}

export interface RoomTheme {
  floor: string[];
  layout?: RoomLayout;
  player: string;
  blocker: string;
  hidingSpot: string;
  hidden: string;
  door: { closed: string; opening: string; open: string; exit: string };
  pickups: Record<
    'regular_sword' | 'de_sword' | 'iron_fists' | 'bomb' | 'hp_restore' | 'zombie_spawn',
    string
  >;
}

const tilesObject: Record<string, string> = {
  j: '/tile-icons/Tile/Tile (10).png', // dirt variation 1
  ')': '/tile-icons/Tile/Tile (72).png', // dirt variation 2
  e: '/tile-icons/Tile/Tile (5).png', // grass variation 1
  1: '/tile-icons/Tile/Tile (54).png', // grass variation 2
  6: '/tile-icons/Tile/Tile (59).png', // water
  g: '/tile-icons/Tile/Tile (7).png', // dirt no grass just grass dot top right
  a: '/tile-icons/Tile/Tile (1).png', // dirt no grass just grass dot bottom right
  i: '/tile-icons/Tile/Tile (9).png', // dirt no grass just grass dot top left
  c: '/tile-icons/Tile/Tile (3).png', // dirt no grass just grass dot bottom left
  h: '/tile-icons/Tile/Tile (8).png', // dirt with grass top
  b: '/tile-icons/Tile/Tile (2).png', // dirt with grass bottom
  f: '/tile-icons/Tile/Tile (6).png', // dirt with grass left
  d: '/tile-icons/Tile/Tile (4).png', // dirt with grass right
  o: '/tile-icons/Tile/Tile (15).png', // dirt with grass left and top
  k: '/tile-icons/Tile/Tile (11).png', // diagonal dirt with grass north-west
  l: '/tile-icons/Tile/Tile (12).png', // diagonal dirt with grass north-east
  m: '/tile-icons/Tile/Tile (13).png', // diagonal dirt with grass south-west
  n: '/tile-icons/Tile/Tile (14).png', // diagonal dirt with grass south-east
  p: '/tile-icons/Tile/Tile (16).png', // dirt with grass right and top
  q: '/tile-icons/Tile/Tile (17).png', // dirt with grass left and bottom
  r: '/tile-icons/Tile/Tile (18).png', // dirt with grass right and bottom
  s: '/tile-icons/Tile/Tile (19).png', // dirt with grass left and top with grass dot bottom right
  t: '/tile-icons/Tile/Tile (20).png', // dirt with grass right and top with grass dot bottom left
  u: '/tile-icons/Tile/Tile (21).png', // dirt with grass left and bottom with grass dot top right
  v: '/tile-icons/Tile/Tile (22).png', // dirt with grass right and bottom with grass dot top left
  w: '/tile-icons/Tile/Tile (23).png', // dirt with grass left with grass dot top right and bottom right
  x: '/tile-icons/Tile/Tile (24).png', // dirt with grass right with grass dot top left and bottom left
  y: '/tile-icons/Tile/Tile (25).png', // dirt no grass just grass dot top right and bottom right
  z: '/tile-icons/Tile/Tile (26).png', // dirt no grass just grass dot top left and bottom left
  A: '/tile-icons/Tile/Tile (27).png', // dirt no grass just grass dot bottom left and bottom right
  B: '/tile-icons/Tile/Tile (28).png', // dirt no grass just grass dot top left and top right
  D: '/tile-icons/Tile/Tile (30).png', // dirt with grass bottom with grass dot |top left and |top right
  C: '/tile-icons/Tile/Tile (29).png', // dirt with grass top with grass dot |bottom left and |bottom right
  E: '/tile-icons/Tile/Tile (31).png', // dirt with grass left with grass dot bottom right
  F: '/tile-icons/Tile/Tile (32).png', // dirt with grass right with grass dot bottom left
  G: '/tile-icons/Tile/Tile (33).png', // dirt with grass left with grass dot top right
  H: '/tile-icons/Tile/Tile (34).png', // dirt with grass right with grass dot top left
  I: '/tile-icons/Tile/Tile (35).png', // dirt with grass top with grass dot bottom right
  J: '/tile-icons/Tile/Tile (36).png', // dirt with grass top with grass dot bottom left
  K: '/tile-icons/Tile/Tile (37).png', // dirt with grass bottom with grass dot top right
  L: '/tile-icons/Tile/Tile (38).png', // dirt with grass bottom with grass dot top left
  M: '/tile-icons/Tile/Tile (39).png', // dirt no grass just grass dot |top left |top right |bottom right -no bottom left
  N: '/tile-icons/Tile/Tile (40).png', // dirt no grass just grass dot |top right |top left |bottom left -no bottom right
  O: '/tile-icons/Tile/Tile (41).png', // dirt no grass just grass dot |bottom right |bottom left -no top left
  P: '/tile-icons/Tile/Tile (42).png', // dirt no grass just grass dot |bottom left |bottom right -no top right
  Q: '/tile-icons/Tile/Tile (43).png', // dirt with grass left top and right
  R: '/tile-icons/Tile/Tile (44).png', // dirt with grass left and right
  S: '/tile-icons/Tile/Tile (45).png', // dirt no grass just grass dot |top left |top right |bottom left |bottom right
  T: '/tile-icons/Tile/Tile (46).png', // dirt with grass top and bottom
  U: '/tile-icons/Tile/Tile (47).png', // dirt with grass left top and bottom
  V: '/tile-icons/Tile/Tile (48).png', // dirt with grass right top and bottom
  W: '/tile-icons/Tile/Tile (49).png', // dirt with grass left bottom and right
  4: '/tile-icons/Tile/Tile (57).png', // water with grass top
  Y: '/tile-icons/Tile/Tile (51).png', // water with grass bottom
  2: '/tile-icons/Tile/Tile (55).png', // water with grass left
  0: '/tile-icons/Tile/Tile (53).png', // water with grass right
  5: '/tile-icons/Tile/Tile (58).png', // water with just grass dot top left
  3: '/tile-icons/Tile/Tile (56).png', // water with just grass dot top right
  Z: '/tile-icons/Tile/Tile (52).png', // water with just grass dot bottom left
  X: '/tile-icons/Tile/Tile (50).png', // water with just grass dot bottom right
  8: '/tile-icons/Tile/Tile (61).png', // diagonal water with grass north-east
  '.': '/tile-icons/Tile/Tile (63).png', // diagonal water with grass south-east
  9: '/tile-icons/Tile/Tile (62).png', // diagonal water with grass south-west
  7: '/tile-icons/Tile/Tile (60).png', // diagonal water with grass north-west
  '#': '/tile-icons/Tile/Tile (64).png', // water with grass left and top
  '@': '/tile-icons/Tile/Tile (65).png', // water with grass right and top
  '$': '/tile-icons/Tile/Tile (66).png', // water with grass left and bottom
  '%': '/tile-icons/Tile/Tile (67).png', // water with grass right and bottom
  ':': '/tile-icons/Tile/Tile (75).png', // water with dirt top
  '&': '/tile-icons/Tile/Tile (69).png', // water with dirt bottom
  '!': '/tile-icons/Tile/Tile (73).png', // water with dirt left
  '(': '/tile-icons/Tile/Tile (71).png', // water with dirt right
  ';': '/tile-icons/Tile/Tile (76).png', // water with just dirt dot top left
  '?': '/tile-icons/Tile/Tile (74).png', // water with just dirt dot top right
  '*': '/tile-icons/Tile/Tile (70).png', // water with just dirt dot bottom left
  '^': '/tile-icons/Tile/Tile (68).png', // water with just dirt dot bottom right
  '>': '/tile-icons/Tile/Tile (78).png', // diagonal water with dirt north-east
  ']': '/tile-icons/Tile/Tile (80).png', // diagonal water with dirt south-east
  '[': '/tile-icons/Tile/Tile (79).png', // diagonal water with dirt south-west
  '<': '/tile-icons/Tile/Tile (77).png', // diagonal water with dirt north-west
  '{': '/tile-icons/Tile/Tile (81).png', // water with dirt left and top
  '}': '/tile-icons/Tile/Tile (82).png', // water with dirt right and top
  '|': '/tile-icons/Tile/Tile (83).png', // water with dirt left and bottom
  '~': '/tile-icons/Tile/Tile (84).png', // water with dirt right and bottom
  '_': '/tile-icons/Tile/Fence (1).png', // fence corner left top
  '-': '/tile-icons/Tile/Fence (2).png', // fence corner right top
  '+': '/tile-icons/Tile/Fence (3).png', // fence vertical with decorative pole
  '=': '/tile-icons/Tile/Fence (4).png', // fence vertical
  '«': '/tile-icons/Tile/Fence (5).png', // fence horizontal stop left
  '€': '/tile-icons/Tile/Fence (6).png', // fence horizontal
  '¡': '/tile-icons/Tile/Fence (7).png', // fence horizontal with decorative pole
  '»': '/tile-icons/Tile/Fence (8).png', // fence horizontal stop right
}

const GRAVEYARD: RoomTheme = {
  floor: Array.from({ length: 20 }, (_, i) => `/tile-icons/Tile/Tile (${i + 1}).png`),
  layout: {
    grid: `
      111$Y%1111m
      11khhhhhhl1
      11fjjjjjjd1
      48mcjjjjjd1
      601fjjjjjd1
      601fjjjjjd1
      Y.kijjjjjd1
      1kijjjjjjd1
      1fjjjjjjjd1
      1mbcjjjjan1
      111mbbbbn1#
    `,
    tiles: tilesObject,
  },
  player: '/tile-icons/Objects/Scarecrow.png',
  blocker: '/tile-icons/Objects/Tombstone (1).png',
  hidingSpot: '/tile-icons/Objects/Coffin.png',
  hidden: '/tile-icons/Objects/Crate.png',
  door: {
    closed:  '/tile-icons/Objects/Coffin.png',
    opening: '/tile-icons/Objects/Coffin.png',
    open:    '/tile-icons/Objects/Dirt.png',
    exit:    '/tile-icons/Objects/Sign (1).png',
  },
  pickups: {
    regular_sword: '/tile-icons/pixel/sword-default.png',
    de_sword:      '/tile-icons/pixel/sword-de.png',
    iron_fists:    '/tile-icons/pixel/sword-default.png',
    bomb:          '/tile-icons/pixel/bomb.png',
    hp_restore:    '/tile-icons/pixel/apple.png',
    zombie_spawn:  '/tile-icons/pixel/zombie-spawn.png',
  },
};

const CRYPT: RoomTheme = {
  floor: Array.from({ length: 20 }, (_, i) => `/tile-icons/Tile/Tile (${i + 21}).png`),
  player: '/tile-icons/Objects/Sekeleton (2).png',
  blocker: '/tile-icons/Objects/Stone (1).png',
  hidingSpot: '/tile-icons/Objects/Bush (2).png',
  hidden: '/tile-icons/Objects/Crate.png',
  door: {
    closed:  '/tile-icons/Objects/Tombstone (3).png',
    opening: '/tile-icons/Objects/Tombstone (4).png',
    open:    '/tile-icons/Objects/Dirt.png',
    exit:    '/tile-icons/Objects/Sign (4).png',
  },
  pickups: {
    regular_sword: '/tile-icons/Objects/Sign (5).png',
    de_sword:      '/tile-icons/Objects/Sign (2).png',
    iron_fists:    '/tile-icons/Objects/Scarecrow.png',
    bomb:          '/tile-icons/Objects/Barrel (2).png',
    hp_restore:    '/tile-icons/Objects/Pumpkin (2).png',
    zombie_spawn:  '/tile-icons/Objects/Tombstone (5).png',
  },
};

export const THEMES: Record<number, RoomTheme> = {
  1: GRAVEYARD,
  2: CRYPT,
};

export const DEFAULT_THEME = GRAVEYARD;

export function themeFor(room: number): RoomTheme {
  return THEMES[room] ?? DEFAULT_THEME;
}

function parseGrid(grid: string): string[] {
  return grid.trim().split('\n').map(row => row.trim());
}

export function floorFor(theme: RoomTheme, x: number, y: number): string {
  if (theme.layout) {
    const rows = parseGrid(theme.layout.grid);
    const char = rows[y - 1]?.[x - 1];
    const tile = char ? theme.layout.tiles[char] : undefined;
    if (tile) return tile;
  }
  const h = Math.abs((x * 73856093) ^ (y * 19349663));
  return theme.floor[h % theme.floor.length];
}
