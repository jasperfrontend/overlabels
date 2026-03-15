<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface Tag {
  id: number;
  tag_name: string;
  display_name: string | null;
  tag_type: string;
  is_active: boolean;
  created_at: string;
  category: { id: number; name: string } | null;
  user: { id: number; name: string } | null;
}

interface Category {
  id: number;
  name: string;
  display_name: string | null;
  sort_order: number;
  template_tags_count: number;
}

interface Paginator {
  data: Tag[];
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
  tags?: Paginator;
  categories?: Category[];
  view?: string;
  filters?: Record<string, string>;
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Tags', href: route('admin.tags.index') },
];

function toggleActive(tag: Tag) {
  const form = useForm({ is_active: !tag.is_active });
  form.patch(route('admin.tags.update', tag.id));
}

function deleteTag(tag: Tag) {
  if (confirm(`Delete tag "${tag.tag_name}"?`)) {
    router.delete(route('admin.tags.destroy', tag.id));
  }
}

function deleteCategory(cat: Category) {
  if (confirm(`Delete category "${cat.name}"?`)) {
    router.delete(route('admin.tags.categories.destroy', cat.id));
  }
}
</script>

<template>
  <Head><title>Admin — Tags</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <h1 class="text-2xl font-bold">Template Tags</h1>

      <Tabs :default-value="view === 'categories' ? 'categories' : 'tags'">
        <TabsList>
          <TabsTrigger value="tags" @click="router.get(route('admin.tags.index'))">Tags</TabsTrigger>
          <TabsTrigger value="categories" @click="router.get(route('admin.tags.categories.index'))">Categories</TabsTrigger>
        </TabsList>

        <!-- Tags tab -->
        <TabsContent value="tags" class="mt-4" v-if="tags">
          <!-- Card view (< lg) -->
          <div class="lg:hidden space-y-2">
            <EmptyState v-if="tags.data.length === 0" message="No tags found." />
            <div v-for="tag in tags.data" :key="`card-${tag.id}`" class="rounded border p-3 text-sm">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="font-mono text-xs font-medium">{{ tag.tag_name }}</div>
                  <div v-if="tag.display_name" class="text-xs text-muted-foreground">{{ tag.display_name }}</div>
                </div>
                <div class="flex shrink-0 gap-2">
                  <button @click="toggleActive(tag)" class="text-xs text-primary hover:underline cursor-pointer">
                    {{ tag.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                  <button @click="deleteTag(tag)" class="text-xs text-destructive hover:underline cursor-pointer">Delete</button>
                </div>
              </div>
              <div class="mt-2 flex flex-wrap gap-1.5">
                <Badge variant="outline">{{ tag.tag_type }}</Badge>
                <Badge :variant="tag.is_active ? 'default' : 'secondary'">{{ tag.is_active ? 'active' : 'inactive' }}</Badge>
              </div>
              <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span v-if="tag.category">{{ tag.category.name }}</span>
                <span>{{ tag.user?.name ?? 'system' }}</span>
              </div>
            </div>
          </div>

          <!-- Table (≥ lg) -->
          <div class="hidden lg:block overflow-x-auto rounded border">
            <table class="w-full text-sm">
              <thead class="bg-muted text-left text-muted-foreground">
                <tr>
                  <th class="px-3 py-2">Tag</th>
                  <th class="px-3 py-2">Category</th>
                  <th class="px-3 py-2">Type</th>
                  <th class="px-3 py-2">Active</th>
                  <th class="px-3 py-2">Owner</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tag in tags.data" :key="tag.id" class="border-t">
                  <td class="px-3 py-2">
                    <div class="font-mono text-xs">{{ tag.tag_name }}</div>
                    <div v-if="tag.display_name" class="text-xs text-muted-foreground">{{ tag.display_name }}</div>
                  </td>
                  <td class="px-3 py-2 text-muted-foreground text-xs">{{ tag.category?.name ?? '—' }}</td>
                  <td class="px-3 py-2"><Badge variant="outline">{{ tag.tag_type }}</Badge></td>
                  <td class="px-3 py-2">
                    <Badge :variant="tag.is_active ? 'default' : 'secondary'">{{ tag.is_active ? 'active' : 'inactive' }}</Badge>
                  </td>
                  <td class="px-3 py-2 text-xs text-muted-foreground">{{ tag.user?.name ?? 'system' }}</td>
                  <td class="px-3 py-2 flex gap-2">
                    <button @click="toggleActive(tag)" class="text-xs text-primary hover:underline">
                      {{ tag.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button @click="deleteTag(tag)" class="text-xs text-destructive hover:underline">Delete</button>
                  </td>
                </tr>
                <EmptyState v-if="tags.data.length === 0" :colspan="6" message="No tags found." />
              </tbody>
            </table>
          </div>

          <div class="mt-2 flex gap-1">
            <template v-for="link in tags.links" :key="link.label">
              <a v-if="link.url" :href="link.url" class="rounded border px-3 py-1 text-sm"
                :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'" v-html="link.label" />
              <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
            </template>
          </div>
        </TabsContent>

        <!-- Categories tab -->
        <TabsContent value="categories" class="mt-4" v-if="categories">
          <!-- Card view (< lg) -->
          <div class="lg:hidden space-y-2">
            <EmptyState v-if="categories.length === 0" message="No categories found." />
            <div v-for="cat in categories" :key="`card-${cat.id}`" class="rounded border p-3 text-sm">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="font-mono text-xs font-medium">{{ cat.name }}</div>
                  <div v-if="cat.display_name" class="text-xs text-muted-foreground">{{ cat.display_name }}</div>
                </div>
                <button @click="deleteCategory(cat)" class="shrink-0 text-xs text-destructive hover:underline">Delete</button>
              </div>
              <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span>Sort: {{ cat.sort_order }}</span>
                <span>{{ cat.template_tags_count }} tags</span>
              </div>
            </div>
          </div>

          <!-- Table (≥ lg) -->
          <div class="hidden lg:block overflow-x-auto rounded border">
            <table class="w-full text-sm">
              <thead class="bg-muted text-left text-muted-foreground">
                <tr>
                  <th class="px-3 py-2">Name</th>
                  <th class="px-3 py-2">Display Name</th>
                  <th class="px-3 py-2">Sort Order</th>
                  <th class="px-3 py-2">Tags</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cat in categories" :key="cat.id" class="border-t">
                  <td class="px-3 py-2 font-mono text-xs">{{ cat.name }}</td>
                  <td class="px-3 py-2">{{ cat.display_name ?? '—' }}</td>
                  <td class="px-3 py-2">{{ cat.sort_order }}</td>
                  <td class="px-3 py-2">{{ cat.template_tags_count }}</td>
                  <td class="px-3 py-2">
                    <button @click="deleteCategory(cat)" class="text-xs text-destructive hover:underline">Delete</button>
                  </td>
                </tr>
                <EmptyState v-if="categories.length === 0" :colspan="5" message="No categories found." />
              </tbody>
            </table>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  </AppLayout>
</template>
