<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { AdminTemplate } from '@/types';

interface User {
  id: number;
  name: string;
  email: string;
  twitch_id: string | null;
  avatar: string | null;
  role: string;
  is_system_user: boolean;
  deleted_at: string | null;
  created_at: string;
  onboarded_at: string | null;
}

interface Token {
  id: number;
  name: string;
  token_prefix: string;
  is_active: boolean;
  expires_at: string | null;
  access_count: number;
  last_used_at: string | null;
}

interface AuditEntry {
  id: number;
  action: string;
  metadata: Record<string, unknown> | null;
  ip_address: string | null;
  created_at: string;
  admin: { id: number; name: string } | null;
}

const props = defineProps<{
  user: User;
  recentTemplates: AdminTemplate[];
  accessTokens: Token[];
  recentAuditEntries: AuditEntry[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id);

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Users', href: route('admin.users.index') },
  { title: props.user.name, href: route('admin.users.show', props.user.id) },
];

// Role form
const roleForm = useForm({ role: props.user.role });
function submitRole() {
  roleForm.patch(route('admin.users.role', props.user.id));
}

// Delete form
const deleteStrategy = ref<'assign_ghost' | 'delete_content'>('assign_ghost');
const showDeleteConfirm = ref(false);
function submitDelete() {
  router.delete(route('admin.users.destroy', props.user.id), {
    data: { strategy: deleteStrategy.value },
  });
}

// Restore
function restore() {
  router.post(route('admin.users.restore', props.user.id));
}
</script>

