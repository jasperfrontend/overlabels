<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { type BreadcrumbItem } from '@/types';
import { ChevronLeft, Sparkles, AlertTriangle, Clock } from '@lucide/vue';

interface BotExpression {
  id: number;
  command: string;
  permission_level: string;
  cooldown_seconds: number;
  expression: string;
  enabled: boolean;
  hidden_from_commands: boolean;
  last_fired_at: string | null;
  destroy_at: string | null;
}

const props = defineProps<{
  expression: BotExpression | null;
  permissionLevels: string[];
  reservedCommands: string[];
  availableControlKeys: string[];
}>();

const isEdit = computed(() => props.expression !== null);

// The destroy timer is stored as an absolute timestamp but authored as
// "hours from now" (mirroring the !ol cmd options ... destroy <hours> chat
// command). Pre-fill the input with the remaining whole hours so an
// incidental save preserves a pending timer rather than silently resetting
// it; a timer already in the past pre-fills empty (the sweep will clear it).
function remainingHours(iso: string | null | undefined): number | null {
  if (!iso) return null;
  const ms = new Date(iso).getTime() - Date.now();
  if (ms <= 0) return null;
  return Math.max(1, Math.ceil(ms / 3_600_000));
}

// Human-readable countdown for the "currently self-destructs in ..." note.
function expiresIn(iso: string): string {
  const ms = new Date(iso).getTime() - Date.now();
  if (ms <= 0) return 'any moment';

  const minutes = Math.floor(ms / 60000);
  const days = Math.floor(minutes / 1440);
  const hours = Math.floor((minutes % 1440) / 60);
  const mins = minutes % 60;

  if (days > 0) return hours > 0 ? `${days}d ${hours}h` : `${days}d`;
  if (hours > 0) return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
  return `${mins}m`;
}

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Bot expressions', href: '/settings/bot/expressions' },
  {
    title: isEdit.value ? `Edit !${props.expression?.command}` : 'New expression',
    href: '#',
  },
];

const form = useForm({
  command: props.expression?.command ?? '',
  permission_level: props.expression?.permission_level ?? 'everyone',
  cooldown_seconds: props.expression?.cooldown_seconds ?? 0,
  expression: props.expression?.expression ?? 'Hi [[[bot:from_user|mention]]]!',
  enabled: props.expression?.enabled ?? true,
  hidden_from_commands: props.expression?.hidden_from_commands ?? false,
  destroy_hours: remainingHours(props.expression?.destroy_at),
});

function submit() {
  if (isEdit.value) {
    form.patch(`/settings/bot/expressions/${props.expression!.id}`, {
      preserveScroll: true,
    });
  } else {
    form.post('/settings/bot/expressions', {
      preserveScroll: true,
    });
  }
}

// Live preview
const previewOutput = ref('');
const previewLength = ref(0);
const previewLoading = ref(false);
const previewError = ref<string | null>(null);
let previewTimer: ReturnType<typeof setTimeout> | null = null;

// The auto-update resolves on every keystroke (debounced), errors and all,
// which some authors find distracting while mid-edit. Let them switch it off
// and render on demand instead. The choice is per-browser so it sticks across
// expressions and sessions; default on so existing behaviour is unchanged.
const LIVE_PREVIEW_KEY = 'ol:bot-expr-live-preview';
const livePreview = ref(readLivePreviewPref());
// True when the expression changed since the last render while live preview
// is off, so the shown output is stale and worth a manual refresh.
const previewStale = ref(false);

function readLivePreviewPref(): boolean {
  try {
    return localStorage.getItem(LIVE_PREVIEW_KEY) !== 'off';
  } catch {
    return true;
  }
}

async function refreshPreview() {
  previewStale.value = false;

  if (!form.expression) {
    previewOutput.value = '';
    previewLength.value = 0;
    previewError.value = null;
    return;
  }

  previewLoading.value = true;
  previewError.value = null;

  try {
    const { data } = await axios.post('/settings/bot/expressions/preview', {
      expression: form.expression,
    });
    previewOutput.value = data.resolved;
    previewLength.value = data.length;
  } catch (e: any) {
    previewError.value = e.response?.data?.message ?? 'Preview failed.';
  } finally {
    previewLoading.value = false;
  }
}

// Render once on load so the panel isn't blank, regardless of the toggle.
refreshPreview();

watch(
  () => form.expression,
  () => {
    if (!livePreview.value) {
      previewStale.value = true;
      return;
    }
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 250);
  }
);

watch(livePreview, (on) => {
  try {
    localStorage.setItem(LIVE_PREVIEW_KEY, on ? 'on' : 'off');
  } catch {
    // Private-mode or blocked storage - the toggle still works this session.
  }
  // Catch up immediately when re-enabling so the panel reflects current input.
  if (on) {
    if (previewTimer) clearTimeout(previewTimer);
    refreshPreview();
  }
});

const charsLeft = computed(() => 500 - previewLength.value);

function insertSnippet(snippet: string) {
  // Append at end if no selection.
  form.expression = (form.expression ?? '') + snippet;
}

