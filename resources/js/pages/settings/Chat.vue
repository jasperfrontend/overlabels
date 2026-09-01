<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Save } from '@lucide/vue';

interface ChatFilterProps {
  hide_commands: boolean;
  hidden_logins: string[];
}

const props = defineProps<{ chatFilters: ChatFilterProps }>();

const MAX_HIDDEN_LOGINS = 200;

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Chat settings', href: '/settings/chat' },
];

const hideCommands = ref(props.chatFilters.hide_commands);
const hiddenLoginsText = ref(props.chatFilters.hidden_logins.join('\n'));

const saving = ref(false);
const saveError = ref('');

const loginCount = computed(
  () =>
    hiddenLoginsText.value
      .split(/[\r\n,]+/)
      .map((line) => line.trim())
      .filter((line) => line !== '').length,
);

const overCap = computed(() => loginCount.value > MAX_HIDDEN_LOGINS);

// Success is the session flash the route sets, shown by AppLayout's toast.
function save() {
  saving.value = true;
  saveError.value = '';

  router.patch(
    route('settings.chat.update'),
    {
      hide_commands: hideCommands.value,
      hidden_logins: hiddenLoginsText.value,
    },
    {
      preserveScroll: true,
      onError: () => {
        saveError.value = 'Saving failed.';
      },
      onFinish: () => {
        saving.value = false;
      },
    },
  );
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="Chat settings" />

    <SettingsLayout>
      <div class="space-y-6">
        <HeadingSmall
          title="Chat display"
          description="What your chat overlay draws. These settings change your overlay only - chat itself is untouched."
        />

        <p class="text-sm text-foreground">
          Your overlay reads chat straight from Twitch. Hiding something here keeps it off your overlay; the message is still in chat, still in the
          VOD, and every viewer and moderator still sees it. To actually remove a message, use Twitch's own moderation - your overlay honours
          deletions and timeouts automatically, and always will.
        </p>

        <div class="space-y-3">
          <label class="flex cursor-pointer items-start gap-3">
            <input v-model="hideCommands" type="checkbox" class="mt-1 size-4 cursor-pointer" />
            <span class="text-sm">
              <span class="font-medium">Hide messages starting with <code class="font-mono">!</code></span>
              <span class="block text-muted-foreground">
                Keeps bot commands off the overlay. Takes every message starting with an exclamation mark, so
                <code class="font-mono">!!!</code> and <code class="font-mono">!what a play</code> go too.
              </span>
            </span>
          </label>
        </div>

        <div class="space-y-3">
          <Label for="hidden-logins">Hidden chatters</Label>
          <p class="text-sm text-muted-foreground">
            One Twitch username per line. Their messages are not drawn on your overlay, including during a shared chat collab. Up to
            {{ MAX_HIDDEN_LOGINS }}.
          </p>
          <textarea
            id="hidden-logins"
            v-model="hiddenLoginsText"
            rows="8"
            class="input-border w-full font-mono text-sm"
            placeholder="somebot&#10;anotherbot"
          ></textarea>
          <p v-if="overCap" class="text-sm text-destructive">{{ loginCount }} names listed. Only the first {{ MAX_HIDDEN_LOGINS }} will be saved.</p>
          <p v-else-if="loginCount > 0" class="text-sm text-muted-foreground">{{ loginCount }} {{ loginCount === 1 ? 'name' : 'names' }} listed.</p>
        </div>

        <div class="flex flex-col items-start gap-3">
          <button
            type="button"
            :disabled="saving"
            class="btn btn-primary cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
            @click="save"
          >
            <Save class="mr-2 size-4" />
            {{ saving ? 'Saving...' : 'Save chat settings' }}
          </button>
          <p v-if="saveError" class="text-sm text-destructive">{{ saveError }}</p>
        </div>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