<template>
  <Head><title>Admin — {{ user.name }}</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <!-- Profile header -->
      <div class="flex items-center gap-4">
        <img v-if="user.avatar" :src="user.avatar" class="h-14 w-14 rounded-full" alt="" />
        <div v-else class="flex h-14 w-14 items-center justify-center rounded-full bg-muted text-2xl font-bold">
          {{ user.name[0] }}
        </div>
        <div>
          <h1 class="text-2xl font-bold">{{ user.name }}</h1>
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <span>{{ user.email }}</span>
            <span v-if="user.twitch_id">· {{ user.twitch_id }}</span>
            <Badge :variant="user.role === 'admin' ? 'default' : 'secondary'">{{ user.role }}</Badge>
            <Badge v-if="user.is_system_user" variant="outline">system</Badge>
            <Badge v-if="user.deleted_at" variant="destructive">deleted</Badge>
          </div>
        </div>
      </div>

      <Tabs default-value="templates">
        <TabsList>
          <TabsTrigger value="templates">Templates ({{ recentTemplates.length }})</TabsTrigger>
          <TabsTrigger value="tokens">Tokens ({{ accessTokens.length }})</TabsTrigger>
          <TabsTrigger value="audit">Audit</TabsTrigger>
          <TabsTrigger value="admin">Admin Actions</TabsTrigger>
        </TabsList>

        <TabsContent value="templates" class="mt-4">
          <table class="w-full text-sm border rounded">
            <thead class="bg-muted text-left text-muted-foreground">
              <tr>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">Public</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in recentTemplates" :key="t.id" class="border-t">
                <td class="px-3 py-2">
                  <a :href="route('admin.templates.show', t.id)" class="hover:underline">{{ t.name }}</a>
                </td>
                <td class="px-3 py-2"><Badge variant="outline">{{ t.type }}</Badge></td>
                <td class="px-3 py-2">{{ t.is_public ? 'Yes' : 'No' }}</td>
                <td class="px-3 py-2 text-muted-foreground">{{ t.created_at }}</td>
              </tr>
              <tr v-if="recentTemplates.length === 0">
                <td colspan="4" class="px-3 py-4 text-center text-muted-foreground">No templates.</td>
              </tr>
            </tbody>
          </table>
        </TabsContent>

        <TabsContent value="tokens" class="mt-4">
          <table class="w-full text-sm border rounded">
            <thead class="bg-muted text-left text-muted-foreground">
              <tr>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Prefix</th>
                <th class="px-3 py-2">Active</th>
                <th class="px-3 py-2">Uses</th>
                <th class="px-3 py-2">Expires</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in accessTokens" :key="t.id" class="border-t">
                <td class="px-3 py-2">{{ t.name }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ t.token_prefix }}</td>
                <td class="px-3 py-2"><Badge :variant="t.is_active ? 'default' : 'secondary'">{{ t.is_active ? 'active' : 'inactive' }}</Badge></td>
                <td class="px-3 py-2">{{ t.access_count }}</td>
                <td class="px-3 py-2 text-muted-foreground">{{ t.expires_at ?? 'Never' }}</td>
              </tr>
              <tr v-if="accessTokens.length === 0">
                <td colspan="5" class="px-3 py-4 text-center text-muted-foreground">No tokens.</td>
              </tr>
            </tbody>
          </table>
        </TabsContent>

        <TabsContent value="audit" class="mt-4">
          <div class="space-y-2">
            <div v-for="entry in recentAuditEntries" :key="entry.id" class="rounded border p-3 text-sm">
              <div class="flex items-start justify-between">
                <div>
                  <span class="font-medium">{{ entry.admin?.name ?? 'Unknown' }}</span>
                  <span class="ml-1 text-muted-foreground">{{ entry.action }}</span>
                </div>
                <span class="text-xs text-muted-foreground">{{ entry.created_at }}</span>
              </div>
              <pre v-if="entry.metadata" class="mt-1 text-xs text-muted-foreground">{{ JSON.stringify(entry.metadata, null, 2) }}</pre>
            </div>
            <p v-if="recentAuditEntries.length === 0" class="text-sm text-muted-foreground">No audit entries.</p>
          </div>
        </TabsContent>

        <TabsContent value="admin" class="mt-4 space-y-6">
          <!-- Role -->
          <Card v-if="!user.is_system_user">
            <CardHeader><CardTitle>Change Role</CardTitle></CardHeader>
            <CardContent>
              <form @submit.prevent="submitRole" class="flex items-center gap-3">
                <select v-model="roleForm.role" class="rounded border px-3 py-1.5 text-sm bg-background"
                  :disabled="user.id === currentUserId">
                  <option value="user">user</option>
                  <option value="admin">admin</option>
                </select>
                <Button type="submit" size="sm" :disabled="roleForm.processing || user.id === currentUserId">Save</Button>
                <p v-if="user.id === currentUserId" class="text-xs text-muted-foreground">Cannot change your own role.</p>
                <p v-if="roleForm.errors.role" class="text-xs text-destructive">{{ roleForm.errors.role }}</p>
              </form>
            </CardContent>
          </Card>

          <!-- Impersonate -->
          <Card v-if="!user.is_system_user && !user.deleted_at && user.id !== currentUserId">
            <CardHeader><CardTitle>Impersonate</CardTitle></CardHeader>
            <CardContent>
              <p class="mb-3 text-sm text-muted-foreground">Log in as this user to debug issues. A banner will appear while impersonating.</p>
              <Button variant="outline" size="sm" @click="router.post(route('admin.impersonate.start', user.id))">
                Login as {{ user.name }}
              </Button>
            </CardContent>
          </Card>

          <!-- Restore -->
          <Card v-if="user.deleted_at">
            <CardHeader><CardTitle>Restore User</CardTitle></CardHeader>
            <CardContent>
              <Button variant="outline" size="sm" @click="restore">Restore Account</Button>
            </CardContent>
          </Card>

          <!-- Delete -->
          <Card v-if="!user.deleted_at && !user.is_system_user" class="border-destructive">
            <CardHeader><CardTitle class="text-destructive">Danger Zone</CardTitle></CardHeader>
            <CardContent class="space-y-3">
              <p class="text-sm text-muted-foreground">Deleting this user is soft-reversible (they can be restored). Choose a content strategy:</p>
              <label class="flex items-start gap-2 text-sm">
                <input type="radio" v-model="deleteStrategy" value="assign_ghost" />
                <div>
                  <div class="font-medium">Assign to Ghost User</div>
                  <div class="text-xs text-muted-foreground">Templates, kits, and tags are reassigned. Content is preserved.</div>
                </div>
              </label>
              <label class="flex items-start gap-2 text-sm">
                <input type="radio" v-model="deleteStrategy" value="delete_content" />
                <div>
                  <div class="font-medium">Keep content in place</div>
                  <div class="text-xs text-muted-foreground">User is soft-deleted. Content remains owned by them until purged.</div>
                </div>
              </label>
              <div v-if="!showDeleteConfirm">
                <Button variant="destructive" size="sm" @click="showDeleteConfirm = true">Delete User</Button>
              </div>
              <div v-else class="space-y-2">
                <p class="text-sm font-medium text-destructive">Are you sure? This will delete the user (soft).</p>
                <div class="flex gap-2">
                  <Button variant="destructive" size="sm" @click="submitDelete">Yes, delete</Button>
                  <Button variant="outline" size="sm" @click="showDeleteConfirm = false">Cancel</Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </AppLayout>
</template>
