<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import { useProductTarget } from '@/composables/useUiMode';

// While a product install's next step is "switch the bot on", the toggle
// wears the fuchsia target edge (see ProductSetup::STEPS). The target moved
// here with the toggle itself.
const botIsProductTarget = useProductTarget('bot-toggle');

interface BotInfo {
  enabled: boolean;
  command_count: number;
  alias_count: number;
  builtin_count: number;
}

const props = defineProps<{
  bot: BotInfo;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Chat bot', href: '/settings/integrations/bot' },
];

const botLoading = ref(false);

function toggleBot() {
  botLoading.value = true;
  router.patch(
    '/settings/integrations/bot',
    { enabled: !props.bot.enabled },
    {
      preserveScroll: true,
      onFinish: () => {
        botLoading.value = false;
      },
    },
  );
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Chat Bot Integration" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall
            title="Chat bot"
            description="The shared @overlabels Twitch account joins your chat, answers your commands and updates your overlay controls."
          />
          <Badge v-if="bot.enabled" variant="success">Connected</Badge>
          <Badge v-else variant="secondary">Not connected</Badge>
        </div>

        <!-- Not connected: explain what this does -->
        <div v-if="!bot.enabled" class="space-y-2 border border-sidebar-border bg-sidebar-accent p-4 text-sm text-foreground">
          <p class="font-medium">How it works</p>
          <ol class="list-decimal space-y-1 pl-4">
            <li>Switch the bot on below. It joins your channel as <strong>@overlabels</strong> within a few seconds.</li>
            <li>
              Type <code class="rounded bg-black/10 px-1 dark:bg-white/10">/mod overlabels</code> in your own chat. Twitch drops the bot's replies
              without it.
            </li>
            <li>
              Try <code class="rounded bg-black/10 px-1 dark:bg-white/10">!ping</code> - it should reply with pong. The default
              <a :href="route('help.bot.commands')" target="_blank" class="underline hover:text-foreground">bot commands</a> are switched on for you
              the first time you enable it.
            </li>
          </ol>
        </div>

        <!-- Lit up while a product install's next step is this toggle. -->
        <div class="border border-sidebar-border p-4" :class="{ 'product-target': botIsProductTarget }">
          <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
              <p class="text-sm font-medium">{{ bot.enabled ? 'The bot is in your chat' : 'The bot is switched off' }}</p>
              <!--
                The bot can only post once it is modded, and nothing here can
                tell whether that has happened - mod status lives bot-side. So
                the instruction stays, phrased as troubleshooting rather than a
                to-do, which is what a done state sounds like.
              -->
              <p v-if="bot.enabled" class="text-sm text-muted-foreground">
                Not replying? Run <code class="rounded bg-muted px-1 py-0.5 text-xs">/mod overlabels</code> in chat, then try
                <code class="rounded bg-muted px-1 py-0.5 text-xs">!ping</code>.
              </p>
              <p v-else class="text-sm text-muted-foreground">Your chat commands, aliases and chat-driven products do nothing until it is on.</p>
            </div>

            <button
              class="btn shrink-0 cursor-pointer"
              :class="bot.enabled ? 'btn-secondary' : 'btn-primary'"
              :disabled="botLoading"
              @click="toggleBot"
            >
              {{ bot.enabled ? 'Disable' : 'Enable' }}
            </button>
          </div>
        </div>

        <Separator />

        <div class="space-y-3">
          <div>
            <p class="text-sm font-medium">What the bot answers</p>
            <p class="text-sm text-muted-foreground">Counts are what is switched on, which is what the bot actually responds to in chat.</p>
          </div>

          <div class="flex items-center justify-between gap-4 border border-sidebar-border p-4">
            <div class="space-y-1">
              <p class="text-sm font-medium">{{ bot.command_count }} chat command{{ bot.command_count === 1 ? '' : 's' }}</p>
              <p class="text-sm text-muted-foreground">
                Custom <code class="rounded bg-muted px-1 py-0.5 text-xs">!command</code> replies, templated against your controls and Twitch data.
              </p>
            </div>
            <Link href="/settings/bot/commands" class="btn btn-sm btn-plain shrink-0 cursor-pointer">Commands</Link>
          </div>

          <div class="flex items-center justify-between gap-4 border border-sidebar-border p-4">
            <div class="space-y-1">
              <p class="text-sm font-medium">{{ bot.alias_count }} alias{{ bot.alias_count === 1 ? '' : 'es' }}</p>
              <p class="text-sm text-muted-foreground">
                Short names that rewrite to longer commands. <code class="rounded bg-muted px-1 py-0.5 text-xs">!w 2</code> -&gt;
                <code class="rounded bg-muted px-1 py-0.5 text-xs">!increment wins 2</code>.
              </p>
            </div>
            <Link href="/settings/bot/aliases" class="btn btn-sm btn-plain shrink-0 cursor-pointer">Aliases</Link>
          </div>

          <div class="flex items-center justify-between gap-4 border border-sidebar-border p-4">
            <div class="space-y-1">
              <p class="text-sm font-medium">{{ bot.builtin_count }} built-in command{{ bot.builtin_count === 1 ? '' : 's' }}</p>
              <p class="text-sm text-muted-foreground">
                The verbs Overlabels ships, like !set and !increment. Switch them off or change who may run them.
              </p>
            </div>
            <Link href="/settings/bot/builtins" class="btn btn-sm btn-plain shrink-0 cursor-pointer">Built-ins</Link>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
