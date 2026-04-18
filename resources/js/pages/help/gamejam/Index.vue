<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import { Swords, Heart, Clock, Dices, DoorOpen, Skull } from 'lucide-vue-next';

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
    name: 'Destruction sword',
    effect: 'Fills weapon slot 2 permanently, 2 damage per hit. Cast with !a:2.',
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
    command: '!a',
    summary: 'Propose attacking with weapon slot 1 (fists by default). Hits every closed door in the 3x3 around the player, diagonals included.',
    example: '!a',
  },
  {
    command: '!a:2',
    summary: 'Attack with weapon slot 2 (destruction sword, if you have one). Deals 2 damage per hit instead of 1.',
    example: '!a:2',
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
        <li>Every round, every active player loses 1 energy block. Voting resets your energy back to 3.</li>
        <li>Miss enough rounds and you go inactive. The shared HP pool loses 1 when that happens.</li>
        <li>Attack closed doors to open them. Walk through the exit to advance. Clear room 5 to win.</li>
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
      <li>The action with the most votes is applied (ties are broken deterministically).</li>
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
        The dungeon has <strong>5 rooms</strong>. Each room has walls, blockers, hidden tiles, some doors, and
        one <strong>exit door</strong>. The goal of a room is to walk onto the exit tile. Step on it and the
        game advances to the next room. Step onto the exit in room 5 and you win the raid.
      </p>
      <p>
        Doors can be <strong>closed</strong>, <strong>opening</strong>, or <strong>open</strong>. Players cannot
        walk through closed or opening doors. To open a door, you attack it.
      </p>
      <p>
        <code class="rounded bg-sidebar px-1">!a</code> hits every closed or opening door in the 3x3 area around
        the player - all 8 neighbouring tiles, diagonals included. One attack can chip away at several doors at
        once, which matters when rooms have clusters of them.
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
        A <strong>destruction sword</strong> fills slot 2 and never expires. 2 damage per hit, no HP cost. Cast
        it with <code class="rounded bg-sidebar px-1">!a:2</code> when you want to blow through a tough door.
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
      <li><strong>Win:</strong> the player walks onto the exit tile in room 5.</li>
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
