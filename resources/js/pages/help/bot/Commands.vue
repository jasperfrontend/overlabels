<script setup lang="ts">
import type { BreadcrumbItem } from '@/types';
import HelpLayout from '@/layouts/HelpLayout.vue';
import { ShieldAlert, ChevronRight } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Twitch Bot', href: '/help/bot' },
  { title: 'Commands', href: '/help/bot/commands' },
];

const STORAGE_KEY = 'help.bot.commands.controlsWarningAck';
const iUnderstand = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '1',
);
watch(iUnderstand, (value) => {
  if (typeof localStorage === 'undefined') return;
  if (value) localStorage.setItem(STORAGE_KEY, '1');
  else localStorage.removeItem(STORAGE_KEY);
});

type Tier = 'everyone' | 'subscriber' | 'vip' | 'moderator' | 'broadcaster';

interface BotCommandDoc {
  command: string;
  tier: Tier;
  summary: string;
  example?: { chat: string; reply: string };
  notes?: string;
}

const tierLabel: Record<Tier, string> = {
  everyone: 'Everyone',
  subscriber: 'Sub+',
  vip: 'VIP+',
  moderator: 'Mod+',
  broadcaster: 'Broadcaster',
};

const tierClass: Record<Tier, string> = {
  everyone: 'bg-emerald-500/15 text-emerald-300',
  subscriber: 'bg-sky-500/15 text-sky-300',
  vip: 'bg-pink-500/15 text-pink-300',
  moderator: 'bg-amber-500/15 text-amber-300',
  broadcaster: 'bg-violet-500/15 text-violet-300',
};

// ── Controls ──────────────────────────────────────────────────────────────
const controlCommands: BotCommandDoc[] = [
  {
    command: '!control <key>',
    tier: 'everyone',
    summary: 'Read the current value of one of your controls.',
    example: { chat: '!control level', reply: '@viewer level: 8' },
  },
  {
    command: '!set <key> <value>',
    tier: 'moderator',
    summary: 'Set a control to an exact value.',
    example: { chat: '!set level 8', reply: '@mod set level to 8' },
  },
  {
    command: '!increment <key> [amount]',
    tier: 'moderator',
    summary: 'Add to a number/counter control. Amount defaults to 1.',
    example: { chat: '!inc wins', reply: '@mod wins is now 4' },
    notes: 'Shorthand: !inc. Negative amounts work (!inc wins -2 subtracts 2).',
  },
  {
    command: '!decrement <key> [amount]',
    tier: 'moderator',
    summary: 'Subtract from a number/counter control. Amount defaults to 1.',
    example: { chat: '!dec lives 1', reply: '@mod lives is now 2' },
    notes: 'Shorthand: !dec.',
  },
  {
    command: '!reset <key>',
    tier: 'broadcaster',
    summary: 'Reset a number or counter control back to 0.',
    example: { chat: '!reset wins', reply: '@broadcaster reset wins to 0' },
  },
  {
    command: '!enable <key>',
    tier: 'moderator',
    summary: 'Enable a boolean control.',
    example: { chat: '!enable mute', reply: '@mod enabled mute' },
  },
  {
    command: '!disable <key>',
    tier: 'moderator',
    summary: 'Disable a boolean control.',
    example: { chat: '!disable mute', reply: '@mod disabled mute' },
  },
  {
    command: '!toggle <key>',
    tier: 'moderator',
    summary: 'Flip a boolean control to the opposite state.',
    example: { chat: '!toggle mute', reply: '@mod mute is now enabled' },
  },
];

// ── Controls-access switch ────────────────────────────────────────────────
const accessCommands: BotCommandDoc[] = [
  {
    command: '!enablecontrols',
    tier: 'broadcaster',
    summary: 'Open the controls surface on this channel. Default is closed.',
    example: { chat: '!enablecontrols', reply: '@broadcaster chat control commands are now enabled' },
  },
  {
    command: '!disablecontrols',
    tier: 'broadcaster',
    summary: 'Close the controls surface again. Chat can no longer touch your controls.',
    example: { chat: '!disablecontrols', reply: '@broadcaster chat control commands are now disabled' },
  },
];

