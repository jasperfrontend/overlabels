<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';

interface User {
  id: number;
  name: string;
  email: string;
  twitch_id: string | null;
  role: string;
  is_system_user: boolean;
  deleted_at: string | null;
  created_at: string;
  overlay_templates_count: number;
  overlay_access_tokens_count: number;
}

interface Paginator {
  data: User[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
  users: Paginator;
  filters: {
    search?: string;
    role?: string;
    include_deleted?: boolean;
  };
}>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Users', href: route('admin.users.index') },
];

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const includeDeleted = ref(props.filters.include_deleted ?? false);

let debounce: ReturnType<typeof setTimeout>;

function applyFilters() {
  router.get(route('admin.users.index'), {
    search: search.value || undefined,
    role: role.value || undefined,
    include_deleted: includeDeleted.value || undefined,
  }, { preserveState: true, replace: true });
}

watch([search, role, includeDeleted], () => {
  clearTimeout(debounce);
  debounce = setTimeout(applyFilters, 400);
});
</script>

<template>
  <Head><title>Admin — Users</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Users</h1>
        <span class="text-sm text-muted-foreground">{{ users.total }} total</span>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-2">
        <Input v-model="search" placeholder="Search name, email, twitch_id…" class="w-64" />
        <select v-model="role" class="rounded border px-3 py-1.5 text-sm bg-background">
          <option value="">All roles</option>
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="includeDeleted" />
          Include deleted
        </label>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded border">
        <table class="w-full text-sm">
          <thead class="bg-muted text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">Name</th>
              <th class="px-3 py-2">Twitch ID</th>
              <th class="px-3 py-2">Role</th>
              <th class="px-3 py-2">Templates</th>
              <th class="px-3 py-2">Tokens</th>
              <th class="px-3 py-2">Joined</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users.data" :key="user.id" class="border-t" :class="{ 'opacity-50': user.deleted_at }">
              <td class="px-3 py-2">
                <div class="font-medium">{{ user.name }}</div>
                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
              </td>
              <td class="px-3 py-2 text-muted-foreground">{{ user.twitch_id ?? '—' }}</td>
              <td class="px-3 py-2">
                <Badge :variant="user.role === 'admin' ? 'default' : 'secondary'">{{ user.role }}</Badge>
                <Badge v-if="user.is_system_user" variant="outline" class="ml-1">system</Badge>
                <Badge v-if="user.deleted_at" variant="destructive" class="ml-1">deleted</Badge>
              </td>
              <td class="px-3 py-2">{{ user.overlay_templates_count }}</td>
              <td class="px-3 py-2">{{ user.overlay_access_tokens_count }}</td>
              <td class="px-3 py-2 text-muted-foreground text-xs">{{ user.created_at }}</td>
              <td class="px-3 py-2">
                <a :href="route('admin.users.show', user.id)" class="text-primary text-xs hover:underline">View</a>
              </td>
            </tr>
            <tr v-if="users.data.length === 0">
              <td colspan="7" class="px-3 py-6 text-center text-muted-foreground">No users found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex gap-1">
        <template v-for="link in users.links" :key="link.label">
          <a
            v-if="link.url"
            :href="link.url"
            class="rounded border px-3 py-1 text-sm"
            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
            v-html="link.label"
          />
          <span v-else class="rounded border px-3 py-1 text-sm opacity-40" v-html="link.label" />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
