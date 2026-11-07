<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { VisuallyHidden } from 'reka-ui';
import {
  BellRing,
  Check,
  ChevronDown,
  CodeIcon,
  FileCode2Icon,
  FileText,
  ImageOff,
  ListIcon,
  PaletteIcon,
  PlugZap,
  SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { AdminTemplate } from '@/types';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';

const { formatDateTime } = useDateTimeFormat();

// The source and meta the public preview shows. The admin route serialises the
// whole model, so these arrive whether the template is public or private.
interface AdminTemplateDetail extends AdminTemplate {
  description: string | null;
  head: string | null;
  html: string | null;
  css: string | null;
  screenshot_url: string | null;
}

// The same OverlayShareService document the public preview renders
// (overlay/public-preview.vue). Keep these shapes in step with that page.
interface ShareControl {
  key: string;
  tag: string;
  label: string | null;
  description: string | null;
  type: string;
  value: string | null;
  config: Record<string, unknown> | null;
  used: boolean;
}

interface ShareServiceControl {
  key: string;
  tag: string;
  label: string | null;
  type: string | null;
  known: boolean;
}

interface ShareService {
  service: string;
  label: string;
  controls: ShareServiceControl[];
}

interface ShareTrigger {
  source: string;
  label: string;
  condition_type: string | null;
  condition_value: string | null;
  duration_ms: number;
}

interface ShareAlert {
  sound_url: string | null;
  tts_message: string | null;
  tts_delay_ms: number | null;
  chat_message: string | null;
  triggers: ShareTrigger[];
}

interface ShareDocument {
  controls: ShareControl[];
  services: ShareService[];
  lists: Array<{ slug: string; tag: string }>;
  dataTags: string[];
  alert: ShareAlert | null;
  blocks: string[];
}

const props = defineProps<{
  template: AdminTemplateDetail;
  share: ShareDocument;
  forksCount: number;
  controlsCount: number;
  eventMappingsCount: number;
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Templates', href: route('admin.templates.index') },
  { title: props.template.name, href: route('admin.templates.show', props.template.id) },
];

const visibilityForm = useForm({ is_public: props.template.is_public });

function toggleVisibility() {
  visibilityForm.is_public = !visibilityForm.is_public;
  visibilityForm.patch(route('admin.templates.update', props.template.id));
}

const showDeleteConfirm = ref(false);

function submitDelete() {
  router.delete(route('admin.templates.destroy', props.template.id));
}

// --- Ripped from overlay/public-preview.vue ---

const hasRequirements = computed(() => props.share.services.length > 0 || props.share.lists.length > 0);

const hasShareDetail = computed(
  () => props.share.controls.length > 0 || hasRequirements.value || props.share.alert !== null || props.share.dataTags.length > 0,
);

// An expression control stores its formula in config.expression rather than in
// value, so it needs pulling out separately or the row reads as empty.
function controlFormula(control: ShareControl): string | null {
  const expression = control.config?.expression;
  return typeof expression === 'string' && expression.length > 0 ? expression : null;
}

function triggerCondition(trigger: ShareTrigger): string {
  if (!trigger.condition_type || trigger.condition_type === 'any') return 'any';
  return `${trigger.condition_type.replace(/_/g, ' ')} ${trigger.condition_value ?? ''}`.trim();
}

type CopyKind = 'head' | 'body' | 'css' | 'full';

const copyFeedback = ref<string>('');
let feedbackTimer: ReturnType<typeof setTimeout> | null = null;

function buildCompleteTemplate(): string {
  const head = props.template.head ?? '';
  const html = props.template.html ?? '';
  const css = props.template.css ?? '';
  const title = props.template.name ?? 'Overlay';

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${title}</title>
  <style>
${css}
  </style>
${head}
</head>
<body>
${html}
</body>
</html>
`;
}

const copyLabels: Record<CopyKind, string> = {
  head: 'HEAD',
  body: 'BODY',
  css: 'CSS',
  full: 'Complete template',
};

function copy(kind: CopyKind) {
  let value = '';
  switch (kind) {
    case 'head':
      value = props.template.head ?? '';
      break;
    case 'body':
      value = props.template.html ?? '';
      break;
    case 'css':
      value = props.template.css ?? '';
      break;
    case 'full':
      value = buildCompleteTemplate();
      break;
  }

  navigator.clipboard.writeText(value).then(() => {
    copyFeedback.value = `Copied ${copyLabels[kind]}!`;
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
      copyFeedback.value = '';
    }, 1500);
  });
}

const showScreenshot = ref(false);
function openScreenshot() {
  if (!props.template.screenshot_url) return;
  showScreenshot.value = true;
}

type SourceTab = 'head' | 'html' | 'css';

const sourceTabs: Array<{ key: SourceTab; label: string; icon: typeof FileCode2Icon; color: string }> = [
  { key: 'head', label: 'HEAD', icon: FileCode2Icon, color: 'text-pink-500 dark:text-pink-400' },
  { key: 'html', label: 'BODY', icon: CodeIcon, color: 'text-cyan-500 dark:text-cyan-400' },
  { key: 'css', label: 'CSS', icon: PaletteIcon, color: 'text-lime-500 dark:text-lime-400' },
];

const activeSourceTab = ref<SourceTab>('html');

const activeSource = computed(() => {
  const v = props.template[activeSourceTab.value];
  return typeof v === 'string' && v.length > 0 ? v : '';
});
</script>

<template>
  <Head
    ><title>Admin — {{ template.name }}</title></Head
  >
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold">{{ template.name }}</h1>
          <div class="font-mono text-sm text-muted-foreground">{{ template.slug }}</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{{ template.type }}</Badge>
          <Badge :variant="template.is_public ? 'default' : 'secondary'">{{ template.is_public ? 'public' : 'private' }} </Badge>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button type="button" class="btn btn-sm btn-chill">
                <Check v-if="copyFeedback" class="h-3.5 w-3.5 text-violet-400" />
                <span>{{ copyFeedback || 'What to copy?' }}</span>
                <ChevronDown v-if="!copyFeedback" class="h-3.5 w-3.5" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56 rounded-none border-sidebar-border bg-card">
              <DropdownMenuLabel class="text-xs tracking-wider text-violet-400 uppercase"> Copy snippet </DropdownMenuLabel>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('head')">
                HEAD
                <span class="ml-auto text-xs text-foreground">&lt;head&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('body')">
                BODY
                <span class="ml-auto text-xs text-foreground">&lt;body&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('css')">
                CSS
                <span class="ml-auto text-xs text-foreground">&lt;style&gt;</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem class="cursor-pointer rounded-none focus:bg-sidebar focus:text-violet-400" @click="copy('full')">
                Complete template
                <span class="ml-auto text-xs text-foreground">.html</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          <button v-if="template.screenshot_url" type="button" class="btn btn-sm btn-secondary" @click="openScreenshot">View screenshot</button>
        </div>
      </div>

      <Card>
        <CardContent class="grid grid-cols-2 gap-4 pt-4 text-sm sm:grid-cols-3">
          <div>
            <span class="text-muted-foreground">Owner</span>
            <div>
              <a v-if="template.owner" :href="route('admin.users.show', template.owner.id)" class="hover:underline">{{ template.owner.name }}</a>
              <span v-else>—</span>
            </div>
          </div>
          <div>
            <span class="text-muted-foreground">Controls</span>
            <div>{{ controlsCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Views</span>
            <div>{{ template.view_count }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Forks</span>
            <div>{{ forksCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Event Mappings</span>
            <div>{{ eventMappingsCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Created</span>
            <div>{{ formatDateTime(template.created_at) }}</div>
          </div>
          <div v-if="template.description" class="col-span-full">
            <span class="text-muted-foreground">Description</span>
            <p class="whitespace-pre-wrap">{{ template.description }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Screenshot + source, as on the public preview -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="border border-sidebar-border bg-card">
          <div class="aspect-video w-full overflow-hidden bg-sidebar">
            <img
              v-if="template.screenshot_url"
              :src="template.screenshot_url"
              :alt="`${template.name} screenshot`"
              class="h-full w-full cursor-pointer object-cover transition-opacity hover:opacity-90"
              @click="openScreenshot"
            />
            <div v-else class="flex h-full w-full flex-col items-center justify-center px-6 text-center">
              <ImageOff class="mb-3 h-10 w-10 text-violet-400" />
              <p class="text-sm text-foreground">No screenshot yet</p>
              <p class="mt-1 text-xs text-foreground">The owner hasn't added a screenshot for this overlay.</p>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-sidebar-border px-4 py-2.5 text-sm text-foreground">
            <span class="text-violet-400">Screenshot</span>
          </div>
        </div>

        <div class="border border-sidebar-border bg-card">
          <div class="flex aspect-video w-full overflow-hidden">
            <div class="flex flex-col bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in sourceTabs"
                :key="tab.key"
                type="button"
                @click="activeSourceTab = tab.key"
                :class="[
                  'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs tracking-wider uppercase transition-colors',
                  activeSourceTab === tab.key
                    ? 'bg-[#f8f8f8] text-foreground dark:bg-[#160e21]'
                    : 'text-foreground hover:bg-background/40 hover:text-violet-400',
                ]"
              >
                <component :is="tab.icon" :class="tab.color" class="h-3.5 w-3.5" />
                <span>{{ tab.label }}</span>
              </button>
              <div class="flex-1 bg-sidebar" />
            </div>
            <div class="relative flex-1 overflow-hidden">
              <pre
                class="h-full w-full overflow-auto bg-white p-4 text-xs leading-relaxed text-gray-700 dark:bg-[#160e21] dark:text-accent-foreground"
              ><code v-if="activeSource">{{ activeSource }}</code><code v-else class="text-foreground">// no {{ activeSourceTab.toUpperCase() }} content</code></pre>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-sidebar-border px-4 py-2.5 text-sm text-foreground">
            <span class="text-violet-400">Source</span>
          </div>
        </div>
      </div>

      <!-- Controls, requirements, alert wiring and data tags, as on the public preview -->
      <div v-if="hasShareDetail" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div v-if="share.controls.length" class="border border-sidebar-border bg-card lg:col-span-2">
          <div class="flex items-center gap-2 border-b border-sidebar-border px-4 py-2.5 text-sm">
            <SlidersHorizontal class="h-3.5 w-3.5 text-violet-400" />
            <span class="text-violet-400">Controls</span>
            <span class="ml-auto text-xs text-foreground">{{ share.controls.length }}</span>
          </div>
          <div class="p-4">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="text-foreground">
                  <tr class="border-b border-sidebar-border">
                    <th class="py-1.5 pr-3 font-medium tracking-wider uppercase">Tag</th>
                    <th class="py-1.5 pr-3 font-medium tracking-wider uppercase">Type</th>
                    <th class="py-1.5 pr-3 font-medium tracking-wider uppercase">Label</th>
                    <th class="py-1.5 font-medium tracking-wider uppercase">Value</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="control in share.controls" :key="control.key">
                    <tr class="border-b border-sidebar-border/50" :class="{ 'border-0': controlFormula(control) }">
                      <td class="py-1.5 pr-3 align-top">
                        <code class="text-violet-400">[[[{{ control.tag }}]]]</code>
                        <span v-if="!control.used" class="ml-1 text-foreground opacity-70">(unused)</span>
                      </td>
                      <td class="py-1.5 pr-3 align-top text-foreground">{{ control.type }}</td>
                      <td class="py-1.5 pr-3 align-top text-foreground">{{ control.label }}</td>
                      <td class="py-1.5 align-top text-foreground">
                        <code v-if="control.value">{{ control.value }}</code>
                      </td>
                    </tr>
                    <tr v-if="controlFormula(control)" class="border-b border-sidebar-border/50 last:border-0">
                      <td colspan="4" class="pb-1.5 align-top">
                        <code class="block overflow-x-auto bg-sidebar px-1.5 py-1 text-xs text-foreground">
                          {{ controlFormula(control) }}
                        </code>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div v-if="hasRequirements" class="border border-sidebar-border bg-card">
          <div class="flex items-center gap-2 border-b border-sidebar-border px-4 py-2.5 text-sm">
            <PlugZap class="h-3.5 w-3.5 text-violet-400" />
            <span class="text-violet-400">Requirements</span>
          </div>
          <div class="space-y-4 p-4">
            <div v-if="share.services.length">
              <div v-for="service in share.services" :key="service.service" class="mb-3 last:mb-0">
                <div class="mb-1.5 text-xs tracking-wider text-violet-400 uppercase">
                  {{ service.label }}
                </div>
                <ul class="space-y-1">
                  <li v-for="control in service.controls" :key="control.tag" class="flex flex-wrap items-baseline gap-x-2 text-xs">
                    <code class="text-violet-400">[[[{{ control.tag }}]]]</code>
                    <span v-if="control.known" class="text-foreground">{{ control.label }}</span>
                    <span v-else class="text-destructive"> not provided by {{ service.label }} - likely a typo </span>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="share.lists.length">
              <div class="mb-1.5 flex items-center gap-1.5 text-xs tracking-wider text-violet-400 uppercase">
                <ListIcon class="h-3.5 w-3.5" />
                Lists
              </div>
              <ul class="space-y-1">
                <li v-for="list in share.lists" :key="list.slug" class="text-xs">
                  <code class="text-violet-400">[[[{{ list.tag }}]]]</code>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div v-if="share.alert" class="border border-sidebar-border bg-card">
          <div class="flex items-center gap-2 border-b border-sidebar-border px-4 py-2.5 text-sm">
            <BellRing class="h-3.5 w-3.5 text-violet-400" />
            <span class="text-violet-400">Alert behaviour</span>
          </div>
          <div class="space-y-3 p-4 text-sm text-foreground">
            <ul class="space-y-1">
              <li v-if="share.alert.sound_url">Plays a sound when it fires.</li>
              <li v-if="share.alert.tts_message">
                Speaks via text to speech<span v-if="share.alert.tts_delay_ms"> after {{ share.alert.tts_delay_ms }}ms</span>:
                <code class="bg-sidebar px-1 py-0.5 text-xs">{{ share.alert.tts_message }}</code>
              </li>
              <li v-if="share.alert.chat_message">
                Posts to chat:
                <code class="bg-sidebar px-1 py-0.5 text-xs">
                  {{ share.alert.chat_message }}
                </code>
              </li>
              <li v-if="!share.alert.sound_url && !share.alert.tts_message && !share.alert.chat_message">
                No sound, speech or chat message. This alert is purely visual.
              </li>
            </ul>

            <div v-if="share.alert.triggers.length">
              <div class="mb-1.5 text-xs tracking-wider text-violet-400 uppercase">Triggers</div>
              <ul class="space-y-1 text-xs">
                <li v-for="(trigger, i) in share.alert.triggers" :key="i">
                  <span class="text-foreground">{{ trigger.label }}</span>
                  <span class="text-foreground opacity-70"> - {{ triggerCondition(trigger) }}, {{ trigger.duration_ms }}ms </span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div v-if="share.dataTags.length" class="border border-sidebar-border bg-card lg:col-span-2">
          <div class="flex items-center gap-2 border-b border-sidebar-border px-4 py-2.5 text-sm">
            <FileText class="h-3.5 w-3.5 text-violet-400" />
            <span class="text-violet-400">Live data tags</span>
            <span class="ml-auto text-xs text-foreground">{{ share.dataTags.length }}</span>
          </div>
          <div class="p-4">
            <div class="flex flex-wrap gap-1.5">
              <code v-for="tag in share.dataTags" :key="tag" class="border border-sidebar-border bg-sidebar px-1.5 py-0.5 text-xs text-violet-400">
                [[[{{ tag }}]]]
              </code>
            </div>
          </div>
        </div>
      </div>

      <Card class="max-w-3xl">
        <CardHeader>
          <CardTitle>Visibility</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <p class="text-sm text-muted-foreground">Toggle whether this template is publicly visible.</p>
          <Button variant="outline" size="sm" @click="toggleVisibility" :disabled="visibilityForm.processing">
            Make {{ template.is_public ? 'private' : 'public' }}
          </Button>
        </CardContent>
      </Card>

      <Card class="max-w-3xl border-destructive">
        <CardHeader>
          <CardTitle class="text-destructive">Danger Zone</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <p class="text-sm text-muted-foreground">Permanently delete this template. Cannot be undone.</p>
          <div v-if="!showDeleteConfirm">
            <Button variant="destructive" size="sm" @click="showDeleteConfirm = true">Delete Template</Button>
          </div>
          <div v-else class="flex gap-2">
            <Button variant="destructive" size="sm" @click="submitDelete">Yes, delete</Button>
            <Button variant="outline" size="sm" @click="showDeleteConfirm = false">Cancel</Button>
          </div>
        </CardContent>
      </Card>

      <Dialog :open="showScreenshot" @update:open="showScreenshot = $event">
        <DialogContent class="max-h-[95vh] w-auto max-w-[95vw] p-2 sm:max-w-[95vw]">
          <VisuallyHidden>
            <DialogTitle>Screenshot preview</DialogTitle>
          </VisuallyHidden>
          <img
            v-if="template.screenshot_url"
            :src="template.screenshot_url"
            :alt="`${template.name} screenshot`"
            class="max-h-[80vh] max-w-[90vw] object-contain"
          />
          <DialogFooter>
            <div class="flex w-full items-center justify-between gap-2">
              <div class="text-sm text-foreground">
                Screenshot: <span class="text-violet-400">{{ template.name }}</span>
              </div>
              <button type="button" class="btn btn-sm btn-secondary" @click="showScreenshot = false">Close</button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