// ── Bot administration (!ol family) ───────────────────────────────────────
const olCommands: BotCommandDoc[] = [
  {
    command: '!ol cmd add <name> <payload>',
    tier: 'moderator',
    summary: 'Create a Bot Expression - a custom !command that speaks a templated reply.',
    example: { chat: '!ol cmd add lol HAHA [[[bot:from_user]]]', reply: '@mod added !lol' },
    notes: 'Payload can include any template tag: [[[c:foo]]] for controls, [[[bot:from_user]]] for the chatter, [[[follower_count]]] for Helix data.',
  },
  {
    command: '!ol cmd edit <name> <payload>',
    tier: 'moderator',
    summary: 'Replace the reply template on an existing expression.',
    example: { chat: '!ol cmd edit lol BAHAHA [[[bot:from_user]]]!', reply: '@mod updated !lol' },
  },
  {
    command: '!ol cmd delete <name>',
    tier: 'moderator',
    summary: 'Remove a Bot Expression from your channel.',
    example: { chat: '!ol cmd delete lol', reply: '@mod deleted !lol' },
  },
  {
    command: '!ol cmd options <name> <option> <value>',
    tier: 'moderator',
    summary: 'Tune one option on an expression. Options: cooldown, permission, enabled, hidden.',
    example: { chat: '!ol cmd options lol cooldown 30', reply: '@mod !lol cooldown is now 30s' },
    notes: 'Permission shortforms work: mod, sub, vip, bc, all. Booleans accept true/false/on/off/yes/no/1/0.',
  },
  {
    command: '!ol alias add <name> <target>',
    tier: 'moderator',
    summary: 'Create a Bot Alias - a short command that rewrites to a longer one.',
    example: { chat: '!ol alias add w !inc wins {1}', reply: '@mod added alias !w -> !inc wins {1}' },
    notes: 'Use {1}, {2}, ... for positional args from the alias call site. {*} captures all remaining args. Aliases can target builtins or your expressions, but not other aliases (one hop only).',
  },
  {
    command: '!ol alias edit <name> <target>',
    tier: 'moderator',
    summary: 'Change what an existing alias rewrites to.',
    example: { chat: '!ol alias edit w !inc wins_total {1}', reply: '@mod updated alias !w -> !inc wins_total {1}' },
  },
  {
    command: '!ol alias delete <name>',
    tier: 'moderator',
    summary: 'Remove an alias.',
    example: { chat: '!ol alias delete w', reply: '@mod deleted alias !w' },
  },
  {
    command: '!ol alias options <name> <option> <value>',
    tier: 'moderator',
    summary: 'Same options as !ol cmd options, applied to an alias.',
    example: { chat: '!ol alias options w permission broadcaster', reply: '@mod alias !w permission is now broadcaster' },
    notes: 'The target command\'s own permission still applies after the alias rewrite, so this only restricts who can trigger the alias itself.',
  },
  {
    command: '!ol list [cmd|alias]',
    tier: 'moderator',
    summary: 'Print every expression and alias you have. Optional filter.',
    example: { chat: '!ol list', reply: '@mod commands: !lol !discord | aliases: !w' },
    notes: 'Output is clipped to ~480 characters so it stays inside Twitch chat\'s message limit.',
  },
  {
    command: '!ol help [cmd|alias|options]',
    tier: 'moderator',
    summary: 'Print a usage line for !ol or one of its subverbs.',
    example: { chat: '!ol help', reply: '@mod !ol cmd <add|edit|delete|options> ; !ol alias <add|edit|delete|options> ; !ol list ; !ol help <cmd|alias|options>' },
  },
];

// ── List meta-command ──────────────────────────────────────────────────────
const listCommands: BotCommandDoc[] = [
  {
    command: '!list <slug> <action> [args]',
    tier: 'moderator',
    summary: 'Operate on one of your Lists. ~20 actions cover read, append, draw, snapshot, clear, etc.',
    example: { chat: '!list raffle count', reply: '@mod \'raffle\' has 17 entries' },
    notes: 'The verb after the slug is the action. Full action vocabulary lives on the /help/lists page - this command exposes every action there to chat.',
  },
];

// ── Misc ───────────────────────────────────────────────────────────────────
const miscCommands: BotCommandDoc[] = [
  {
    command: '!ping',
    tier: 'everyone',
    summary: 'Liveness check. The bot says pong.',
    example: { chat: '!ping', reply: '@viewer pong' },
  },
];
</script>

