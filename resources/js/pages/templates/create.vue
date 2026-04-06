<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import Modal from '@/components/Modal.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import TemplateTagsList from '@/components/TemplateTagsList.vue';
import TemplateCodeEditor from '@/components/templates/TemplateCodeEditor.vue';
import { Brackets, Code, InfoIcon, Save, ExternalLink, Zap, Layout } from 'lucide-vue-next';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { stripScriptsFromFields } from '@/utils/sanitize';

const form = useForm({
  name: '',
  description: '',
  head: '',
  html: '',
  css: '',
  type: 'static',
  is_public: true,
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Create New Overlay', href: '/templates/create' }];

const showPreview = ref(false);
const previewHtml = ref('');

const mainTabs = [
  { key: 'meta', label: 'Meta', icon: InfoIcon },
  { key: 'code', label: 'Code', icon: Code },
  { key: 'tags', label: 'Tags', icon: Brackets },
] as const;

const mainTab = ref<'meta' | 'code' | 'tags'>('meta');

const toastMessage = ref<string>('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');
const showToast = ref(false);

const { register } = useKeyboardShortcuts();

const submitForm = () => {
  const { sanitized, removed } = stripScriptsFromFields({
    name: form.name,
    description: form.description,
    head: form.head,
    html: form.html,
    css: form.css,
  });
  Object.assign(form, sanitized);

  if (removed > 0) {
    toastMessage.value = `Also removed ${removed} script tag${removed === 1 ? '' : 's'} — inline scripts aren't supported.`;
    toastType.value = 'warning';
    showToast.value = true;
  }

  form.post(route('templates.store'));
};

const previewTemplate = (): void => {
  const sampleData: Record<string, string> = {
    user_id: '123456789',
    user_login: 'wilko_dj',
    user_name: 'wilko_dj',
    user_type: 'affiliate',
    user_broadcaster_type: 'affiliate',
    user_description: 'I am a Twitch streamer!',
    user_avatar: 'https://static-cdn.jtvnw.net/jtv_user_pictures/7db44749-286f-4db0-9c99-574b16170d44-profile_image-70x70.png', // profile picture of /twitch
    user_offline_banner: 'https://static-cdn.jtvnw.net/jtv_user_pictures/3f5f72bf-ae59-4470-8f8a-730d9ef87500-channel_offline_image-1920x1080.png', // offline banner of /twitch
    user_follower_count: '1234',
    user_view_count: '45678',
    user_email: 'test@example.com',
    user_created: '2023-01-01T00:00:00Z',
    channel_game_id: '123456789',
    channel_game: 'Just Chatting',
    channel_id: '123456789',
    channel_login: 'wilko_dj',
    channel_name: 'wilko_dj',
    channel_title: 'Creating Overlabels overlays!',
    channel_language: 'en',
    channel_subscription_count: '123',
    channel_delay: '5000',
    channel_tags_0: 'tag1',
    channel_tags_1: 'tag2',
    channel_tags_2: 'tag3',
    channel_tags_3: 'tag4',
    channel_tags_4: 'tag5',
    channel_tags_5: 'tag6',
    channel_tags_6: 'tag7',
    channel_tags_7: 'tag8',
    channel_tags_8: 'tag9',
    channel_tags_9: 'tag10',
    channel_is_branded: 'false',
    channel_followers: 'Twitch, wilko_dj, and 123 others',
    channel_followers_count: '1234',
    stream_title: 'Creating Overlabels overlays!',
    followers_total: '1234',
    followers_latest_user_id: '123456789',
    followers_latest_user_login: 'twitchUser123',
    followers_latest_user_name: 'twitchUser123',
    followers_latest_date: '2023-01-01T00:00:00Z',
    followed_channels: '1234',
    followed_channels_count: '1234',
    followed_total: '1234',
    followed_latest_id: '123456789',
    followed_latest_login: 'twitchuser123',
    followed_latest_name: 'twitchUser123',
    followed_latest_date: '2023-01-01T00:00:00Z',
    subscribers_latest_broadcaster_id: '123456789',
    subscribers_latest_broadcaster_login: 'twitchuser123',
    subscribers_latest_broadcaster_name: 'twitchUser123',
    subscribers_latest_gifter_id: '123456789',
    subscribers_latest_gifter_login: 'subgifter123',
    subscribers_latest_gifter_name: 'subGifter123',
    subscribers_latest_is_gift: 'false',
    subscribers_latest_plan_name: 'Tier 1 subscriber',
    subscribers_latest_tier: '1000',
    subscribers_latest_user_id: '123456789',
    subscribers_latest_user_login: 'twitchUser123',
    subscribers_latest_user_name: 'twitchUser123',
    subscribers_points: '1234',
    subscribers_total: '1234',
    subscribers_pagination_cursor: '123456789',
    subscribers_channels: '1234',
    subscribers_channels_count: '1234',
    subscribers_channels_pagination_cursor: '123456789',
    subscribers_channels_latest_id: '123456789',
  };

  let htmlContent = form.html;
  let cssContent = form.css;
  Object.entries(sampleData).forEach(([tag, value]) => {
    const tagPattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    htmlContent = htmlContent.replace(tagPattern, value);
    cssContent = cssContent.replace(tagPattern, value);
  });

  previewHtml.value = `<!DOCTYPE html>
<html lang="en">
  <head><style>${cssContent}</style>${form.head}</head>
  <body>${htmlContent}</body>
</html>`;
  showPreview.value = true;
};

onMounted(() => {
  register('save-overlay', 'ctrl+s', () => submitForm(), { description: 'Create overlay' });
  register('preview-overlay', 'ctrl+p', () => previewTemplate(), { description: 'Preview overlay' });
});

</script>

<template>
  <Head title="Create Overlay" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <Heading title="New Overlay" description="Build your overlay with HTML, CSS, and Tags." description-class="text-sm text-muted-foreground" />
        <div class="flex shrink-0 items-center gap-2">
          <button type="button" @click="previewTemplate" class="btn btn-cancel">Preview <ExternalLink class="ml-2 h-4 w-4" /></button>
          <button @click="submitForm" :disabled="form.processing" class="btn btn-primary">
            <Save class="mr-2 h-4 w-4" />
            Create Overlay
          </button>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <!-- Tab bar -->
        <div class="rounded-sm rounded-b-none border border-b-0 border-sidebar bg-sidebar-accent">
          <div class="flex border-b border-violet-600 dark:border-violet-400">
            <button
              v-for="(tab, index) in mainTabs"
              :key="tab.key"
              type="button"
              @click="mainTab = tab.key"
              :class="[
                'flex cursor-pointer items-center gap-1.5 px-5 py-2.5 text-sm font-medium transition-colors hover:bg-background',
                index === 0 && 'rounded-tl-sm',
                mainTab === tab.key ? 'bg-violet-400 hover:bg-violet-500 text-black' : 'text-accent-foreground',
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Content box -->
        <div class="rounded-b-sm border border-t-0 border-sidebar bg-sidebar-accent p-4">
          <!-- Meta Tab -->
          <div v-if="mainTab === 'meta'" class="max-w-5xl space-y-5">
            <div>
              <label for="name" class="mb-1 block text-sm font-medium text-accent-foreground/50">Overlay Name *</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="input-border w-full"
                placeholder="My Awesome Overlay"
                required
                autofocus
                data-1p-ignore
              />
              <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
            </div>

            <div>
              <label for="description" class="mb-1 block text-sm font-medium text-accent-foreground/50">Description</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="input-border w-full"
                placeholder="Describe what your overlay does…"
              />
              <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
            </div>

            <!-- Overlay Type -->
            <div>
              <label class="mb-2 block text-sm font-medium text-accent-foreground/50">Overlay Type *</label>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label
                  class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-background"
                  :class="form.type === 'static' ? 'border-violet-400 bg-violet-400/10 dark:bg-violet-400/5' : 'border-sidebar'"
                >
                  <input v-model="form.type" type="radio" value="static" class="sr-only" required />
                  <div class="flex items-start">
                    <div
                      class="mt-0.5 mr-3 flex h-5 w-5 items-center justify-center rounded-full border-2"
                      :class="form.type === 'static' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'"
                    >
                      <div v-if="form.type === 'static'" class="h-2 w-2 rounded-full bg-white" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <Layout class="h-4 w-4" />
                        <span class="text-sm font-medium">Static Overlay</span>
                      </div>
                      <p class="mt-1 text-sm text-muted-foreground">Persistent content with live Twitch data (follower count, stream title, etc.)</p>
                    </div>
                  </div>
                </label>

                <label
                  class="relative flex cursor-pointer items-start rounded-sm border p-4 transition-all hover:bg-background"
                  :class="form.type === 'alert' ? 'border-violet-500 bg-violet-500/10 dark:bg-violet-500/5' : 'border-sidebar'"
                >
                  <input v-model="form.type" type="radio" value="alert" class="sr-only" required />
                  <div class="flex items-start">
                    <div
                      class="mt-0.5 mr-3 flex h-5 w-5 items-center justify-center rounded-full border-2"
                      :class="form.type === 'alert' ? 'border-violet-500 bg-violet-500' : 'border-gray-400'"
                    >
                      <div v-if="form.type === 'alert'" class="h-2 w-2 rounded-full bg-white" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <Zap class="h-4 w-4" />
                        <span class="text-sm font-medium">Event Alert</span>
                      </div>
                      <p class="mt-1 text-sm text-muted-foreground">Shows temporarily when events occur (new follower, subscription, raid, etc.)</p>
                    </div>
                  </div>
                </label>
              </div>
              <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</div>
            </div>

            <!-- Event alert tips -->
            <div v-if="form.type === 'alert'" class="rounded-sm bg-sidebar p-4 text-sm">
              <strong class="text-accent-foreground/70">Event Alert tips:</strong>
              <ul class="mt-2 list-inside list-disc space-y-1 text-muted-foreground">
                <li>
                  Visit the <a class="text-violet-400 hover:underline" href="/help/conditionals#event-based-template-tags" target="_blank">Help docs</a> for all
                  event-based tags.
                </li>
                <li>Mix event tags with regular tags like <code class="rounded bg-sidebar-accent px-1">[[[followers_total]]]</code>.</li>
                <li>Keep alert overlays simple — they display briefly on screen.</li>
              </ul>
            </div>

            <div>
              <label class="flex items-center gap-2">
                <input
                  v-model="form.is_public"
                  type="checkbox"
                  class="rounded border-gray-300 text-violet-600 shadow-sm focus:border-violet-500 focus:ring-violet-500"
                />
                <span class="text-sm">Make this overlay public (others can view and fork it)</span>
              </label>
            </div>
          </div>

          <!-- Code Tab -->
          <TemplateCodeEditor
            v-if="mainTab === 'code'"
            v-model:head="form.head"
            v-model:body="form.html"
            v-model:css="form.css"
          />

          <!-- Tags Tab -->
          <div v-if="mainTab === 'tags'">
            <TemplateTagsList />
          </div>
        </div>

        <!-- Form Actions -->
        <div class="mt-6 flex justify-between">
          <Link :href="route('dashboard.index')" class="btn btn-cancel">← Back to Dashboard</Link>
          <button type="submit" :disabled="form.processing" class="btn btn-primary">Create Overlay</button>
        </div>
      </form>
    </div>

    <!-- Preview Modal -->
    <Modal :show="showPreview" @close="showPreview = false" max-width="4xl">
      <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-foreground">Overlay Preview</h3>
          <button @click="showPreview = false" class="rounded-full p-1 hover:bg-sidebar-accent">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" style="fill: currentColor">
              <path
                fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>
        <div class="rounded-sm border border-border bg-muted" style="height: 400px">
          <iframe v-if="previewHtml" :srcdoc="previewHtml" class="h-full w-full border-0" sandbox="allow-scripts" />
        </div>
        <p class="mt-4 text-sm text-muted-foreground">Tags are shown with sample data in preview.</p>
      </div>
    </Modal>

    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @dismiss="showToast = false" />
  </AppLayout>
</template>
