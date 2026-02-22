<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref } from 'vue';

interface Template {
  id: number;
  name: string;
  slug: string;
  type: string;
  is_public: boolean;
  fork_count: number;
  view_count: number;
  created_at: string;
  updated_at: string;
  owner: { id: number; name: string; twitch_id: string | null } | null;
}

const props = defineProps<{
  template: Template;
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
</script>

<template>
  <Head><title>Admin — {{ template.name }}</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4 max-w-3xl">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-bold">{{ template.name }}</h1>
          <div class="font-mono text-sm text-muted-foreground">{{ template.slug }}</div>
        </div>
        <div class="flex gap-2">
          <Badge variant="outline">{{ template.type }}</Badge>
          <Badge :variant="template.is_public ? 'default' : 'secondary'">{{ template.is_public ? 'public' : 'private' }}</Badge>
        </div>
      </div>

      <Card>
        <CardContent class="pt-4 grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-muted-foreground">Owner</span>
            <div>
              <a v-if="template.owner" :href="route('admin.users.show', template.owner.id)" class="hover:underline">{{ template.owner.name }}</a>
              <span v-else>—</span>
            </div>
          </div>
          <div>
            <span class="text-muted-foreground">Forks</span>
            <div>{{ forksCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Controls</span>
            <div>{{ controlsCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Event Mappings</span>
            <div>{{ eventMappingsCount }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Views</span>
            <div>{{ template.view_count }}</div>
          </div>
          <div>
            <span class="text-muted-foreground">Created</span>
            <div>{{ template.created_at }}</div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Visibility</CardTitle></CardHeader>
        <CardContent class="space-y-3">
          <p class="text-sm text-muted-foreground">Toggle whether this template is publicly visible.</p>
          <Button variant="outline" size="sm" @click="toggleVisibility" :disabled="visibilityForm.processing">
            Make {{ template.is_public ? 'private' : 'public' }}
          </Button>
        </CardContent>
      </Card>

      <Card class="border-destructive">
        <CardHeader><CardTitle class="text-destructive">Danger Zone</CardTitle></CardHeader>
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
    </div>
  </AppLayout>
</template>
