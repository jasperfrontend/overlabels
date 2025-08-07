<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import Heading from '@/components/Heading.vue';
import { type BreadcrumbItem } from '@/types';
import { EyeIcon, CodeSquareIcon, CalendarIcon, ClockArrowUpIcon, AlarmClockOffIcon  } from 'lucide-vue-next';

const props = defineProps({
  tokens: Array,
});

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Overlay Access Tokens',
    href: '/tokens',
  },
];


const showCreateModal = ref(false);
const showTokenModal = ref(false);
const newToken = ref('');
const ipInput = ref();

const form = ref({
  name: '',
  expires_at: null,
  allowed_ips: [],
  abilities: ['read'],
});

const parseIps = () => {
  if (ipInput.value) {
    form.value.allowed_ips = ipInput.value
      .split(',')
      //@ts-ignore
      .map(ip => ip.trim())
      //@ts-ignore
      .filter(ip => ip);
  } else {
    form.value.allowed_ips = [];
  }
};

const createToken = async () => {
  try {
    const response = await axios.post('/tokens', form.value);
    newToken.value = response.data.plain_token;
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

const revokeToken = async (token:string) => {
  if (!confirm('Are you sure you want to revoke this token?')) return;

  try {
    await axios.post(`/tokens/${token.id}/revoke`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to revoke token:', error);
    alert('Failed to revoke token');
  }
};

const deleteToken = async (token:string) => {
  if (!confirm('Are you sure you want to delete this token? This cannot be undone.')) return;

  try {
    await axios.delete(`/tokens/${token.id}`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to delete token:', error);
    alert('Failed to delete token');
  }
};

const showUsage = (token:string) => {
  router.visit(`/tokens/${token.id}/usage`);
};

const formatDate = (date:string) => {
  return new Date(date).toLocaleString();
};
</script>

<template>
  <Head title="Your Secure Tokens" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <div class="flex justify-between items-center mb-6">
        <Heading title="Overlay Access Tokens" description="Manage your access tokens for your overlays." />
        <button
          @click="showCreateModal = true"
          class="btn btn-primary"
        >
          Create New Token
        </button>
      </div>

      <!-- Token List -->
      <div class="space-y-4 bg-accent/20 overflow-hidden shadow-sm sm:rounded-lg">
        <div
          v-for="token in tokens"
          :key="token.id"
          class="border rounded-2xl p-4"
        >
          <div class="flex justify-self-start gap-4 items-start">
            <div>
              <h3 class="font-semibold">{{ token.name }}</h3>
            </div>
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50">
                <CodeSquareIcon class="inline-block w-4 h-4 mr-1 -mt-0.5" />
                Prefix: <code class="bg-accent-foreground/10 px-1">{{ token.prefix }}...</code>
              </p>
            </div>
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50" title="Access Count">
                <EyeIcon class="inline-block w-4 h-4 mr-1 -mt-0.5" />
                {{ token.access_count }} view{{ token.access_count === 1 ? '' : 's' }}
              </p>
            </div>

            <div class="space-x-2 ml-auto flex">

              <button
                @click="showUsage(token)"
                class="btn btn-sm btn-secondary"
              >
                View Usage
              </button>
              <button
                v-if="token.is_active"
                @click="revokeToken(token)"
                class="btn btn-sm btn-warning"
              >
                Revoke
              </button>
              <button
                @click="deleteToken(token)"
                class="btn btn-sm btn-danger"
              >
                Delete
              </button>
            </div>
          </div>
          <div class="flex justify-self-start gap-4 mt-2 items-start">
            <div class="mt-0.5">
              <p class="text-sm text-accent-foreground/50">
                <CalendarIcon class="inline-block w-4 h-4 mr-1 -mt-0.5" />
                Created: {{ formatDate(token.created_at) }}
              </p>
            </div>
            <div class="mt-0.5">
              <p v-if="token.expires_at" class="text-sm text-accent-foreground/50">
                <AlarmClockOffIcon class="inline-block w-4 h-4 mr-1 -mt-0.5" />
                Expires: {{ formatDate(token.expires_at) }}
              </p>
            </div>
            <div class="mt-0.5">
              <p v-if="token.last_used_at" class="text-sm text-accent-foreground/50">
                <ClockArrowUpIcon class="inline-block w-4 h-4 mr-1 -mt-0.5" />
                Last viewed: {{ formatDate(token.last_used_at) }}
              </p>

            </div>

          </div>
          <div
            v-if="!token.is_active"
            class="mt-2 text-sm text-red-600 font-semibold"
          >
            REVOKED
          </div>
        </div>
      </div>

    </div>

    <!-- Create Token Modal -->
    <Modal :show="showCreateModal" @close="showCreateModal = false" class="z-50">
      <div class="p-6">
        <h2 class="text-lg font-semibold mb-4">Create New Access Token</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium" for="token-name">Token Name</label>
            <input
              v-model="form.name"
              type="text"
              id="token-name"
              class="mt-1 p-2 border block w-full rounded-md"
              placeholder="My OBS Stream"
            />
          </div>

          <div>
            <label class="block text-sm font-medium">Expires At (Optional)</label>
            <input
              v-model="form.expires_at"
              type="datetime-local"
              class="mt-1 p-2 border block w-full rounded-md"
            />
          </div>

          <div>
            <label class="block text-sm font-medium">Allowed IPs (Optional)</label>
            <input
              v-model="ipInput"
              type="text"
              class="mt-1 p-2 border block w-full rounded-md"
              placeholder="192.168.1.1, 10.0.0.1"
              @input="parseIps"
            />
            <p class="text-xs text-gray-500 mt-1">Comma-separated IP addresses</p>
          </div>

          <div>
            <label class="block text-sm font-medium">Abilities</label>
            <div class="space-y-2 mt-2">
              <label class="flex items-center">
                <input
                  type="checkbox"
                  value="read"
                  v-model="form.abilities"
                  class="rounded"
                />
                <span class="ml-2">Read</span>
              </label>
              <label class="flex items-center">
                <input
                  type="checkbox"
                  value="write"
                  v-model="form.abilities"
                  class="rounded"
                />
                <span class="ml-2">Write</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-2">
            <button
              @click="showCreateModal = false"
              class="px-4 py-2 border rounded-md"
            >
              Cancel
            </button>
            <button
              @click="createToken"
              :disabled="!form.name"
              class="px-4 py-2 bg-blue-500 text-white rounded-md disabled:opacity-50"
            >
              Create Token
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Token Created Modal -->
    <Modal :show="showTokenModal" @close="showTokenModal = false">
      <div class="p-6">
        <h2 class="text-lg font-semibold mb-4">Token Created Successfully!</h2>
        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-4">
          <p class="text-lg text-yellow-800">
            Copy this token now. It won't be shown again!
          </p>
        </div>
        <div class="bg-accent p-4 rounded-md mb-4">
          <code class="text-sm break-all">{{ newToken }}</code>
        </div>
        <button
          @click="copyToken(); showTokenModal = false"
          class="w-full bg-blue-500 text-white py-2 rounded-md cursor-pointer"
        >
          Copy Token
        </button>
      </div>
    </Modal>
  </AppLayout>
</template>


