interface Command {
  command: string;
  summary: string;
  example: string;
  example2?: string;
}

export const commands: Command[] = [
  {
    command: '!join',
    summary: 'Join the raid. Adds +1 HP to the shared pool and gives you 3 energy blocks. You start pending - you cannot vote in the same round you joined.',
    example: '!join',
  },
  {
    command: '!p [direction] [steps: (1-3) default: (1)]',
    summary: 'Propose a move. Direction is up, down, left, or right. Optional steps is 1-3 (defaults to 1). Steps stop at walls, blockers, and closed doors.',
    example: '!p up',
    example2: '!p right 3',
  },
  {
    command: '!h',
    summary: 'Propose hiding. The player teleports to the nearest hiding spot this round. Some rooms have no hiding spots - the vote does nothing there.',
    example: '!h',
  },
  {
    command: '!a [slot]',
    summary: 'Propose attacking. Defaults to slot 1 (fists, or regular sword if you picked one up). !a 2 uses slot 2 (double-edged sword, if you have one) which deals 2 damage per hit instead of 1. Reaches the 8 tiles around the player (horizontal, vertical, and diagonal - everything except the tile you stand on), so you have to be adjacent to the exit door to damage it.',
    example: '!a',
    example2: '!a 2',
  },
  {
    command: '!s',
    summary: 'Stay. An explicit skip vote that still resets your energy to 3. Useful when you want to keep your slot alive but not influence the round.',
    example: '!s',
  },
];
