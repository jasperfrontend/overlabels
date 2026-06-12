<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { ChevronLeft, ArrowRight, Info } from '@lucide/vue';

interface BotAlias {
  id: number;
  command: string;
  target_template: string;
  permission_level: string;
  cooldown_seconds: number;
  enabled: boolean;
  hidden_from_commands: boolean;
  last_fired_at: string | null;
}

interface KnownCommand {
  command: string;
  kind: 'builtin' | 'expression';
}

const props = defineProps<{
  alias: BotAlias | null;
  permissionLevels: string[];
  knownCommands: KnownCommand[];
}>();

const isEdit = computed(() => props.alias !== null);

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Bot aliases', href: '/settings/bot/aliases' },
  {
    title: isEdit.value ? `Edit !${props.alias?.command}` : 'New alias',
    href: '#',
  },
];

const form = useForm({
  command: props.alias?.command ?? '',
  target_template: props.alias?.target_template ?? 'increment ',
  permission_level: props.alias?.permission_level ?? 'moderator',
  cooldown_seconds: props.alias?.cooldown_seconds ?? 0,
  enabled: props.alias?.enabled ?? true,
  hidden_from_commands: props.alias?.hidden_from_commands ?? false,
});

function submit() {
  if (isEdit.value) {
    form.patch(`/settings/bot/aliases/${props.alias!.id}`, {
      preserveScroll: true,
    });
  } else {
    form.post('/settings/bot/aliases', {
      preserveScroll: true,
    });
  }
}

function insertTargetCommand(command: string) {
  // Replace just the leading command token, keep any args/placeholders the user already typed.
  const rest = (form.target_template ?? '').replace(/^!?\s*[\w-]*\s*/, '');
  form.target_template = rest ? `${command} ${rest}` : `${command} `;
}

function insertPlaceholder(placeholder: string) {
  form.target_template = (form.target_template ?? '') + placeholder;
}

// Derived preview: render the alias call site -> the rewritten command, using sample args.
const previewExample = computed(() => {
  const cmd = form.command || 'alias';
  const template = form.target_template || '';
  // Detect highest positional placeholder used so we synthesise enough sample args.
  const positional = [...template.matchAll(/\{(\d+)\}/g)].map((m) => parseInt(m[1], 10));
  const hasStar = template.includes('{*}');
  const maxIdx = positional.length > 0 ? Math.max(...positional) : 0;
  const sampleArgs = ['2', 'hello', 'world', 'four'];
  const argsForCallsite = sampleArgs.slice(0, Math.max(maxIdx, hasStar ? 3 : 0)) || [];

  let resolved = template;
  for (let i = 1; i <= maxIdx; i++) {
    resolved = resolved.replaceAll(`{${i}}`, sampleArgs[i - 1] ?? '');
  }
  if (hasStar) {
    resolved = resolved.replaceAll('{*}', argsForCallsite.slice(maxIdx).join(' '));
  }

  return {
    callsite: `!${cmd}${argsForCallsite.length ? ' ' + argsForCallsite.join(' ') : ''}`,
    resolved: `!${resolved.trim()}`,
  };
});
</script>

