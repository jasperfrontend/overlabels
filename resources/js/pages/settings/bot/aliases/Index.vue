<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Plus, Pencil, Trash2, CornerDownRight, ArrowRight } from '@lucide/vue';

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

const props = defineProps<{
  aliases: BotAlias[];
  botEnabled: boolean;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Bot aliases', href: '/settings/bot/aliases' },
];

function deleteAlias(alias: BotAlias) {
  if (!confirm(`Delete alias "!${alias.command}"? This cannot be undone.`)) return;
  router.delete(`/settings/bot/aliases/${alias.id}`, {
    preserveScroll: true,
  });
}

function formatDate(iso: string | null): string {
  if (!iso) return 'never';
  const d = new Date(iso);
  return d.toLocaleString();
}
</script>

<template>
  <Head>
    <title>Bot aliases - Overlabels</title>
  </Head>

  <AppLayout :breadcrumbs="breadcrumbItems">
    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Bot aliases"
          description="Short rewrites that expand to a longer command before the bot runs them. Type !w 2 and the bot fires !increment wins 2."
        />

        <div v-if="!props.botEnabled" class="rounded border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
          <p class="text-foreground">
            The Overlabels bot isn't enabled yet. Aliases are saved here, but nothing fires until the bot is on.
          </p>
          <Link
            href="/settings/integrations"
            class="mt-2 inline-block underline cursor-pointer hover:text-amber-400"
          >
            Enable it on the Integrations page -&gt;
          </Link>
        </div>

        <div class="flex justify-end">
          <Button as-child class="cursor-pointer">
            <Link href="/settings/bot/aliases/create">
              <Plus class="mr-2 size-4" />
              New alias
            </Link>
          </Button>
        </div>

        <div v-if="props.aliases.length === 0" class="rounded border border-sidebar-border p-8 text-center">
          <CornerDownRight class="mx-auto size-10 text-foreground/40" />
          <p class="mt-4 text-foreground">You haven't authored any bot aliases yet.</p>
          <p class="mt-1 text-sm text-foreground/70">
            Create one to give a long command a short nickname. Aliases default to moderator-only.
          </p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="alias in props.aliases"
            :key="alias.id"
            class="flex flex-col gap-3 rounded border border-sidebar-border p-4 sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="min-w-0 flex-1 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <code class="rounded bg-muted px-2 py-0.5 font-mono text-sm">!{{ alias.command }}</code>
                <ArrowRight class="size-3.5 text-foreground/50" />
                <code class="rounded bg-muted px-2 py-0.5 font-mono text-sm">!{{ alias.target_template }}</code>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="rounded px-2 py-0.5 text-xs uppercase tracking-wide"
                  :class="
                    alias.enabled
                      ? 'bg-emerald-500/15 text-emerald-400'
                      : 'bg-foreground/10 text-foreground/60'
                  "
                >
                  {{ alias.enabled ? 'enabled' : 'disabled' }}
                </span>
                <span class="text-xs text-foreground/70">
                  {{ alias.permission_level }}
                </span>
                <span v-if="alias.cooldown_seconds > 0" class="text-xs text-foreground/70">
                  cooldown: {{ alias.cooldown_seconds }}s
                </span>
              </div>

              <p class="text-xs text-foreground/60">
                Last fired: {{ formatDate(alias.last_fired_at) }}
              </p>
            </div>

            <div class="flex shrink-0 gap-2">
              <Button as-child variant="secondary" size="sm" class="cursor-pointer">
                <Link :href="`/settings/bot/aliases/${alias.id}/edit`">
                  <Pencil class="mr-1 size-3.5" />
                  Edit
                </Link>
              </Button>
              <Button
                variant="ghost"
                size="sm"
                class="cursor-pointer text-rose-400 hover:bg-rose-500/10 hover:text-rose-300"
                @click="deleteAlias(alias)"
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
