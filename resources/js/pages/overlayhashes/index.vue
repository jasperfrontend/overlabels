<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Badge from '@/components/ui/badge/Badge.vue';
import { useLinkWarning } from '@/composables/useLinkWarning';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger
} from '@/components/ui/dialog';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
  Copy,
  Plus,
  Trash2,
  RefreshCw,
  Ban,
  ExternalLink,
  AlertTriangle,
  Check,
  Clock,
  ShieldQuestion
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';

interface OverlayHash {
  id: number;
  overlay_name: string;
  description?: string;
  hash_key: string;
  is_active: boolean;
  access_count: number;
  last_accessed_at?: string;
  expires_at?: string;
  overlay_url: string;
  created_at: string;
  is_valid: boolean;
}

defineProps<{
  hashes: OverlayHash[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Secure Overlay Generator',
    href: '/overlay-hashes',
  },
];

const { triggerLinkWarning } = useLinkWarning();
const page = usePage<AppPageProps>();
const toastMessage = ref(null);
const toastType = ref('info');

watch(
  () => page.props.flash?.message,
  (newMessage) => {
    if (newMessage) {
      toastMessage.value = newMessage;
      toastType.value = page.props.flash?.type || 'info';
    }
  },
  { immediate: true },
);

// State for creating a new hash
const showCreateDialog = ref(false);
const createForm = ref({
  overlay_name: '',
  description: '',
  expires_in_days: null as number | null,
  allowed_ips: [] as string[],
});

// State for various operations
const isCreating = ref(false);

// Reset form
const resetCreateForm = () => {
  createForm.value = {
    overlay_name: '',
    description: '',
    expires_in_days: null,
    allowed_ips: [],
  };
};

const openExternalLink = (link: any, target: string ) => {
  window.open(
    link,
    target
  )
};

// Create a new overlay hash
const createHash = async () => {
  if (!createForm.value.overlay_name.trim()) {
    alert('Please enter an overlay name');
    return;
  }

  isCreating.value = true;

  try {
    const response = await fetch('/overlay-hashes', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify(createForm.value),
    });

    const data = await response.json();

    if (response.ok) {
      // Close dialog and reset form
      showCreateDialog.value = false;
      resetCreateForm();

      // Refresh the page to show a new hash
      router.reload({
        only: ['hashes']
      });

      page.props.flash.message = 'Hash created successfully';
      page.props.flash.type = 'success';

    } else {
      console.error('Error creating hash:', data);
      page.props.flash.message = data.message || 'Unknown error';
      page.props.flash.type = 'error';
    }
  } catch (error) {
    console.error('Error creating hash:', error);
    page.props.flash.message = 'Failed to create hash';
    page.props.flash.type = 'error';
  } finally {
    isCreating.value = false;
  }
};