<template>
  <Head>
    <title>{{ isEdit ? `Edit !${props.alias?.command}` : 'New bot alias' }} - Overlabels</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <div>
          <Link
            href="/settings/bot/aliases"
            class="mb-2 inline-flex items-center text-sm text-foreground/70 cursor-pointer hover:text-foreground"
          >
            <ChevronLeft class="mr-1 size-4" />
            Back to bot aliases
          </Link>
          <HeadingSmall
            :title="isEdit ? `Edit !${props.alias?.command}` : 'New bot alias'"
            description="Short bot commands that rewrite to longer ones. The rewritten command is dispatched as if the chatter typed it directly, so the target's own permissions still apply."
          />
        </div>

        <form class="space-y-6" @submit.prevent="submit">
          <!-- Command -->
          <div class="space-y-2">
            <Label for="command">Alias command</Label>
            <div class="flex items-center gap-1">
              <span class="text-foreground/60">!</span>
              <Input
                id="command"
                v-model="form.command"
                placeholder="w"
                maxlength="30"
                class="font-mono"
              />
            </div>
            <p v-if="form.errors.command" class="text-sm text-rose-400">{{ form.errors.command }}</p>
            <p v-else class="text-xs text-foreground/60">
              Letters, numbers, hyphens, underscores. Can't collide with built-ins or your own expressions.
            </p>
          </div>

          <!-- Target template -->
          <div class="space-y-2">
            <Label for="target_template">Expands to</Label>
            <div class="flex items-center gap-1">
              <span class="text-foreground/60">!</span>
              <Input
                id="target_template"
                v-model="form.target_template"
                placeholder="increment wins {1}"
                maxlength="200"
                class="font-mono"
              />
            </div>
            <p v-if="form.errors.target_template" class="text-sm text-rose-400">{{ form.errors.target_template }}</p>
            <p v-else class="text-xs text-foreground/60">
              Use <code class="text-foreground/80">{1}</code>, <code class="text-foreground/80">{2}</code>, ... to pass
              the chatter's args in order. <code class="text-foreground/80">{*}</code> means "all remaining args".
            </p>

            <!-- Quick-insert helpers -->
            <div class="flex flex-wrap gap-1.5 pt-1">
              <span class="text-xs text-foreground/60 self-center mr-1">Placeholders:</span>
              <button
                v-for="ph in ['{1}', '{2}', '{3}', '{*}']"
                :key="ph"
                type="button"
                class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs cursor-pointer hover:bg-foreground/10"
                @click="insertPlaceholder(ph)"
              >
                {{ ph }}
              </button>
            </div>

            <details v-if="props.knownCommands.length > 0" class="rounded border border-sidebar-border p-3 text-sm">
              <summary class="cursor-pointer font-medium text-xs uppercase tracking-wide text-foreground/60">
                Target a command
              </summary>
              <div class="mt-2 space-y-2">
                <div>
                  <p class="mb-1 text-xs font-semibold text-foreground">Built-in commands</p>
                  <div class="flex flex-wrap gap-1.5">
                    <button
                      v-for="c in props.knownCommands.filter((c) => c.kind === 'builtin')"
                      :key="`b-${c.command}`"
                      type="button"
                      class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs cursor-pointer hover:bg-foreground/10"
                      @click="insertTargetCommand(c.command)"
                    >
                      !{{ c.command }}
                    </button>
                  </div>
                </div>
                <div v-if="props.knownCommands.some((c) => c.kind === 'expression')">
                  <p class="mb-1 text-xs font-semibold text-foreground">Your expressions</p>
                  <div class="flex flex-wrap gap-1.5">
                    <button
                      v-for="c in props.knownCommands.filter((c) => c.kind === 'expression')"
                      :key="`e-${c.command}`"
                      type="button"
                      class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs cursor-pointer hover:bg-foreground/10"
                      @click="insertTargetCommand(c.command)"
                    >
                      !{{ c.command }}
                    </button>
                  </div>
                </div>
              </div>
            </details>
          </div>

          <!-- Live example -->
          <div class="rounded border border-sidebar-border bg-sidebar-accent/30 p-4">
            <div class="mb-2 flex items-center gap-2 text-xs uppercase tracking-wide text-foreground/60">
              Example
            </div>
            <div class="flex flex-wrap items-center gap-2 font-mono text-sm">
              <code class="rounded bg-muted px-2 py-0.5">{{ previewExample.callsite }}</code>
              <ArrowRight class="size-3.5 text-foreground/50" />
              <code class="rounded bg-muted px-2 py-0.5">{{ previewExample.resolved }}</code>
            </div>
          </div>

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
              <p v-else class="text-xs text-foreground/60">
                Defaults to <code class="text-foreground/80">moderator</code>. The target command's own permission
                still applies after rewrite.
              </p>
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
                <p class="text-xs text-foreground/60">Disabled aliases stay in your library but don't fire.</p>
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
                  When the bot exposes a <code>!commands</code> meta-command, this alias won't be listed.
                </p>
              </div>
            </label>
          </div>

          <!-- Heads up: how the rewrite is dispatched -->
          <div class="rounded border border-sidebar-border bg-sidebar-accent/30 p-4">
            <div class="flex items-start gap-2">
              <Info class="mt-0.5 size-4 shrink-0 text-foreground/60" />
              <div class="text-xs text-foreground/70 space-y-1">
                <p>
                  Aliases are one hop only. An alias can't point at another alias - point it at the underlying
                  built-in or expression instead.
                </p>
                <p>
                  After rewrite, the target command runs with the chatter's original badges, so target-side
                  permission checks still gate (e.g. <code class="text-foreground/80">!increment</code> stays
                  moderator-only even if the alias is open to everyone).
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-2 pt-2">
            <Button as-child variant="ghost" class="cursor-pointer">
              <Link href="/settings/bot/aliases">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing" class="cursor-pointer">
              {{ isEdit ? 'Save changes' : 'Create alias' }}
            </Button>
          </div>
        </form>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
