<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useLinkWarning } from '@/composables/useLinkWarning';
import RekaToast from '@/components/RekaToast.vue';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import {
  AlertCircle,
  CheckCircle,
  ChevronDown,
  ChevronRight,
  Code,
  ExternalLinkIcon,
  FileWarningIcon,
  Keyboard,
  Palette,
  Play,
  RefreshCcwDot,
  Save,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Codemirror } from 'vue-codemirror';

interface Props {
  existingTemplate: {
    html: string;
    css: string;
  };
  availableTags: Array<{
    tag_name: string;
    display_name: string;
    description: string;
    data_type: string;
    category: string;
    sample_data?: string;
  }>;
}

const props = defineProps<Props>();
const { triggerLinkWarning } = useLinkWarning();

// State - Uses templates from backend (either existing custom templates or default from service)
const htmlTemplate = ref(props.existingTemplate.html || '');
const cssTemplate = ref(props.existingTemplate.css || '');
const defaultTemplates = ref<{ html: string; css: string } | null>(null);
const isSaving = ref(false);
const validationResults = ref<any>(null);
const isValidating = ref(false);
const isLoadingDefaults = ref(false);
const showConditionalExamples = ref(false);
const showActionsDropdown = ref(false);
const htmlEditor = ref();
const cssEditor = ref();

// Toast state
const toastMessage = ref('');
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('success');
const showToast = ref(false);

// Hide toast
const hideToast = () => {
  showToast.value = false;
};

watch(toastMessage, () => {
  setTimeout(() => {
    showToast.value = false;
  }, 5000);
});

// Theme detection
const isDark = ref(document.documentElement.classList.contains('dark'));

// Load default templates from the centralized service when the component mounts
onMounted(async () => {
  await loadDefaultTemplates();
});

/**
 * Load default templates from the DefaultTemplateProviderService
 * This ensures we always have the latest default templates from the actual files
 */
const loadDefaultTemplates = async () => {
  try {
    isLoadingDefaults.value = true;

    const response = await fetch('/api/template/defaults', {
      method: 'GET',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (response.ok) {
      const result = await response.json();
      defaultTemplates.value = result.templates;

      // If current templates are empty, use the defaults
      if (!htmlTemplate.value && !cssTemplate.value) {
        // @ts-expect-error htmlTemplate.value is not properly defined
        htmlTemplate.value = defaultTemplates?.value?.html;
        // @ts-expect-error cssTemplate.value is not properly defined
        cssTemplate.value = defaultTemplates?.value?.css;
      }
    } else {
      console.error('Failed to load default templates from service');
    }
  } catch (error) {
    console.error('Error loading default templates:', error);
  } finally {
    isLoadingDefaults.value = false;
  }
};

// CodeMirror extensions
const htmlExtensions = computed(() => [
  html(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
  }),
  ...(isDark.value ? [oneDark] : []),
]);

const cssExtensions = computed(() => [
  css(),
  EditorView.theme({
    '&': { fontSize: '14px' },
    '.cm-content': { padding: '16px' },
    '.cm-focused .cm-cursor': { borderLeftColor: '#3b82f6' },
  }),
  ...(isDark.value ? [oneDark] : []),
]);

// Computed preview HTML
const previewHtml = computed(() => {
  let html = htmlTemplate.value;

  if (cssTemplate.value.trim()) {
    if (html.includes('<style>')) {
      html = html.replace(/<style[^>]*>[\s\S]*?<\/style>/g, `<style>${cssTemplate.value}</style>`);
    } else {
      html = html.replace('[[[style]]]', `<style>${cssTemplate.value}</style>`);
    }
  }

  return html;
});

// Grouped template tags by category
const tagsByCategory = computed(() => {
  const grouped: Record<string, typeof props.availableTags> = {};

  props.availableTags.forEach((tag) => {
    if (!grouped[tag.category]) {
      grouped[tag.category] = [];
    }
    grouped[tag.category].push(tag);
  });

  return grouped;
});

const copyTag = async (tagName: string) => {
  const tag = `[[[${tagName}]]]`;
  try {
    await navigator.clipboard.writeText(tag);
    showToast.value = true;
    toastMessage.value = `Copied tag to clipboard: ${tagName}`;
    toastType.value = 'success';
  } catch (err) {
    showToast.value = true;
    toastMessage.value = 'Failed to copy tag to clipboard!';
    toastType.value = 'error';
  }
};

const validateTemplate = async () => {
  isValidating.value = true;
  validationResults.value = null;

  try {
    const response = await fetch(route('api.template.validate'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        html_template: htmlTemplate.value,
        css_template: cssTemplate.value,
      }),
    });

    validationResults.value = await response.json();
  } catch (error) {
    console.error('Validation error:', error);
    showToast.value = true;
    toastMessage.value = 'Failed to validate template!';
    toastType.value = 'error';
    validationResults.value = {
      success: false,
      errors: ['Failed to validate template'],
    };
  } finally {
    isValidating.value = false;
  }
};