// Revoke hash
const revokeHash = async (hash: OverlayHash) => {
  if (!confirm(`Are you sure you want to revoke "${hash.overlay_name}"? This will make the overlay stop working immediately.`)) {
    return;
  }

  try {
    const response = await fetch(`/overlay-hashes/${hash.id}/revoke`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (response.ok) {
      router.reload({ only: ['hashes'] });
      page.props.flash.message = 'Hash revoked successfully';
      page.props.flash.type = 'success';
    } else {
      console.error('Error revoking hash:', response);
      page.props.flash.message = 'Failed to revoke hash';
      page.props.flash.type = 'error';
    }
  } catch (error) {
    console.error('Error revoking hash:', error);
    page.props.flash.message = 'Failed to revoke hash';
    page.props.flash.type = 'error';
  }
};

// Regenerate hash
const regenerateHash = async (hash: OverlayHash) => {
  if (!confirm(`Are you sure you want to regenerate the hash for "${hash.overlay_name}"? You'll need to update the URL in OBS.`)) {
    return;
  }

  try {
    const response = await fetch(`/overlay-hashes/${hash.id}/regenerate`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (response.ok) {
      router.reload({ only: ['hashes'] });
      page.props.flash.message = 'Hash regenerated successfully';
      page.props.flash.type = 'success';
    } else {
      console.error('Error regenerating hash:', response);
      page.props.flash.message = 'Failed to regenerate hash';
      page.props.flash.type = 'error';
    }
  } catch (error) {
    console.error('Error regenerating hash:', error);
    page.props.flash.message = 'Failed to regenerate hash';
    page.props.flash.type = 'error';
  }
};

// Delete hash
const deleteHash = async (hash: OverlayHash) => {
  if (!confirm(`Are you sure you want to permanently delete "${hash.overlay_name}"? This cannot be undone.`)) {
    return;
  }

  try {
    const response = await fetch(`/overlay-hashes/${hash.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    if (response.ok) {
      router.reload({ only: ['hashes'] });
      page.props.flash.message = 'Hash deleted successfully';
      page.props.flash.type = 'success';
    } else {
      console.error('Error deleting hash:', response);
      page.props.flash.message = 'Failed to delete hash';
      page.props.flash.type = 'error';
    }
  } catch (error) {
    console.error('Error deleting hash:', error);
    page.props.flash.message = 'Failed to delete hash';
    page.props.flash.type = 'error';
  }
};

// Copy URL to clipboard
const copyUrl = async (url: string) => {
  try {
    await navigator.clipboard.writeText(url);
    // You could add a toast notification here
    page.props.flash.message = 'URL copied to clipboard';
    page.props.flash.type = 'success';
  } catch (error) {
    console.error('Failed to copy URL:', error);
    page.props.flash.message = 'Failed to copy URL';
    page.props.flash.type = 'error';
  }
};


// Get status badge props
type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | "success" | "warning" | null | undefined;
interface StatusBadge {
  variant: BadgeVariant;
  icon: any;
  text: string;
  class: string;
}
const getStatusBadge = (hash: OverlayHash): StatusBadge => {
  if (!hash.is_active) {
    return { variant: 'destructive', icon: Ban, text: 'Revoked', class: 'bg-orange-300/20 border-orange-300/40' };
  }
  if (hash.expires_at && new Date(hash.expires_at) < new Date()) {
    return { variant: 'destructive', icon: Clock, text: 'Expired', class: 'bg-red-300/20 border-red-300/40' };
  }
  if (hash.is_valid) {
    return { variant: 'outline', icon: Check, text: 'Active', class: 'bg-green-300/20 border-green-300/40' };
  }
  return { variant: 'secondary', icon: AlertTriangle, text: 'Invalid', class: 'bg-red-300/20 border-red-300/40' };
};
</script>

<template>
  <Head title="Secure Overlay Generator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @dismiss="toastMessage = null" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <Heading title="Secure Overlay Generator" description="Manage secure overlay URLs for your OBS overlays." />
        </div>

        <Dialog v-model:open="showCreateDialog">
          <DialogTrigger as-child>
            <Button @click="showCreateDialog = true" class="cursor-pointer">
              <Plus class="w-4 h-4" />
              Create New Hash
            </Button>
          </DialogTrigger>

          <DialogContent class="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Create New Overlay Hash</DialogTitle>
              <DialogDescription>
                Generate a secure hash for accessing your overlay data
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
              <div class="space-y-2">
                <Label for="overlay_name">Overlay Name *</Label>
                <Input
                  id="overlay_name"
                  v-model="createForm.overlay_name"
                  placeholder="My Follower Counter"
                  required
                />
              </div>

              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="createForm.description"
                  placeholder="Optional description of what this overlay does"
                  :rows="2"
                />
              </div>

              <div class="space-y-2">
                <Label for="expires_in_days">Expires in (days)</Label>
                <Input
                  id="expires_in_days"
                  :v-model="createForm.expires_in_days"
                  type="number"
                  placeholder="Leave empty for no expiration"
                  min="1"
                  max="365"
                />
              </div>
            </div>

            <DialogFooter>
              <Button
                @click="showCreateDialog = false"
                variant="outline"
                class="cursor-pointer"
              >
                Cancel
              </Button>
              <Button
                @click="createHash"
                :disabled="isCreating"
                class="cursor-pointer"
              >
                <RefreshCw v-if="isCreating" class="w-4 h-4 animate-spin" />
                {{ isCreating ? 'Creating...' : 'Create Hash' }}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <!-- Info Card -->
      <Card class="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-500/30 rounded-none border-1">
        <CardContent>
          <div class="flex items-start gap-3">
            <div class="rounded-full bg-red-500 p-1">
              <ShieldQuestion class="w-4 h-4 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-red-900 dark:text-red-100">
                  WARNING: Do NOT show any generated overlay URLs on stream!!!!
              </h3>
              <p class="text-sm text-red-700 dark:text-red-200 mt-1">
              Anyone who sees your overlay URL can access your Twitch data and overlays without logging in.
              <span class="font-bold underline">Treat this URL like a password</span>—never share it publicly, on stream, or in screenshots.
              If you think it has leaked, <span class="font-bold">revoke or regenerate the hash immediately</span>.
              </p>

            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Hashes List -->
      <div class="space-y-4">
        <div v-if="hashes.length === 0" class="text-center py-12">
          <div class="text-muted-foreground">
            <ExternalLink class="w-12 h-12 mx-auto mb-4 opacity-50" />
            <h3 class="text-lg font-medium mb-2">No overlay hashes yet</h3>
            <p class="text-sm mb-4">Create your first hash to start using secure overlay URLs</p>
            <Button @click="showCreateDialog = true" class="cursor-pointer">
              <Plus class="w-4 h-4" />
              Create Your First Hash
            </Button>
          </div>
        </div>

        <div v-else>
          <Card v-for="hash in hashes" :key="hash.id" class="overflow-hidden mb-4 rounded-2xl border bg-accent/40 p-4 shadow backdrop-blur-sm transition hover:bg-accent/40 hover:shadow-lg">
            <CardHeader>
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <CardTitle class="text-lg">{{ hash.overlay_name }}</CardTitle>
                  <CardDescription v-if="hash.description" class="mt-1">
                    {{ hash.description }}
                  </CardDescription>
                  <div class="flex items-center gap-2 mt-2">
                    <Badge :variant="getStatusBadge(hash).variant" :class="getStatusBadge(hash).class">
                      <component :is="getStatusBadge(hash).icon" class="w-3 h-3 mr-1" />
                      {{ getStatusBadge(hash).text }}
                    </Badge>
                  </div>
                </div>

                <div>
                  <div class="flex items-end content-end text-right justify-between gap-1">
                    <Button
                      @click="regenerateHash(hash)"
                      :disabled="!hash.is_active"
                      variant="ghost"
                      size="sm"
                      class="cursor-pointer"
                      title="Regenerate hash"
                    >
                      <RefreshCw class="w-4 h-4" />
                    </Button>

                    <Button
                      @click="revokeHash(hash)"
                      :disabled="!hash.is_active"
                      variant="ghost"
                      size="sm"
                      class="cursor-pointer text-orange-600 hover:text-orange-700"
                      title="Revoke hash"
                    >
                      <Ban class="w-4 h-4" />
                    </Button>

                    <Button
                      @click="deleteHash(hash)"
                      variant="ghost"
                      size="sm"
                      class="cursor-pointer text-red-600 hover:text-red-700"
                      title="Delete hash"
                    >
                      <Trash2 class="w-4 h-4" />
                    </Button>
                  </div>

                  <div class="leading-snug">
                    <div>
                      <span class="text-xs text-muted-foreground">Created: </span>
                      <span class="text-xs text-muted-foreground opacity-70">{{ hash.created_at }}</span>
                    </div>
                    <div v-if="hash.expires_at">
                      <span class="text-xs text-muted-foreground">Expires:</span>
                      <span class="text-xs text-muted-foreground opacity-70">{{ hash.expires_at }}</span>
                    </div>
                  </div>

                  <span class="text-xs text-muted-foreground bg-muted rounded px-1 py-0.5 mr-1">
                    {{ hash.access_count }} views
                  </span>
                  <span v-if="hash.last_accessed_at" class="text-xs text-muted-foreground">
                    Last: {{ hash.last_accessed_at }}
                  </span>
                </div>
              </div>
            </CardHeader>

            <CardContent class="space-y-3">
              <!-- Overlay URL -->
              <div class="space-y-2">

                <div class="flex items-center gap-2" v-if="hash.is_active">

                  <Button
                    @click="copyUrl(hash.overlay_url)"
                    variant="outline"
                    size="lg"
                    class="cursor-pointer rounded-2xl hover:bg-accent/50 hover:ring-2 hover:ring-gray-300 dark:hover:ring-gray-700"
                    title="Copy URL"
                  >
                    <Copy class="w-4 h-4" /> Copy Overlay URL
                  </Button>
                  <Button
                    :as="'a'"
                    @click="triggerLinkWarning(() => openExternalLink(hash.overlay_url, '_blank'), 'Remember: DO NOT EVER show this link on stream! Treat it like a password. If you think it has leaked, revoke or regenerate the hash immediately.')"
                    target="_blank"
                    variant="secondary"
                    size="lg"
                    class="cursor-pointer rounded-2xl hover:ring-2 hover:ring-red-300 hover:bg-red-600/50 dark:hover:ring-red-700"
                    title="Test overlay"
                  >
                    <ExternalLink class="w-4 h-4" /> Open Overlay URL
                  </Button>
                </div>
              </div>

              <!-- Usage Instructions -->
              <details class="text-xs text-muted-foreground">
                <summary class="cursor-pointer font-medium hover:text-foreground">
                  OBS Setup Instructions
                </summary>
                <div class="mt-2 p-3 bg-muted rounded border space-y-2">
                  <p><strong>1.</strong> In OBS, add a new Browser Source</p>
                  <p><strong>2.</strong> Set the URL to the value you have copied above</p>
                  <p><strong>3.</strong> Set width&times;height to 1920&times;1080</p>
                  <p><strong>4.</strong> Check "Refresh browser when scene becomes active" for live updates</p>
                </div>
              </details>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
