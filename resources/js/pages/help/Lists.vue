<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Lists', href: '/help/lists' },
];
</script>

<template>
  <Head>
    <title>Lists - Overlabels</title>
    <meta
      name="description"
      content="Lists in Overlabels: user-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals - all driven by the same primitive."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/lists" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Lists - Overlabels" />
    <meta
      property="og:description"
      content="User-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals - all driven by the same primitive."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Lists - Overlabels" />
    <meta
      name="twitter:description"
      content="User-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals."
    />
    <meta name="twitter:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta name="twitter:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">

        <!-- Header -->
        <div class="mb-10">
          <Heading
            title="Lists in Overlabels"
            title-class="text-4xl font-bold mb-4"
            description="User-owned arrays of values that streamers manage from the dashboard or chat. Raffles, queues, quote walls, leaderboards, donation goals."
          />
        </div>

        <!-- TOC -->
        <div class="mb-12 border border-sidebar-accent bg-card p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#what" class="text-violet-400 hover:underline">What is a List?</a></li>
            <li><a href="#creating" class="text-violet-400 hover:underline">Creating and editing Lists</a></li>
            <li><a href="#reading" class="text-violet-400 hover:underline">Reading from a List in your overlay</a></li>
            <li><a href="#foreach" class="text-violet-400 hover:underline">Iterating with <code>foreach</code></a></li>
            <li><a href="#appenders" class="text-violet-400 hover:underline">Chat appenders - viewers grow your List</a></li>
            <li><a href="#meta" class="text-violet-400 hover:underline">The <code>!list</code> meta-command</a></li>
            <li><a href="#actions" class="text-violet-400 hover:underline">The action vocabulary in detail</a></li>
            <li><a href="#snapshots" class="text-violet-400 hover:underline">Snapshots - undo for destructive actions</a></li>
            <li><a href="#expiry" class="text-violet-400 hover:underline">Auto-expiry - entry age-out and whole-list deadlines</a></li>
            <li><a href="#disable" class="text-violet-400 hover:underline">Disable and enable</a></li>
            <li><a href="#examples" class="text-violet-400 hover:underline">Worked examples</a></li>
            <li><a href="#things-to-know" class="text-violet-400 hover:underline">Things to know</a></li>
            <li><a href="#quick-ref" class="text-violet-400 hover:underline">Quick reference card</a></li>
          </ol>
        </div>

        <!-- What is a List? -->
        <section class="mb-14" id="what">
          <h2 class="mb-4 text-2xl font-bold">What is a List?</h2>
          <p class="mb-4 text-foreground">
            A List is a named array of values you own. Each List has a slug (the identifier you reference in overlays
            and chat) and an optional human-readable label. The values inside can be anything - viewer names, custom
            messages, numbers, URLs - and Overlabels stores them exactly as they were entered. No deduplication, no
            whitespace trimming, no quiet reordering. Lists are lists.
          </p>
          <p class="mb-4 text-foreground">
            You reference a List in your overlay HTML/CSS with
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[c:list:your_slug]]]</code>.
            That tag resolves to the full array as a JSON string when used bare; the more common usage is one of the
            derived read tags (see <a href="#reading" class="text-violet-400 hover:underline">Reading from a List</a>)
            or a <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">foreach</code> loop.
          </p>
          <p class="text-foreground">
            Lists are <strong>user-scoped</strong>: every streamer has their own. They are <strong>not</strong> shared
            across users. A List you create is only visible inside your overlays, your chat commands, and your
            dashboard.
          </p>
        </section>

        <!-- Creating -->
        <section class="mb-14" id="creating">
          <h2 class="mb-4 text-2xl font-bold">Creating and editing Lists</h2>
          <p class="mb-4 text-foreground">
            Lists live at <Link href="/dashboard/lists" class="text-violet-400 hover:underline">/dashboard/lists</Link>.
            Click "New list", pick a slug (lowercase letters, digits, underscores - must start with a letter, max 50
            chars), optionally a label, and optionally a starting set of items - one per line.
          </p>
          <div class="border border-sidebar-accent bg-card p-6">
            <h3 class="mb-2 text-lg font-semibold">Why no dashes in the slug?</h3>
            <p class="text-foreground">
              Tag parser context. A slug like <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">my-raffle</code>
              would collide with hyphen handling inside the tag namespace. Snake_case keeps everything composable.
            </p>
          </div>
          <p class="my-4 text-foreground">
            Once a List exists you can edit its items in a freeform textarea, one item per line. Empty lines, leading
            or trailing whitespace, and duplicates are all preserved exactly. The only character we strip is the
            NUL byte (because it breaks JSON encoding and you didn't actually mean to type it).
          </p>
          <p class="text-foreground">
            Some Lists are created automatically by recipes you install. Those show a "from Recipe" badge and may be
            locked (the recipe declares whether you can edit the items). Locking only affects items - you can still
            disable/enable a recipe-managed List from your dashboard.
          </p>
        </section>

        <!-- Reading -->
        <section class="mb-14" id="reading">
          <h2 class="mb-4 text-2xl font-bold">Reading from a List in your overlay</h2>
          <p class="mb-4 text-foreground">
            Every List ships a set of tags into your overlay's data store on render. Here's the full set for a List
            with slug <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">donors</code> and items
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">["Alice", "Bob", "Carol"]</code>:
          </p>
          <div class="mb-6 overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Tag</th>
                  <th class="p-3 font-semibold">Resolves to</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors]]]</code></td>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">["Alice","Bob","Carol"]</code> (full array as JSON string)</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:first]]]</code></td>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">Alice</code></td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:last]]]</code></td>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">Carol</code></td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:count]]]</code></td>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">3</code></td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:empty]]]</code></td>
                  <td class="p-3 text-foreground"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">0</code> (would be <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">1</code> if empty) - pair with <Link href="/help/conditionals" class="text-violet-400 hover:underline">conditional tags</Link></td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:random]]]</code></td>
                  <td class="p-3 text-foreground">Random item - stable per overlay mount (does not re-roll on each broadcast)</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:sum]]]</code></td>
                  <td class="p-3 text-foreground">Numeric sum of items. Empties and whitespace are 0; non-numeric content shows an inline error pointing at the offending row.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:expires_at]]]</code></td>
                  <td class="p-3 text-foreground">Unix seconds when the List expires (empty when no deadline set). See <a href="#expiry" class="text-violet-400 hover:underline">Auto-expiry</a>.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:donors:countdown]]]</code></td>
                  <td class="p-3 text-foreground">Live seconds remaining until expiry. Ticks every frame; pair with <Link href="/help/formatting" class="text-violet-400 hover:underline">formatting pipes</Link>.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-foreground">
            All tags update live. When the underlying List changes - because you edited it, because a chat appender
            fired, because the sweeper aged out an entry - every overlay reading it patches its data store and
            re-renders. No reload, no polling.
          </p>
        </section>

        <!-- Foreach -->
        <section class="mb-14" id="foreach">
          <h2 class="mb-4 text-2xl font-bold">Iterating with <code class="rounded bg-background px-2 py-0.5 font-mono text-xl">foreach</code></h2>
          <p class="mb-4 text-foreground">
            The derived tags above are great for "show the first item" or "show the count". When you want to render
            every item, use <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">foreach</code>:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm text-foreground">&lt;ul&gt;
  [[[foreach:c:list:donors as donor]]]
    &lt;li&gt;[[[donor]]]&lt;/li&gt;
  [[[endforeach]]]
