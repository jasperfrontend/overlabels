<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { BreadcrumbItem } from '@/types'
import Heading from '@/components/Heading.vue';
import axios from 'axios';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { oneDark } from '@codemirror/theme-one-dark';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import FlashMessage from '@/components/FlashMessage.vue';

const props = defineProps({
  template: Object,
});

const isDark = ref(document.documentElement.classList.contains('dark'));
const page = usePage();

const form = useForm({
  name: props.template.name,
  description: props.template.description || '',
  html: props.template.html || '',
  css: props.template.css || '',
  js: props.template.js || '',
  is_public: props.template.is_public,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Template Builder',
    href: route('templates.edit', props.template),
  }
]
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
const showPreview = ref(false);
const previewHtml = ref('');
const showSuccessToast = ref(false);

// Check for a flash message when the component mounts
if (page.props.flash?.success) {
  showSuccessToast.value = true;
  setTimeout(() => {
    showSuccessToast.value = false;
  }, 3000);
}

const submitForm = () => {
  form.put(route('templates.update', props.template), {
    preserveScroll: true,
    onSuccess: () => {
      // Show success toast
      showSuccessToast.value = true;
      setTimeout(() => {
        showSuccessToast.value = false;
      }, 3000);
    },
    onError: () => {
      // Inertia automatically handles errors
    },
  });
};

const insertTag = (editor) => {
  // Fetch available tags from the API
  axios.get('/api/template-tags')
    .then(response => {
      const tags = response.data.tags;
      const tagList = [];

      // Flatten the categorized tags
      Object.entries(tags).forEach(([category, categoryTags]) => {
        categoryTags.forEach(tag => {
          tagList.push(`${tag.tag} - ${tag.description}`);
        });
      });

      const selected = prompt('Select a tag:\n\n' + tagList.join('\n'));
      if (selected) {
        const tag = selected.split(' - ')[0];
        if (editor === 'html') {
          form.html += tag;
        } else if (editor === 'css') {
          form.css += tag;
        } else if (editor === 'js') {
          form.js += tag;
        }
      }
    })
    .catch(() => {
      // Fallback to manual input
      const tag = prompt('Enter template tag name (e.g., user_name):');
      if (tag) {
        const fullTag = `[[[${tag}]]]`;
        if (editor === 'html') {
          form.html += fullTag;
        } else if (editor === 'css') {
          form.css += fullTag;
        } else if (editor === 'js') {
          form.js += fullTag;
        }
      }
    });
};

const formatCode = (type) => {
  // Simple formatting - in production you might use a proper formatter
  if (type === 'html') {
    // Basic HTML formatting
    form.html = form.html
      .replace(/></g, '>\n<')
      .replace(/(\n\s*)+/g, '\n');
  } else if (type === 'css') {
    // Basic CSS formatting
    form.css = form.css
      .replace(/}/g, '}\n')
      .replace(/{/g, ' {\n  ')
      .replace(/;/g, ';\n  ')
      .replace(/\n\s*\n/g, '\n');
  } else if (type === 'js') {
    // Basic JS formatting
    form.js = form.js
      .replace(/;/g, ';\n')
      .replace(/{/g, ' {\n  ')
      .replace(/}/g, '\n}')
      .replace(/\n\s*\n/g, '\n');
  }
};

