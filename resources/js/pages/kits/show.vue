<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilIcon, GitFork, Package, Globe, Lock, ArrowLeft, Trash2Icon } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import TemplateCard from '@/components/TemplateCard.vue';
import { Badge } from '@/components/ui/badge';

interface Template {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  created_at: string;
  updated_at: string;
}

interface Kit {
  id: number;
  title: string;
  description: string | null;
  thumbnail: string | null;
  thumbnail_url?: string | null;
  is_public: boolean;
  fork_count: number;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  templates?: Template[];
  forked_from?: {
    id: number;
    title: string;
  };
  created_at: string;
  updated_at: string;
}

interface Props {
  kit: Kit;
  canEdit: boolean;
  canFork: boolean;
  auth?: {
    user?: {
      id: number;
    };
  };
}

const props = defineProps<Props>();

const handleFork = () => {
  if (confirm('Fork this kit to your account? This will also fork all templates within the kit.')) {
    router.post(`/kits/${props.kit.id}/fork`);
  }
};

const handleDelete = () => {
  if (props.kit.fork_count > 0) {
    alert('This kit cannot be deleted because it has been forked.');
    return;
  }

  if (confirm('Are you sure you want to delete this kit? This action cannot be undone.')) {
    router.delete(`/kits/${props.kit.id}`, {
      onSuccess: () => router.visit('/kits')
    });
  }
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <AppLayout>
    <Head :title="kit.title" />

    <div class="container mx-auto px-4 py-8">
      <!-- Back button -->
      <Link :href="route('kits.index')" class="mb-6 inline-flex items-center text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back to Kits
      </Link>

      <!-- Kit header -->
      <div class="mb-8 overflow-hidden rounded-lg bg-card lg:max-w-[75%]">
        <!-- Thumbnail -->
        <div v-if="kit.thumbnail_url" class="aspect-[16/9] w-full overflow-hidden bg-muted lg:aspect-[16/9]">
          <img
            :src="kit.thumbnail_url"
            :alt="kit.title"
            class="h-full w-full object-cover"
          />
        </div>
        <div v-else class="flex aspect-[16/9] w-full items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5 lg:aspect-[21/9]">
          <Package class="h-16 w-16 text-primary/40" />
        </div>

        <div class="p-6 lg:p-8">
          <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <div class="flex-1">
              <div class="mb-2 flex items-center gap-3">
                <h1 class="text-3xl font-bold">{{ kit.title }}</h1>
                <Badge :variant="kit.is_public ? 'default' : 'secondary'" class="flex items-center gap-1">
                  <component :is="kit.is_public ? Globe : Lock" class="h-3 w-3" />
                  {{ kit.is_public ? 'Public' : 'Private' }}
                </Badge>
              </div>

              <p v-if="kit.description" class="mb-4 text-muted-foreground">
                {{ kit.description }}
              </p>

              <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                <div v-if="kit.owner" class="flex items-center gap-2">
                  <img v-if="kit.owner.avatar" :src="kit.owner.avatar" :alt="kit.owner.name" class="h-6 w-6 rounded-full" />
                  <span>by {{ kit.owner.name }}</span>
                </div>

                <div v-if="kit.forked_from" class="flex items-center gap-1">
                  <GitFork class="h-4 w-4" />
                  <span>Forked from</span>
                  <Link :href="`/kits/${kit.forked_from.id}`" class="font-medium hover:underline">
                    {{ kit.forked_from.title }}
                  </Link>
                </div>

                <div class="flex items-center gap-1">
                  <GitFork class="h-4 w-4" />
                  <span>{{ kit.fork_count }} fork{{ kit.fork_count !== 1 ? 's' : '' }}</span>
                </div>

                <div>
                  Created {{ formatDate(kit.created_at) }}
                </div>
              </div>
            </div>

            <div class="flex gap-2">
              <Link v-if="canEdit" :href="`/kits/${kit.id}/edit`" class="btn btn-secondary">
                Edit Kit
                <PencilIcon class="ml-2 h-4 w-4" />
              </Link>

              <button v-if="canFork" @click="handleFork" class="btn btn-primary">
                Fork Kit
                <GitFork class="ml-2 h-4 w-4" />
              </button>

              <button
                v-if="canEdit && kit.fork_count === 0"
                @click="handleDelete"
                class="btn btn-danger"
                title="Delete kit"
              >
                <Trash2Icon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Templates section -->
      <div>
        <h2 class="mb-4 text-xl font-semibold">
          Templates in this Kit ({{ kit.templates?.length || 0 }})
        </h2>

        <div v-if="kit.templates && kit.templates.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <TemplateCard
            v-for="template in kit.templates"
            :key="template.id"
            :template="template"
            :current-user-id="auth?.user?.id"
            :show-owner="false"
          />
        </div>

        <div v-else class="rounded-lg border-2 border-dashed border-muted-foreground/25 p-12 text-center">
          <Package class="mx-auto mb-4 h-12 w-12 text-muted-foreground/50" />
          <p class="text-muted-foreground">
            No templates in this kit yet.
          </p>
          <Link v-if="canEdit" :href="`/kits/${kit.id}/edit`" class="btn btn-primary mt-4">
            Add Templates
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