const saveTemplate = async () => {
  isSaving.value = true;

  try {
    const response = await fetch(route('api.template.save'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        overlay_slug: props.overlayHash.slug,
        html_template: htmlTemplate.value,
        css_template: cssTemplate.value,
      }),
    });

    const result = await response.json();

    if (result.success) {
      showToast.value = true;
      toastMessage.value = 'Template saved!';
      toastType.value = 'success';
    } else {
      showToast.value = true;
      toastMessage.value = 'Failed to save template!';
      toastType.value = 'error';
      console.error('Save error:', result.message || 'Failed to save template');
    }
  } catch (error) {
    console.error('Save error:', error);
    showToast.value = true;
    toastMessage.value = 'Failed to save template!';
    toastType.value = 'error';
  } finally {
    isSaving.value = false;
    await validateTemplate();
  }
};

const openExternalLink = (link: any, target: string) => {
  window.open(link, target);
};

const resetTofDefault = async () => {
  if (confirm('Are you sure you want to reset to the default template? This will overwrite your current changes.')) {
    if (defaultTemplates.value) {
      htmlTemplate.value = defaultTemplates.value.html;
      cssTemplate.value = defaultTemplates.value.css;
      showToast.value = true;
      toastMessage.value = 'Reset to default template!';
      toastType.value = 'success';
    } else {
      // Reload defaults if not loaded yet
      await loadDefaultTemplates();
      if (defaultTemplates.value) {
        htmlTemplate.value = defaultTemplates?.value?.html;
        cssTemplate.value = defaultTemplates?.value?.css;
        showToast.value = true;
        toastMessage.value = 'Reset to default template!';
        toastType.value = 'success';
      }
    }
  }
};



// Watch for theme changes
watch(
  () => document.documentElement.classList.contains('dark'),
  (newDark) => {
    isDark.value = newDark;
  },
);

// Initialize keyboard shortcuts
const { register, getAllShortcuts } = useKeyboardShortcuts();
const showKeyboardShortcuts = ref(false);

// Register keyboard shortcuts
onMounted(() => {
  // Save template with Ctrl+S
  register(
    'save-template',
    'ctrl+s',
    () => {
      saveTemplate();
    },
    { description: 'Save template' },
  );

  // Preview with Ctrl+P
  register(
    'preview-live',
    'ctrl+p',
    () => {
      triggerLinkWarning(
        () => openExternalLink(`/overlay/${props.overlayHash.slug}/${props.overlayHash.hash_key}`, '_blank'),
        'Remember: DO NOT EVER show this link on stream! Treat it like a password. If you think it has leaked, revoke or regenerate the hash immediately.',
      );
    },
    { description: 'Preview in new tab' },
  );

  // Validate template with Ctrl+V
  register(
    'validate-template',
    'ctrl+v',
    () => {
      validateTemplate();
    },
    { description: 'Validate template' },
  );

  // Toggle keyboard shortcuts display with Ctrl+K
  register(
    'toggle-shortcuts',
    'ctrl+k',
    () => {
      showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    },
    { description: 'Show keyboard shortcuts' },
  );
});

// Get all keyboard shortcuts for display
const keyboardShortcutsList = computed(() => getAllShortcuts());
</script>