&lt;/ul&gt;</pre>
          <p class="mb-4 text-foreground">
            Inside the loop, <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[donor]]]</code>
            resolves to each item in turn. The loop body can use any other tag the overlay knows about, and the
            template engine materialises one block per item.
          </p>
          <p class="text-foreground">
            See the <Link href="/help/reference" class="text-violet-400 hover:underline">Reference page</Link> for
            the full <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">foreach</code> syntax,
            including index access and nested iteration.
          </p>
        </section>

        <!-- Appenders -->
        <section class="mb-14" id="appenders">
          <h2 class="mb-4 text-2xl font-bold">Chat appenders - viewers grow your List</h2>
          <p class="mb-4 text-foreground">
            A chat appender wires a custom command (like <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!raffle</code>
            or <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!join</code>) to a List. When a viewer
            runs the command, the appender resolves a template string and pushes the result into the target List. The
            viewer's name lands in the array; the overlay updates live.
          </p>
          <p class="mb-4 text-foreground">
            Each appender configures:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li><strong>Command</strong> - the bang word viewers type (must be unique across your custom commands, recipes, and meta-commands)</li>
            <li><strong>Target List</strong> - which List the appended value goes to</li>
            <li><strong>Permission level</strong> - everyone, follower, subscriber, vip, moderator, broadcaster</li>
            <li><strong>Cooldown</strong> - global cooldown in seconds (broadcaster bypasses)</li>
            <li><strong>Value template</strong> - the string to append. Uses the same template language as <Link href="/help/expressions" class="text-violet-400 hover:underline">Bot Expressions</Link>: <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[bot:from_user]]]</code>, <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[bot:args]]]</code>, control reads, pipe formatters</li>
            <li><strong>Empty-args reply</strong> - what to say when the chatter forgot to type an argument the template expects</li>
            <li><strong>Dedup policy</strong> - <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">none</code>, <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">per_chatter</code>, or <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">per_chatter_per_stream</code></li>
            <li><strong>Max size</strong> - hard cap; further fires silently refuse (so slot 100 actually means slot 100)</li>
          </ul>
          <p class="mb-4 text-foreground">
            <strong>Example - raffle entry by display name, one per chatter per stream:</strong>
          </p>
          <div class="mb-4 overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3 font-semibold w-40">Command</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">raffle</code></td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold">Target List</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">raffle_entries</code></td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold">Value template</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[bot:from_user]]]</code></td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold">Dedup</td>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">per_chatter_per_stream</code></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="text-foreground">
            <strong>Example - quote wall, value is whatever the viewer typed:</strong> command
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">quote</code>, value template
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">[[[bot:from_user]]]: [[[bot:args]]]</code>,
            empty-args reply
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">@[[[bot:from_user]]] you forgot the quote! Use !quote whatever the streamer said.</code>
          </p>
        </section>

        <!-- !list meta-command -->
        <section class="mb-14" id="meta">
          <h2 class="mb-4 text-2xl font-bold">The <code class="rounded bg-background px-2 py-0.5 font-mono text-xl">!list</code> meta-command</h2>
          <p class="mb-4 text-foreground">
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code> is a single configurable
            chat command that gives you (and your mods) the full action vocabulary against any of your Lists from chat,
            without wiring a separate command per action. You opt in once from
            <Link href="/dashboard/lists" class="text-violet-400 hover:underline">/dashboard/lists</Link> and pick the
            command name - default is <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code>,
            but if that collides with a bot you already use (StreamElements, Nightbot, Fossabot), rename it to whatever
            you like (<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!ol</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!l</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!mylist</code>...).
          </p>
          <p class="mb-4 text-foreground">
            Syntax is always:
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm text-foreground">!list &lt;slug&gt; &lt;action&gt; [args...]</pre>
          <p class="mb-4 text-foreground">
            Examples:
          </p>
          <pre class="mb-6 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm text-foreground">!list raffle_entries count
