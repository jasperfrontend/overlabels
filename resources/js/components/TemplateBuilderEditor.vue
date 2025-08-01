<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Codemirror } from 'vue-codemirror';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Eye, Save, Code, Palette, Play, AlertCircle, CheckCircle,
  FileWarningIcon, ExternalLinkIcon, RefreshCcwDot, Keyboard,
  ChevronDown, ChevronRight, Info
} from 'lucide-vue-next';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

interface Props {
    overlayHash: {
        hash_key: string;
        overlay_name: string;
        slug: string;
        id: number;
    };
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

// State - Uses templates from backend (either existing custom templates or default from service)
const htmlTemplate = ref(props.existingTemplate.html || '');
const cssTemplate = ref(props.existingTemplate.css || '');
const defaultTemplates = ref<{ html: string; css: string } | null>(null);
const isSaving = ref(false);
const saveMessage = ref('');
const validationResults = ref<any>(null);
const isValidating = ref(false);
const isLoadingDefaults = ref(false);
const showConditionalExamples = ref(false);
const showActionsDropdown = ref(false);

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
    saveMessage.value = '';
    const tag = `[[[${tagName}]]]`;
    try {
        await navigator.clipboard.writeText(tag);
        saveMessage.value = `copy success`;
        setTimeout(() => {
            saveMessage.value = '';
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
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
    saveMessage.value = '';

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
            saveMessage.value = 'Saved successfully!';
            setTimeout(() => {
                saveMessage.value = '';
            }, 3000);
        } else {
            saveMessage.value = result.message || 'Failed to save template';
        }
    } catch (error) {
        console.error('Save error:', error);
        saveMessage.value = 'Failed to save template';
    } finally {
        isSaving.value = false;
    }
};

const previewLive = () => {
    const url = `/overlay/${props.overlayHash.slug}/${props.overlayHash.hash_key}`;

    // Show warning about security implications before opening the URL
    const confirmed = confirm(
        "⚠️ SECURITY WARNING ⚠️\n\n" +
        "The URL you're about to open contains a hash that authenticates your Twitch account.\n\n" +
        "DO NOT share this URL with anyone or post it publicly. Doing so could give others access to your Twitch account information.\n\n" +
        "Click OK to continue or Cancel to abort."
    );

    if (confirmed) {
        window.open(url, '_blank');
    }
};

const resetToDefault = async () => {
    if (confirm('Are you sure you want to reset to the default template? This will overwrite your current changes.')) {
        if (defaultTemplates.value) {
            htmlTemplate.value = defaultTemplates.value.html;
            cssTemplate.value = defaultTemplates.value.css;
            saveMessage.value = 'Reset to default template!';
            setTimeout(() => {
                saveMessage.value = '';
            }, 3000);
        } else {
            // Reload defaults if not loaded yet
            await loadDefaultTemplates();
            if (defaultTemplates.value) {
                htmlTemplate.value = defaultTemplates?.value.html;
                cssTemplate.value = defaultTemplates?.value.css;
                saveMessage.value = 'Reset to default template!';
                setTimeout(() => {
                    saveMessage.value = '';
                }, 3000);
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
    register('save-template', 'ctrl+s', () => {
        saveTemplate();
    }, { description: 'Save template' });

    // Preview with Ctrl+P
    register('preview-live', 'ctrl+p', () => {
        previewLive();
    }, { description: 'Preview in new tab' });

    // Validate template with Ctrl+V
    register('validate-template', 'ctrl+v', () => {
        validateTemplate();
    }, { description: 'Validate template' });

    // Toggle keyboard shortcuts display with Ctrl+K
    register('toggle-shortcuts', 'ctrl+k', () => {
        showKeyboardShortcuts.value = !showKeyboardShortcuts.value;
    }, { description: 'Show keyboard shortcuts' });
});

// Get all keyboard shortcuts for display
const keyboardShortcutsList = computed(() => getAllShortcuts());
</script>

<template>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
    <!-- Left sidebar with template tags and actions -->
    <div class="space-y-6 lg:col-span-1">
      <!-- Action buttons -->
      <Card>
        <CardHeader class="pb-3">
          <CardTitle class="flex items-center gap-2 text-base">
            <Play class="h-4 w-4" />
            Actions
            <!-- Keyboard shortcuts indicator -->
            <Button @click="showKeyboardShortcuts = !showKeyboardShortcuts" variant="ghost" size="sm" class="ml-1 h-6 w-6 p-0">
              <Keyboard class="h-4 w-4" />
            </Button>
            <!-- Save message -->
            <div
              v-if="saveMessage"
              class="ml-auto rounded-md px-1 text-center text-sm"
              :class="
                                saveMessage.includes('success')
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                            "
            >
              {{ saveMessage }}
            </div>
          </CardTitle>
        </CardHeader>
        <CardContent class="pt-0">
          <!-- Primary action: Save -->
          <Button
            @click="saveTemplate"
            :disabled="isSaving"
            class="w-full mb-3 cursor-pointer bg-green-600 hover:bg-green-700 text-white shadow"
          >
            <RefreshCcwDot v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
            <Save v-else class="mr-2 h-4 w-4" />
            {{ isSaving ? 'Saving...' : 'Save' }}
            <span class="ml-1 rounded bg-black/10 px-1 text-xs">⌃S</span>
          </Button>

          <!-- Secondary actions dropdown -->
          <div class="relative">
            <Button
              @click="showActionsDropdown = !showActionsDropdown"
              variant="outline"
              class="w-full justify-between cursor-pointer"
            >
              <span>More Actions</span>
              <ChevronDown :class="['h-4 w-4 transition-transform', showActionsDropdown ? 'rotate-180' : '']" />
            </Button>

            <div
              v-if="showActionsDropdown"
              class="absolute z-10 mt-1 w-full rounded-md border bg-white shadow-lg dark:bg-gray-800"
            >
              <Button
                @click="previewLive"
                variant="ghost"
                class="w-full justify-start cursor-pointer"
              >
                <ExternalLinkIcon class="mr-2 h-4 w-4" />
                Preview in New Tab
                <span class="ml-auto rounded bg-black/10 px-1 text-xs">⌃P</span>
              </Button>

              <Button
                @click="validateTemplate"
                :disabled="isValidating"
                variant="ghost"
                class="w-full justify-start cursor-pointer"
              >
                <RefreshCcwDot v-if="isValidating" class="mr-2 h-4 w-4 animate-spin" />
                <CheckCircle v-else class="mr-2 h-4 w-4" />
                {{ isValidating ? 'Validating...' : 'Validate' }}
                <span class="ml-auto rounded bg-black/10 px-1 text-xs">⌃V</span>
              </Button>

              <div class="border-t my-1"></div>

              <Button
                @click="resetToDefault; showActionsDropdown = false"
                :disabled="isLoadingDefaults"
                variant="ghost"
                class="w-full justify-start text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
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
          <CardTitle class="text-base">Template Tags</CardTitle>
          <p class="text-sm text-gray-600 dark:text-gray-400">Click to copy template tags to your clipboard</p>
        </CardHeader>
        <CardContent class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto pr-2">
          <!-- Conditional Template Tags - Collapsible -->
          <div>
            <Button
              @click="showConditionalExamples = !showConditionalExamples"
              variant="ghost"
              class="w-full justify-between p-0 h-auto mb-2 bg-chart-2/50 hover:bg-chart-2/100 dark:hover:bg-chart-2/90 cursor-pointer"
            >
              <h4 class="text-sm font-medium flex items-center p-2 px-0">
                Conditional Template Tags
              </h4>
              <ChevronRight
                :class="['h-4 w-4 transition-transform', showConditionalExamples ? 'rotate-90' : '']"
              />
            </Button>

            <div v-if="showConditionalExamples" class="space-y-3 text-xs">
              <div class="space-y-1 border-dashed border p-2 bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded">
                <div class="font-medium mb-1">Basic if statement</div>
                <code class="text-muted-foreground">
                  [[[if:channel_is_branded]]]<br />
                  <span style="text-indent: 1rem; display: inline-block;">Woah branded content!</span> <br />
                  [[[endif]]]
                </code>
              </div>

              <div class="space-y-1 border-dashed border p-2 bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded">
                <div class="font-medium mb-1">if/else statement</div>
                <code class="text-muted-foreground">
                  [[[if:channel_is_branded]]]<br />
                  <span style="text-indent: 1rem; display: inline-block;">Woah branded content!</span> <br />
                  [[[else]]]<br />
                  <span style="text-indent: 1rem; display: inline-block;">Nah, no sponsor today lol</span> <br />
                  [[[endif]]]
                </code>
              </div>

              <div class="space-y-1 border-dashed border p-2 bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded">
                <div class="font-medium mb-1">Numeric comparison</div>
                <code class="text-muted-foreground">
                  [[[if:followers_total >= 2000]]]<br />
                  <span style="text-indent: 1rem; display: inline-block;">WOW! Over 2000 followers!</span> <br />
                  [[[else]]]<br />
                  <span style="text-indent: 1rem; display: inline-block;">Help us reach 2000 followers!</span> <br />
                  [[[endif]]]
                </code>
                <div class="flex items-center gap-2 mt-2">
                  <span class="bg-chart-2 py-0 px-1 rounded text-xs">==</span>
                  <span class="bg-chart-2 py-0 px-1 rounded text-xs">!=</span>
                  <span class="bg-chart-2 py-0 px-1 rounded text-xs">>=</span>
                  <span class="bg-chart-2 py-0 px-1 rounded text-xs">></span>
                  <span class="bg-chart-2 py-0 px-1 rounded text-xs"><=</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Regular template tags by category -->
          <div v-for="(tags, category) in tagsByCategory" :key="category">
            <h4 class="mb-2 text-sm font-medium">{{ category }}</h4>
            <div class="space-y-1">
              <Button
                v-for="tag in tags"
                :key="tag.tag_name"
                @click="copyTag(tag.tag_name)"
                variant="ghost"
                size="sm"
                class="h-auto w-full justify-start py-1 text-xs"
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
        <TabsList class="grid w-full grid-cols-3">
          <TabsTrigger value="html" class="flex items-center gap-2">
            <Code class="h-4 w-4" />
            HTML
          </TabsTrigger>
          <TabsTrigger value="css" class="flex items-center gap-2">
            <Palette class="h-4 w-4" />
            CSS
          </TabsTrigger>
          <TabsTrigger value="preview" class="flex items-center gap-2">
            <Eye class="h-4 w-4" />
            Preview
          </TabsTrigger>
        </TabsList>
        <TabsContent value="html" class="mt-4">
          <Card>
            <CardHeader>
              <CardTitle class="text-base">HTML Template</CardTitle>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Use template tags like <code>[[[channel_name]]]</code> for dynamic content
              </p>
            </CardHeader>
            <CardContent>
              <div class="overflow-hidden rounded-lg border">
                <Codemirror
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
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Style your overlay with CSS. Use transparent backgrounds for OBS compatibility.
              </p>
            </CardHeader>
            <CardContent>
              <div class="overflow-hidden rounded-lg border">
                <Codemirror
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

  <!-- Keyboard shortcuts dialog -->
  <div v-if="showKeyboardShortcuts"
       class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
       @click.self="showKeyboardShortcuts = false">
    <div class="w-full max-w-md overflow-hidden rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-medium">Keyboard Shortcuts</h3>
        <button @click="showKeyboardShortcuts = false" class="rounded-full p-1 hover:bg-gray-100 dark:hover:bg-gray-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
      <div class="space-y-2">
        <div v-for="shortcut in keyboardShortcutsList" :key="shortcut.id"
             class="flex items-center justify-between rounded-md border p-2 text-sm">
          <span>{{ shortcut.description }}</span>
          <kbd class="rounded bg-gray-100 px-2 py-1 font-mono text-xs dark:bg-gray-700">
            {{ shortcut.keys }}
          </kbd>
        </div>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
          Press <kbd class="rounded bg-gray-100 px-1 dark:bg-gray-700">Ctrl+K</kbd> to toggle this dialog.<br/><br/>
          Keyboard shortcuts do not work when focused on the code editor.<br/>
          Click outside first, then hit ctrl+s.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.cm-editor) {
  height: 100%;
}
</style>