const previewTemplate = () => {
  // Create a preview with sample data that matches the database tags
  const sampleData = {
    user_name: 'SampleStreamer',
    user_follower_count: '1,234',
    user_view_count: '45,678',
    stream_title: 'Playing an awesome game!',
    stream_game_name: 'Just Chatting',
    stream_viewer_count: '567',
    user_broadcaster_type: 'affiliate',
    channel_subscription_count: '123',
  };

  // Simple template tag replacement for preview
  let html = form.html;
  let css = form.css;
  let js = form.js;

  // Replace template tags with sample data
  Object.entries(sampleData).forEach(([tag, value]) => {
    const tagPattern = new RegExp(`\\[\\[\\[${tag}]]]`, 'g');
    html = html.replace(tagPattern, value);
    css = css.replace(tagPattern, value);
    js = js.replace(tagPattern, value);
  });

  previewHtml.value = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <style>${css}</style>
    </head>
    <body>
      ${html}
    </body>
  </html>`;
  showPreview.value = true;
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
  <Head title="Template Builder" />
  <AppLayout>
    <!-- Flash Message -->
    <FlashMessage
      :show="showSuccessToast"
      type="success"
      title="Template saved successfully!"
      message="Your changes have been saved."
      @close="showSuccessToast = false"
    />

    <div class="p-4">
    <Heading title="Template Builder" description="Create custom HTML/CSS templates for your overlays using our CodePen-style editor." />
      <div class="mt-4">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">

            <form @submit.prevent="submitForm">
              <!-- Template Name -->
              <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                  Template Name *
                </label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="p-2 rounded border w-full  hover:shadow-sm transition"
                  required
                />
                <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">
                  {{ form.errors.name }}
                </div>
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                  Description
                </label>
                <textarea
                  id="description"
                  v-model="form.description"
                  rows="3"
                  class="p-2 rounded border w-full hover:shadow-sm transition"
                />
                <div v-if="form.errors.description" class="text-red-600 text-sm mt-1">
                  {{ form.errors.description }}
                </div>
              </div>

              <!-- Code Editors -->
              <div class="space-y-6">
                <!-- HTML Editor -->
                <div>
                  <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                      HTML *
                    </label>
                    <div class="space-x-2">
                      <button
                        type="button"
                        @click="formatCode('html')"
                        class="text-sm text-gray-600 hover:text-gray-800"
                      >
                        Format
                      </button>
                      <button
                        type="button"
                        @click="insertTag('html')"
                        class="text-sm text-blue-600 hover:text-blue-800"
                      >
                        Insert Template Tag
                      </button>
                    </div>
                  </div>
                  <div class="border rounded-md overflow-hidden">

                    <div class="overflow-hidden rounded-lg border">
                      <Codemirror
                        v-model="form.html"
                        :style="{ height: '500px' }"
                        :autofocus="true"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="htmlExtensions"
                        placeholder="Enter your HTML template here... Use [[[tag_name]]] for dynamic content"
                      />
                    </div>

                  </div>
                  <div v-if="form.errors.html" class="text-red-600 text-sm mt-1">
                    {{ form.errors.html }}
                  </div>
                </div>

                <!-- CSS Editor -->
                <div>
                  <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                      CSS
                    </label>
                    <div class="space-x-2">
                      <button
                        type="button"
                        @click="formatCode('css')"
                        class="text-sm text-gray-600 hover:text-gray-800"
                      >
                        Format
                      </button>
                      <button
                        type="button"
                        @click="insertTag('css')"
                        class="text-sm text-blue-600 hover:text-blue-800"
                      >
                        Insert Template Tag
                      </button>
                    </div>
                  </div>
                  <div class="border rounded-md overflow-hidden">
                    <div class="overflow-hidden rounded-lg border">
                      <Codemirror
                        v-model="form.css"
                        :style="{ height: '500px' }"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :extensions="cssExtensions"
                        placeholder="Enter your CSS styles here..."
                      />
                    </div>
                  </div>
                  <div v-if="form.errors.css" class="text-red-600 text-sm mt-1">
                    {{ form.errors.css }}
                  </div>
                </div>

              </div>

              <!-- Visibility -->
              <div class="mt-6">
                <label class="flex items-center">
                  <input
                    v-model="form.is_public"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">
                    Make this template public (others can view and fork it)
                  </span>
                </label>
              </div>

              <!-- Template Info -->
              <div class="mt-6 grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-md text-sm">
                <div>
                  <span class="text-gray-600">Created:</span>
                  <span class="ml-2">{{ new Date(template.created_at).toLocaleDateString() }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Last updated:</span>
                  <span class="ml-2">{{ new Date(template.updated_at).toLocaleDateString() }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Views:</span>
                  <span class="ml-2">{{ template.view_count }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Forks:</span>
                  <span class="ml-2">{{ template.fork_count }}</span>
                </div>
              </div>

              <!-- Available Tags Help -->
              <div class="mt-6 p-4 bg-gray-50 rounded-md">
                <p class="text-xs text-gray-500 mt-2">
                  <a href="/tags-generator" target="_blank" class="text-blue-600 hover:underline">
                    View all available tags
                  </a>
                </p>
                <div v-if="template.template_tags && template.template_tags.length > 0" class="mt-3">
                  <p class="text-sm text-gray-600 mb-1">Currently used tags:</p>
                  <div class="flex flex-wrap gap-1">
                    <code
                      v-for="tag in template.template_tags"
                      :key="tag"
                      class="bg-yellow-100 px-2 py-1 rounded text-xs"
                    >
                      [[[{{ tag }}]]]
                    </code>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="mt-6 flex justify-between">
                <Link
                  :href="route('templates.show', template)"
                  class="text-gray-600 hover:text-gray-900"
                >
                  ← Back to Template
                </Link>
                <div class="space-x-3">
                  <button
                    type="button"
                    @click="previewTemplate"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                  >
                    Preview
                  </button>
                  <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                  >
                    Save Changes
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <Modal :show="showPreview" @close="showPreview = false" max-width="4xl">
      <div class="p-6">
        <h3 class="text-lg font-semibold mb-4">Template Preview</h3>
        <div class="border rounded-md p-4 bg-gray-50" style="height: 400px; position: relative;">
          <iframe
            v-if="previewHtml"
            :srcdoc="previewHtml"
            class="w-full h-full border-0"
            sandbox="allow-scripts"
          />
        </div>
        <div class="mt-4 text-sm text-gray-600">
          Note: Template tags are shown with sample data in preview
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