!list raffle_entries draw
!list raffle_entries clear
!list raffle_entries pop first
!list raffle_entries clone backup_today
!list quotes random 3
!list quotes last
!list shoutouts disable</pre>
          <p class="mb-4 text-foreground">
            <strong>Permission level is fixed to moderator and above.</strong> The action vocabulary is destructive or
            chat-emitting, and we don't want a random chatter clearing your raffle list. Broadcaster and moderators can
            run it; everyone else's invocations are ignored silently.
          </p>
          <p class="text-foreground">
            The command is <strong>self-documenting</strong>. Bare <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code>
            replies with global help. <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list &lt;slug&gt;</code>
            with no action replies with the per-list help. An unknown action lists the valid ones. Missing required arguments
            (like <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list raffle pop</code> with no
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">first|last</code>) reply with usage hints
            inline rather than failing silently.
          </p>
        </section>

        <!-- Actions in detail -->
        <section class="mb-14" id="actions">
          <h2 class="mb-6 text-2xl font-bold">The action vocabulary in detail</h2>
          <p class="mb-6 text-foreground">
            The same ten verbs are available from both the chat <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code>
            command and the action buttons on <Link href="/dashboard/lists" class="text-violet-400 hover:underline">/dashboard/lists</Link>.
            They split into three groups by semantics.
          </p>

          <h3 class="mb-3 text-xl font-semibold">Read actions (no mutation, no snapshot)</h3>
          <div class="mb-8 overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Action</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">count</code></td>
                  <td class="p-3 text-foreground">Replies with the number of items.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">first [N]</code></td>
                  <td class="p-3 text-foreground">Replies with the first <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">N</code> items (default 1, max = list size).</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">last [N]</code></td>
                  <td class="p-3 text-foreground">Replies with the last <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">N</code> items (default 1, max = list size).</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">random [N]</code></td>
                  <td class="p-3 text-foreground">Replies with <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">N</code> random items, without replacement (default 1).</td>
                </tr>
              </tbody>
            </table>
          </div>

          <h3 class="mb-3 text-xl font-semibold">Destructive actions (auto-snapshot, broadcast)</h3>
          <div class="mb-8 overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Action</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">draw</code></td>
                  <td class="p-3 text-foreground">Picks a random item, removes it, announces the winner. The classic raffle action.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">clear</code></td>
                  <td class="p-3 text-foreground">Empties the List. Use before starting a fresh raffle or queue.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">pop first</code></td>
                  <td class="p-3 text-foreground">Removes and announces the head of the List. Useful for FIFO queues - "who's next?".</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">pop last</code></td>
                  <td class="p-3 text-foreground">Removes and announces the tail of the List.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">clone &lt;new_slug&gt;</code></td>
                  <td class="p-3 text-foreground">Duplicates the List into a new List with the given slug. Inherits items, label, and item ages verbatim.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mb-6 text-foreground">
            Every destructive action automatically creates a snapshot of the List's previous state before it mutates.
            That snapshot is what makes <a href="#snapshots" class="text-violet-400 hover:underline">undo</a> possible.
          </p>

          <h3 class="mb-3 text-xl font-semibold">State actions</h3>
          <div class="overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold w-1/3">Action</th>
                  <th class="p-3 font-semibold">What it does</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">disable</code></td>
                  <td class="p-3 text-foreground">Disables the List. Chat appenders silently refuse; existing items stay visible.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">enable</code></td>
                  <td class="p-3 text-foreground">Re-enables a disabled List.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Snapshots -->
        <section class="mb-14" id="snapshots">
          <h2 class="mb-4 text-2xl font-bold">Snapshots - undo for destructive actions</h2>
          <p class="mb-4 text-foreground">
            Every time a destructive action runs - <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">clear</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">draw</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">pop</code>, or a
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">restore</code> from an earlier snapshot -
            Overlabels writes the List's previous state into a snapshot row. You can also take manual snapshots from the
            dashboard before a risky edit.
          </p>
          <p class="mb-4 text-foreground">
            The snapshots panel under each List shows up to 50 recent snapshots with their reason badge, item count, and
            age. You can:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li><strong>Restore</strong> - replace current items with the snapshot's items. Creates a <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">before_restore</code> snapshot first, so the restore is itself undoable.</li>
            <li><strong>Pin</strong> - exempt the snapshot from auto-retention. Pinned snapshots stay until you unpin or delete them.</li>
            <li><strong>Delete</strong> - remove a single snapshot immediately. No further undo.</li>
            <li><strong>Save snapshot</strong> - take a manual snapshot of the current state right now.</li>
          </ul>
          <div class="border border-sidebar-accent bg-card p-6">
            <h3 class="mb-2 text-lg font-semibold">Retention</h3>
            <p class="text-foreground">
              Unpinned snapshots are automatically deleted 30 days after they were created and cannot be recovered after
              that point. Pinned snapshots stay until you act on them. Deleting a List removes all its snapshots,
              pinned or not. This retention behavior is also covered in the
              <Link href="/privacy" class="text-violet-400 hover:underline">privacy policy</Link>.
            </p>
          </div>
        </section>

        <!-- Expiry -->
        <section class="mb-14" id="expiry">
          <h2 class="mb-4 text-2xl font-bold">Auto-expiry - entry age-out and whole-list deadlines</h2>
          <p class="mb-4 text-foreground">
            Two independent timers you can set on a List from the dashboard's Expiry panel:
          </p>

          <h3 class="mb-3 text-xl font-semibold">Per-item age-out</h3>
          <p class="mb-4 text-foreground">
            Set <strong>Per-item age-out</strong> (seconds, minutes, or hours, max 30 days) and any item older than that
            is removed automatically. The sweeper runs every minute. Useful for rolling shoutout walls, "recent donors"
            displays, queue cleanups, anything where staleness is bad.
          </p>
          <p class="mb-6 text-foreground">
            Reordering items in the dashboard preserves their age. Renaming an item (or typing a new one) resets that
            entry's age to zero. Cloning a List inherits item ages verbatim, so a mid-stream clone of a 5-minute-old
            raffle entry keeps the 5 minutes already accrued.
          </p>

          <h3 class="mb-3 text-xl font-semibold">Whole-list deadline</h3>
          <p class="mb-4 text-foreground">
            Set <strong>Whole-list deadline</strong> to a future moment, and at that moment the List is snapshotted,
            cleared, and disabled. The snapshot follows the regular 30-day retention rule. Clearing the deadline on a
            previously-expired List also re-enables it - "reopen" is a single action.
          </p>

          <h3 class="mb-3 text-xl font-semibold">Tags for the deadline</h3>
          <p class="mb-4 text-foreground">
            Two tags surface the deadline directly in your overlay:
          </p>
          <div class="mb-4 overflow-x-auto border border-sidebar-accent bg-card">
            <table class="w-full text-sm">
              <thead class="border-b border-sidebar text-left">
                <tr>
                  <th class="p-3 font-semibold">Tag</th>
                  <th class="p-3 font-semibold">What it resolves to</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar">
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:raffle:expires_at]]]</code></td>
                  <td class="p-3 text-foreground">Unix seconds of the deadline. Empty string when no deadline is set.</td>
                </tr>
                <tr>
                  <td class="p-3"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:raffle:countdown]]]</code></td>
                  <td class="p-3 text-foreground">Seconds remaining (clamped at zero), updated every frame. Pair with a duration formatter for display.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p class="mb-4 text-foreground">
            <strong>Example - live mm:ss countdown in your overlay:</strong>
          </p>
          <pre class="mb-4 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm text-foreground">Raffle closes in [[[c:list:raffle:countdown|duration:mm:ss]]]</pre>
          <p class="text-foreground">
            See <Link href="/help/formatting" class="text-violet-400 hover:underline">formatting pipes</Link> for the
            full duration pattern reference (<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">hh:mm:ss</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">dd:hh:mm:ss</code>, etc).
          </p>
        </section>

        <!-- Disable -->
        <section class="mb-14" id="disable">
          <h2 class="mb-4 text-2xl font-bold">Disable and enable</h2>
          <p class="mb-4 text-foreground">
            Disabling a List flips a single flag with two visible effects:
          </p>
          <ul class="mb-4 list-disc space-y-2 pl-6 text-foreground">
            <li>Chat appenders silently refuse new appends. No error message, no apology - you disabled it intentionally.</li>
            <li>Existing items stay visible to overlays. You can still curate them manually from the dashboard.</li>
          </ul>
          <p class="text-foreground">
            Use it when a raffle has closed but you're not ready to draw yet, when a queue is paused for a break, or
            when you want to freeze a state for screenshotting without losing chat-appender wiring. Toggle from the
            dashboard, from chat via <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list &lt;slug&gt; disable</code>,
            or implicitly through whole-list expiry.
          </p>
        </section>

        <!-- Worked examples -->
        <section class="mb-14" id="examples">
          <h2 class="mb-6 text-2xl font-bold">Worked examples</h2>

          <!-- Example 1: Raffle -->
          <div class="mb-10 border border-sidebar-accent bg-card p-6">
            <h3 class="mb-3 text-xl font-semibold">Raffle - !raffle to enter, !list raffle draw to pick a winner</h3>
            <ol class="mb-4 list-decimal space-y-2 pl-6 text-foreground">
              <li>Create a List <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">raffle_entries</code>.</li>
              <li>
                Create a chat appender: command <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">raffle</code>,
                target <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">raffle_entries</code>,
                value template <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[bot:from_user]]]</code>,
                dedup <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">per_chatter_per_stream</code>.
              </li>
              <li>Opt into the <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">!list</code> meta-command.</li>
              <li>
                Optionally set a whole-list deadline so the raffle closes on its own. Put
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:raffle_entries:countdown|duration:mm:ss]]]</code>
                in your overlay.
              </li>
              <li>When the deadline hits, the List disables itself. Run <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">!list raffle_entries draw</code> from chat to pick the winner.</li>
              <li>
                Want to redraw? The before-draw snapshot is right there in the snapshots panel - restore and
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">draw</code> again.
              </li>
            </ol>
          </div>

          <!-- Example 2: Queue -->
          <div class="mb-10 border border-sidebar-accent bg-card p-6">
            <h3 class="mb-3 text-xl font-semibold">FIFO queue - !join to enter, !list queue pop first for next up</h3>
            <ol class="mb-4 list-decimal space-y-2 pl-6 text-foreground">
              <li>Create a List <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">queue</code>.</li>
              <li>
                Create a chat appender: command <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">join</code>,
                value template <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[bot:from_user]]]</code>,
                dedup <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">per_chatter</code> (lifetime, not per-stream).
              </li>
              <li>
                Put the queue in your overlay as a foreach:
                <pre class="my-2 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-3 font-mono text-xs text-foreground">[[[foreach:c:list:queue as player]]]
  &lt;li&gt;[[[player]]]&lt;/li&gt;
