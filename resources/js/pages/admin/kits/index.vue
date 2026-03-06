<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Star } from 'lucide-vue-next';

interface Kit {
  id: number;
  title: string;
  is_public: boolean;
  is_starter_kit: boolean;
  fork_count: number;
  created_at: string;
  owner: { id: number; name: string; twitch_id: string | null } | null;
}

defineProps<{ kits: Kit[] }>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Kits', href: route('admin.kits.index') },
];

function setStarter(kit: Kit) {
  router.post(route('admin.kits.set-starter', kit.id));
}
</script>

<template>
  <Head><title>Admin — Kits</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Kits" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ kits.length }} original kits</span>
        </template>
      </PageHeader>

      <p class="text-sm text-muted-foreground">
        The <strong class="text-foreground">starter kit</strong> is automatically forked for every new user during onboarding.
        Only one kit can be the starter kit at a time.
      </p>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="kits.length === 0" message="No original kits found." />
        <div
          v-for="kit in kits"
          :key="kit.id"
          class="rounded border p-3 text-sm"
          :class="kit.is_starter_kit ? 'border-yellow-500/50 bg-yellow-500/5' : ''"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-1.5 font-medium">
                <Star v-if="kit.is_starter_kit" class="h-4 w-4 text-yellow-400 fill-yellow-400" />
                {{ kit.title }}
              </div>
              <div v-if="kit.owner" class="text-xs text-muted-foreground">{{ kit.owner.name }}</div>
            </div>
            <Button v-if="!kit.is_starter_kit" size="sm" variant="outline" @click="setStarter(kit)">
              Set as Starter
            </Button>
            <Badge v-else class="bg-yellow-500/20 text-yellow-300 border-yellow-500/40">Starter Kit</Badge>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <Badge :variant="kit.is_public ? 'default' : 'secondary'">{{ kit.is_public ? 'public' : 'private' }}</Badge>
            <span class="text-xs text-muted-foreground">{{ kit.fork_count }} forks</span>
            <span class="text-xs text-muted-foreground">{{ kit.created_at }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Title</th>
              <th class="px-3 py-2">Owner</th>
              <th class="px-3 py-2">Public</th>
              <th class="px-3 py-2">Forks</th>
              <th class="px-3 py-2">Created</th>
              <th class="px-3 py-2">Starter Kit</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="kit in kits"
              :key="kit.id"
              class="border-t"
              :class="kit.is_starter_kit ? 'bg-yellow-500/5' : ''"
            >
              <td class="px-3 py-2 font-medium">
                <div class="flex items-center gap-1.5">
                  <Star v-if="kit.is_starter_kit" class="h-4 w-4 text-yellow-400 fill-yellow-400 shrink-0" />
                  {{ kit.title }}
                </div>
              </td>
              <td class="px-3 py-2">
                <a v-if="kit.owner" :href="route('admin.users.show', kit.owner.id)" class="hover:underline">
                  {{ kit.owner.name }}
                </a>
                <span v-else class="text-muted-foreground">—</span>
              </td>
              <td class="px-3 py-2">
                <Badge :variant="kit.is_public ? 'default' : 'secondary'">{{ kit.is_public ? 'public' : 'private' }}</Badge>
              </td>
              <td class="px-3 py-2">{{ kit.fork_count }}</td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ kit.created_at }}</td>
              <td class="px-3 py-2">
                <Badge v-if="kit.is_starter_kit" class="bg-yellow-500/20 text-yellow-300 border-yellow-500/40">
                  <Star class="h-3 w-3 fill-yellow-300 mr-1" /> Starter Kit
                </Badge>
                <Button v-else size="sm" variant="outline" @click="setStarter(kit)">
                  Set as Starter
                </Button>
              </td>
            </tr>
            <EmptyState v-if="kits.length === 0" :colspan="6" message="No original kits found." />
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
