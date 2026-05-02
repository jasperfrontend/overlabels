interface GameCommand {
  command: string;
  summary: string;
  description?: string;
  example: string;
  example2?: string;
}

export const gameCommands: GameCommand[] = [
  {
    command: '!join',
    summary: 'Join the raid.',
    description: 'Adds +1 HP to the shared pool and gives you 3 energy blocks. You start pending - you cannot vote in the same round you joined.',
    example: '!join',
  },
  {
    command: '!p [direction] [steps: (1-3) default: (1)]',
    summary: 'Propose a move. Max 3 blocks in the given direction.',
    description: 'Direction is up, down, left, or right. Optional steps is 1-3 (defaults to 1). Steps stop at walls, blockers, and closed doors.',
    example: '!p up',
    example2: '!p right 3',
  },
  {
    command: '!h',
    summary: 'Propose to hide.',
    description: 'The player teleports to the nearest hiding spot this round. Some rooms have no hiding spots - the vote does nothing there.',
    example: '!h',
  },
  {
    command: '!a [slot]',
    summary: 'Propose to attack.',
    description: 'Defaults to slot 1 (fists or regular sword). !a 2 uses slot 2 (if you have one) which deals 2 damage per hit instead of 1.',
    example: '!a',
    example2: '!a 2',
  },
  {
    command: '!s',
    summary: 'Propose to stay (do nothing).',
    description: 'An explicit skip vote that still resets your energy to 3. Useful when you want to keep your slot alive but not influence the round.',
    example: '!s',
  },
];
