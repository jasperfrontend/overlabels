<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
  Check,
  ChevronDown,
  CodeIcon,
  Eye,
  FileCode2Icon,
  Flag,
  GitFork,
  ImageOff,
  PaletteIcon,
} from '@lucide/vue';
import type { AppPageProps } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

const { confirm } = useConfirm();
interface OwnerInfo {
  name: string;
  avatar: string | null;
}

interface PreviewTemplate {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  head: string | null;
  html: string | null;
  css: string | null;
  screenshot_url: string | null;
  view_count: number;
  fork_count: number;
  created_at: string;
  owner: OwnerInfo | null;
}

const props = defineProps<{
  template: PreviewTemplate;
  reportTicket: string;
}>();

const page = usePage<AppPageProps>();
const isAuthed = computed(() => !!page.props.auth?.user);

// Bounce unauthenticated users through the login flow and back to this public
// preview once they connect. Mirrors RedirectIfUnauthenticated: the login page
// stores redirect_to as url.intended, which the Twitch OAuth callback honours.
const loginUrl = computed(() => `${route('login')}?redirect_to=${encodeURIComponent(window.location.href)}`);

const csrf = computed(() => {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
});

type CopyKind = 'head' | 'body' | 'css' | 'full';

const copyFeedback = ref<string>('');
let feedbackTimer: ReturnType<typeof setTimeout> | null = null;

// The dialog is async, so the native submit can never be allowed to proceed
// inline. Always cancel it, then re-submit the form directly on accept -
// form.submit() bypasses this handler, so it will not loop.
async function confirmCopy(event: Event) {
  event.preventDefault();
  const form = event.currentTarget as HTMLFormElement;
  if (await confirm({ message: 'Copy this overlay to your account?', confirmLabel: 'Copy', tone: 'neutral' })) {
    form.submit();
  }
}

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

// Reporting. Logged-out visitors can report too, because most people who land
// on a public overlay arrived from a shared link and have no account. Their
// submission carries an email instead of an identity, plus the honeypot below
// and the server-signed ticket that backs the timing trap.
const showReport = ref(false);
const reportSent = ref(false);

const reportForm = useForm({
  reason: '',
  email: '',
  ticket: props.reportTicket,
  website: '', // honeypot: hidden from humans, filled in by bots
});

function openReport() {
  reportSent.value = false;
  reportForm.clearErrors();
  showReport.value = true;
}

function submitReport() {
  reportForm.post(route('reports.store', props.template.slug), {
    preserveScroll: true,
    onSuccess: () => {
      reportForm.reset('reason', 'email', 'website');
      reportSent.value = true;
    },
  });
}
</script>

