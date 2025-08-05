
<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types'
import Modal from '@/components/Modal.vue';
import axios from 'axios';
import Heading from '@/components/Heading.vue';

const form = useForm({
  name: '',
  description: '',
  html: '',
  css: '',
  js: '',
  is_public: true,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlay Creator',
    href: '/templates/create',
  }
]

const showPreview = ref(false);
const previewHtml = ref('');

const submitForm = () => {
  form.post(route('templates.store'), {
    onSuccess: () => {
      // Will redirect to index or show page
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
      Object.entries(tags).forEach(([categoryTags]) => {
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

  previewHtml.value = `<!DOCTYPE html>
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
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Create New Template" />
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="mb-6">
              <Heading title="Overlay Creator" description="Design a new overlay template with HTML, CSS, and Template Tags" /> /
            </div>

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
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="My Awesome Overlay"
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
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="Describe what your template does..."
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
                    <button
                      type="button"
                      @click="insertTag('html')"
                      class="text-sm text-blue-600 hover:text-blue-800"
                    >
                      Insert Template Tag
                    </button>
                  </div>
                  <div class="border rounded-md overflow-hidden">
                    <textarea
                      v-model="form.html"
                      class="w-full font-mono text-sm p-3 h-64 focus:outline-none"
                      placeholder="<div class='overlay'>&#10;  <h1>[[[user_name]]]</h1>&#10;  <p>Followers: [[[user_follower_count]]]</p>&#10;</div>"
                      required
                    />
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
                    <button
                      type="button"
                      @click="insertTag('css')"
                      class="text-sm text-blue-600 hover:text-blue-800"
                    >
                      Insert Template Tag
                    </button>
                  </div>
                  <div class="border rounded-md overflow-hidden">
                    <textarea
                      v-model="form.css"
                      class="w-full font-mono text-sm p-3 h-64 focus:outline-none"
                      placeholder=".overlay {&#10;  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);&#10;  padding: 20px;&#10;  border-radius: 10px;&#10;}"
                    />
                  </div>
                  <div v-if="form.errors.css" class="text-red-600 text-sm mt-1">
                    {{ form.errors.css }}
                  </div>
                </div>

                <!-- JavaScript Editor -->
                <div>
                  <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                      JavaScript
                    </label>
                    <button
                      type="button"
                      @click="insertTag('js')"
                      class="text-sm text-blue-600 hover:text-blue-800"
                    >
                      Insert Template Tag
                    </button>
                  </div>
                  <div class="border rounded-md overflow-hidden">
                    <textarea
                      v-model="form.js"
                      class="w-full font-mono text-sm p-3 h-64 focus:outline-none"
                      placeholder="// Your JavaScript code here&#10;console.log('Overlay loaded!');"
                    />
                  </div>
                  <div v-if="form.errors.js" class="text-red-600 text-sm mt-1">
                    {{ form.errors.js }}
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

              <!-- Available Tags Help -->
              <div class="mt-6 p-4 bg-gray-50 rounded-md">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Available Template Tags:</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                  <code class="bg-white px-2 py-1 rounded">[[[user_name]]]</code>
                  <code class="bg-white px-2 py-1 rounded">[[[user_follower_count]]]</code>
                  <code class="bg-white px-2 py-1 rounded">[[[user_view_count]]]</code>
                  <code class="bg-white px-2 py-1 rounded">[[[stream_title]]]</code>
                  <code class="bg-white px-2 py-1 rounded">[[[stream_game_name]]]</code>
                  <code class="bg-white px-2 py-1 rounded">[[[stream_viewer_count]]]</code>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                  Use [[[tag_name]]] syntax in your templates.
                  <a href="/tags-generator" class="text-blue-600 hover:underline">
                    All available tags
                  </a>
                </p>
              </div>

              <!-- Form Actions -->
              <div class="mt-6 flex justify-end space-x-3">
                <Link
                  :href="route('templates.index')"
                  class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                  Cancel
                </Link>
                <button
                  type="button"
                  @click="previewTemplate"
                  class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                >
                  Preview
                </button>
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                >
                  Create Template
                </button>
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
