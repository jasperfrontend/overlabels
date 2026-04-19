<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import { Swords, Heart, Clock, Dices, DoorOpen, Skull, Ghost } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Chat Castle', href: '/help/gamejam' },
];

interface ChestItem {
  name: string;
  effect: string;
  tone: 'good' | 'neutral' | 'bad';
}

const chestItems: ChestItem[] = [
  {
    name: 'Regular sword',
    effect: 'Fills weapon slot 1 with 10 uses, 1 damage per hit. Reverts to fists when used up.',
    tone: 'good',
  },
  {
    name: 'Double-edged sword',
    effect: 'Fills weapon slot 2 permanently, 2 damage per hit. Cast with !a 2.',
    tone: 'good',
  },
  {
    name: 'Iron fists',
    effect: 'Permanent. Removes the 1 HP self-damage cost when you attack with bare fists.',
    tone: 'good',
  },
  {
    name: 'HP restore',
    effect: '+1 HP to the shared pool.',
    tone: 'good',
  },
  {
    name: 'Bomb',
    effect: '-1 HP. If the pool hits zero, the raid ends then and there.',
    tone: 'bad',
  },
  {
    name: 'Empty',
    effect: "Nothing happens. The tile just opens up and you keep going.",
    tone: 'neutral',
  },
];

interface Command {
  command: string;
  summary: string;
  example: string;
}

const commands: Command[] = [
  {
    command: '!join',
    summary: 'Join the raid. Adds +1 HP to the shared pool and gives you 3 energy blocks. You start pending - you cannot vote in the same round you joined.',
    example: '!join',
  },
  {
    command: '!p <dir> [steps]',
    summary: 'Propose a move. Direction is up, down, left, or right. Optional steps is 1-3 (defaults to 1). Steps stop at walls, blockers, and closed doors.',
    example: '!p up 2',
  },
  {
    command: '!h',
    summary: 'Propose hiding. The player teleports to the nearest hiding spot this round. Some rooms have no hiding spots - the vote does nothing there.',
    example: '!h',
  },
  {
    command: '!a [slot]',
    summary: 'Propose attacking. Defaults to slot 1 (fists, or regular sword if you picked one up). !a 2 uses slot 2 (double-edged sword, if you have one) which deals 2 damage per hit instead of 1. Reaches the 8 tiles around the player (horizontal, vertical, and diagonal - everything except the tile you stand on), so you have to be adjacent to the exit door to damage it.',
    example: '!a 2',
  },
  {
    command: '!s',
    summary: 'Stay. An explicit skip vote that still resets your energy to 3. Useful when you want to keep your slot alive but not influence the round.',
    example: '!s',
  },
];

const chestClass: Record<ChestItem['tone'], string> = {
  good: 'border-emerald-500/40 bg-emerald-500/5',
  neutral: 'border-sidebar bg-sidebar/40',
  bad: 'border-rose-500/40 bg-rose-500/5',
};

interface Zombie {
  room: string;
  count: string;
  hp: number;
  damage: number;
  notes: string;
}

type AdjacencyTile = 'player' | 'hit' | 'miss' | 'empty';

const adjacencyTiles: AdjacencyTile[] = Array.from({ length: 81 }, (_, i) => {
  const col = i % 9;
  const row = Math.floor(i / 9);
  const dx = col - 4;
  const dy = row - 4;
  if (dx === 0 && dy === 0) return 'player';
  if ((dx === 0 && Math.abs(dy) === 1) || (dy === 0 && Math.abs(dx) === 1)) return 'hit';
  if (Math.abs(dx) === 1 && Math.abs(dy) === 1) return 'miss';
  return 'empty';
});