<template>
  <Head :title="`${template.name} - Public Preview`" />
  <!-- This page renders outside AppLayout, which is where the app's single
       ConfirmDialog normally lives. Without this mount the copy confirm would
       resolve to nothing and the button would appear dead. -->
  <ConfirmDialog />
  <div class="min-h-screen bg-background text-foreground">
    <div class="mx-auto max-w-450 p-4 lg:p-6">
      <!-- Slim brand strip -->
      <div class="mb-4 flex items-center justify-between">
        <!-- Plain anchor: '/' is a Blade view, not an Inertia page. -->
        <a href="/" class="flex cursor-pointer items-center gap-2 text-sm font-bold tracking-tight text-foreground hover:text-violet-400">
          <img src="/favicon-light.svg" alt="" class="h-6 w-6 dark:hidden" />
          <img src="/favicon.png" alt="" class="hidden h-6 w-6 dark:block" />
          Overlabels
        </a>
        <Link
          v-if="isAuthed"
          :href="route('dashboard.index')"
          class="text-sm text-violet-400 hover:underline"
        >
          Dashboard
        </Link>
      </div>

      <!-- Top header strip -->
      <div class="flex flex-wrap items-center justify-between gap-3 border border-sidebar-border bg-sidebar px-4 py-3">
        <div class="flex items-center gap-3 min-w-0">
          <img
            v-if="template.owner?.avatar"
            :src="template.owner.avatar"
            alt=""
            class="h-9 w-9 shrink-0 object-cover"
          />
          <div class="min-w-0">
            <div class="truncate text-base font-medium text-foreground">{{ template.name }}</div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-foreground">
              <span>
                by <span class="text-violet-400">{{ template.owner?.name ?? 'Anonymous' }}</span>
              </span>
              <span class="inline-flex items-center gap-1">
                <Eye class="h-3.5 w-3.5 text-violet-400" />
                {{ template.view_count }}
              </span>
              <span class="inline-flex items-center gap-1">
                <GitFork class="h-3.5 w-3.5 text-violet-400" />
                {{ template.fork_count }}
              </span>
              <span class="border border-sidebar-border bg-card px-1.5 py-0.5 text-xs uppercase text-violet-400">
                {{ template.type }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button type="button" class="ovl-btn cursor-pointer">
                <Check v-if="copyFeedback" class="h-3.5 w-3.5 text-violet-400" />
                <span>{{ copyFeedback || 'Copy...' }}</span>
                <ChevronDown v-if="!copyFeedback" class="h-3.5 w-3.5" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56 rounded-none border-sidebar-border bg-card">
              <DropdownMenuLabel class="text-xs uppercase tracking-wider text-violet-400">
                Copy snippet
              </DropdownMenuLabel>
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

          <button
            v-if="template.screenshot_url"
            type="button"
            class="ovl-btn cursor-pointer"
            @click="openScreenshot"
          >
            Screenshot
          </button>

          <form
            v-if="isAuthed"
            :action="route('templates.fork', template.id)"
            method="POST"
            class="inline"
            @submit="confirmCopy"
          >
            <input type="hidden" name="_token" :value="csrf" />
            <button type="submit" class="ovl-btn-copy cursor-pointer">Copy</button>
          </form>
          <a v-else :href="loginUrl" class="ovl-btn-copy cursor-pointer">Log in to copy</a>
        </div>
      </div>

      <!-- 2-column body -->
      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Left: screenshot in 16/9 -->
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
              <p class="mt-1 text-xs text-foreground">
                The owner hasn't added a screenshot for this overlay.
              </p>
            </div>
          </div>
          <div class="flex items-center justify-between border-t border-sidebar-border px-4 py-2.5 text-sm text-foreground">
            <span class="text-violet-400">Screenshot</span>
          </div>
        </div>

        <!-- Right: raw source viewer with HEAD / BODY / CSS tabs -->
        <div class="border border-sidebar-border bg-card">
          <div class="flex aspect-video w-full overflow-hidden">
            <!-- Vertical tab strip -->
            <div class="flex flex-col bg-sidebar text-sidebar-foreground">
              <button
                v-for="tab in sourceTabs"
                :key="tab.key"
                type="button"
                @click="activeSourceTab = tab.key"
                :class="[
                  'flex cursor-pointer items-center gap-1.5 px-5 py-3 text-left text-xs uppercase tracking-wider transition-colors',
                  activeSourceTab === tab.key
                    ? 'bg-[#f8f8f8] dark:bg-[#160e21] text-foreground'
                    : 'text-foreground hover:bg-background/40 hover:text-violet-400',
                ]"
              >
                <component :is="tab.icon" :class="tab.color" class="h-3.5 w-3.5" />
                <span>{{ tab.label }}</span>
              </button>
              <div class="flex-1 bg-sidebar" />
            </div>
            <!-- Source panel -->
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

      <!--
        Description + report. The row renders even without a description: the
        report button has to be reachable on every public overlay, and the
        description is the optional half of this pairing, not the other way
        round. `ml-auto` (rather than justify-between) keeps the button hard
        right whether or not there is a description beside it.
      -->
      <div class="mt-4 flex flex-col gap-4 border border-sidebar-border bg-card p-4 sm:flex-row sm:items-start">
        <p v-if="template.description" class="text-sm text-foreground whitespace-pre-wrap">
          {{ template.description }}
        </p>
        <button type="button" class="ovl-btn shrink-0 cursor-pointer self-start sm:ml-auto" @click="openReport">
          <Flag class="h-3.5 w-3.5" />
          <span>Report</span>
        </button>
      </div>

      <!-- Report dialog -->
      <Dialog :open="showReport" @update:open="showReport = $event">
        <DialogContent class="max-w-lg sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{{ reportSent ? 'Report sent' : 'Report this overlay' }}</DialogTitle>
            <DialogDescription>
              <template v-if="reportSent">
                Your report about "{{ template.name }}" has been sent to the Overlabels admins.
              </template>
              <template v-else>
                This sends a report about "{{ template.name }}" to the Overlabels admins.
              </template>
            </DialogDescription>
          </DialogHeader>

          <form v-if="!reportSent" class="space-y-4" @submit.prevent="submitReport">
            <div>
              <label for="report-reason" class="mb-1.5 block text-sm font-medium text-foreground">
                Why are you reporting it?
              </label>
              <textarea
                id="report-reason"
                v-model="reportForm.reason"
                rows="5"
                maxlength="2000"
                required
                placeholder="Be specific about what is wrong with this overlay."
                class="w-full border border-sidebar-border bg-background p-2.5 text-sm text-foreground focus:border-violet-400 focus:outline-none"
              />
              <p v-if="reportForm.errors.reason" class="mt-1 text-xs text-destructive">
                {{ reportForm.errors.reason }}
              </p>
            </div>

            <div v-if="isAuthed" class="text-sm text-foreground">
              Reporting as <span class="text-violet-400">{{ page.props.auth?.user?.name }}</span>.
            </div>
            <div v-else>
              <label for="report-email" class="mb-1.5 block text-sm font-medium text-foreground">
                Your email address
              </label>
              <input
                id="report-email"
                v-model="reportForm.email"
                type="email"
                maxlength="255"
                required
                placeholder="you@example.com"
                class="w-full border border-sidebar-border bg-background p-2.5 text-sm text-foreground focus:border-violet-400 focus:outline-none"
              />
              <p class="mt-1 text-xs text-foreground">
                Stored with the report so an admin can follow up, and for nothing else. See the
                <a href="/privacy" target="_blank" rel="noopener" class="cursor-pointer text-violet-400 hover:underline">
                  privacy policy</a>.
              </p>
              <p v-if="reportForm.errors.email" class="mt-1 text-xs text-destructive">
                {{ reportForm.errors.email }}
              </p>
            </div>

            <!--
              Honeypot. Hidden from humans and from screen readers; bots that
              scrape the form and fill every field give themselves away. The
              server discards those submissions silently.
            -->
            <div class="hidden" aria-hidden="true">
              <label for="report-website">Website</label>
              <input id="report-website" v-model="reportForm.website" type="text" tabindex="-1" autocomplete="off" />
            </div>

            <DialogFooter>
              <div class="flex w-full flex-col gap-3">
                <div class="flex items-center justify-end gap-2">
                  <button type="button" class="ovl-btn cursor-pointer" @click="showReport = false">Cancel</button>
                  <button
                    type="submit"
                    class="ovl-btn-copy cursor-pointer disabled:opacity-50"
                    :disabled="reportForm.processing"
                  >
                    {{ reportForm.processing ? 'Sending...' : 'Submit report' }}
                  </button>
                </div>
                <p class="text-xs text-foreground">
                  Knowingly filing a false report can get your own account banned. Only submit this if you are sure.
                </p>
              </div>
            </DialogFooter>
          </form>

          <DialogFooter v-else>
            <button type="button" class="ovl-btn cursor-pointer" @click="showReport = false">Close</button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Fullscreen screenshot dialog -->
      <Dialog :open="showScreenshot" @update:open="showScreenshot = $event">
        <DialogContent class="max-w-[95vw] max-h-[95vh] w-auto p-2 sm:max-w-[95vw]">
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
              <button type="button" class="ovl-btn cursor-pointer" @click="showScreenshot = false">Close</button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>

<style scoped>
.ovl-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 500;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border: 1px solid var(--sidebar-border);
  background: var(--card);
  color: var(--foreground);
  transition: color 120ms ease, background 120ms ease, border-color 120ms ease;
}

.ovl-btn:hover {
  color: var(--color-violet-400, #a78bfa);
  border-color: var(--color-violet-400, #a78bfa);
}

.ovl-btn-copy {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.85rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  border: 1px solid var(--color-violet-400, #a78bfa);
  background: var(--color-violet-400, #a78bfa);
  color: #000;
  transition: background 120ms ease, border-color 120ms ease;
}

.ovl-btn-copy:hover {
  background: var(--color-violet-300, #c4b5fd);
  border-color: var(--color-violet-300, #c4b5fd);
}
</style>
