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
import { ChevronLeft, Sparkles, AlertTriangle } from 'lucide-vue-next';

interface BotExpression {
  id: number;
  command: string;
  permission_level: string;
  cooldown_seconds: number;
  expression: string;
  enabled: boolean;
  hidden_from_commands: boolean;
  last_fired_at: string | null;
}

const props = defineProps<{
  expression: BotExpression | null;
  permissionLevels: string[];
  reservedCommands: string[];
  availableControlKeys: string[];
}>();

const isEdit = computed(() => props.expression !== null);

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
  expression: props.expression?.expression ?? 'Hi [[[bot:from_user]]]!',
  enabled: props.expression?.enabled ?? true,
  hidden_from_commands: props.expression?.hidden_from_commands ?? false,
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

async function refreshPreview() {
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

watch(
  () => form.expression,
  () => {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 250);
  },
  { immediate: true }
);

const charsLeft = computed(() => 500 - previewLength.value);

function insertSnippet(snippet: string) {
  // Append at end if no selection.
  form.expression = (form.expression ?? '') + snippet;
}
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
              rows="4"
              class="font-mono text-sm"
              placeholder="Hi [[[bot:from_user]]], the count is [[[c:my_counter]]]"
            />
            <p v-if="form.errors.expression" class="text-sm text-rose-400">{{ form.errors.expression }}</p>
          </div>

          <!-- Live preview -->
          <div class="rounded border border-sidebar-border bg-sidebar-accent/30 p-4">
            <div class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-foreground/60">
              <Sparkles class="size-3.5" />
              Live preview
            </div>
            <div v-if="previewError" class="flex items-center gap-2 text-sm text-rose-400">
              <AlertTriangle class="size-4" />
              {{ previewError }}
            </div>
            <div v-else-if="previewLoading" class="text-sm text-foreground/50 italic">
              Resolving...
            </div>
            <div v-else-if="!previewOutput" class="text-sm text-foreground/50 italic">
              (empty)
            </div>
            <div v-else class="text-sm whitespace-pre-wrap break-words">
              {{ previewOutput }}
            </div>
            <p class="mt-3 text-xs text-foreground/60">
              Bot context shown as <code class="text-foreground/80">CoolChatter</code> with sample args.
              Twitch tags resolve to empty in preview.
            </p>
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
