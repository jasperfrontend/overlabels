<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
  /** The word the board listens for, without its `!`. */
  plinkoCommand: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Integrations', href: '/settings/integrations' },
  { title: 'Plinko', href: '/settings/integrations/plinko' },
];

const form = useForm({ plinko_command: props.plinkoCommand });

function save() {
  form.post('/settings/integrations/plinko', {
    preserveScroll: true,
    // The route forgives a typed `!` and capitals, so show what was saved.
    onSuccess: (page) => {
      form.plinko_command = (page.props as { plinkoCommand?: string }).plinkoCommand ?? form.plinko_command;
      form.defaults();
    },
  });
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Plinko settings" />

    <SettingsLayout>
      <div class="space-y-6">
        <div class="flex items-center justify-between gap-2">
          <HeadingSmall
            title="Plinko"
            description="Your chat types a command and a ball with their name on it bounces down the Plinko board on your stream."
          />
          <Badge variant="success">Installed</Badge>
        </div>

        <form class="space-y-6" @submit.prevent="save">
          <div class="space-y-3">
            <Label for="plinko-command">Chat command</Label>
            <p class="text-sm text-muted-foreground">
              Every message starting with this command drops a ball. <code class="font-mono">!drop</code> by default. Pick another word if your
              channel already uses <code class="font-mono">!drop</code> for something else, like another chat game.
            </p>
            <div class="flex max-w-xs items-center">
              <span class="border border-r-0 border-violet-400/80 bg-sidebar-accent px-3 py-2 font-mono text-sm dark:border-violet-400/50">!</span>
              <input
                id="plinko-command"
                v-model="form.plinko_command"
                type="text"
                maxlength="26"
                autocomplete="off"
                spellcheck="false"
                class="input-border font-mono"
                placeholder="drop"
              />
            </div>
            <p v-if="form.errors.plinko_command" class="text-sm text-destructive">{{ form.errors.plinko_command }}</p>
            <p class="text-sm text-muted-foreground">After saving, reload the Plinko browser source in OBS to use the new word.</p>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="form.processing">
            {{ form.processing ? 'Saving...' : 'Save changes' }}
          </button>
        </form>

        <p class="text-sm text-muted-foreground">
          The board itself, the OBS link and the uninstall button are on the
          <Link :href="route('products.manage', 'twitch-chat-plinko')" class="cursor-pointer text-violet-600 hover:underline dark:text-violet-400"
            >Twitch Chat Plinko page</Link
          >.
        </p>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
