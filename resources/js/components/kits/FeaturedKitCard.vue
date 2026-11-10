<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Eye, Package } from '@lucide/vue';
import { serviceLabel } from '@/utils/services';

// One official ready-made kit (App\Services\Kits\FeaturedKits), shown on the
// kit picker a new account lands on and at the top of the Alerts shelf. The
// button opens the kit page, where its alerts play before anything is copied.
export interface FeaturedKit {
  id: number;
  title: string;
  description: string | null;
  thumbnail: string | null;
  covers: string[];
  alerts: number;
}

defineProps<{ kit: FeaturedKit }>();
</script>

<template>
  <li class="flex flex-col border border-border bg-card">
    <div class="aspect-video w-full overflow-hidden border-b border-border bg-muted">
      <img v-if="kit.thumbnail" :src="kit.thumbnail" :alt="kit.title" class="h-full w-full object-cover" loading="lazy" />
      <div v-else class="flex h-full w-full items-center justify-center"><Package class="size-10 text-muted-foreground" /></div>
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
      <h3 class="text-base font-semibold text-foreground">{{ kit.title }}</h3>
      <p v-if="kit.description" class="line-clamp-3 text-sm text-foreground">{{ kit.description }}</p>
      <p class="text-xs text-muted-foreground">
        {{ kit.alerts }} alert{{ kit.alerts === 1 ? '' : 's'
        }}<template v-if="kit.covers.length"> for {{ kit.covers.map(serviceLabel).join(', ') }}</template>
      </p>
      <div class="mt-auto flex pt-2">
        <Link :href="`/kits/${kit.id}`" class="btn btn-sm btn-primary cursor-pointer gap-1.5">
          <Eye class="size-4" />
          See it play
        </Link>
      </div>
    </div>
  </li>
</template>
