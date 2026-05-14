<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Help', href: '/help' },
  { title: 'Bot Aliases', href: '/help/aliases' },
];
</script>

<template>
  <Head>
    <title>Bot Aliases - Overlabels</title>
    <meta
      name="description"
      content="Bot Aliases in Overlabels: short chat commands that rewrite to longer ones before dispatch. Positional placeholders {1}/{2}/{*}, one-hop guard, and the same permission model as built-in commands."
    />

    <meta property="og:type" content="website" />
    <meta property="og:url" content="https://overlabels.com/help/aliases" />
    <meta property="og:site_name" content="Overlabels" />
    <meta property="og:title" content="Bot Aliases - Overlabels" />
    <meta
      property="og:description"
      content="Short chat commands that rewrite to longer ones. !w 2 becomes !inc wins 2 before the bot dispatches it. Positional placeholders, one-hop guard, shared validation."
    />
    <meta property="og:image"
          content="https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Overlabels - build Twitch overlays with HTML, CSS, and live data" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bot Aliases - Overlabels" />
    <meta
      name="twitter:description"
      content="Short chat commands that rewrite to longer ones. Positional placeholders, one-hop guard, shared validation."
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
          <h1 class="mb-4 text-4xl font-bold">Bot Aliases</h1>
          <p class="text-lg font-medium text-violet-400">
            Short chat commands that rewrite to longer ones. <code class="rounded bg-background px-1.5 py-0.5 font-mono text-base">!w 2</code> becomes
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-base">!inc wins 2</code> before the bot dispatches it.
          </p>
        </div>

        <!-- TOC -->
        <div class="mb-12 rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-xl font-bold" id="toc">Table of contents</h2>
          <ol class="list-decimal space-y-1 pl-6 text-foreground">
            <li><a href="#what" class="text-violet-400 hover:underline">What is an alias?</a></li>
            <li><a href="#creating" class="text-violet-400 hover:underline">Creating aliases - dashboard or chat</a></li>
            <li><a href="#placeholders" class="text-violet-400 hover:underline">Placeholder syntax</a></li>
            <li><a href="#permission" class="text-violet-400 hover:underline">Permission and the one-hop rule</a></li>
            <li><a href="#options" class="text-violet-400 hover:underline">Options - cooldown, permission, enabled, hidden</a></li>
            <li><a href="#examples" class="text-violet-400 hover:underline">Worked examples</a></li>
            <li><a href="#things-to-know" class="text-violet-400 hover:underline">Things to know</a></li>
            <li><a href="#quick-ref" class="text-violet-400 hover:underline">Quick reference</a></li>
          </ol>
        </div>

        <!-- What is an alias? -->
        <section class="mb-14" id="what">
          <h2 class="mb-4 text-2xl font-bold">What is an alias?</h2>
          <p class="mb-4 text-foreground">
            An alias is a short chat command that <strong>rewrites to a longer one</strong> before the bot dispatches it.
            You type <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!w 2</code> in chat; the bot sees
            that <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!w</code> is an alias whose target is
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!inc wins {1}</code>; the
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">{1}</code> gets replaced with
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">2</code>; the bot now routes the
            rewritten command <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!inc wins 2</code>
            through its normal dispatch as if you'd typed it directly. The original chatter context (badges, reply
            threading) carries through both hops.
          </p>
          <p class="mb-4 text-foreground">
            Aliases are <strong>per-user</strong>. They live on your account; another streamer creating
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!w</code> on their channel doesn't
            affect yours. Aliases can target Overlabels built-ins
            (<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!inc</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!set</code>,
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!reset</code>...) or your own
            <Link href="/help/expressions" class="text-violet-400 hover:underline">Bot Expressions</Link>.
          </p>
          <p class="text-foreground">
            What aliases <strong>can't</strong> do: target another alias (one hop only), point to themselves, or
            collide with a name already taken by a built-in or one of your expressions. The dashboard and the chat
            admin command (<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!ol alias add</code>)
            both validate against the same rules, so chat-side mistakes get caught with the same error message.
          </p>
        </section>

        <!-- Creating aliases -->
        <section class="mb-14" id="creating">
          <h2 class="mb-4 text-2xl font-bold">Creating aliases - dashboard or chat</h2>
          <p class="mb-4 text-foreground">
            Two surfaces, identical validation. Pick whichever fits the moment.
          </p>

          <h3 class="mb-2 text-xl font-semibold">From the dashboard</h3>
          <p class="mb-4 text-foreground">
            Settings &gt; Integrations &gt; <Link href="/settings/bot/aliases" class="text-violet-400 hover:underline">Manage aliases</Link>.
            The editor has quick-insert chips for the placeholders and a "Target a command" expander listing all
            built-ins and your expressions. It also renders a live example showing how a sample call site resolves.
            Best path when you're building a complicated target with multiple placeholders and want to see the
            rewrite preview before saving.
          </p>

          <h3 class="mb-2 text-xl font-semibold">From chat</h3>
          <p class="mb-4 text-foreground">
            Mod-or-broadcaster can run <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!ol alias
            add &lt;name&gt; &lt;target&gt;</code> in chat. Replies thread normally through the bot's outbox.
          </p>
          <div class="rounded-lg border border-sidebar bg-background p-4 font-mono text-sm">
            <div><span class="text-muted-foreground">@mod:</span> !ol alias add w !inc wins {1}</div>
            <div class="mt-1"><span class="text-muted-foreground">@overlabels:</span> added alias !w -&gt; !inc wins {1}</div>
          </div>
          <p class="mt-4 text-foreground">
            Full <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!ol alias</code> reference
            (add / edit / delete / options) lives on <Link href="/help/bot/commands#ol" class="text-violet-400 hover:underline">/help/bot/commands</Link>.
          </p>
        </section>

        <!-- Placeholder syntax -->
        <section class="mb-14" id="placeholders">
          <h2 class="mb-4 text-2xl font-bold">Placeholder syntax</h2>
          <p class="mb-4 text-foreground">
            The target template can contain placeholders that get replaced with the chatter's args at fire time.
            Three forms:
          </p>

          <div class="mb-6 space-y-3">
            <div class="rounded border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-mono text-sm">
                <code class="rounded bg-background px-2 py-0.5">{1}</code>,
                <code class="rounded bg-background px-2 py-0.5">{2}</code>,
                <code class="rounded bg-background px-2 py-0.5">{3}</code>, ...
              </p>
              <p class="text-foreground">
                Positional placeholders, 1-indexed. <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{1}</code>
                is the first whitespace-separated arg the chatter typed after the alias name,
                <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{2}</code> the second, and so on.
                Missing args substitute to empty string (no error, no warning).
              </p>
            </div>

            <div class="rounded border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-mono text-sm">
                <code class="rounded bg-background px-2 py-0.5">{*}</code>
              </p>
              <p class="text-foreground">
                Captures every arg past the highest-numbered positional placeholder, space-joined. With no
                positional placeholders, <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{*}</code>
                is "every arg." With <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{1} {*}</code>,
                <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{*}</code> is "everything from
                arg 2 onward."
              </p>
            </div>
          </div>

          <p class="text-foreground">
            Anything else inside braces (<code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{x}</code>,
            <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{foo}</code>,
            <code class="rounded bg-background px-1 py-0.5 font-mono text-sm">{}</code>) is rejected at save time
            with a clear error pointing at the offending placeholder. The valid set is small on purpose - aliases are
            not a templating language.
          </p>
        </section>

        <!-- Permission and one-hop -->
        <section class="mb-14" id="permission">
          <h2 class="mb-4 text-2xl font-bold">Permission and the one-hop rule</h2>
          <p class="mb-4 text-foreground">
            Aliases ship with <strong>moderator</strong> as the default permission, but the dropdown lets you set any
            tier from everyone to broadcaster. The chosen permission gates who can <em>fire</em> the alias.
          </p>
          <p class="mb-4 text-foreground">
            After the rewrite, the target command's own permission still applies. This is defence-in-depth: even if
            you accidentally open an alias to everyone, the target command's gate still runs against the original
            chatter's badges.
          </p>
          <div class="mb-6 rounded-lg border border-sidebar bg-sidebar p-4 font-mono text-sm">
            <div class="text-muted-foreground"># An alias to !reset (broadcaster-only), opened to everyone</div>
            <div>!ol alias add hardreset reset {1}</div>
            <div>!ol alias options hardreset permission everyone</div>
            <div class="mt-3 text-muted-foreground"># A viewer fires it</div>
            <div><span class="text-muted-foreground">@viewer:</span> !hardreset wins</div>
            <div class="mt-3 text-muted-foreground"># Alias gate passes. Rewrite to !reset wins.</div>
            <div class="text-muted-foreground"># !reset is broadcaster-only -&gt; second-hop gate denies.</div>
            <div class="text-muted-foreground"># Silent drop. Nothing happens.</div>
          </div>

          <h3 class="mb-2 text-xl font-semibold">One hop only</h3>
          <p class="mb-4 text-foreground">
            The rewritten command runs through normal dispatch once - it cannot land on another alias. The backend
            rejects alias-&gt;alias chains at save time with a clear error
            (<em>"!w is itself an alias. Point this alias at the underlying command instead."</em>), and the bot
            defensively drops any chain that would result from stale map data. This keeps the model simple to reason
            about and immune to loops.
          </p>
        </section>

        <!-- Options -->
        <section class="mb-14" id="options">
          <h2 class="mb-4 text-2xl font-bold">Options - cooldown, permission, enabled, hidden</h2>
          <p class="mb-4 text-foreground">
            Each alias has four toggles that match the Bot Expression vocabulary one-for-one. Editable from the
            dashboard or via <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!ol alias options
            &lt;name&gt; &lt;option&gt; &lt;value&gt;</code> in chat.
          </p>
          <div class="overflow-hidden rounded-lg border border-sidebar bg-sidebar">
            <div class="grid grid-cols-3 border-b border-sidebar bg-background/30 px-4 py-2 text-xs uppercase tracking-wide text-muted-foreground">
              <div>Option</div>
              <div class="col-span-2">Value</div>
            </div>
            <div class="grid grid-cols-3 border-b border-sidebar px-4 py-3 text-foreground">
              <div class="font-mono text-sm">cooldown</div>
              <div class="col-span-2 text-sm">Integer seconds, 0 to 86400. Broadcaster bypasses the cooldown.</div>
            </div>
            <div class="grid grid-cols-3 border-b border-sidebar px-4 py-3 text-foreground">
              <div class="font-mono text-sm">permission</div>
              <div class="col-span-2 text-sm">
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">everyone</code> /
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">subscriber</code> /
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">vip</code> /
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">moderator</code> /
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">broadcaster</code>. Shortforms
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">sub</code>,
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">mod</code>,
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">bc</code>,
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">all</code> work too.
              </div>
            </div>
            <div class="grid grid-cols-3 border-b border-sidebar px-4 py-3 text-foreground">
              <div class="font-mono text-sm">enabled</div>
              <div class="col-span-2 text-sm">
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">true</code> /
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">false</code>. Also accepts
                on/off, yes/no, 1/0. Disabled aliases stay in your library but don't fire.
              </div>
            </div>
            <div class="grid grid-cols-3 px-4 py-3 text-foreground">
              <div class="font-mono text-sm">hidden</div>
              <div class="col-span-2 text-sm">Hides the alias from the future <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!commands</code> listing without disabling it.</div>
            </div>
          </div>
        </section>

        <!-- Worked examples -->
        <section class="mb-14" id="examples">
          <h2 class="mb-4 text-2xl font-bold">Worked examples</h2>

          <h3 class="mb-2 text-xl font-semibold">A counter shortcut</h3>
          <p class="mb-3 text-foreground">
            Bind <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">!w</code> to incrementing your
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">wins</code> counter.
          </p>
          <div class="mb-6 rounded-lg border border-sidebar bg-background p-4 font-mono text-sm">
            <div class="text-muted-foreground"># Create</div>
            <div>!ol alias add w !inc wins {1}</div>
            <div class="mt-3 text-muted-foreground"># Use - positive</div>
            <div><span class="text-muted-foreground">@mod:</span> !w</div>
            <div><span class="text-muted-foreground">@mod:</span> !w 2</div>
            <div class="mt-3 text-muted-foreground"># Use - negative. !inc wins -2 subtracts because !inc</div>
            <div class="text-muted-foreground"># accepts a signed amount. Aliases pass the arg through verbatim.</div>
            <div><span class="text-muted-foreground">@mod:</span> !w -2</div>
          </div>

          <h3 class="mb-2 text-xl font-semibold">Capturing the whole rest of the message</h3>
          <p class="mb-3 text-foreground">
            <code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">{*}</code> is for cases where you
            don't know how many args the chatter will type. Good for wrapping commands that accept a free-form string.
          </p>
          <div class="mb-6 rounded-lg border border-sidebar bg-background p-4 font-mono text-sm">
            <div class="text-muted-foreground"># Create</div>
            <div>!ol alias add shout !set announcement {*}</div>
            <div class="mt-3 text-muted-foreground"># Use</div>
            <div><span class="text-muted-foreground">@mod:</span> !shout big raid incoming, thanks SomeStreamer!</div>
            <div class="text-muted-foreground"># Rewrites to !set announcement big raid incoming, thanks SomeStreamer!</div>
          </div>

          <h3 class="mb-2 text-xl font-semibold">Two-positional with a fixed middle</h3>
          <p class="mb-3 text-foreground">
            Positionals can appear anywhere in the target template, with literal text in between.
          </p>
          <div class="mb-6 rounded-lg border border-sidebar bg-background p-4 font-mono text-sm">
            <div class="text-muted-foreground"># Create</div>
            <div>!ol alias add gift !give {1} from {2}</div>
            <div class="mt-3 text-muted-foreground"># Use</div>
            <div><span class="text-muted-foreground">@mod:</span> !gift @alice @bob</div>
            <div class="text-muted-foreground"># Rewrites to !give @alice from @bob</div>
          </div>

          <h3 class="mb-2 text-xl font-semibold">Aliasing a Bot Expression</h3>
          <p class="mb-3 text-foreground">
            Aliases can target your own
            <Link href="/help/expressions" class="text-violet-400 hover:underline">Bot Expressions</Link>, not just
            built-ins. Useful when you want a short trigger for a long templated reply.
          </p>
          <div class="rounded-lg border border-sidebar bg-background p-4 font-mono text-sm">
            <div class="text-muted-foreground"># Suppose !discord is one of your Bot Expressions.</div>
            <div class="text-muted-foreground"># Make !d an alias for it.</div>
            <div>!ol alias add d !discord</div>
            <div class="mt-3 text-muted-foreground"># Use</div>
            <div><span class="text-muted-foreground">@viewer:</span> !d</div>
            <div class="text-muted-foreground"># Rewrites to !discord, which the bot resolves as an expression</div>
            <div class="text-muted-foreground"># and speaks the template result.</div>
          </div>
        </section>

        <!-- Things to know -->
        <section class="mb-14" id="things-to-know">
          <h2 class="mb-4 text-2xl font-bold">Things to know</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">One hop only</p>
              <p class="text-sm text-muted-foreground">
                Aliases can't target other aliases. Self-loops are also rejected. Validation catches both at save time
                with explicit errors.
              </p>
            </div>
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">Target permission still applies</p>
              <p class="text-sm text-muted-foreground">
                After the rewrite, the target command's own permission gate runs against the original chatter. An
                alias can't escalate privilege.
              </p>
            </div>
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">Cooldown is per-alias</p>
              <p class="text-sm text-muted-foreground">
                The alias's <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">cooldown_seconds</code>
                gates how often the alias itself fires. If the target also has a cooldown (e.g. a Bot Expression),
                that runs independently on the second hop.
              </p>
            </div>
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">Missing args are silent</p>
              <p class="text-sm text-muted-foreground">
                <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">{1}</code> with no arg substitutes
                empty string. The rewritten command keeps running - it just sees a shorter arg list. No error to chat.
              </p>
            </div>
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">Negative numbers work</p>
              <p class="text-sm text-muted-foreground">
                Args pass through verbatim. <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!w -2</code>
                with target <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!inc wins {1}</code>
                expands to <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!inc wins -2</code>, which
                subtracts because <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!inc</code> accepts
                signed amounts.
              </p>
            </div>
            <div class="rounded-lg border border-sidebar bg-sidebar p-4">
              <p class="mb-2 font-semibold text-foreground">Hide from listings if it's internal</p>
              <p class="text-sm text-muted-foreground">
                The <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">hidden</code> option keeps an
                alias out of the future <code class="rounded bg-background px-1 py-0.5 font-mono text-xs">!commands</code>
                listing without disabling it. Useful for mod-only helpers you don't want chat asking about.
              </p>
            </div>
          </div>
        </section>

        <!-- Quick reference -->
        <section class="mb-14" id="quick-ref">
          <h2 class="mb-4 text-2xl font-bold">Quick reference</h2>
          <div class="rounded-lg border border-violet-400/40 bg-violet-400/5 p-6">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-violet-300">Chat commands</h3>
            <div class="mb-5 space-y-1 font-mono text-sm text-foreground">
              <div>!ol alias add &lt;name&gt; &lt;target&gt;</div>
              <div>!ol alias edit &lt;name&gt; &lt;target&gt;</div>
              <div>!ol alias delete &lt;name&gt;</div>
              <div>!ol alias options &lt;name&gt; &lt;option&gt; &lt;value&gt;</div>
              <div>!ol list alias</div>
            </div>

            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-violet-300">Placeholders</h3>
            <div class="mb-5 space-y-1 font-mono text-sm text-foreground">
              <div>{1}, {2}, {3}, ...   positional, 1-indexed</div>
              <div>{*}                  every arg past the highest positional</div>
            </div>

            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-violet-300">Options</h3>
            <div class="mb-5 space-y-1 font-mono text-sm text-foreground">
              <div>cooldown    0-86400 (seconds)</div>
              <div>permission  everyone | sub | vip | mod | broadcaster</div>
              <div>enabled     true | false</div>
              <div>hidden      true | false</div>
            </div>

            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-violet-300">Dashboard</h3>
            <Link href="/settings/bot/aliases" class="font-mono text-sm text-violet-400 hover:underline">
              /settings/bot/aliases
            </Link>
          </div>
        </section>

        <!-- Related -->
        <section class="mb-14">
          <h2 class="mb-4 text-2xl font-bold">Related</h2>
          <ul class="space-y-2 text-foreground">
            <li>
              <Link href="/help/expressions" class="text-violet-400 hover:underline">Bot Expressions</Link> -
              custom <code>!command</code> chat replies templated against your controls, Twitch data, and the chatter
              who fired them.
            </li>
            <li>
              <Link href="/help/bot/commands" class="text-violet-400 hover:underline">Bot Commands</Link> - every
              built-in chat command the @overlabels bot ships with, plus the full <code>!ol</code> chat-admin
              vocabulary.
            </li>
            <li>
              <Link href="/help/lists" class="text-violet-400 hover:underline">Lists</Link> - if you find yourself
              aliasing list operations, the underlying <code>!list</code> meta-command is documented end-to-end here.
            </li>
          </ul>
        </section>

      </div>
    </div>
  </AppLayout>
</template>