[[[endforeach]]]</pre>
              </li>
              <li>Each play, run <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">!list queue pop first</code> in chat to grab the next person.</li>
            </ol>
          </div>

          <!-- Example 3: Quote wall -->
          <div class="mb-10 border border-sidebar-accent bg-card p-6">
            <h3 class="mb-3 text-xl font-semibold">Quote wall - !quote to add, random rotation in overlay</h3>
            <ol class="list-decimal space-y-2 pl-6 text-foreground">
              <li>Create a List <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">quotes</code>.</li>
              <li>
                Create a chat appender: command <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">quote</code>,
                value template <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[bot:from_user]]]: [[[bot:args]]]</code>,
                permission <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">moderator</code>.
              </li>
              <li>
                In your overlay use
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:list:quotes:random]]]</code>
                to show one quote at a time. It stays stable per overlay mount; reload the browser source to pick a new one.
              </li>
              <li>Per-item age-out optional - set 30 days to keep the wall recent without manual pruning.</li>
            </ol>
          </div>

          <!-- Example 4: Donation goal -->
          <div class="border border-sidebar-accent bg-card p-6">
            <h3 class="mb-3 text-xl font-semibold">Donation tally - List of amounts, :sum as the goal driver</h3>
            <p class="mb-3 text-foreground">
              When you don't want to wire a full Ko-fi or StreamLabs integration but you do want a quick tally:
            </p>
            <ol class="list-decimal space-y-2 pl-6 text-foreground">
              <li>Create a List <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">tips</code>.</li>
              <li>
                Create a chat appender: command <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">tip</code>,
                value template <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[bot:args]]]</code>,
                permission <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">moderator</code>
                (so only mods log tips after confirming them out-of-band).
              </li>
              <li>
                In your overlay:
                <pre class="my-2 overflow-x-auto border border-sidebar-border bg-sidebar-accent p-3 font-mono text-xs text-foreground">Raised: €[[[c:list:tips:sum]]] of €500 goal</pre>
              </li>
              <li>
                Want a progress bar? Create an <Link href="/help/expressions" class="text-violet-400 hover:underline">Expression Control</Link>
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">tip_pct</code> with expression
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">c.list.tips.sum / 500 * 100</code>
                and reference <code class="rounded bg-background px-1.5 py-0.5 font-mono text-xs">[[[c:tip_pct]]]</code>.
              </li>
            </ol>
          </div>
        </section>

        <!-- Things to know -->
        <section class="mb-14" id="things-to-know">
          <h2 class="mb-6 text-2xl font-bold">Things to know</h2>

          <div class="space-y-6">
            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Lists are lists. We don't sanitise content.</h3>
              <p class="text-foreground">
                Whatever you (or your viewers) put in, we keep. Empty lines, duplicates, 200x the same value, lengthy
                whitespace - it's all yours. The only character stripped is the NUL byte, because it would break
                JSON serialisation downstream. This contract is intentional: opinionated trimming would surprise people
                who actually want what they typed.
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold"><code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">:random</code> is stable per overlay mount.</h3>
              <p class="text-foreground">
                The random tag picks once on initial render and keeps the same value across broadcasts. Otherwise every
                append would re-roll it and your overlay would flicker. To pick a new random item, reload the browser
                source. To pick at the moment of an action, use the chat <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list &lt;slug&gt; random</code>
                action instead.
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Mod permission is the floor for <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code>.</h3>
              <p class="text-foreground">
                The meta-command can clear, draw, disable, and clone. We don't want a viewer running
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list raffle clear</code> mid-raffle.
                Chat appenders (<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!join</code>,
                <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!raffle</code>) have their own
                independent permission setting that can be looser (everyone, follower, subscriber, etc).
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Command-name collisions are checked at save time.</h3>
              <p class="text-foreground">
                A chat appender's command, the <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!list</code>
                meta-command, your Bot Expressions, your recipe triggers, and the built-in commands all share the same
                namespace. Save-time validation refuses collisions with a clear error rather than silently letting one
                of them win at runtime.
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Sweeps run every minute.</h3>
              <p class="text-foreground">
                Both per-item age-out and whole-list deadlines are evaluated by a sweep that runs every minute. So a
                30-second TTL doesn't mean entries vanish on the second; they vanish on the next sweep tick after they
                age past the cutoff. This is fine for "trim things that should not stick around" - if you need exact
                timing, use a timer Control instead.
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Recipe-managed Lists.</h3>
              <p class="text-foreground">
                Recipes can create Lists on your behalf. They show up with a "from Recipe" badge. If the recipe declared
                the items locked, you can't edit them - uninstall the recipe to free the List. You can still disable,
                enable, set TTLs, and use the action vocabulary against locked Lists.
              </p>
            </div>

            <div class="border border-sidebar-accent bg-card p-6">
              <h3 class="mb-2 text-lg font-semibold">Live updates everywhere.</h3>
              <p class="text-foreground">
                Every change to a List (manual edit, chat append, action, sweeper) broadcasts to your overlays and your
                dashboard. Overlays patch their data store in place. The dashboard page patches the active row; if
                you're mid-edit with unsaved changes, your draft wins until you save - we don't trash your work.
              </p>
            </div>
          </div>
        </section>

        <!-- Quick reference -->
        <section class="mb-14" id="quick-ref">
          <h2 class="mb-4 text-2xl font-bold">Quick reference card</h2>
          <pre class="overflow-x-auto border border-sidebar-border bg-sidebar-accent p-4 font-mono text-sm leading-relaxed text-foreground">Tags
  [[[c:list:slug]]]              JSON array string
  [[[c:list:slug:first]]]        first item
  [[[c:list:slug:last]]]         last item
  [[[c:list:slug:count]]]        item count
  [[[c:list:slug:empty]]]        "1" if empty, "0" otherwise
  [[[c:list:slug:random]]]       random item (stable per mount)
  [[[c:list:slug:sum]]]          numeric sum of items
  [[[c:list:slug:expires_at]]]   deadline as Unix seconds
  [[[c:list:slug:countdown]]]    live seconds remaining

Foreach
  [[[foreach:c:list:slug as item]]] [[[item]]] [[[endforeach]]]

Chat - !list meta-command (mod+)
  !list &lt;slug&gt; count
  !list &lt;slug&gt; first [N]
  !list &lt;slug&gt; last [N]
  !list &lt;slug&gt; random [N]
  !list &lt;slug&gt; draw
  !list &lt;slug&gt; clear
  !list &lt;slug&gt; pop first|last
  !list &lt;slug&gt; clone &lt;new_slug&gt;
  !list &lt;slug&gt; disable
  !list &lt;slug&gt; enable

In an Expression Control
  c.list.slug.sum
  c.list.slug.count</pre>
        </section>

        <p class="mb-12 text-sm text-muted-foreground">
          For deeper context on the template language and pipe formatters, see
          <Link href="/help/conditionals" class="text-violet-400 hover:underline">Conditional and Event Tags</Link> and
          <Link href="/help/formatting" class="text-violet-400 hover:underline">Formatting Pipes</Link>.
          For numeric computation on top of Lists, see
          <Link href="/help/expressions" class="text-violet-400 hover:underline">Expression Controls</Link>.
        </p>
      </div>
    </div>
  </AppLayout>
</template>
