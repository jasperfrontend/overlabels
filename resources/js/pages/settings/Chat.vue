<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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

interface ChatRedactProps {
  enabled: boolean;
  /** Without its `!`. */
  command: string;
}

const props = defineProps<{ chatFilters: ChatFilterProps; chatRedact: ChatRedactProps }>();

const MAX_HIDDEN_LOGINS = 200;

const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Chat settings', href: '/settings/chat' },
];

const hideCommands = ref(props.chatFilters.hide_commands);
const hiddenLoginsText = ref(props.chatFilters.hidden_logins.join('\n'));

const redactEnabled = ref(props.chatRedact.enabled);
const redactCommand = ref(props.chatRedact.command);

// The route forgives a typed `!` and capitals, so show the word as saved.
watch(
  () => props.chatRedact,
  (saved) => {
    redactEnabled.value = saved.enabled;
    redactCommand.value = saved.command;
  },
);

const saving = ref(false);
const saveError = ref('');
const errors = ref<Record<string, string>>({});

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
  errors.value = {};

  router.patch(
    route('settings.chat.update'),
    {
      hide_commands: hideCommands.value,
      hidden_logins: hiddenLoginsText.value,
      redact_enabled: redactEnabled.value,
      redact_command: redactCommand.value,
    },
    {
      preserveScroll: true,
      onError: (fieldErrors) => {
        errors.value = fieldErrors;
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

        <!-- Redacted messages: the chatter's own choice to hide their words on
             the overlay, the streamer's choice to allow it. -->
        <div id="redacted-messages" class="scroll-mt-24 space-y-3">
          <label class="flex cursor-pointer items-start gap-3">
            <input v-model="redactEnabled" type="checkbox" class="mt-1 size-4 cursor-pointer" />
            <span class="text-sm">
              <span class="font-medium">Let chatters hide a message</span>
              <span class="block text-muted-foreground">
                A chatter types <code class="font-mono">!{{ redactCommand || 'hide' }}</code> before their message, and your overlay draws it as solid
                bars. It still shows in Twitch chat as normal. Off by default, and once it is on, telling your chat about it is up to you.
              </span>
            </span>
          </label>

          <div v-if="redactEnabled" class="space-y-2 pl-7">
            <Label for="redact-command">Chat command</Label>
            <p class="text-sm text-muted-foreground">
              Pick another word if your channel already uses <code class="font-mono">!hide</code> for something else.
            </p>
            <div class="flex max-w-xs items-center">
              <span class="border border-r-0 border-violet-400/80 bg-sidebar-accent px-3 py-2 font-mono text-sm dark:border-violet-400/50">!</span>
              <input
                id="redact-command"
                v-model="redactCommand"
                type="text"
                maxlength="26"
                autocomplete="off"
                spellcheck="false"
                class="input-border font-mono"
                placeholder="hide"
              />
            </div>
            <p v-if="errors.redact_command" class="text-sm text-destructive">{{ errors.redact_command }}</p>
          </div>
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
