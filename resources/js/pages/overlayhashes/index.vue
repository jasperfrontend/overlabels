<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Badge from '@/components/ui/badge/Badge.vue';

import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle, 
  DialogTrigger 
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
  Copy, 
  Plus, 
  Trash2, 
  RefreshCw, 
  Ban, 
  ExternalLink,
  Eye,
  EyeOff,
  AlertTriangle,
  CheckCircle,
  Clock,
  ShieldQuestion
} from 'lucide-vue-next';

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

const props = defineProps<{
  hashes: OverlayHash[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Secure Overlay Generator',
    href: '/overlay-hashes',
  },
];

// State for creating new hash
const showCreateDialog = ref(false);
const createForm = ref({
  overlay_name: '',
  description: '',
  expires_in_days: null as number | null,
  allowed_ips: [] as string[],
});

// State for various operations
const isCreating = ref(false);
const showHashKeys = ref<Record<number, boolean>>({});

// Reset create form
const resetCreateForm = () => {
  createForm.value = {
    overlay_name: '',
    description: '',
    expires_in_days: null,
    allowed_ips: [],
  };
};

// Create new overlay hash
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
      console.log('✅ Hash created:', data);
      
      // Close dialog and reset form
      showCreateDialog.value = false;
      resetCreateForm();
      
      // Refresh the page to show new hash
      router.reload({
        only: ['hashes']
      });
      
    } else {
      console.error('❌ Creation failed:', data);
      alert(`Failed to create hash: ${data.message || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('💥 Creation error:', error);
    alert('Failed to create hash. Please try again.');
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
    } else {
      alert('Failed to revoke hash');
    }
  } catch (error) {
    console.error('Error revoking hash:', error);
    alert('Failed to revoke hash');
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
    } else {
      alert('Failed to regenerate hash');
    }
  } catch (error) {
    console.error('Error regenerating hash:', error);
    alert('Failed to regenerate hash');
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
    } else {
      alert('Failed to delete hash');
    }
  } catch (error) {
    console.error('Error deleting hash:', error);
    alert('Failed to delete hash');
  }
};

// Copy URL to clipboard
const copyUrl = async (url: string) => {
  try {
    await navigator.clipboard.writeText(url);
    // You could add a toast notification here
    console.log('URL copied to clipboard');
  } catch (error) {
    console.error('Failed to copy URL:', error);
  }
};

// Toggle hash key visibility
const toggleHashKeyVisibility = (hashId: number) => {
  showHashKeys.value[hashId] = !showHashKeys.value[hashId];
};

// Get status badge props
type BadgeVariant = "default" | "destructive" | "outline" | "secondary" | "success" | "warning" | null | undefined;
interface StatusBadge {
  variant: BadgeVariant;
  icon: any;
  text: string;
}
const getStatusBadge = (hash: OverlayHash): StatusBadge => {
  if (!hash.is_active) {
    return { variant: 'destructive', icon: Ban, text: 'Revoked' };
  }
  if (hash.expires_at && new Date(hash.expires_at) < new Date()) {
    return { variant: 'destructive', icon: Clock, text: 'Expired' };
  }
  if (hash.is_valid) {
    return { variant: 'default', icon: CheckCircle, text: 'Active' };
  }
  return { variant: 'secondary', icon: AlertTriangle, text: 'Invalid' };
};

// Format hash key for display
const formatHashKey = (hashKey: string, show: boolean) => {
  if (show) return hashKey;
  return hashKey.substring(0, 8) + '••••••••••••••••••••••••••••••••••••••••••••••••••••••••';
};
</script>

<template>
  <Head title="Secure Overlay Generator" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Secure Overlay Generator</h1>
          <p class="text-muted-foreground">
            Manage secure overlay URL and hashcodes for your OBS overlays.
          </p>
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
      <Card class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
        <CardContent>
          <div class="flex items-start gap-3">
            <div class="rounded-full bg-blue-500 p-1">
              <ShieldQuestion class="w-4 h-4 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-blue-900 dark:text-blue-100">
                How Secure Overlays Work
              </h3>
              <p class="text-sm text-blue-700 dark:text-blue-200 mt-1">
                Each overlay is created with a secure code in the overlay URL that can access your Twitch data without requiring login. 
                Use these URLs in OBS Browser Sources to display live overlay data. Hashes can be revoked or regenerated at any time for security.
              </p>

            </div>
          </div>
        </CardContent>
      </Card>
      <div class="mt-4">
        <div class="border-4 border-red-600 bg-red-100 dark:bg-red-950 rounded-xl p-6 flex items-center gap-4 shadow-lg">
          <AlertTriangle class="w-10 h-10 text-red-600 flex-shrink-0" />
          <div>
            <h4 class="text-xl font-bold text-red-800 dark:text-red-200 mb-2 uppercase tracking-wide">
              WARNING: Do NOT show this page on stream!!!!
            </h4>
            <p class="text-base text-red-700 dark:text-red-300 font-semibold">
              Anyone who sees your overlay URL can access your Twitch data and overlays without logging in. 
              <span class="font-bold underline">Treat this URL like a password</span>—never share it publicly, on stream, or in screenshots.
              If you think it has leaked, <span class="font-bold">revoke or regenerate the hash immediately</span>.
            </p>
          </div>
        </div>
      </div>
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

        <Card v-for="hash in hashes" :key="hash.id" class="overflow-hidden">
          <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <CardTitle class="text-lg">{{ hash.overlay_name }}</CardTitle>
                <CardDescription v-if="hash.description" class="mt-1">
                  {{ hash.description }}
                </CardDescription>
                <div class="flex items-center gap-2 mt-2">
                  <Badge :variant="getStatusBadge(hash).variant" class="text-xs">
                    <component :is="getStatusBadge(hash).icon" class="w-3 h-3 mr-1" />
                    {{ getStatusBadge(hash).text }}
                  </Badge>
                  <span class="text-xs text-muted-foreground">
                    {{ hash.access_count }} accesses
                  </span>
                  <span v-if="hash.last_accessed_at" class="text-xs text-muted-foreground">
                    Last: {{ hash.last_accessed_at }}
                  </span>
                </div>
              </div>
              
              <div class="flex items-center gap-1">
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
            </div>
          </CardHeader>
          
          <CardContent class="space-y-3">
            <!-- Overlay URL -->
            <div class="space-y-2">
              <Label class="text-sm font-medium">Overlay URL</Label>
              <div class="flex items-center gap-2">
                <Input
                  :value="hash.overlay_url"
                  readonly
                  class="font-mono text-xs"
                />
                <Button
                  @click="copyUrl(hash.overlay_url)"
                  variant="outline"
                  size="sm"
                  class="cursor-pointer"
                  title="Copy URL"
                >
                  <Copy class="w-4 h-4" />
                </Button>
                <Button
                  :as="'a'"
                  :href="hash.overlay_url"
                  target="_blank"
                  variant="outline"
                  size="sm"
                  class="cursor-pointer"
                  title="Test overlay"
                >
                  <ExternalLink class="w-4 h-4" />
                </Button>
              </div>
            </div>
            
            <!-- Hash Key -->
            <div class="space-y-2">
              <Label class="text-sm font-medium">Hash Key</Label>
              <div class="flex items-center gap-2">
                <Input
                  :value="formatHashKey(hash.hash_key, showHashKeys[hash.id])"
                  readonly
                  class="font-mono text-xs"
                />
                <Button
                  @click="toggleHashKeyVisibility(hash.id)"
                  variant="outline"
                  size="sm"
                  class="cursor-pointer"
                  :title="showHashKeys[hash.id] ? 'Hide hash' : 'Show hash'"
                >
                  <Eye v-if="!showHashKeys[hash.id]" class="w-4 h-4" />
                  <EyeOff v-else class="w-4 h-4" />
                </Button>
              </div>
            </div>
            
            <!-- Metadata -->
            <div class="grid grid-cols-2 gap-4 text-xs text-muted-foreground">
              <div>
                <span class="font-medium">Created:</span>
                {{ hash.created_at }}
              </div>
              <div v-if="hash.expires_at">
                <span class="font-medium">Expires:</span>
                {{ hash.expires_at }}
              </div>
            </div>
            
            <!-- Usage Instructions -->
            <details class="text-xs text-muted-foreground">
              <summary class="cursor-pointer font-medium hover:text-foreground">
                OBS Setup Instructions
              </summary>
              <div class="mt-2 p-3 bg-muted rounded border space-y-2">
                <p><strong>1.</strong> In OBS, add a new "Browser Source"</p>
                <p><strong>2.</strong> Set the URL to: <code class="bg-background px-1 rounded">{{ hash.overlay_url }}</code></p>
                <p><strong>3.</strong> Set width/height as needed for your overlay</p>
                <p><strong>4.</strong> Check "Refresh browser when scene becomes active" for live updates</p>
              </div>
            </details>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>