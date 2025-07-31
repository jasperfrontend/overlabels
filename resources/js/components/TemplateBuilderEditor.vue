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
import { Eye, Save, Code, Palette, Play, AlertCircle, CheckCircle, FileWarningIcon, ExternalLinkIcon, RefreshCcwDot } from 'lucide-vue-next';

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

// Methods
// const insertTag = (tagName: string) => {
//   const tag = `[[[${tagName}]]]`
//   htmlTemplate.value += tag
// }

const insertTag = async (tagName: string) => {
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
            saveMessage.value = 'Template saved successfully!';
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
</script>

<template>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <!-- Left sidebar with template tags and actions -->
        <div class="space-y-6 lg:col-span-1">
            <!-- Action buttons -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Play class="h-4 w-4" />
                        Actions

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
                <CardContent class="flex flex-wrap gap-2 space-y-3">
                    <Button
                        @click="saveTemplate"
                        :disabled="isSaving"
                        class="w-28 cursor-pointer bg-green-300 text-green-800 shadow transition hover:bg-green-400 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800"
                    >
                        <RefreshCcwDot v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{ isSaving ? '&hellip;' : 'Save' }}
                    </Button>

                    <Button @click="previewLive" class="w-28 cursor-pointer" variant="outline">
                        <ExternalLinkIcon />
                        New Tab
                    </Button>

                    <Button @click="validateTemplate" :disabled="isValidating" class="w-28 cursor-pointer" variant="outline">
                        <RefreshCcwDot v-if="isValidating" class="mr-2 h-4 w-4 animate-spin" />
                        <CheckCircle v-else class="mr-2 h-4 w-4" />
                        {{ isValidating ? '&hellip;' : 'Validate' }}
                    </Button>

                    <Button
                        title="Reset your layout. Be careful, this will destroy any changes you have made to this template!"
                        @click="resetToDefault"
                        :disabled="isLoadingDefaults"
                        class="w-28 cursor-pointer bg-red-400/50 hover:bg-red-500 hover:text-red-100"
                    >
                        <RefreshCcwDot v-if="isLoadingDefaults" class="mr-2 h-4 w-4 animate-spin" />
                        <FileWarningIcon v-else class="h-4 w-4"></FileWarningIcon>
                        {{ isLoadingDefaults ? '&hellip;' : 'Reset' }}
                    </Button>
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
                <CardContent class="space-y-4">
                    <div v-for="(tags, category) in tagsByCategory" :key="category">
                        <h4 class="mb-2 text-sm font-medium">{{ category }}</h4>
                        <div class="space-y-1">
                            <Button
                                v-for="tag in tags"
                                :key="tag.tag_name"
                                @click="insertTag(tag.tag_name)"
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
</template>

<style scoped>
:deep(.cm-editor) {
    height: 100%;
}
</style>
