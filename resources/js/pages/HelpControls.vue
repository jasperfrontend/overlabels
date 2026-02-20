<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Controls',
    href: '/help/controls',
  },
];
</script>

<template>
  <Head title="Controls Help" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-background">
      <div class="mx-auto max-w-4xl p-6">
        <div class="mb-8">
          <h1 class="mb-4 text-4xl font-bold">Controls</h1>
          <p class="text-lg text-muted-foreground">
            Controls are mutable, overlay-scoped values you can update live during a stream — no code, no deployment.
            Use them to display death counts, donation goals, timers, custom text, or anything that changes while you play.
          </p>
        </div>

        <!-- What are Controls -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">What are Controls?</h2>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <p class="mb-4 text-muted-foreground">
                A Control is a named value that lives on your template. You define its key, type, and optional label, and then
                reference it in your overlay HTML with the <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">[[[c:key]]]</code> syntax.
                During your stream, you update its value from the <strong>Control Panel</strong> — and the change appears in OBS instantly.
              </p>
              <p class="text-muted-foreground">
                Controls are <strong>overlay-scoped</strong>: each template has its own set. They are never shared between templates unless
                you explicitly import them when forking.
              </p>
            </div>

            <!-- Control types -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Control Types</h3>
              <div class="space-y-4">
                <div class="flex gap-4">
                  <div class="mt-0.5 flex h-6 w-16 shrink-0 items-center justify-center rounded bg-sidebar font-mono text-xs font-bold">text</div>
                  <div class="text-muted-foreground">Free-form text. Displayed as-is in your template. HTML is stripped for safety.</div>
                </div>
                <div class="flex gap-4">
                  <div class="mt-0.5 flex h-6 w-16 shrink-0 items-center justify-center rounded bg-sidebar font-mono text-xs font-bold">number</div>
                  <div class="text-muted-foreground">A numeric value with optional min, max, and step. Saved and displayed as a plain number.</div>
                </div>
                <div class="flex gap-4">
                  <div class="mt-0.5 flex h-6 w-16 shrink-0 items-center justify-center rounded bg-sidebar font-mono text-xs font-bold">counter</div>
                  <div class="text-muted-foreground">A whole-number counter with <strong>+</strong> / <strong>−</strong> / Reset buttons in the Control Panel. Great for deaths, wins, donations.</div>
                </div>
                <div class="flex gap-4">
                  <div class="mt-0.5 flex h-6 w-16 shrink-0 items-center justify-center rounded bg-sidebar font-mono text-xs font-bold">timer</div>
                  <div class="text-muted-foreground">A stopwatch or countdown. Start, stop, and reset from the Control Panel. The overlay reads elapsed time in real time.</div>
                </div>
                <div class="flex gap-4">
                  <div class="mt-0.5 flex h-6 w-16 shrink-0 items-center justify-center rounded bg-sidebar font-mono text-xs font-bold">datetime</div>
                  <div class="text-muted-foreground">A fixed date and time value. Useful for "stream starts at" countdowns or logging purposes.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Creating, editing, deleting -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">Managing Controls</h2>
          <p class="mb-6 text-muted-foreground">
            Controls live on the <strong>Controls</strong> tab of your template's detail page. You must be the template owner to manage them.
          </p>

          <div class="space-y-6">
            <!-- Create -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-1 text-xl font-semibold">
                <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-500 text-white text-xs font-bold">+</span>
                Creating a Control
              </h3>
              <p class="mb-4 mt-3 text-muted-foreground">Click <strong>Add control</strong> in the Controls tab to open the creation modal.</p>
              <div class="space-y-3 text-muted-foreground">
                <div><strong class="text-foreground">Key</strong> — A lowercase slug used in template tags, e.g. <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">deaths</code>, <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">goal_amount</code>. Must start with a letter and contain only lowercase letters, digits, and underscores. The key is permanent and cannot be changed after creation.</div>
                <div><strong class="text-foreground">Label</strong> — An optional human-readable name displayed in the Control Panel, e.g. "Death Counter". If omitted, the key is used.</div>
                <div><strong class="text-foreground">Type</strong> — One of: text, number, counter, timer, datetime.</div>
                <div><strong class="text-foreground">Sort order</strong> — Controls the display order in the Control Panel. Lower numbers appear first.</div>
                <div><strong class="text-foreground">Type-specific config</strong> — Number and counter controls accept min, max, step, and reset value. Timer controls accept a mode (count-up or countdown) and a base duration.</div>
              </div>
            </div>

            <!-- Edit -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-1 text-xl font-semibold">
                <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-white text-xs font-bold">✎</span>
                Editing a Control
              </h3>
              <p class="mt-3 text-muted-foreground">
                Click the pencil icon on any control row in the Controls tab. You can update the label, sort order, and type-specific configuration.
                The <strong>key</strong> and <strong>type</strong> cannot be changed after creation to protect references already used in your template HTML.
              </p>
            </div>

            <!-- Delete -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-1 text-xl font-semibold">
                <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold">✕</span>
                Deleting a Control
              </h3>
              <p class="mt-3 text-muted-foreground">
                Click the trash icon on a control row and confirm the prompt. Deletion is permanent. Any <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">[[[c:key]]]</code> references
                left in your template will render as blank after deletion — no errors, just empty space.
              </p>
            </div>

            <!-- Snippet copy -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-3 text-xl font-semibold">Copying the Snippet</h3>
              <p class="text-muted-foreground">
                Each row in the Controls table shows a copy button with the ready-to-paste snippet
                <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">[[[c:key]]]</code>.
                Click it to copy the snippet to your clipboard so you can paste it directly into your template editor.
              </p>
            </div>
          </div>
        </div>

        <!-- Using controls in templates -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">Using Controls in Templates</h2>
          <p class="mb-6 text-muted-foreground">
            Once a control exists, reference its current value anywhere in your overlay or alert template HTML using the
            <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">[[[c:key]]]</code> syntax.
          </p>

          <div class="space-y-6">
            <!-- Basic value injection -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Displaying a Value</h3>
              <p class="mb-4 text-muted-foreground">
                Place the tag wherever you want the value to appear. At render time the overlay substitutes the current value.
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm leading-relaxed">
                &lt;div class="deaths-counter"&gt;<br />
                &nbsp;&nbsp;Deaths: &lt;span&gt;[[[c:deaths]]]&lt;/span&gt;<br />
                &lt;/div&gt;
              </div>
              <p class="mt-4 text-sm text-muted-foreground">
                The overlay updates in real time whenever the value changes — no page reload required.
              </p>
            </div>

            <!-- Conditionals with controls -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Conditionals with Control Values</h3>
              <p class="mb-4 text-muted-foreground">
                Control values participate fully in the conditional engine. Use them exactly as you would any other template variable.
              </p>

              <div class="mb-4 rounded bg-sidebar p-4 font-mono text-sm leading-relaxed">
                [[[if:c:deaths >= 10]]]<br />
                &nbsp;&nbsp;&lt;div class="danger"&gt;Struggling a bit tonight...&lt;/div&gt;<br />
                [[[elseif:c:deaths >= 5]]]<br />
                &nbsp;&nbsp;&lt;div class="warning"&gt;Getting rough.&lt;/div&gt;<br />
                [[[else]]]<br />
                &nbsp;&nbsp;&lt;div class="ok"&gt;Still alive!&lt;/div&gt;<br />
                [[[endif]]]
              </div>

              <div class="mb-4 rounded bg-sidebar p-4 font-mono text-sm leading-relaxed">
                &lt;!-- Show a goal bar only when goal is set --&gt;<br />
                [[[if:c:goal_label]]]<br />
                &nbsp;&nbsp;&lt;div class="goal-bar"&gt;<br />
                &nbsp;&nbsp;&nbsp;&nbsp;&lt;span&gt;[[[c:goal_label]]]&lt;/span&gt;<br />
                &nbsp;&nbsp;&nbsp;&nbsp;&lt;progress value="[[[c:goal_current]]]" max="[[[c:goal_target]]]"&gt;&lt;/progress&gt;<br />
                &nbsp;&nbsp;&lt;/div&gt;<br />
                [[[endif]]]
              </div>

              <p class="text-sm text-muted-foreground">
                String comparison, numeric comparison, boolean truthiness — all operators work the same way as with Twitch data tags.
                See the <Link class="text-accent-foreground underline hover:no-underline" href="/help">Syntax Help</Link> page for the full comparison reference.
              </p>
            </div>

            <!-- Controls in CSS -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Controls in CSS</h3>
              <p class="mb-4 text-muted-foreground">
                Just like Twitch data tags, control tags can appear inside <code>&lt;style&gt;</code> blocks, which opens up dynamic styling.
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm leading-relaxed">
                &lt;style&gt;<br />
                &nbsp;&nbsp;.goal-fill &#123;<br />
                &nbsp;&nbsp;&nbsp;&nbsp;[[[if:c:goal_pct >= 100]]]<br />
                &nbsp;&nbsp;&nbsp;&nbsp;background: #22c55e; /* green when complete */<br />
                &nbsp;&nbsp;&nbsp;&nbsp;[[[else]]]<br />
                &nbsp;&nbsp;&nbsp;&nbsp;background: #3b82f6;<br />
                &nbsp;&nbsp;&nbsp;&nbsp;[[[endif]]]<br />
                &nbsp;&nbsp;&#125;<br />
                &lt;/style&gt;
              </div>
            </div>

            <!-- Alert templates -->
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Controls in Alert Templates</h3>
              <p class="mb-4 text-muted-foreground">
                Alert templates also support control tags. This lets an alert read the current state of your overlay to decide what to display.
              </p>
              <div class="rounded bg-sidebar p-4 font-mono text-sm leading-relaxed">
                &lt;!-- Alert for a sub that mentions the current death count --&gt;<br />
                &lt;div class="sub-alert"&gt;<br />
                &nbsp;&nbsp;[[[event.user_name]]] just subscribed!<br />
                &nbsp;&nbsp;[[[if:c:deaths > 0]]]<br />
                &nbsp;&nbsp;&nbsp;&nbsp;&lt;span class="subtle"&gt;(and yes, [[[c:deaths]]] deaths so far)&lt;/span&gt;<br />
                &nbsp;&nbsp;[[[endif]]]<br />
                &lt;/div&gt;
              </div>
            </div>
          </div>
        </div>

        <!-- Control Panel -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">The Control Panel</h2>
          <p class="mb-6 text-muted-foreground">
            The <strong>Control Panel</strong> is a live dashboard for updating control values during your stream.
            It lives on the <strong>Control Panel</strong> tab of your template's detail page.
            Open it in a browser window before going live and keep it on a second monitor or phone.
          </p>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">How each type works</h3>
              <div class="space-y-5">
                <div>
                  <div class="mb-1 font-semibold">Text &amp; Number</div>
                  <p class="text-muted-foreground">
                    Type a new value into the input field and click <strong>Save</strong>. The overlay updates immediately.
                    Number controls respect the min, max, and step you configured.
                  </p>
                </div>
                <div>
                  <div class="mb-1 font-semibold">Counter</div>
                  <p class="text-muted-foreground">
                    Three buttons: <strong>−</strong> decrements by one step, <strong>+</strong> increments by one step,
                    and <strong>Reset</strong> returns the counter to its configured reset value (default 0).
                    Each press fires immediately — no save button needed.
                  </p>
                </div>
                <div>
                  <div class="mb-1 font-semibold">Timer</div>
                  <p class="text-muted-foreground">
                    <strong>Start</strong> begins counting (count-up or countdown, depending on your config). The display ticks
                    every half-second in the Control Panel and in the overlay simultaneously.
                    <strong>Stop</strong> pauses at the current time. <strong>Reset</strong> returns to zero (or the base duration for countdowns).
                  </p>
                </div>
                <div>
                  <div class="mb-1 font-semibold">Datetime</div>
                  <p class="text-muted-foreground">
                    Pick a date and time from the datetime picker and click <strong>Save</strong>.
                    Useful for "Next stream: [[[c:next_stream]]]" display text.
                  </p>
                </div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Real-time updates</h3>
              <p class="text-muted-foreground">
                Every Control Panel action broadcasts the new value over your live channel. Any open overlay that
                references the changed control re-renders that value in real time — typically in under a second.
                No refresh required in OBS.
              </p>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Access</h3>
              <p class="text-muted-foreground">
                The Control Panel is available only to the template owner and requires a logged-in session.
                Your viewers or collaborators cannot accidentally change your values — there is no public endpoint for mutations.
              </p>
            </div>
          </div>
        </div>

        <!-- Forking -->
        <div class="mb-12">
          <h2 class="mb-6 text-2xl font-bold">Forking a Template with Controls</h2>
          <p class="mb-6 text-muted-foreground">
            When you fork a public template that has controls attached, Overlabels walks you through the
            <strong>Import Wizard</strong> before navigating to your new fork.
          </p>

          <div class="space-y-6">
            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">The Import Wizard</h3>
              <p class="mb-4 text-muted-foreground">
                The wizard shows a table of every control from the source template. For each one you can choose:
              </p>
              <div class="space-y-3">
                <div class="flex gap-3">
                  <span class="mt-0.5 inline-flex h-5 w-14 shrink-0 items-center justify-center rounded bg-green-600 text-xs font-semibold text-white">Create</span>
                  <span class="text-muted-foreground">Recreate this control in your fork with the same type and config. You can edit the key before confirming if you want to rename it.</span>
                </div>
                <div class="flex gap-3">
                  <span class="mt-0.5 inline-flex h-5 w-14 shrink-0 items-center justify-center rounded bg-sidebar text-xs font-semibold text-muted-foreground">Skip</span>
                  <span class="text-muted-foreground">Leave this control out of your fork. Any template tags referencing it will render blank until you add a matching control yourself.</span>
                </div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">What gets copied — and what doesn't</h3>
              <div class="space-y-3 text-muted-foreground">
                <div><strong class="text-foreground">Copied:</strong> key, label, type, configuration (min/max/mode/base duration, etc.), and sort order.</div>
                <div><strong class="text-foreground">Not copied:</strong> the current value. Your fork starts fresh — counter at 0, timer at 0:00, text blank — so you're never inheriting stale state from someone else's stream.</div>
                <div><strong class="text-foreground">New IDs:</strong> Each created control gets a brand-new database ID. Changes you make to your fork's controls never affect the original template.</div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
              <h3 class="mb-4 text-xl font-semibold">Skipping the wizard</h3>
              <p class="text-muted-foreground">
                Clicking <strong>Skip all, take me to the fork</strong> skips import entirely and takes you straight to your new template.
                Your fork will have zero controls at that point. You can always add controls manually from the Controls tab later,
                as long as you give them the same keys that your template HTML references.
              </p>
            </div>
          </div>
        </div>

        <!-- Tips -->
        <div class="rounded-lg border border-sidebar bg-sidebar-accent p-6">
          <h2 class="mb-4 text-2xl font-bold">Tips &amp; Best Practices</h2>
          <div class="space-y-4 text-muted-foreground">
            <div>
              <h4 class="font-semibold text-foreground">Choose descriptive keys</h4>
              <p>
                Keys are permanent. Pick something you'll still understand six months from now:
                <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">boss_deaths</code> over <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">d</code>.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Use sort order deliberately</h4>
              <p>The Control Panel displays controls in sort-order. Put your most-used controls at the top (sort order 0, 1, 2…) so you can reach them fast during a stream.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Counter beats text for numbers</h4>
              <p>If you're tracking "times I said 'gg'" reach for a counter, not a text control. The +/- buttons are faster under stream pressure than typing a new number.</p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Controls work with conditionals</h4>
              <p>
                Don't just display the raw value — use <code class="rounded bg-sidebar px-1.5 py-0.5 font-mono text-sm">[[[if:c:deaths >= 10]]]</code> to
                swap out CSS classes or show entirely different content when thresholds are crossed.
              </p>
            </div>
            <div>
              <h4 class="font-semibold text-foreground">Values are sanitized</h4>
              <p>HTML is stripped from text values before storage. You can't accidentally inject markup through a Control Panel update.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
