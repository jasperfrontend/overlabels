<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { type BreadcrumbItem } from '@/types';
import { Copy, AlertCircle } from '@lucide/vue';

interface TemplateTag {
  tag_name: string;
  display_tag: string;
  display_name: string;
  description: string;
  data_type: string;
  json_path: string;
  sample_data: unknown;
  is_live: boolean;
}

interface CategoryData {
  category: {
    name: string;
    display_name: string;
    description: string;
    is_group: boolean;
    sort_order: number;
  };
  tags: TemplateTag[];
}

// Every account gets the same catalogue, so there is nothing to generate and
// nothing to poll - the server builds this from a constant on each request and
// fills in this account's own current values.
const props = defineProps<{
  tags: Record<string, CategoryData>;
  liveValues: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Template Tags', href: '/tags' }];

const toastMessage = ref('');
const showToast = ref(false);

const categories = computed(() => Object.values(props.tags).filter((c) => c.tags.length > 0));
const totalTags = computed(() => categories.value.reduce((n, c) => n + c.tags.length, 0));

function displayValue(tag: TemplateTag): string {
  if (tag.sample_data === null || tag.sample_data === undefined || tag.sample_data === '') return '';
  if (typeof tag.sample_data === 'boolean') return tag.sample_data ? 'true' : 'false';
  return String(tag.sample_data);
}

async function copyTag(tag: TemplateTag) {
  try {
    await navigator.clipboard.writeText(tag.display_tag);
    toastMessage.value = `Copied ${tag.display_tag}`;
    showToast.value = true;
  } catch {
    toastMessage.value = 'Could not copy to clipboard';
    showToast.value = true;
  }
}
</script>

<template>
  <Head title="Template Tags" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <RekaToast v-model:open="showToast" :message="toastMessage" type="success" />

      <Heading title="Template Tags" :description="`${totalTags} tags you can drop into any overlay. Values shown are your own, right now.`" />

      <div
        v-if="!liveValues"
        class="flex items-start gap-2 rounded-sm border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
      >
        <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <p class="text-sm text-foreground">
          Could not reach Twitch just now, so the values below are examples rather than your own data. The tags themselves are always available.
        </p>
      </div>

      <div class="space-y-4">
        <div v-for="categoryData in categories" :key="categoryData.category.name" class="rounded-sm border border-sidebar">
          <details class="group" open>
            <summary class="flex cursor-pointer list-none items-center justify-between rounded-sm bg-accent p-4 hover:bg-sidebar">
              <span class="flex items-center gap-3">
                <span class="text-lg font-semibold text-foreground">{{ categoryData.category.display_name }}</span>
                <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">
                  {{ categoryData.tags.length }}
                </span>
              </span>
              <svg
                class="h-5 w-5 transform text-foreground transition-transform group-open:rotate-180"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </summary>

            <p class="px-4 pt-3 text-sm text-foreground">{{ categoryData.category.description }}</p>

            <div class="space-y-2 p-4">
              <div
                v-for="tag in categoryData.tags"
                :key="tag.tag_name"
                class="collection-row flex flex-col gap-2 p-3 md:flex-row md:items-center md:justify-between"
              >
                <div class="min-w-0 flex-1">
                  <button
                    class="cursor-pointer font-mono text-sm text-violet-700 hover:underline dark:text-violet-300"
                    @click="copyTag(tag)"
                    :title="`Copy ${tag.display_tag}`"
                  >
                    {{ tag.display_tag }}
                  </button>
                  <p class="mt-1 text-sm text-foreground">{{ tag.description }}</p>
                </div>

                <div class="flex items-center gap-3 md:w-1/2 md:justify-end">
                  <span v-if="displayValue(tag)" class="truncate font-mono text-sm text-foreground" :title="displayValue(tag)">
                    {{ displayValue(tag) }}
                  </span>
                  <button
                    class="shrink-0 cursor-pointer text-foreground hover:text-violet-600 dark:hover:text-violet-300"
                    @click="copyTag(tag)"
                    :title="`Copy ${tag.display_tag}`"
                  >
                    <Copy class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </details>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
