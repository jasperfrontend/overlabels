<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Plus, Pencil, Trash2, MessageSquare, Clock } from '@lucide/vue';

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
  expressions: BotExpression[];
  botEnabled: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Bot expressions', href: '/settings/bot/expressions' },
];

function deleteExpression(expression: BotExpression) {
  if (!confirm(`Delete "!${expression.command}"? This cannot be undone.`)) return;
  router.delete(`/settings/bot/expressions/${expression.id}`, {
    preserveScroll: true,
  });
}

function formatDate(iso: string | null): string {
  if (!iso) return 'never';
  const d = new Date(iso);
  return d.toLocaleString();
}

// Human-readable countdown to a destroy_at timestamp. The sweep runs every
// minute, so a timestamp in the (recent) past just means "any second now".
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
</script>

<template>
  <Head>
    <title>Bot expressions - Overlabels</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Bot expressions"
          description="Custom chat commands that read from your controls, Twitch data, and the chatter who fired them. The bot speaks the resolved string."
        />
        <div>
          <a class="btn btn-primary" href="/help/bot/expressions" target="_blank">Learn how Bot Expressions work</a>
        </div>
        <div v-if="!botEnabled" class="rounded border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
          <p class="text-foreground">
            The Overlabels bot isn't enabled yet. Bot expressions are saved here, but nothing fires until the bot is on.
          </p>
          <Link
            href="/settings/integrations"
            class="mt-2 inline-block underline cursor-pointer hover:text-amber-400"
          >
            Enable it on the Integrations page →
          </Link>
        </div>

        <div class="flex justify-end">
          <Button as-child class="cursor-pointer">
            <Link href="/settings/bot/expressions/create">
              <Plus class="mr-2 size-4" />
              New expression
            </Link>
          </Button>
        </div>

        <div v-if="props.expressions.length === 0" class="rounded border border-sidebar-border p-8 text-center">
          <MessageSquare class="mx-auto size-10 text-foreground/40" />
          <p class="mt-4 text-foreground">You haven't authored any bot expressions yet.</p>
          <p class="mt-1 text-sm text-foreground/70">
            Create one to let chatters fire a command and have the bot reply with a templated string.
          </p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="expression in props.expressions"
            :key="expression.id"
            class="flex flex-col gap-3 rounded border border-sidebar-border p-4 sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="min-w-0 flex-1 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <code class="rounded bg-muted px-2 py-0.5 font-mono text-sm">!{{ expression.command }}</code>
                <span
                  class="rounded px-2 py-0.5 text-xs uppercase tracking-wide"
                  :class="
                    expression.enabled
                      ? 'bg-emerald-500/15 text-emerald-400'
                      : 'bg-foreground/10 text-foreground/60'
                  "
                >
                  {{ expression.enabled ? 'enabled' : 'disabled' }}
                </span>
                <span class="text-xs text-foreground/70">
                  {{ expression.permission_level }}
                </span>
                <span v-if="expression.cooldown_seconds > 0" class="text-xs text-foreground/70">
                  cooldown: {{ expression.cooldown_seconds }}s
                </span>
                <span
                  v-if="expression.destroy_at"
                  class="inline-flex items-center gap-1 rounded bg-amber-500/15 px-2 py-0.5 text-xs text-amber-400"
                  :title="`Self-destructs at ${formatDate(expression.destroy_at)}`"
                >
                  <Clock class="size-3" />
                  self-destructs in {{ expiresIn(expression.destroy_at) }}
                </span>
              </div>

              <p class="break-words font-mono text-sm text-foreground/80">{{ expression.expression }}</p>

              <p class="text-xs text-foreground/60">
                Last fired: {{ formatDate(expression.last_fired_at) }}
              </p>
            </div>

            <div class="flex shrink-0 gap-2">
              <Button as-child variant="secondary" size="sm" class="cursor-pointer">
                <Link :href="`/settings/bot/expressions/${expression.id}/edit`">
                  <Pencil class="mr-1 size-3.5" />
                  Edit
                </Link>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                class="cursor-pointer text-rose-400 hover:bg-rose-500/10 hover:text-rose-300"
                @click="deleteExpression(expression)"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