const zombies: Zombie[] = [
  { room: 'Room 1', count: '1 regular', hp: 3, damage: 1, notes: 'Spawns at least 3 tiles from the player.' },
  { room: 'Room 2', count: '1 regular', hp: 4, damage: 2, notes: 'Starts to bite.' },
  { room: 'Room 3', count: '1 regular', hp: 6, damage: 3, notes: 'Harder to two-shot with a regular sword.' },
  { room: 'Room 4', count: '4 regulars', hp: 8, damage: 4, notes: 'Four at once on a 9x9 grid - tight quarters.' },
  { room: 'Room 5', count: '1 boss', hp: 30, damage: 4, notes: 'Sits dead-centre. The four corner tiles are HP restores - worth grabbing before the fight.' },
];
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Chat Castle - A Twitch Chat Dungeon Raid"
    description="Chat Castle is a chat-driven dungeon crawler. Every viewer votes, the top vote wins the round, the party shares one HP pool. Here is how every command, tick, and chest works."
    canonical-url="https://overlabels.com/help/gamejam"
  >
    <div class="mb-8">
      <h1 class="mb-4 text-4xl font-bold">Chat Castle</h1>
      <p class="text-lg text-foreground">
        Chat Castle is a co-op dungeon raid you play together with the streamer's chat. Every round, everyone who
        has joined votes on one action - move, hide, attack, or stay. The most-voted action wins. Clear 5 rooms
        before the shared HP pool hits zero.
      </p>
    </div>

    <div class="mb-10 rounded-lg border border-violet-500/40 bg-violet-500/10 p-5">
      <h2 class="mb-2 flex items-center gap-2 text-xl font-semibold text-violet-400">
        <Dices class="h-5 w-5" />
        The short version
      </h2>
      <ul class="list-disc space-y-1 pl-6 text-foreground">
        <li>Type <code class="rounded bg-background/40 px-1">!join</code> in chat to enter the raid.</li>
        <li>Each round, vote with one of the commands below. You cannot vote the same round you joined.</li>
        <li>You can change your <code class="rounded bg-background/40 px-1">!vote</code> as many times as you like before the next game tick.</li>
        <li>Every round, every active player loses 1 energy block. Voting resets your energy back to 3.</li>
        <li>Miss enough rounds and you go inactive. The shared HP pool loses 1 when that happens.</li>
        <li>Attack doors to open them. Walk through an open exit door to advance. Clear room 5 to win.</li>
        <li>Every room has zombies. Fight them (<code class="rounded bg-background/40 px-1">!a</code>),
          dodge them (<code class="rounded bg-background/40 px-1">!p</code>), or hide from them
          (<code class="rounded bg-background/40 px-1">!h</code>). Details further down.</li>
      </ul>
    </div>

    <h2 id="commands" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <Swords class="h-6 w-6 text-violet-400" />
      Commands
    </h2>
    <p class="mb-4 text-foreground">
      All Chat Castle commands are available to everyone in chat. You cast a vote per round - your last accepted
      vote wins. Vote early, vote often, or vote once and let it ride.
    </p>

    <div class="mb-10 space-y-3">
      <div
        v-for="cmd in commands"
        :key="cmd.command"
        class="rounded-lg border border-sidebar bg-sidebar p-5"
      >
        <div class="mb-2 flex flex-wrap items-center gap-3">
          <code class="rounded bg-card px-2 py-1 font-mono text-base font-semibold">{{ cmd.command }}</code>
          <code class="rounded bg-background/50 px-2 py-0.5 font-mono text-sm text-muted-foreground">
            example: {{ cmd.example }}
          </code>
        </div>
        <p class="text-sm text-foreground">{{ cmd.summary }}</p>
      </div>
    </div>

    <h2 id="tick" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <Clock class="h-6 w-6 text-violet-400" />
      The tick
    </h2>
    <p class="mb-4 text-foreground">
      The game moves in <strong>rounds</strong>. A round lasts a fixed number of seconds (configured by the
      streamer, usually 15-30s). When the round ends:
    </p>
    <ol class="mb-10 list-decimal space-y-2 pl-6 text-foreground">
      <li>Every active player who voted has their vote tallied.</li>
      <li>
        <strong>The winning action is applied.</strong> Movement into a zombie's tile stops you
        and takes that zombie's damage (a <em>bump</em>). Attacks hit the nearest zombie in reach
        if there is one, otherwise fall back to the exit door's 3x3 AoE.
      </li>
      <li>
        <strong>Zombies take their turn.</strong> Each one re-checks line of sight, chases if it
        can see you or drifts if it cannot, then moves one tile. After every zombie has moved, any
        zombie orthogonally adjacent to you deals its damage (multiple stack). A zombie that
        already bumped you in step 2 is skipped here - it cannot double-hit you in the same tick.
      </li>
      <li>Every active player's energy drops by 1. Voting any time during the round sets it back to 3.</li>
      <li>Players whose energy hits 0 flip to inactive - see below.</li>
      <li>Pending joiners (people who just joined this round) are promoted to active.</li>
      <li>The next round starts.</li>
    </ol>

    <h2 id="energy" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <Heart class="h-6 w-6 text-violet-400" />
      Energy blocks and going inactive
    </h2>
    <div class="mb-10 space-y-3 text-foreground">
      <p>
        Every active player has 3 energy blocks. Think of them as your attention span. They work like this:
      </p>
      <ul class="list-disc space-y-2 pl-6">
        <li>Every round you do not vote, you lose 1 block.</li>
        <li>Every vote you cast - move, hide, attack, or stay - resets your blocks back to 3.</li>
        <li>Your last vote <strong>persists</strong> across rounds. As long as you have energy, your last action
          keeps being counted. You only have to re-vote when you want to change your action or top up your blocks.</li>
        <li>When your blocks hit 0, you flip to <strong>inactive</strong>. The shared HP pool loses 1 HP on that
          transition (floor is 1 - you cannot kill the party just by going AFK).</li>
        <li>While inactive you do nothing. Your votes are ignored. To rejoin, type <code class="rounded bg-sidebar px-1">!join</code>
          again. That gives the pool +1 HP and puts you back in with fresh 3 energy blocks.</li>
      </ul>
      <p class="text-muted-foreground text-sm">
        Practical read: one vote buys you 3 rounds of presence. If you only care to weigh in occasionally, one
        vote every 3 rounds is enough to stay alive.
      </p>
    </div>

    <h2 id="rooms" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <DoorOpen class="h-6 w-6 text-violet-400" />
      Rooms, doors, and the exit
    </h2>
    <div class="mb-10 space-y-3 text-foreground">
      <p>
        The dungeon has <strong>5 rooms</strong> on a 9x9 grid. Each room has the player spawn at the bottom,
        the <strong>exit door</strong> at the top (always the same tile), plus hidden chest tiles scattered
        around. Room 5 also has pillars you cannot walk through. The only door in any room is the exit.
      </p>
      <p>
        The exit door starts <strong>closed</strong>. You cannot walk through it. To pass, you have to attack it
        until it opens, then walk onto its tile. Walking through the open exit in rooms 1 to 4 advances you to
        the next room. Walking through the open exit in room 5 wins the raid.
      </p>
      <p>
        Doors have three states: <strong>closed</strong>, <strong>opening</strong>, and <strong>open</strong>.
        A movement vote into a closed or opening door tile does nothing - you simply do not move onto it.
        Multi-step moves (e.g. <code class="rounded bg-sidebar px-1">!p up 3</code>) stop on the last walkable
        tile before the door.
      </p>
      <p>
        <code class="rounded bg-sidebar px-1">!a</code> hits the exit door if it is one of the 8 tiles around
        the player - horizontal, vertical, and diagonal neighbours (everything except the tile you stand on).
        Each room's exit has its own HP: room 1 needs 2 fist-damage hits to open, room 2 needs 3, room 3 needs
        3, room 4 needs 4, room 5 needs 5. The double-edged sword does 2 damage per hit, which cuts that in
        half.
      </p>
    </div>

    <h2 id="zombies" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <Ghost class="h-6 w-6 text-violet-400" />
      Zombies
    </h2>
    <div class="mb-10 space-y-4 text-foreground">
      <p>
        Every room has zombies. Rooms 1 to 4 have regular zombies; room 5 has a single boss. Each
        room escalates: more HP, more damage per hit, and in room 4 there are four of them at once.
      </p>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="z in zombies"
          :key="z.room"
          class="rounded-lg border border-sidebar bg-sidebar p-4"
        >
          <h3 class="mb-1 font-semibold">{{ z.room }}</h3>
          <p class="text-sm text-foreground">{{ z.count }} - {{ z.hp }} HP, {{ z.damage }} damage per hit</p>
          <p class="mt-1 text-xs text-muted-foreground">{{ z.notes }}</p>
        </div>
      </div>

      <h3 id="zombies-brain" class="mt-6 text-lg font-semibold">How zombies see and move</h3>
      <p>
        Every tick, each zombie picks one of two states based on whether it can currently see you:
      </p>
      <ul class="list-disc space-y-2 pl-6">
        <li>
          <strong>Line of sight</strong> is a straight line from the zombie to the player. Pillars
          (the solid blocks in room 5 and the occasional blocker tile) are <em>opaque</em> - they
          block both movement and sight. A pillar between you and a zombie breaks line of sight
          completely.
        </li>
        <li>
          <strong>Hiding</strong> (via <code class="rounded bg-sidebar px-1">!h</code>) makes you
          fully invisible. Zombies flip to drifting the moment you are on a hiding spot, even if
          the map between you is wide open. They do not steer toward hiding tiles.
        </li>
      </ul>
      <ul class="list-disc space-y-2 pl-6">
        <li>
          <strong>Chasing</strong> - zombie has sight of you: it steps 1 tile toward you on the axis
          with the bigger gap. If that step is blocked (pillar, another zombie, or your tile - it
          will not step onto the player), it tries the other axis. If both are blocked, it stays put
          for the tick.
        </li>
        <li>
          <strong>Drifting</strong> - zombie does not have sight of you: it tries to step in its
          current facing direction. If that is blocked it rotates clockwise (up → right → down →
          left → up) and tries again, up to four attempts. If every direction is blocked, it stays
          put for the tick.
        </li>
      </ul>

      <h3 id="zombies-damage" class="mt-6 text-lg font-semibold">How a zombie actually hits you</h3>
      <p>
        This is where it pays to understand <a href="#tick" class="underline">the tick</a>. Zombie
        damage arrives in one of two ways, at two different moments inside the same tick:
      </p>
      <div class="rounded-lg border border-rose-500/40 bg-rose-500/5 p-5">
        <p class="mb-2 font-semibold text-rose-300">1. Bump damage (during your action, step 2 of the tick)</p>
        <p class="text-sm">
          If the winning vote is <code class="rounded bg-sidebar px-1">!p</code> into a zombie's
          tile, you <em>do not move</em> and you take that zombie's damage instantly. Multi-step
          moves stop on the bump, so <code class="rounded bg-sidebar px-1">!p up 3</code> toward a
          zombie two tiles north walks one tile and bumps on the second attempt - one bump, one
          hit's worth of damage, you stop early.
        </p>
      </div>
      <div class="rounded-lg border border-rose-500/40 bg-rose-500/5 p-5">
        <p class="mb-2 font-semibold text-rose-300">2. Adjacency damage (after the zombies move, step 3 of the tick)</p>
        <p class="text-sm">
          Once every zombie has finished its turn, every zombie that ends up <strong>orthogonally
          adjacent</strong> to you (directly up, down, left, or right - diagonals do not count)
          deals its damage. Multiple adjacent zombies stack. This is how a zombie that was one tile
          away at the start of the tick still hits you even if nobody bumped - it walks up to you
          on its turn, and the game checks adjacency after.
        </p>
      </div>

      <div class="rounded-lg border border-sidebar bg-sidebar/40 p-5">
        <p class="mb-3 text-sm font-semibold">What "orthogonally adjacent" actually means</p>
        <div class="flex flex-wrap items-start gap-6">
          <div class="grid w-fit grid-cols-9 gap-0.5" aria-hidden="true">
            <div
              v-for="(tile, i) in adjacencyTiles"
              :key="i"
              class="h-6 w-6 rounded-sm"
              :class="{
                'border border-sidebar/60 bg-background/40': tile === 'empty',
                'bg-violet-500/80 ring-2 ring-violet-300': tile === 'player',
                'bg-rose-500/80': tile === 'hit',
                'border-2 border-dashed border-rose-500/60 bg-rose-500/10': tile === 'miss',
              }"
            />
          </div>
          <ul class="min-w-[12rem] flex-1 space-y-3 text-sm">
            <li class="flex items-center gap-2">
              <span class="inline-block h-4 w-4 shrink-0 rounded-sm bg-violet-500/80 ring-2 ring-violet-300"></span>
              You
            </li>
            <li class="flex items-center gap-2">
              <span class="inline-block h-4 w-4 shrink-0 rounded-sm bg-rose-500/80"></span>
              A zombie on any of these four tiles <strong>hits you</strong>
            </li>
            <li class="flex items-center gap-2">
              <span class="inline-block h-4 w-4 shrink-0 rounded-sm border-2 border-dashed border-rose-500/60 bg-rose-500/10"></span>
              A zombie on these four diagonal tiles does <strong>not</strong> hit you this tick
            </li>
          </ul>
        </div>
        <p class="mt-3 text-xs text-muted-foreground">
          Orthogonal just means "on a right-angle axis": up, down, left, right. Diagonals are a
          tile away but their centres are further (by the Pythagorean diagonal of one tile), and
          the zombie cannot land a punch across the corner.
        </p>
      </div>

      <p>
        A zombie that already dealt bump damage in step 2 is <strong>skipped</strong> in step 3 -
        the game will not double-hit you with the same zombie inside one tick.
      </p>
      <p>
        <strong>Hiding caveat:</strong> zombies cannot see you while you are in a hiding spot, so
        they stop chasing. They can still drift into the tile next to yours on their own, and if
        they do, the adjacency hit <strong>doubles</strong>: a room-1 zombie (1 dmg) hits for 2, a
        room-4 zombie (4 dmg) hits for 8. Hiding is only actually safe if nothing nearby can
        stumble into you. If everyone is busy elsewhere and no zombie ends up next to you this
        tick, hiding instead gives the party <strong>+1 HP</strong>.
      </p>
      <p class="text-sm text-muted-foreground">
        Practical read: "two tiles away" is not safe distance. If you vote
        <code class="rounded bg-card px-1">!p up</code> toward a zombie two north, you end up one
        tile south of it - and the zombie steps onto the tile between you on its turn. You take the
        hit. To actually dodge, move perpendicular, put a pillar between you, or kill it first.
      </p>

      <h3 id="zombies-kill" class="mt-6 text-lg font-semibold">Killing zombies</h3>
      <p>
        <code class="rounded bg-sidebar px-1">!a</code> always hits the nearest zombie in reach
        first - only if there is no zombie in reach does the attack fall back to the exit door's
        3x3 AoE. Reach depends on the weapon:
      </p>
      <ul class="list-disc space-y-1 pl-6">
        <li>
          <strong>Fists</strong> and <strong>regular sword</strong>: reach 1 tile, orthogonal only
          (up, down, left, right). No diagonals.
        </li>
        <li>
          <strong>Double-edged sword</strong> (<code class="rounded bg-sidebar px-1">!a 2</code>):
          reach 2 tiles measured as Manhattan distance. That is the eight orthogonal and diagonal
          neighbours plus the four straight-line tiles two steps out.
        </li>
      </ul>
      <p>
        Damage per hit against a zombie: fists <strong>2</strong>, regular sword <strong>3</strong>,
        double-edged sword <strong>4</strong>. (Those numbers are higher than their damage against
        doors, where fists/regular do 1 and the double-edged does 2 - so swords feel dramatically
        better in a fight than on a door.)
      </p>
      <p>
        When your attack kills a zombie, you automatically <strong>step one tile toward it</strong>
        on the axis with the bigger gap, no extra move vote needed. If the zombie was right next to
        you, that means you step onto its now-empty tile.
      </p>
      <p class="text-sm text-muted-foreground">
        Fists still cost 1 HP per swing whether you attack a zombie, a door, or the empty air.
        Iron fists removes that cost permanently. The regular sword consumes 1 use per swing
        regardless of target; the double-edged sword never expires.
      </p>
    </div>

    <h2 id="chests" class="mb-4 flex items-center gap-2 text-2xl font-bold">
      <Skull class="h-6 w-6 text-violet-400" />
      What is in the chests
    </h2>
    <p class="mb-4 text-foreground">
      Some tiles are hidden. Step on one and it reveals. The contents are seeded when the game starts, so what
      you find is not known until someone walks over it. Possible contents:
    </p>
    <div class="mb-10 grid gap-3 sm:grid-cols-2">
      <div
        v-for="item in chestItems"
        :key="item.name"
        :class="chestClass[item.tone]"
        class="rounded-lg border p-4"
      >
        <h3 class="mb-1 font-semibold">{{ item.name }}</h3>
        <p class="text-sm text-foreground">{{ item.effect }}</p>
      </div>
    </div>

    <h2 id="weapons" class="mb-4 text-2xl font-bold">Weapons and attack costs</h2>
    <div class="mb-10 space-y-3 text-foreground">
      <p>
        You start with <strong>fists</strong> in slot 1. Fists do 1 damage per hit - but every swing costs 1 HP
        of self-damage from the shared pool, unless you are wearing <strong>iron fists</strong>.
      </p>
      <p>
        A <strong>regular sword</strong> replaces your fists in slot 1 for 10 uses. It does 1 damage per hit and
        costs no HP. When the 10 uses are spent, slot 1 reverts to fists.
      </p>
      <p>
        A <strong>double-edged sword</strong> fills slot 2 and never expires. 2 damage per hit, no HP cost. Cast
        it with <code class="rounded bg-sidebar px-1">!a 2</code> when you want to blow through a tough door.
      </p>
      <p class="text-muted-foreground text-sm">
        All attacks are AoE - the weapon you choose determines the damage, but the shape is always the 3x3 around
        the player.
      </p>
    </div>

    <h2 id="pool" class="mb-4 text-2xl font-bold">The shared HP pool</h2>
    <div class="mb-10 space-y-3 text-foreground">
      <p>
        There is one HP pool for the whole raid. The streamer starts the game with a base HP value. Every
        <code class="rounded bg-sidebar px-1">!join</code> adds +1 HP. Every player going inactive costs 1 HP
        (floored at 1). Bombs cost 1 HP. Punching with bare fists costs 1 HP per swing.
      </p>
      <p>
        HP restores from chests add to the pool directly. If HP ever hits 0 from a bomb or a punch, the raid
        ends and the party loses.
      </p>
      <p class="text-muted-foreground text-sm">
        Practical read: the more people who <code class="rounded bg-sidebar px-1">!join</code>, the more runway
        the raid has. But everyone who AFKs after joining is a slow drain. Get people to vote or accept that
        they will eventually cost you 1.
      </p>
    </div>

    <h2 id="winning" class="mb-4 text-2xl font-bold">How a run ends</h2>
    <ul class="list-disc space-y-2 pl-6 text-foreground">
      <li><strong>Win:</strong> the player opens the exit door in room 5 and walks through it.</li>
      <li><strong>Lose:</strong> the HP pool hits 0 from a bomb or a bare-fist punch.</li>
      <li>
        <strong>Streamer ended:</strong> the broadcaster can end the game at any time. The overlay closes and
        the next <code class="rounded bg-sidebar px-1">!join</code> wave starts fresh.
      </li>
    </ul>

    <div class="mt-10 rounded-lg border border-sidebar bg-sidebar p-5 text-sm text-foreground">
      <p>
        Viewers: share this page with <code class="rounded bg-background/40 px-1">!castlehelp</code> in chat.
        Streamers running Chat Castle can enable the command by opting into the @overlabels bot.
      </p>
    </div>
  </HelpLayout>
</template>