<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <!-- Toast Component -->
    <RekaToast v-if="showToast" :message="toastMessage" :type="toastType" @update:visible="hideToast" />
    <!-- Left sidebar with template tags and actions -->
    <div class="space-y-6 lg:col-span-1">
      <!-- Action buttons -->
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="flex items-center gap-2 text-base">
            Actions
            <!-- Keyboard shortcuts indicator -->
            <Button @click="showKeyboardShortcuts = !showKeyboardShortcuts" variant="ghost" size="sm" class="ml-auto h-6 w-6 p-0">
              <Keyboard class="h-4 w-4" />
            </Button>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <!-- Primary action: Save -->
          <Button @click="saveTemplate" :disabled="isSaving" class="mb-3 w-full cursor-pointer bg-green-600 text-white shadow hover:bg-green-700">
            <RefreshCcwDot v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
            <Save v-else class="mr-2 h-4 w-4" />
            {{ isSaving ? 'Saving...' : 'Save' }}
            <span class="ml-1 rounded bg-black/10 px-1 text-xs">⌃S</span>
          </Button>

          <!-- Secondary actions dropdown -->
          <div class="relative">
            <Button @click="showActionsDropdown = !showActionsDropdown" variant="outline" class="w-full cursor-pointer justify-between">
              <span>More Actions</span>
              <ChevronDown :class="['h-4 w-4 transition-transform', showActionsDropdown ? 'rotate-180' : '']" />
            </Button>

            <div v-if="showActionsDropdown" class="absolute z-10 mt-1 w-full rounded-md border bg-white shadow-lg dark:bg-gray-800">
              <Button
                @click="
                  triggerLinkWarning(
                    () => openExternalLink(`/overlay/${props.overlayHash.slug}/${props.overlayHash.hash_key}`, '_blank'),
                    'Remember: DO NOT EVER show this link on stream! Treat it like a password. If you think it has leaked, revoke or regenerate the hash immediately.',
                  );
                  showActionsDropdown = !showActionsDropdown;
                "
                variant="ghost"
                class="w-full cursor-pointer justify-start"
              >
                <ExternalLinkIcon class="mr-2 h-4 w-4" />
                Preview in New Tab
                <span class="ml-auto rounded bg-black/10 px-1 text-xs">⌃P</span>
              </Button>

              <Button
                @click="
                  validateTemplate();
                  showActionsDropdown = !showActionsDropdown;
                "
                :disabled="isValidating"
                variant="ghost"
                class="w-full cursor-pointer justify-start"
              >
                <RefreshCcwDot v-if="isValidating" class="mr-2 h-4 w-4 animate-spin" />
                <CheckCircle v-else class="mr-2 h-4 w-4" />
                {{ isValidating ? 'Validating...' : 'Validate' }}
                <span class="ml-auto rounded bg-black/10 px-1 text-xs">⌃V</span>
              </Button>


              <div class="my-1 border-t"></div>

              <Button
                @click="
                  resetToDefault();
                  showActionsDropdown = !showActionsDropdown;
                "
                :disabled="isLoadingDefaults"
                variant="ghost"
                class="w-full cursor-pointer justify-start text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
              >
                <RefreshCcwDot v-if="isLoadingDefaults" class="mr-2 h-4 w-4 animate-spin" />
                <FileWarningIcon v-else class="mr-2 h-4 w-4" />
                {{ isLoadingDefaults ? 'Resetting...' : 'Reset to Default' }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Template validation results -->
      <Card v-if="validationResults">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-base">
            <AlertCircle class="h-4 w-4" />
            Validation Results
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="validationResults.success" class="text-sm text-green-600 dark:text-green-400">✅ Template is valid!</div>
          <div v-else class="space-y-2">
            <div v-if="validationResults.errors?.length" class="text-sm text-red-600 dark:text-red-400">
              <strong>Errors:</strong>
              <ul class="list-inside list-disc">
                <li v-for="error in validationResults.errors" :key="error">{{ error }}</li>
              </ul>
            </div>
            <div v-if="validationResults.warnings?.length" class="text-sm text-yellow-600 dark:text-yellow-400">
              <strong>Warnings:</strong>
              <ul class="list-inside list-disc">
                <li v-for="warning in validationResults.warnings" :key="warning">{{ warning }}</li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Available template tags -->
      <Card>
        <CardHeader>
          <div class="flex justify-between items-center">
            <CardTitle class="text-base">Template Tags</CardTitle>
            <a
              title="Read more about Conditional Template Tags"
              class="ml-2 pt-0.5 px-2 h-6 text-sm cursor-pointer flex bg-sidebar-accent hover:bg-sidebar-accent/70 rounded"
              href="https://help.overlabels.com/template-builder/conditional-template-tags/#core"
              target="_blank"
            >
              Conditional Tags
              <ExternalLinkIcon class="h-[14px] w-[14px] ml-1 mt-0.5" />
            </a>
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Click to copy template tags to your clipboard</p>
        </CardHeader>
        <CardContent class="max-h-[calc(100vh-300px)] space-y-4 overflow-y-auto">
          <!-- Conditional Template Tags - Collapsible -->
          <div>
            <Button
              @click.prevent="showConditionalExamples = !showConditionalExamples"
              variant="ghost"
              class="mb-2 h-auto w-full cursor-pointer justify-between border p-0"
            >
              <h4 class="flex items-center p-2 px-0 text-sm font-medium">Conditional Template Tags</h4>
              <ChevronRight :class="['h-4 w-4 transition-transform', showConditionalExamples ? 'rotate-90' : '']" />
            </Button>

            <div v-if="showConditionalExamples" class="space-y-3 text-xs">
              <div class="space-y-1 rounded border border-dashed border-gray-200 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-1 font-medium">Basic if statement</div>
                <code class="text-muted-foreground">
                  [[[if:channel_is_branded]]]<br />
                  <span style="text-indent: 1rem; display: inline-block">Woah branded content!</span> <br />
                  [[[endif]]]
                </code>
              </div>

              <div class="space-y-1 rounded border border-dashed border-gray-200 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-1 font-medium">if/else statement</div>
                <code class="text-muted-foreground">
                  [[[if:channel_is_branded]]]<br />
                  <span style="text-indent: 1rem; display: inline-block">Woah branded content!</span> <br />
                  [[[else]]]<br />
                  <span style="text-indent: 1rem; display: inline-block">Nah, no sponsor today lol</span> <br />
                  [[[endif]]]
                </code>
              </div>

              <div class="space-y-1 rounded border border-dashed border-gray-200 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-1 font-medium">Numeric comparison</div>
                <code class="text-muted-foreground">
                  [[[if:followers_total >= 2000]]]<br />
                  <span style="text-indent: 1rem; display: inline-block">WOW! Over 2000 followers!</span> <br />
                  [[[else]]]<br />
                  <span style="text-indent: 1rem; display: inline-block">Help us reach 2000 followers!</span> <br />
                  [[[endif]]]
                </code>
                <div class="mt-2 flex items-center gap-2">
                  <span class="rounded bg-chart-2 px-1 py-0 text-xs">==</span>
                  <span class="rounded bg-chart-2 px-1 py-0 text-xs">!=</span>
                  <span class="rounded bg-chart-2 px-1 py-0 text-xs">>=</span>
                  <span class="rounded bg-chart-2 px-1 py-0 text-xs">></span>
                  <span class="rounded bg-chart-2 px-1 py-0 text-xs"><=</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Regular template tags by category -->
          <div v-for="(tags, category) in tagsByCategory" :key="category">
            <h4 class="mb-2 font-medium">{{ category }}</h4>
            <div class="space-y-1">
              <Button
                v-for="tag in tags"
                :key="tag.tag_name"
                @click="copyTag(tag.tag_name)"
                variant="ghost"
                class="h-auto w-full justify-start py-1 px-0 text-xs"
                :title="tag.description"
              >
                <code class="text-xs">[[[{{ tag.tag_name }}]]]</code>
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Editor Area -->
    <div class="space-y-6 lg:col-span-3">
      <Tabs default-value="html" class="w-full">
        <TabsList class="grid w-full grid-cols-5 gap-1">
          <TabsTrigger value="html" class="flex cursor-pointer items-center gap-2 hover:bg-accent dark:hover:bg-accent">
            <Code class="h-4 w-4" />
            HTML
          </TabsTrigger>
          <TabsTrigger value="css" class="flex cursor-pointer items-center gap-2 hover:bg-accent dark:hover:bg-accent">
            <Palette class="h-4 w-4" />
            CSS
          </TabsTrigger>
        </TabsList>
        <TabsContent value="html" class="mt-4">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">HTML Template</CardTitle>
              <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Omit <code>doctype</code>, <code>html</code>, <code>head</code> and <code>body</code>.
              </p>
            </CardHeader>
            <CardContent>
              <div class="overflow-hidden rounded-lg border">
                <Codemirror
                  ref="htmlEditor"
                  v-model="htmlTemplate"
                  :style="{ height: '500px' }"
                  :autofocus="true"
                  :indent-with-tab="true"
                  :tab-size="2"
                  :extensions="htmlExtensions"
                  placeholder="Enter your HTML template here... Use [[[tag_name]]] for dynamic content"
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="css" class="mt-4">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">CSS Styles</CardTitle>
              <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Style your overlay with CSS. Template tags work in css as well! You can also <code>@import</code> external stylesheets, eg. for fonts.
              </p>
            </CardHeader>
            <CardContent>
              <div class="overflow-hidden rounded-lg border">
                <Codemirror
                  ref="cssEditor"
                  v-model="cssTemplate"
                  :style="{ height: '500px' }"
                  :indent-with-tab="true"
                  :tab-size="2"
                  :extensions="cssExtensions"
                  placeholder="Enter your CSS styles here..."
                />
              </div>
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="preview" class="mt-4">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">Live Preview</CardTitle>
              <p class="text-sm text-gray-600 dark:text-gray-400">This preview shows how your overlay will look with sample data</p>
            </CardHeader>
            <CardContent>
              <div class="rounded-lg border bg-gray-50 dark:bg-gray-900">
                <iframe :srcdoc="previewHtml" class="h-[500px] w-full border-0" sandbox="allow-same-origin" />
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </div>


</template>

<style scoped>
@reference "tailwindcss";

:deep(.cm-editor) {
  height: 100%;
}
code {

  padding: 0.1rem 0.2rem;
  border-radius: 0.2rem;
  font-size: 0.8rem;
  font-family: 'Fira Code', monospace;
}
</style>
