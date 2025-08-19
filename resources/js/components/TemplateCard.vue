<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Globe, Lock, Eye, GitFork, AlertCircle, Layers } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

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

const props = defineProps<{
  template: Template;
  showOwner?: boolean;
  currentUserId?: number;
}>();

const isOwnTemplate = props.currentUserId && props.template.owner?.id === props.currentUserId;

const handleFork = () => {
  router.post(`/templates/${props.template.id}/fork`);
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};
</script>

<template>
  <Card class="group relative overflow-hidden transition-all hover:shadow-lg hover:border-accent h-full flex flex-col">
    <CardHeader class="pb-4">
      <div class="space-y-2">
        <div class="flex items-start justify-between gap-2">
          <CardTitle class="text-base flex-1 min-w-0">
            <Link 
              :href="`/templates/${template.id}/edit`" 
              class="block truncate hover:text-accent-foreground/80 transition-colors"
              :title="template.name"
            >
              {{ template.name }}
            </Link>
          </CardTitle>
          <div class="flex items-center gap-2 flex-shrink-0">
            <Badge 
              :variant="template.type === 'alert' ? 'default' : 'secondary'"
              class="flex items-center gap-1 whitespace-nowrap"
            >
              <component 
                :is="template.type === 'alert' ? AlertCircle : Layers" 
                class="w-3 h-3" 
              />
              {{ template.type }}
            </Badge>
            <div 
              class="p-1.5 rounded-full"
              :class="template.is_public ? 'bg-green-500/10' : 'bg-violet-500/10'"
              :title="template.is_public ? 'Public template' : 'Private template'"
            >
              <component 
                :is="template.is_public ? Globe : Lock" 
                :class="[
                  'w-4 h-4',
                  template.is_public ? 'text-green-600' : 'text-violet-600'
                ]"
              />
            </div>
          </div>
        </div>
        <CardDescription v-if="template.description" class="line-clamp-2 text-sm">
          {{ template.description }}
        </CardDescription>
      </div>
    </CardHeader>
    
    <CardContent class="flex-1 flex flex-col justify-between space-y-4">
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4 text-sm text-muted-foreground">
            <div class="flex items-center gap-1.5">
              <Eye class="w-4 h-4" />
              <span>{{ template.view_count || 0 }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <GitFork class="w-4 h-4" />
              <span>{{ template.fork_count || 0 }}</span>
            </div>
          </div>
          
          <span class="text-xs text-muted-foreground">
            {{ formatDate(template.updated_at) }}
          </span>
        </div>
        
        <div v-if="showOwner && template.owner" class="pt-3 border-t flex items-center gap-2">
          <img 
            v-if="template.owner.avatar"
            :src="template.owner.avatar" 
            :alt="template.owner.name"
            class="w-6 h-6 rounded-full"
          >
          <span class="text-sm text-muted-foreground truncate">
            by {{ template.owner.name }}
          </span>
        </div>
      </div>
      
      <div class="flex gap-2 pt-2">
        <Button 
          v-if="isOwnTemplate"
          size="sm" 
          variant="outline" 
          class="flex-1" 
          asChild
        >
          <Link :href="`/templates/${template.id}/edit`">
            Edit
          </Link>
        </Button>
        <Button 
          v-else-if="template.is_public"
          size="sm" 
          variant="outline" 
          class="flex-1"
          @click="handleFork"
        >
          <GitFork class="w-3 h-3 mr-1" />
          Fork
        </Button>
        <Button 
          size="sm" 
          variant="outline" 
          class="flex-1" 
          asChild
        >
          <Link :href="`/overlay/${template.slug}/public`" target="_blank">
            Preview
          </Link>
        </Button>
      </div>
    </CardContent>
  </Card>
</template>