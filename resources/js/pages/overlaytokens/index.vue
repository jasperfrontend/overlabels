<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem } from '@/types';
import { EyeIcon, CodeSquareIcon, CalendarIcon, ClockArrowUpIcon, AlarmClockOffIcon } from 'lucide-vue-next';

/** Types */
type TokenAbility = 'read' | 'write';

interface Token {
  id: number;
  name: string;
  prefix: string;
  plain_token?: string | null; // only on "create" response
  created_at: string;
  expires_at: string | null;
  last_used_at: string | null;
  is_active: boolean;
  access_count: number;
  abilities: TokenAbility[];
}

const { tokens } = defineProps<{ tokens: Token[] }>();

/** UI */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Overlay Access Tokens', href: '/tokens' },
];

/** Create flow */
const showCreateModal = ref(false);
const showTokenModal = ref(false);
const newToken = ref('');
const ipInput = ref<string>(''); // bound to text input

const form = ref<{
  name: string;
  expires_at: string | null;     // datetime-local string or null
  allowed_ips: string[];
  abilities: TokenAbility[];
}>({
  name: '',
  expires_at: null,
  allowed_ips: [],
  abilities: ['read'],
});

/** Keep form.allowed_ips in sync with ipInput */
watch(ipInput, (v) => {
  form.value.allowed_ips = v
    .split(',')
    .map(s => s.trim())
    .filter(Boolean);
});

const createToken = async () => {
  try {
    const { data } = await axios.post('/tokens', form.value);
    newToken.value = data.plain_token ?? '';
    showCreateModal.value = false;
    showTokenModal.value = true;
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to create token:', error);
    alert('Failed to create token');
  }
};

const copyToken = () => {
  navigator.clipboard.writeText(newToken.value);
  alert('Token copied to clipboard!');
};

const revokeToken = async (t: Token) => {
  if (!confirm('Are you sure you want to revoke this token?')) return;
  try {
    await axios.post(`/tokens/${t.id}/revoke`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to revoke token:', error);
    alert('Failed to revoke token');
  }
};

const deleteToken = async (t: Token) => {
  if (!confirm('Are you sure you want to delete this token? This cannot be undone.')) return;
  try {
    await axios.delete(`/tokens/${t.id}`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to delete token:', error);
    alert('Failed to delete token');
  }
};

const showUsage = (t: Token) => {
  router.visit(`/tokens/${t.id}/usage`);
};

const formatDate = (date: string | null | undefined) =>
  date ? new Date(date).toLocaleString() : '-';
</script>

<template>
  <Head title="Your Secure Tokens" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="mb-6 flex items-center justify-between">
        <Heading title="Overlay Access Tokens" description="Manage your access tokens for your overlays." />
        <button @click="showCreateModal = true" class="btn btn-primary">Create New Token</button>
      </div>

      <!-- Token List -->
      <div class="space-y-4 overflow-hidden">
        <div v-for="token in tokens" :key="token.id" class="rounded-2xl border bg-accent/20 p-4">
          <div class="flex items-start gap-4 justify-self-start">
            <div>
              <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ token.name }}</h3>
            </div>
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50">
                <CodeSquareIcon class="-mt-0.5 mr-1 inline-block h-4 w-4" />
                Prefix: <code class="bg-accent-foreground/10 px-1">{{ token.prefix }}...</code>
              </p>
            </div>
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50" title="Access Count">
                <EyeIcon class="-mt-0.5 mr-1 inline-block h-4 w-4" />
                {{ token.access_count }} view{{ token.access_count === 1 ? '' : 's' }}
              </p>
            </div>

            <div class="ml-auto flex space-x-2">
              <button v-if="token.is_active" @click="revokeToken(token)" class="btn btn-sm btn-warning">Revoke</button>
              <button @click="deleteToken(token)" class="btn btn-sm btn-danger">Delete</button>
            </div>
          </div>

          <div class="mt-2 flex items-start gap-4 justify-self-start">
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50">
                <CalendarIcon class="-mt-0.5 mr-1 inline-block h-4 w-4" />
                Created: {{ formatDate(token.created_at) }}
              </p>
            </div>
            <div class="mt-0.5">
              <p v-if="token.expires_at" class="text-sm text-accent-foreground/50">
                <AlarmClockOffIcon class="-mt-0.5 mr-1 inline-block h-4 w-4" />
                Expires: {{ formatDate(token.expires_at) }}
              </p>
            </div>
            <div class="mt-0.5">
              <p v-if="token.last_used_at" class="text-sm text-accent-foreground/50">
                <ClockArrowUpIcon class="-mt-0.5 mr-1 inline-block h-4 w-4" />
                Last viewed: {{ formatDate(token.last_used_at) }}
              </p>
            </div>
          </div>

          <div v-if="!token.is_active" class="mt-2 text-sm font-semibold text-red-600">REVOKED</div>
        </div>
      </div>
    </div>

    <!-- Create Token Modal -->
    <Modal :show="showCreateModal" @close="showCreateModal = false" closeable class="z-50 margin-auto">
      <div class="p-6">
        <h2 class="mb-4 text-lg font-semibold">Create New Access Token</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium" for="token-name">Token Name</label>
            <input v-model="form.name" type="text" id="token-name" class="mt-1 block w-full rounded-md border p-2" placeholder="My OBS Stream" />
          </div>

          <div>
            <label class="block text-sm font-medium">Expires At (Optional)</label>
            <input v-model="form.expires_at" type="datetime-local" class="mt-1 block w-full rounded-md border p-2" />
          </div>

          <div>
            <label class="block text-sm font-medium">Allowed IPs (Optional)</label>
            <input
              v-model="ipInput"
              type="text"
              class="mt-1 block w-full rounded-md border p-2"
              placeholder="192.168.1.1, 10.0.0.1"
            />
            <p class="mt-1 text-xs text-gray-500">Comma-separated IP addresses</p>
          </div>

          <div>
            <label class="block text-sm font-medium">Abilities</label>
            <div class="mt-2 space-y-2">
              <label class="flex items-center">
                <input type="checkbox" value="read" v-model="form.abilities" class="rounded" />
                <span class="ml-2">Read</span>
              </label>
              <label class="flex items-center">
                <input type="checkbox" value="write" v-model="form.abilities" class="rounded" />
                <span class="ml-2">Write</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-2">
            <button @click="showCreateModal = false" class="btn btn-cancel">Cancel</button>
            <button @click="createToken" :disabled="!form.name" class="btn btn-primary">
              Create Token
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Token Created Modal -->
    <Modal :show="showTokenModal" @close="showTokenModal = false">
      <div class="p-6">
        <h2 class="mb-4 text-lg font-semibold">Token Created Successfully!</h2>
        <div class="mb-4 rounded-md border border-yellow-200 bg-yellow-50 p-4">
          <p class="text-lg text-yellow-800">Copy this token now. It won't be shown again!</p>
        </div>
        <div class="mb-4 rounded-md bg-accent p-4">
          <code class="text-sm break-all">{{ newToken }}</code>
        </div>
        <button
          @click="
            copyToken();
            showTokenModal = false;
          "
          class="w-full cursor-pointer rounded-md bg-blue-500 py-2 text-white"
        >
          Copy Token
        </button>
      </div>
    </Modal>
  </AppLayout>
</template>