<template>
  <HelpLayout
    :breadcrumbs="breadcrumbs"
    title="Bot Commands - Overlabels Help"
    description="Every chat command the @overlabels Twitch bot understands - controls, !ol chat-admin meta-command, list operations, and built-ins."
    canonical-url="https://overlabels.com/help/bot/commands"
  >
    <!-- Hero -->
    <div class="mb-8">
      <Heading
        title="Bot Commands"
        title-class="text-4xl font-bold mb-4"
        description="Every chat command the @overlabels Twitch bot understands - controls, !ol chat-admin meta-command, list operations, and built-ins. Click a row to expand it and show an example."
      />
    </div>

    <!-- Controls-access warning (collapsed version once acknowledged) -->
    <div
      v-if="!iUnderstand"
      class="mb-8 border border-amber-500 bg-amber-500/20 p-5"
    >
      <h2 class="mb-2 text-xl font-semibold text-amber-500">
        <ShieldAlert class="mr-2 inline-block h-5 w-5" />
        Control commands are OFF by default
      </h2>
      <p class="mb-3 text-foreground">
        Everything in the <em>Controls</em> section below is gated behind a per-channel switch. The default is
        <strong>off</strong>: until you flip it on, the bot will ignore <code>!control</code>, <code>!set</code>,
        <code>!increment</code>, <code>!decrement</code>, <code>!reset</code>, <code>!enable</code>,
        <code>!disable</code>, and <code>!toggle</code> entirely. <strong>The !ol chat-admin commands and
        !list meta-command work regardless</strong> - they don't touch your controls layer.
      </p>
      <p class="mb-3 text-foreground">
        To open the controls surface for your channel, type <code class="rounded bg-background px-1.5 py-0.5 font-mono">!enablecontrols</code>
        in your own chat (broadcaster only). To close it again, <code class="rounded bg-background px-1.5 py-0.5 font-mono">!disablecontrols</code>.
      </p>
      <button class="btn btn-chill cursor-pointer" @click="iUnderstand = true">
        I understand, let's go!
      </button>
    </div>
    <div
      v-else
      class="mb-8 flex flex-wrap items-center justify-between gap-3 border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm"
    >
      <p class="text-foreground">
        <ShieldAlert class="mr-1 inline-block h-4 w-4 text-amber-500" />
        Controls section requires <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!enablecontrols</code> first.
      </p>
      <button
        class="cursor-pointer text-sm text-amber-500 underline hover:text-amber-400"
        @click="iUnderstand = false"
      >
        Show details
      </button>
    </div>

    <!-- TOC -->
    <div class="mb-12 border border-sidebar-border bg-card p-5">
      <h2 class="mb-3 text-lg font-semibold" id="toc">Jump to</h2>
      <ol class="grid list-decimal grid-cols-1 gap-x-6 gap-y-1 pl-6 text-foreground sm:grid-cols-2">
        <li><a href="#controls" class="text-violet-400 hover:underline">Controls</a></li>
        <li><a href="#access" class="text-violet-400 hover:underline">Controls-access switch</a></li>
        <li><a href="#ol" class="text-violet-400 hover:underline"><code>!ol</code> chat-admin</a></li>
        <li><a href="#list" class="text-violet-400 hover:underline"><code>!list</code> meta-command</a></li>
        <li><a href="#user" class="text-violet-400 hover:underline">Your own commands</a></li>
        <li><a href="#misc" class="text-violet-400 hover:underline">Miscellaneous</a></li>
        <li><a href="#tiers" class="text-violet-400 hover:underline">Permission tiers</a></li>
      </ol>
    </div>

    <!-- Section: Controls -->
    <section class="mb-12" id="controls">
      <h2 class="mb-3 text-2xl font-bold">Controls</h2>
      <p class="mb-5 text-foreground">
        Read and write your overlay controls from chat. The bot only ever touches controls you created yourself -
        service-managed controls (Ko-fi, StreamLabs, StreamElements counters) are intentionally invisible to chat.
        Requires <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!enablecontrols</code> on your channel.
      </p>
      <div class="overflow-hidden border border-sidebar-border bg-sidebar-accent">
        <details
          v-for="(cmd, i) in controlCommands"
          :key="cmd.command"
          class="group"
          :class="{ 'border-t border-sidebar': i > 0 }"
        >
          <summary class="flex flex-col md:flex-row cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-foreground/5 sm:items-center">
            <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90 sm:mt-0" />
            <code class="rounded bg-background px-2 py-0.5 font-mono text-sm whitespace-nowrap">{{ cmd.command }}</code>
            <span :class="tierClass[cmd.tier]" class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap">
              {{ tierLabel[cmd.tier] }}
            </span>
            <span class="text-sm text-foreground">{{ cmd.summary }}</span>
          </summary>
          <div class="border-t border-sidebar bg-background/30 px-4 py-3">
            <div v-if="cmd.example" class="mb-2 rounded border border-sidebar bg-background/50 p-3 font-mono text-sm">
              <div class="text-foreground"><span class="text-muted-foreground">chat:</span> {{ cmd.example.chat }}</div>
              <div class="mt-1 text-foreground"><span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}</div>
            </div>
            <p v-if="cmd.notes" class="text-sm text-muted-foreground">{{ cmd.notes }}</p>
          </div>
        </details>
      </div>
    </section>

    <!-- Section: Controls-access -->
    <section class="mb-12" id="access">
      <h2 class="mb-3 text-2xl font-bold">Controls-access switch</h2>
      <p class="mb-5 text-foreground">
        Broadcaster-only toggles that open or close the entire <em>Controls</em> section above. Useful when you
        want chat to drive your overlay state for one stream and not the next.
      </p>
      <div class="overflow-hidden border border-sidebar-border bg-sidebar-accent">
        <details
          v-for="(cmd, i) in accessCommands"
          :key="cmd.command"
          class="group"
          :class="{ 'border-t border-sidebar': i > 0 }"
        >
          <summary class="flex flex-col md:flex-row cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-foreground/5 sm:items-center">
            <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90 sm:mt-0" />
            <code class="rounded bg-background px-2 py-0.5 font-mono text-sm whitespace-nowrap">{{ cmd.command }}</code>
            <span :class="tierClass[cmd.tier]" class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap">
              {{ tierLabel[cmd.tier] }}
            </span>
            <span class="text-sm text-foreground">{{ cmd.summary }}</span>
          </summary>
          <div class="border-t border-sidebar bg-background/30 px-4 py-3">
            <div v-if="cmd.example" class="rounded border border-sidebar bg-background/50 p-3 font-mono text-sm">
              <div class="text-foreground"><span class="text-muted-foreground">chat:</span> {{ cmd.example.chat }}</div>
              <div class="mt-1 text-foreground"><span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}</div>
            </div>
          </div>
        </details>
      </div>
    </section>

    <!-- Section: !ol chat-admin -->
    <section class="mb-12" id="ol">
      <h2 class="mb-3 text-2xl font-bold"><code>!ol</code> chat-admin</h2>
      <p class="mb-3 text-foreground">
        Manage your custom commands and aliases without leaving Twitch. <code class="rounded bg-background px-1.5 py-0.5 font-mono">!ol</code>
        is namespaced this way so it doesn't fight with other bots (StreamElements, Wizebot, Nightbot, Streamlabs Cloudbot
        all already own <code>!command</code> / <code>!cmd</code> / <code>!commands</code>).
      </p>
      <p class="mb-5 text-foreground">
        All <code>!ol</code> subverbs are moderator+. The replies are queued through the bot's outbox so they thread
        normally in chat. Validation runs server-side - the same rules that gate the dashboard form catch chat-side
        typos too (reserved names, self-looping aliases, bad placeholder syntax, etc).
      </p>
      <div class="overflow-hidden border border-sidebar-border bg-sidebar-accent">
        <details
          v-for="(cmd, i) in olCommands"
          :key="cmd.command"
          class="group"
          :class="{ 'border-t border-sidebar': i > 0 }"
        >
          <summary class="flex flex-col md:flex-row cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-foreground/5 sm:items-center">
            <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90 sm:mt-0" />
            <code class="rounded bg-background px-2 py-0.5 font-mono text-sm whitespace-nowrap">{{ cmd.command }}</code>
            <span :class="tierClass[cmd.tier]" class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap">
              {{ tierLabel[cmd.tier] }}
            </span>
            <span class="text-sm text-foreground">{{ cmd.summary }}</span>
          </summary>
          <div class="border-t border-sidebar bg-background/30 px-4 py-3">
            <div v-if="cmd.example" class="mb-2 rounded border border-sidebar bg-background/50 p-3 font-mono text-sm">
              <div class="text-foreground"><span class="text-muted-foreground">chat:</span> {{ cmd.example.chat }}</div>
              <div class="mt-1 text-foreground"><span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}</div>
            </div>
            <p v-if="cmd.notes" class="text-sm text-muted-foreground">{{ cmd.notes }}</p>
          </div>
        </details>
      </div>

      <div class="mt-5 border border-sidebar-border bg-card p-4 text-sm">
        <h3 class="mb-2 font-semibold text-foreground">Options vocabulary</h3>
        <p class="mb-3 text-foreground">
          <code class="rounded bg-background px-1.5 py-0.5 font-mono">!ol cmd options &lt;name&gt; &lt;option&gt; &lt;value&gt;</code>
          and the alias equivalent both accept these option keys:
        </p>
        <ul class="space-y-1 text-foreground">
          <li><code class="rounded bg-background px-1.5 py-0.5 font-mono">cooldown</code> - integer seconds, 0 to 86400. Broadcaster bypasses cooldown.</li>
          <li><code class="rounded bg-background px-1.5 py-0.5 font-mono">permission</code> - <code>everyone</code> / <code>sub</code> / <code>vip</code> / <code>mod</code> / <code>broadcaster</code>. <code>all</code> is a synonym for everyone, <code>bc</code> for broadcaster.</li>
          <li><code class="rounded bg-background px-1.5 py-0.5 font-mono">enabled</code> - <code>true</code> or <code>false</code> (also accepts on/off, yes/no, 1/0).</li>
          <li><code class="rounded bg-background px-1.5 py-0.5 font-mono">hidden</code> - hides this command from the future <code>!commands</code> listing without disabling it.</li>
        </ul>
      </div>
    </section>

    <!-- Section: !list -->
    <section class="mb-12" id="list">
      <h2 class="mb-3 text-2xl font-bold"><code>!list</code> meta-command</h2>
      <p class="mb-5 text-foreground">
        One mod-only command exposes the full action vocabulary of your Lists to chat. The shape is
        <code class="rounded bg-background px-1.5 py-0.5 font-mono">!list &lt;slug&gt; &lt;action&gt; [args]</code> and the verbs
        cover read (<code>count</code>, <code>first</code>, <code>random</code>...), grow (<code>add</code>), shrink (<code>draw</code>, <code>pop</code>, <code>clear</code>),
        snapshot/restore, and lifecycle (<code>disable</code>, <code>enable</code>).
      </p>
      <div class="overflow-hidden border border-sidebar-border bg-sidebar-accent">
        <details
          v-for="(cmd, i) in listCommands"
          :key="cmd.command"
          class="group"
          :class="{ 'border-t border-sidebar': i > 0 }"
        >
          <summary class="flex flex-col md:flex-row cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-foreground/5 sm:items-center">
            <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90 sm:mt-0" />
            <code class="rounded bg-background px-2 py-0.5 font-mono text-sm whitespace-nowrap">{{ cmd.command }}</code>
            <span :class="tierClass[cmd.tier]" class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap">
              {{ tierLabel[cmd.tier] }}
            </span>
            <span class="text-sm text-foreground">{{ cmd.summary }}</span>
          </summary>
          <div class="border-t border-sidebar bg-background/30 px-4 py-3">
            <div v-if="cmd.example" class="mb-2 rounded border border-sidebar bg-background/50 p-3 font-mono text-sm">
              <div class="text-foreground"><span class="text-muted-foreground">chat:</span> {{ cmd.example.chat }}</div>
              <div class="mt-1 text-foreground"><span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}</div>
            </div>
            <p v-if="cmd.notes" class="text-sm text-muted-foreground">{{ cmd.notes }}</p>
          </div>
        </details>
      </div>
      <p class="mt-3 text-sm text-foreground">
        Full action reference: <a href="/help/lists#actions" class="text-violet-400 hover:underline">/help/lists - the action vocabulary in detail</a>.
      </p>
    </section>

    <!-- Section: User-defined -->
    <section class="mb-12" id="user">
      <h2 class="mb-3 text-2xl font-bold">Your own commands</h2>
      <p class="mb-4 text-foreground">
        Everything above is built into the bot. On top of that you can author four kinds of custom commands - each
        is managed from the dashboard, and three of them are also reachable through <code>!ol</code> in chat.
      </p>
      <div class="grid gap-3 sm:grid-cols-3">
        <a :href="route('help.expressions')" class="block border border-sidebar-border bg-sidebar-accent p-4 hover:border-violet-400/50">
          <p class="mb-1 font-semibold text-foreground">Bot Expressions</p>
          <p class="text-sm text-muted-foreground">
            Custom <code>!command</code> chat replies templated against controls, Helix data, and the chatter who
            fired them. The bot speaks the resolved string.
          </p>
        </a>
        <a :href="route('help.bot.aliases')" class="block border border-sidebar-border bg-sidebar-accent p-4 hover:border-violet-400/50">
          <p class="mb-1 font-semibold text-foreground">Bot Aliases</p>
          <p class="text-sm text-muted-foreground">
            Short commands that rewrite to longer ones before dispatch. <code>!w 2</code> becomes
            <code>!inc wins 2</code>. Positional placeholders: <code>{1}</code>, <code>{2}</code>, <code>{*}</code>.
          </p>
        </a>
        <a :href="`${route('help.lists')}#appenders`" class="block border border-sidebar-border bg-sidebar-accent p-4 hover:border-violet-400/50">
          <p class="mb-1 font-semibold text-foreground">List Appenders</p>
          <p class="text-sm text-muted-foreground">
            Chat commands that append a chatter's input to one of your Lists. Raffle entries, quote walls,
            song requests - one verb per kind of growing list.
          </p>
        </a>
      </div>
      <p class="mt-4 text-sm text-foreground">
        A custom command can't claim the name of a built-in - <code>!control</code>, <code>!ol</code>,
        <code>!list</code>, <code>!ping</code>, etc. are reserved. Validation catches the collision at save time
        on both the dashboard and <code>!ol cmd add</code>.
      </p>
    </section>

    <!-- Section: Misc -->
    <section class="mb-12" id="misc">
      <h2 class="mb-3 text-2xl font-bold">Miscellaneous</h2>
      <div class="overflow-hidden border border-sidebar-border bg-sidebar-accent">
        <details
          v-for="(cmd, i) in miscCommands"
          :key="cmd.command"
          class="group"
          :class="{ 'border-t border-sidebar': i > 0 }"
        >
          <summary class="flex flex-col md:flex-row cursor-pointer items-start gap-3 px-4 py-2.5 hover:bg-foreground/5 sm:items-center">
            <ChevronRight class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-90 sm:mt-0" />
            <code class="rounded bg-background px-2 py-0.5 font-mono text-sm whitespace-nowrap">{{ cmd.command }}</code>
            <span :class="tierClass[cmd.tier]" class="rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap">
              {{ tierLabel[cmd.tier] }}
            </span>
            <span class="text-sm text-foreground">{{ cmd.summary }}</span>
          </summary>
          <div class="border-t border-sidebar bg-background/30 px-4 py-3">
            <div v-if="cmd.example" class="rounded border border-sidebar bg-background/50 p-3 font-mono text-sm">
              <div class="text-foreground"><span class="text-muted-foreground">chat:</span> {{ cmd.example.chat }}</div>
              <div class="mt-1 text-foreground"><span class="text-muted-foreground">@overlabels:</span> {{ cmd.example.reply }}</div>
            </div>
          </div>
        </details>
      </div>
      <p class="mt-3 text-sm text-foreground">
        Chat Castle (the chat-driven map game) ships several command verbs of its own - <code>!join</code>,
        <code>!p</code>, <code>!h</code>, <code>!a</code>, <code>!s</code>, <code>!castlehelp</code>. Those are
        documented separately at <a href="/help/gamejam" class="text-violet-400 hover:underline">/help/gamejam</a>.
      </p>
    </section>

    <!-- Section: Permission tiers -->
    <section class="mb-12" id="tiers">
      <h2 class="mb-3 text-2xl font-bold">Permission tiers</h2>
      <p class="mb-4 text-foreground">
        Permission tiers stack from least to most privileged: a moderator can invoke anything tagged Moderator+,
        VIP+, Sub+, or Everyone. Broadcaster can invoke anything.
      </p>
      <div class="flex flex-wrap gap-2">
        <span class="rounded px-2 py-1 text-sm font-medium bg-emerald-500/15 text-emerald-300">Everyone</span>
        <span class="rounded px-2 py-1 text-sm font-medium bg-sky-500/15 text-sky-300">Sub+</span>
        <span class="rounded px-2 py-1 text-sm font-medium bg-pink-500/15 text-pink-300">VIP+</span>
        <span class="rounded px-2 py-1 text-sm font-medium bg-amber-500/15 text-amber-300">Mod+</span>
        <span class="rounded px-2 py-1 text-sm font-medium bg-violet-500/15 text-violet-300">Broadcaster</span>
      </div>
      <p class="mt-4 text-sm text-foreground">
        Founder counts as Sub+ on the @overlabels tier ladder. The bot doesn't model founder as a separate tier.
      </p>
    </section>
  </HelpLayout>
</template>

<style scoped>
/* Remove the default disclosure triangle in favor of the ChevronRight icon. */
summary {
  list-style: none;
}
summary::-webkit-details-marker {
  display: none;
}
</style>
