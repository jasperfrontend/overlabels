<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import Modal from '@/components/Modal.vue';
import Heading from '@/components/Heading.vue';
import CollectionList from '@/components/CollectionList.vue';
import { type BreadcrumbItem } from '@/types';
import { AlertTriangle } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm, alert } = useConfirm();
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
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Overlay Access Tokens', href: '/tokens' },
];

/** Create flow */
const showCreateModal = ref(false);
const showTokenModal = ref(false);
const newToken = ref('');
const ipInput = ref<string>(''); // bound to text input

const form = ref<{
  name: string;
  expires_at: string | null; // datetime-local string or null
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
    .map((s) => s.trim())
    .filter(Boolean);
});

/**
 * Pull a readable message out of a 422. Laravel names the offending entry by
 * its array index ("The allowed_ips.0 field must be a valid IP address"), which
 * is accurate and useless to a streamer, so the field token is rewritten.
 */
const validationMessage = (error: unknown): string | null => {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) return null;

  const errors = error.response.data?.errors as Record<string, string[]> | undefined;
  if (!errors) return null;

  const messages = Object.values(errors)
    .flat()
    .map((m) => m.replace(/allowed_ips\.\d+/g, 'Allowed IPs').replace(/expires_at/g, 'Expires At'));

  return messages.length > 0 ? messages.join('\n') : null;
};

const createToken = async () => {
  try {
    const { data } = await axios.post('/tokens', form.value);
    newToken.value = data.plain_token ?? '';
    showCreateModal.value = false;
    showTokenModal.value = true;
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to create token:', error);
    // A 422 here is almost always a malformed entry in Allowed IPs, and the
    // generic message left the user with no idea which field was wrong.
    await alert(validationMessage(error) ?? 'Failed to create token');
  }
};

const copyToken = async () => {
  navigator.clipboard.writeText(newToken.value);
  await alert('Token copied to clipboard!');
};

const revokeToken = async (t: Token) => {
  if (!(await confirm({ message: 'Are you sure you want to revoke this token?', confirmLabel: 'Revoke' }))) return;
  try {
    await axios.post(`/tokens/${t.id}/revoke`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to revoke token:', error);
    await alert('Failed to revoke token');
  }
};

const deleteToken = async (t: Token) => {
  if (!(await confirm({ message: 'Are you sure you want to delete this token? This cannot be undone.', confirmLabel: 'Delete' }))) return;
  try {
    await axios.delete(`/tokens/${t.id}`);
    router.reload({ only: ['tokens'] });
  } catch (error) {
    console.error('Failed to delete token:', error);
    await alert('Failed to delete token');
  }
};

// const showUsage = (t: Token) => {
//   router.visit(`/tokens/${t.id}/usage`);
// };

const page = usePage();
const userLocale = computed<string | undefined>(() => {
  const user = (page.props as any)?.auth?.user;
  return user?.locale || undefined;
});

const formatDate = (date: string | null | undefined) => (date ? new Date(date).toLocaleString(userLocale.value) : '-');
</script>

<template>
  <Head title="Overlay Access Tokens" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <SettingsLayout>
      <div>
        <div class="mb-6 flex items-center justify-between">
          <Heading title="Overlay Access Tokens" description="Manage your access tokens for your overlays." />
          <button @click="showCreateModal = true" class="btn btn-primary">Create Token</button>
        </div>

        <!-- Token list: same row as /triggers. Rows are not navigable - a token has no page of its own. -->
        <CollectionList
          :items="tokens"
          :item-key="(t) => t.id"
          :row-class="(t) => (t.is_active ? undefined : 'border-l-amber-400')"
          empty-message="No tokens yet. Create one to put an overlay in OBS."
          empty-dashed
        >
          <template #item="{ item: token }">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
              <span class="font-medium text-foreground">{{ token.name }}</span>
              <span
                class="hidden rounded-full border border-dashed border-violet-300/30 px-2 py-0.5 font-mono text-xs text-slate-500 sm:inline dark:text-slate-400"
              >
                {{ token.prefix }}...
              </span>
            </div>

            <div class="mt-1 text-sm text-foreground">
              {{ token.access_count }} view{{ token.access_count === 1 ? '' : 's' }}
              <span class="text-muted-foreground"> · Created {{ formatDate(token.created_at) }}</span>
              <span v-if="token.expires_at" class="text-muted-foreground"> · Expires {{ formatDate(token.expires_at) }}</span>
              <span v-if="token.last_used_at" class="text-muted-foreground"> · Last viewed {{ formatDate(token.last_used_at) }}</span>
            </div>

            <div v-if="!token.is_active" class="mt-1.5 flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400">
              <AlertTriangle class="mt-px h-3.5 w-3.5 shrink-0" />
              <span>Revoked - this token no longer opens any overlay.</span>
            </div>
          </template>

          <template #actions="{ item: token }">
            <button v-if="token.is_active" @click="revokeToken(token)" class="btn btn-sm btn-warning">Revoke</button>
            <button @click="deleteToken(token)" class="btn btn-sm btn-danger">Delete</button>
          </template>
        </CollectionList>
        <div class="my-4 bg-background text-sm">
          <Heading
            title="Important!"
            description="Your Overlay Tokens are only shown once during creation. The Prefix you see here is just for reference. Create a new Overlay Token if you lost
        access to it, or if you think it may have leaked on stream."
            description-class="text-orange-300"
          />
        </div>
        <div class="my-4 bg-background text-sm">
          <Heading
            title="Treat your Overlay Token like a password."
            description="Don't show Overlay Tokens on stream, don't share your Overlay Tokens with anyone."
            description-class="text-orange-300"
          />
        </div>
      </div>

      <!-- Create Token Modal -->
      <Modal :show="showCreateModal" @close="showCreateModal = false" closeable class="margin-auto z-50">
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
              <input v-model="ipInput" type="text" class="mt-1 block w-full rounded-md border p-2" placeholder="192.168.1.1, 10.0.0.1" />
              <p class="mt-1 text-xs text-gray-500">
                Comma-separated IP addresses. Exact addresses only - ranges like <code>192.168.1.0/24</code> are not supported. Leave this empty
                unless your connection has a fixed IP.
              </p>
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
              <button @click="createToken" :disabled="!form.name" class="btn btn-primary">Create Token</button>
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
    </SettingsLayout>
  </AppLayout>
</template>