// Slash commands never make it out of the bot: the Send Chat Message API
// transmits literal text, so Twitch drops a leading `/timeout` (or any slash
// command) and posts the rest as plain chat. The server validator rejects
// these too - this just warns the author live, before they submit, rather
// than bouncing them off a save error.
const startsWithSlash = computed(() => (form.expression ?? '').trimStart().startsWith('/'));
</script>

<template>
  <Head>
    <title>{{ isEdit ? `Edit !${props.expression?.command}` : 'New bot expression' }} - Overlabels</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <div>
          <Link
            href="/settings/bot/expressions"
            class="mb-2 inline-flex items-center text-sm text-foreground/70 cursor-pointer hover:text-foreground"
          >
            <ChevronLeft class="mr-1 size-4" />
            Back to bot expressions
          </Link>
          <HeadingSmall
            :title="isEdit ? `Edit !${props.expression?.command}` : 'New bot expression'"
            description="Build a chat command. The bot replies with whatever you template here."
          />
        </div>

        <form class="space-y-6" @submit.prevent="submit">
          <!-- Command -->
          <div class="space-y-2">
            <Label for="command">Command</Label>
            <div class="flex items-center gap-1">
              <span class="text-foreground/60">!</span>
              <Input
                id="command"
                v-model="form.command"
                placeholder="distance"
                maxlength="30"
                class="font-mono"
              />
            </div>
            <p v-if="form.errors.command" class="text-sm text-rose-400">{{ form.errors.command }}</p>
            <p v-else class="text-xs text-foreground/60">
              Letters, numbers, hyphens, underscores. Cannot collide with built-ins:
              <code class="text-foreground/80">{{ props.reservedCommands.join(', ') }}</code>
            </p>
          </div>

          <!-- Expression -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <Label for="expression">Expression</Label>
              <span
                class="text-xs"
                :class="charsLeft < 0 ? 'text-rose-400' : 'text-foreground/60'"
              >
                {{ previewLength }} / 500 chars
              </span>
            </div>
            <Textarea
              id="expression"
              v-model="form.expression"
              :rows="4"
              class="font-mono text-sm"
              placeholder="Hi [[[bot:from_user]]], the count is [[[c:my_counter]]]"
            />
            <p v-if="form.errors.expression" class="text-sm text-rose-400">{{ form.errors.expression }}</p>
            <div
              v-if="startsWithSlash"
              class="flex items-start gap-2 rounded border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200"
            >
              <AlertTriangle class="mt-0.5 size-4 shrink-0" />
              <span>
                Slash commands like <code class="text-amber-100">/timeout</code>,
                <code class="text-amber-100">/ban</code> or <code class="text-amber-100">/me</code> don't work here.
                The overlabels bot sends plain chat text, so Twitch drops the leading
                <code class="text-amber-100">/</code> and posts the rest as a message. The bot powers your overlays,
                it isn't a chat moderator - use your mod tools for that.
              </span>
            </div>
          </div>

          <!-- Preview -->
          <div class="rounded border border-sidebar-border bg-sidebar-accent/30 p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-foreground/60">
                <Sparkles class="size-3.5" />
                Preview
                <span v-if="previewLoading" class="normal-case italic text-foreground/40">resolving...</span>
              </div>
              <label class="flex items-center gap-2 text-xs text-foreground/70 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="livePreview"
                  class="size-3.5 cursor-pointer"
                />
                Live preview
              </label>
            </div>
            <!--
              The content stays mounted across refreshes - we never swap it for
              a "Resolving..." line. Collapsing multi-line output to one line and
              back is what made the panel jump; instead the last output stays put
              (dimmed) while a new one resolves, so height changes only ever go
              directly from old content to new. min-height floors the empty/error
              states so they don't collapse either.
            -->
            <div class="min-h-16 text-sm">
              <div v-if="previewError" class="flex items-center gap-2 text-rose-400">
                <AlertTriangle class="size-4" />
                {{ previewError }}
              </div>
              <div v-else-if="!previewOutput" class="italic text-foreground/50">
                (empty)
              </div>
              <div
                v-else
                class="whitespace-pre-wrap wrap-break-word transition-opacity duration-150"
                :class="{ 'opacity-50': previewLoading }"
              >
                {{ previewOutput }}
              </div>
            </div>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-2">
              <p class="text-xs text-foreground/60">
                Bot context shown as <code class="text-foreground/80">CoolChatter</code> with sample args.
                Twitch tags resolve to empty in preview.
              </p>
              <div v-if="!livePreview" class="flex items-center gap-2">
                <span v-if="previewStale" class="text-xs text-amber-400">edited</span>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded bg-foreground/10 px-2 py-1 text-xs cursor-pointer hover:bg-foreground/15"
                  @click="refreshPreview"
                >
                  <Sparkles class="size-3" />
                  Render preview
                </button>
              </div>
            </div>
          </div>

          <!-- Available tags helper -->
          <details class="rounded border border-sidebar-border p-4 text-sm">
            <summary class="cursor-pointer font-medium">Available tags</summary>
            <div class="mt-3 space-y-3 text-xs text-foreground/80">
              <div>
                <p class="mb-1 font-semibold text-foreground">Bot context</p>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="tag in [
                      'bot:from_user',
                      'bot:from_user_login',
                      'bot:from_user_id',
                      'bot:command',
                      'bot:args',
                      'bot:args.0',
                      'bot:args.1',
                      'bot:channel',
                    ]"
                    :key="tag"
                    type="button"
                    class="rounded bg-muted px-1.5 py-0.5 font-mono cursor-pointer hover:bg-foreground/10"
                    @click="insertSnippet(`[[[${tag}]]]`)"
                  >
                    [[[{{ tag }}]]]
                  </button>
                </div>
              </div>
              <div v-if="props.availableControlKeys.length > 0">
                <p class="mb-1 font-semibold text-foreground">Your controls</p>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="key in props.availableControlKeys"
                    :key="key"
                    type="button"
                    class="rounded bg-muted px-1.5 py-0.5 font-mono cursor-pointer hover:bg-foreground/10"
                    @click="insertSnippet(`[[[c:${key}]]]`)"
                  >
                    [[[c:{{ key }}]]]
                  </button>
                </div>
              </div>
              <p class="text-foreground/60">
                Twitch tags use bare names like
                <code class="text-foreground/80">[[[followers_total]]]</code>. They resolve at fire time, not in preview.
              </p>
              <p class="text-foreground/60">
                Pipe formatters: <code class="text-foreground/80">|number</code>,
                <code class="text-foreground/80">|distance:mi</code>,
                <code class="text-foreground/80">|round:2</code>,
                <code class="text-foreground/80">|uppercase</code>,
                <code class="text-foreground/80">|date:HH:mm</code>.
              </p>
              <p class="text-foreground/60">
                For shoutouts: <code class="text-foreground/80">|mention</code> keeps the
                <code class="text-foreground/80">@</code> so it pings,
                <code class="text-foreground/80">|login</code> strips it and lowercases for URLs like
                <code class="text-foreground/80">twitch.tv/[[[bot:args.0|login]]]</code>.
              </p>
              <p class="text-foreground/60">
                Default value: add <code class="text-foreground/80">?? something</code> to show literal text when a
                tag is empty, e.g. <code class="text-foreground/80">[[[bot:args.0 ?? everyone]]]</code> or
                <code class="text-foreground/80">[[[c:donations|number ?? 0]]]</code>. It only fills in for a
                missing value; a value that's present is shown as-is.
              </p>
            </div>
          </details>

          <!-- Permission + cooldown row -->
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="permission_level">Who can fire it</Label>
              <select
                id="permission_level"
                v-model="form.permission_level"
                class="w-full rounded border border-input bg-background px-3 py-2 text-sm cursor-pointer"
              >
                <option v-for="lvl in props.permissionLevels" :key="lvl" :value="lvl">
                  {{ lvl }}
                </option>
              </select>
              <p v-if="form.errors.permission_level" class="text-sm text-rose-400">{{ form.errors.permission_level }}</p>
            </div>

            <div class="space-y-2">
              <Label for="cooldown_seconds">Cooldown (seconds)</Label>
              <Input
                id="cooldown_seconds"
                v-model.number="form.cooldown_seconds"
                type="number"
                min="0"
                max="86400"
              />
              <p class="text-xs text-foreground/60">Per channel. Broadcaster bypasses cooldown.</p>
            </div>
          </div>

          <!-- Self-destruct timer -->
          <div class="space-y-2">
            <Label for="destroy_hours">Self-destruct timer (hours)</Label>
            <Input
              id="destroy_hours"
              v-model.number="form.destroy_hours"
              type="number"
              min="0"
              max="8760"
              placeholder="Leave empty to keep it forever"
            />
            <p v-if="form.errors.destroy_hours" class="text-sm text-rose-400">{{ form.errors.destroy_hours }}</p>
            <p v-else class="text-xs text-foreground/60">
              Automatically deletes this command after a set number of whole hours (max 8760 = one year). Leave empty to
              keep it forever. Saving restarts the countdown from now.
            </p>
            <p
              v-if="isEdit && props.expression?.destroy_at"
              class="inline-flex items-center gap-1 text-xs text-amber-400"
            >
              <Clock class="size-3" />
              Currently self-destructs in {{ expiresIn(props.expression.destroy_at) }}
            </p>
          </div>

          <!-- Toggles -->
          <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.enabled"
                class="mt-1 size-4 cursor-pointer"
              />
              <div>
                <p class="text-sm font-medium">Enabled</p>
                <p class="text-xs text-foreground/60">Disabled expressions stay in your library but don't fire.</p>
              </div>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="form.hidden_from_commands"
                class="mt-1 size-4 cursor-pointer"
              />
              <div>
                <p class="text-sm font-medium">Hide from <code>!commands</code> listing</p>
                <p class="text-xs text-foreground/60">
                  When the bot eventually exposes a <code>!commands</code> meta-command, this expression won't be listed.
                </p>
              </div>
            </label>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-2">
            <Button as-child variant="ghost" class="cursor-pointer">
              <Link href="/settings/bot/expressions">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing" class="cursor-pointer">
              {{ isEdit ? 'Save changes' : 'Create expression' }}
            </Button>
          </div>
        </form>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
