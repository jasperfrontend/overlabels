export interface RoomTheme {
  floor: string[];
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

const GRAVEYARD: RoomTheme = {
  floor: Array.from({ length: 20 }, (_, i) => `/tile-icons/Tile/Tile (${i + 1}).png`),
  player: '/tile-icons/Objects/Sekeleton (1).png',
  blocker: '/tile-icons/Objects/Tombstone (1).png',
  hidingSpot: '/tile-icons/Objects/Bush (1).png',
  hidden: '/tile-icons/Objects/Crate.png',
  door: {
    closed:  '/tile-icons/Objects/Coffin.png',
    opening: '/tile-icons/Objects/Coffin.png',
    open:    '/tile-icons/Objects/Dirt.png',
    exit:    '/tile-icons/Objects/Sign (1).png',
  },
  pickups: {
    regular_sword: '/tile-icons/Objects/Sign (2).png',
    de_sword:      '/tile-icons/Objects/Sign (3).png',
    iron_fists:    '/tile-icons/Objects/Scarecrow.png',
    bomb:          '/tile-icons/Objects/Barrel (1).png',
    hp_restore:    '/tile-icons/Objects/Pumpkin (1).png',
    zombie_spawn:  '/tile-icons/Objects/Tombstone (2).png',
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

export function floorFor(theme: RoomTheme, x: number, y: number): string {
  const h = Math.abs((x * 73856093) ^ (y * 19349663));
  return theme.floor[h % theme.floor.length];
}
