<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import { Plus, Pencil, Trash2, MessageSquare, Clock } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();
interface BotCommand {
  id: number;
  command: string;
  permission_level: string;
  cooldown_seconds: number;
  reply: string;
  enabled: boolean;
  hidden: boolean;
  last_fired_at: string | null;
  destroy_at: string | null;
}

const props = defineProps<{
  commands: BotCommand[];
  botEnabled: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Bot commands', href: '/settings/bot/commands' },
];

async function deleteCommand(command: BotCommand) {
  if (!(await confirm({ message: `Delete "!${command.command}"? This cannot be undone.`, confirmLabel: 'Delete' }))) return;
  router.delete(`/settings/bot/commands/${command.id}`, {
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
    <title>Bot commands - Overlabels</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Bot commands"
          description="Custom chat commands that read from your controls, Twitch data, and the chatter who fired them. The bot speaks the resolved string. Click the question mark bottom of the page to read related help documentation about the Overlabels bot."
        />

        <div v-if="!botEnabled" class="border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
          <p class="text-foreground">The Overlabels bot isn't enabled yet. Bot commands are saved here, but nothing fires until the bot is on.</p>
          <Link href="/settings/integrations" class="mt-2 inline-block cursor-pointer underline hover:text-amber-400">
            Enable it on the Integrations page →
          </Link>
        </div>

        <div class="flex justify-end">
          <Link href="/settings/bot/commands/create" class="btn btn-sm btn-primary flex items-center">
            <Plus class="mr-2 size-4" />
            New command
          </Link>
        </div>

        <div v-if="props.commands.length === 0" class="border border-sidebar-border p-8 text-center">
          <MessageSquare class="mx-auto size-10 text-foreground/40" />
          <p class="mt-4 text-foreground">You haven't authored any bot commands yet.</p>
          <p class="mt-1 text-sm text-foreground/70">Create one to let chatters fire a command and have the bot reply with a templated string.</p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="command in props.commands"
            :key="command.id"
            class="flex flex-col gap-3 border border-sidebar-border p-4 sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="min-w-0 flex-1 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <code class="bg-muted px-2 py-0.5 font-mono text-sm">!{{ command.command }}</code>
                <span
                  class="px-2 py-0.5 text-xs tracking-wide uppercase"
                  :class="command.enabled ? 'bg-emerald-500/15 text-emerald-400' : 'bg-foreground/10 text-foreground/60'"
                >
                  {{ command.enabled ? 'enabled' : 'disabled' }}
                </span>
                <span class="text-xs text-foreground/70">
                  {{ command.permission_level }}
                </span>
                <span v-if="command.cooldown_seconds > 0" class="text-xs text-foreground/70"> cooldown: {{ command.cooldown_seconds }}s </span>
                <span
                  v-if="command.destroy_at"
                  class="inline-flex items-center gap-1 bg-amber-500/15 px-2 py-0.5 text-xs text-amber-400"
                  :title="`Self-destructs at ${formatDate(command.destroy_at)}`"
                >
                  <Clock class="size-3" />
                  self-destructs in {{ expiresIn(command.destroy_at) }}
                </span>
              </div>

              <p class="font-mono text-sm wrap-break-word text-foreground/80">{{ command.reply }}</p>

              <p class="text-xs text-foreground/60">Last fired: {{ formatDate(command.last_fired_at) }}</p>
            </div>

            <div class="flex shrink-0 gap-2">
              <Link :href="`/settings/bot/commands/${command.id}/edit`" class="btn btn-sm btn-primary">
                <Pencil class="mr-1 size-3.5" />
                Edit
              </Link>
              <button class="btn btn-danger" @click="deleteCommand(command)">
                <Trash2 class="size-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
