<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { ref } from 'vue';

interface SessionUser {
  id: number;
  name: string;
  email: string;
  twitch_id: string | null;
}

interface Session {
  id: string;
  user_id: number | null;
  ip_address: string | null;
  last_activity: number;
  last_activity_human: string;
  user: SessionUser | null;
  is_user_banned: boolean;
  is_ip_banned: boolean;
}

interface Paginator {
  data: Session[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

interface IpLocation {
  ip: string;
  countryName: string | null;
  countryCode: string | null;
  regionName: string | null;
  regionCode: string | null;
  cityName: string | null;
  zipCode: string | null;
  latitude: string | null;
  longitude: string | null;
  timezone: string | null;
  currencyCode: string | null;
  isp: string | null;
  org: string | null;
  asName: string | null;
  query: string | null;
}

defineProps<{ sessions: Paginator }>();

const breadcrumbs = [
  { title: 'Admin', href: route('admin.dashboard') },
  { title: 'Sessions', href: route('admin.sessions.index') },
];

function invalidate(id: string) {
  if (confirm('Invalidate this session?')) {
    router.delete(route('admin.sessions.destroy', id));
  }
}

// IP Lookup
const ipDialogOpen = ref(false);
const ipLoading = ref(false);
const ipError = ref<string | null>(null);
const ipLocation = ref<IpLocation | null>(null);

async function lookupIp(ip: string) {
  ipDialogOpen.value = true;
  ipLoading.value = true;
  ipError.value = null;
  ipLocation.value = null;

  try {
    const response = await fetch(route('admin.sessions.ip-lookup', ip));
    if (!response.ok) {
      const data = await response.json();
      ipError.value = data.error || 'Failed to look up IP address.';
      return;
    }
    ipLocation.value = await response.json();
  } catch {
    ipError.value = 'Network error while looking up IP address.';
  } finally {
    ipLoading.value = false;
  }
}

// Ban from session
const banTarget = ref<Session | null>(null);
const banForm = useForm({
  session_id: '',
  ban_user: false,
  ban_ip: false,
  comment: '',
  duration: 'permanent',
});

function openBanForm(session: Session, banUser: boolean, banIp: boolean) {
  banTarget.value = session;
  banForm.session_id = session.id;
  banForm.ban_user = banUser;
  banForm.ban_ip = banIp;
  banForm.comment = '';
  banForm.duration = 'permanent';
}

function closeBanForm() {
  banTarget.value = null;
}

function submitBan() {
  banForm.post(route('admin.bans.from-session'), {
    preserveScroll: true,
    onSuccess: () => closeBanForm(),
  });
}

const durations = [
  { value: '1h', label: '1 hour' },
  { value: '6h', label: '6 hours' },
  { value: '24h', label: '24 hours' },
  { value: '7d', label: '7 days' },
  { value: '30d', label: '30 days' },
  { value: 'permanent', label: 'Permanent' },
];

const locationFields: { key: keyof IpLocation; label: string }[] = [
  { key: 'ip', label: 'IP Address' },
  { key: 'cityName', label: 'City' },
  { key: 'regionName', label: 'Region' },
  { key: 'countryName', label: 'Country' },
  { key: 'countryCode', label: 'Country Code' },
  { key: 'zipCode', label: 'Zip Code' },
  { key: 'latitude', label: 'Latitude' },
  { key: 'longitude', label: 'Longitude' },
  { key: 'timezone', label: 'Timezone' },
  { key: 'currencyCode', label: 'Currency' },
  { key: 'isp', label: 'ISP' },
  { key: 'org', label: 'Organization' },
  { key: 'asName', label: 'AS' },
];
</script>

<template>
  <Head><title>Admin — Sessions</title></Head>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-4 p-4">
      <PageHeader title="Active Sessions" title-class="text-2xl font-bold">
        <template #actions>
          <span class="text-sm text-muted-foreground">{{ sessions.meta.total }} total</span>
        </template>
      </PageHeader>

      <!-- Ban Form Dialog -->
      <div v-if="banTarget" class="rounded border border-destructive p-4 space-y-3 bg-destructive/5">
        <h3 class="text-sm font-medium">
          Ban {{ banForm.ban_user && banTarget.user ? banTarget.user.name : '' }}
          {{ banForm.ban_user && banForm.ban_ip ? ' + ' : '' }}
          {{ banForm.ban_ip ? banTarget.ip_address : '' }}
        </h3>
        <div class="flex flex-wrap gap-3">
          <Input v-model="banForm.comment" placeholder="Reason (optional)" class="w-64" />
          <select v-model="banForm.duration" class="rounded border border-sidebar px-3 py-1.5 text-sm bg-background">
            <option v-for="d in durations" :key="d.value" :value="d.value">{{ d.label }}</option>
          </select>
          <button @click="submitBan" :disabled="banForm.processing" class="rounded bg-destructive px-3 py-1.5 text-destructive-foreground text-sm hover:bg-destructive/90 disabled:opacity-50">
            Confirm Ban
          </button>
          <button @click="closeBanForm" class="rounded border border-sidebar px-3 py-1.5 text-sm hover:bg-muted">Cancel</button>
        </div>
        <div v-if="banForm.errors.ban_user || banForm.errors.ban_ip || banForm.errors.session_id" class="text-sm text-destructive">
          {{ banForm.errors.ban_user || banForm.errors.ban_ip || banForm.errors.session_id }}
        </div>
      </div>

      <!-- Card view (< lg) -->
      <div class="lg:hidden space-y-2">
        <EmptyState v-if="sessions.data.length === 0" message="No sessions found." />
        <div v-for="session in sessions.data" :key="`card-${session.id}`" class="rounded border p-3 text-sm">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="font-medium flex items-center gap-2">
                <a v-if="session.user" :href="route('admin.users.show', session.user.id)" class="hover:underline">{{ session.user.name }}</a>
                <span v-else class="text-muted-foreground">Guest</span>
                <Badge v-if="session.is_user_banned" variant="destructive" class="text-[10px]">Banned</Badge>
                <Badge v-if="session.is_ip_banned" variant="destructive" class="text-[10px]">IP Banned</Badge>
              </div>
              <div class="font-mono text-xs text-muted-foreground">{{ session.id.substring(0, 24) }}…</div>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
              <button @click="invalidate(session.id)" class="text-xs text-destructive hover:underline">Invalidate</button>
              <button v-if="session.user && !session.is_user_banned" @click="openBanForm(session, true, false)" class="text-xs text-destructive hover:underline">Ban User</button>
              <button v-if="session.ip_address && !session.is_ip_banned" @click="openBanForm(session, false, true)" class="text-xs text-destructive hover:underline">Ban IP</button>
            </div>
          </div>
          <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <button v-if="session.ip_address" @click="lookupIp(session.ip_address!)" class="font-mono hover:underline">{{ session.ip_address }}</button>
            <span v-else>No IP</span>
            <span>Active {{ session.last_activity_human }}</span>
          </div>
        </div>
      </div>

      <!-- Table (≥ lg) -->
      <div class="hidden lg:block overflow-x-auto rounded border border-sidebar">
        <table class="w-full text-sm">
          <thead class="bg-card text-left text-muted-foreground">
            <tr>
              <th class="px-3 py-2">User</th>
              <th class="px-3 py-2">IP Address</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Last Activity</th>
              <th class="px-3 py-2">Session ID</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="session in sessions.data" :key="session.id" class="border-t border-sidebar">
              <td class="px-3 py-2">
                <div v-if="session.user">
                  <a :href="route('admin.users.show', session.user.id)" class="hover:underline">{{ session.user.name }}</a>
                </div>
                <span v-else class="text-muted-foreground">Guest</span>
              </td>
              <td class="px-3 py-2 font-mono text-xs">
                <button v-if="session.ip_address" @click="lookupIp(session.ip_address!)" class="text-muted-foreground cursor-pointer hover:text-foreground hover:underline">{{ session.ip_address }}</button>
                <span v-else class="text-muted-foreground">&mdash;</span>
              </td>
              <td class="px-3 py-2">
                <div class="flex gap-1">
                  <Badge v-if="session.is_user_banned" variant="destructive" class="text-[10px]">User Banned</Badge>
                  <Badge v-if="session.is_ip_banned" variant="destructive" class="text-[10px]">IP Banned</Badge>
                </div>
              </td>
              <td class="px-3 py-2 text-xs text-muted-foreground">{{ session.last_activity_human }}</td>
              <td class="px-3 py-2 font-mono text-xs text-muted-foreground">{{ session.id.substring(0, 16) }}…</td>
              <td class="px-3 py-2">
                <div class="flex gap-2">
                  <button @click="invalidate(session.id)" class="text-xs text-destructive hover:underline">Invalidate</button>
                  <button v-if="session.user && !session.is_user_banned" @click="openBanForm(session, true, false)" class="text-xs text-destructive hover:underline">Ban User</button>
                  <button v-if="session.ip_address && !session.is_ip_banned" @click="openBanForm(session, false, true)" class="text-xs text-destructive hover:underline">Ban IP</button>
                </div>
              </td>
            </tr>
            <EmptyState v-if="sessions.data.length === 0" :colspan="6" message="No sessions found." />
          </tbody>
        </table>
      </div>
    </div>

    <!-- IP Location Dialog -->
    <Dialog v-model:open="ipDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>IP Location Lookup</DialogTitle>
          <DialogDescription>Geolocation data from ip-api.com</DialogDescription>
        </DialogHeader>

        <div v-if="ipLoading" class="py-4 text-center text-sm text-muted-foreground">Loading...</div>

        <div v-else-if="ipError" class="py-4 text-center text-sm text-destructive">{{ ipError }}</div>

        <div v-else-if="ipLocation" class="space-y-1">
          <div v-for="field in locationFields" :key="field.key" class="flex justify-between gap-4 text-sm border-b border-border py-1.5 last:border-0">
            <span class="text-muted-foreground shrink-0">{{ field.label }}</span>
            <span class="font-mono text-right">{{ ipLocation[field.key] ?? '—' }}</span>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
