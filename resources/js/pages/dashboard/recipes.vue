<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import RekaToast from '@/components/RekaToast.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Copy, Play, Loader2, ChefHat } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

interface RecipeButton {
  picker_ref: string;
  label: string;
  last_result: string | null;
  last_result_at: number | null;
  is_running: boolean;
}

interface RecipeInstanceRow {
  id: number;
  instance_slug: string;
  label: string | null;
  recipe: { slug: string | null; name: string | null; version: number | null };
  tag_prefix: string;
  buttons: RecipeButton[];
}

const props = defineProps<{
  instances: RecipeInstanceRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Recipes', href: '/dashboard/recipes' },
];

// Local mutable mirror so a fire updates the result inline without a full router reload.
const instances = ref<RecipeInstanceRow[]>(JSON.parse(JSON.stringify(props.instances)));
const firing = ref<string | null>(null);
const toastMessage = ref<string | null>(null);
const toastType = ref<'info' | 'success' | 'warning' | 'error'>('info');

function buttonKey(instanceId: number, pickerRef: string): string {
  return `${instanceId}:${pickerRef}`;
}

async function fireButton(instanceId: number, pickerRef: string) {
  const key = buttonKey(instanceId, pickerRef);
  firing.value = key;
  try {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const res = await fetch(`/recipes/instances/${instanceId}/fire-button`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ picker_ref: pickerRef }),
    });
    const data = await res.json();
    if (res.ok && data.fired) {
      const instance = instances.value.find(i => i.id === instanceId);
      const button = instance?.buttons.find(b => b.picker_ref === pickerRef);
      if (button) {
        button.last_result = data.result;
        button.last_result_at = Math.floor(Date.now() / 1000);
      }
      toastMessage.value = `Fired: ${data.result}`;
      toastType.value = 'success';
    } else {
      toastMessage.value = data.result === null ? 'Picker rejected the fire (busy or exhausted).' : 'Fire failed.';
      toastType.value = 'warning';
    }
  } catch {
    toastMessage.value = 'Network error while firing.';
    toastType.value = 'error';
  } finally {
    firing.value = null;
  }
}

async function copyTag(prefix: string) {
  const tag = `[[[${prefix}:result]]]`;
  try {
    await navigator.clipboard.writeText(tag);
    toastMessage.value = `Copied ${tag}`;
    toastType.value = 'success';
  } catch {
    toastMessage.value = 'Clipboard write failed.';
    toastType.value = 'error';
  }
}

function formatRelativeTime(unixSeconds: number | null): string | null {
  if (!unixSeconds) return null;
  const deltaSec = Math.max(0, Math.floor(Date.now() / 1000) - unixSeconds);
  if (deltaSec < 5) return 'just now';
  if (deltaSec < 60) return `${deltaSec}s ago`;
  if (deltaSec < 3600) return `${Math.floor(deltaSec / 60)}m ago`;
  if (deltaSec < 86400) return `${Math.floor(deltaSec / 3600)}h ago`;
  return `${Math.floor(deltaSec / 86400)}d ago`;
}
</script>

<template>
  <Head title="Recipes" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
      <Heading title="Recipes" description="Installed recipes that produce values into your controls layer. Click a button to fire its picker; the result lands in your overlays via the matching control tag." />

      <RekaToast v-if="toastMessage" :message="toastMessage" :type="toastType" @close="toastMessage = null" />

      <div v-if="instances.length === 0" class="rounded-lg border border-dashed p-10 text-center">
        <ChefHat class="mx-auto h-10 w-10 text-muted-foreground" />
        <p class="mt-4 text-foreground">No recipes installed yet.</p>
        <p class="mt-1 text-sm text-muted-foreground">
          Install a recipe from the catalogue to see its dashboard buttons here.
        </p>
      </div>

      <div v-else class="grid gap-4 md:grid-cols-2">
        <Card v-for="instance in instances" :key="instance.id" class="border-sidebar">
          <CardHeader>
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <CardTitle class="truncate">{{ instance.label ?? instance.recipe.name }}</CardTitle>
                <CardDescription class="mt-1 flex flex-wrap items-center gap-2 text-foreground">
                  <span>{{ instance.recipe.name }}</span>
                  <Badge variant="secondary">v{{ instance.recipe.version }}</Badge>
                  <span class="text-muted-foreground">{{ instance.instance_slug }}</span>
                </CardDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                class="cursor-pointer shrink-0"
                @click="copyTag(instance.tag_prefix)"
                title="Copy the result tag for this instance"
              >
                <Copy class="h-4 w-4" />
                <span class="ml-1.5 hidden sm:inline">Copy tag</span>
              </Button>
            </div>
          </CardHeader>
          <CardContent class="space-y-3">
            <div v-if="instance.buttons.length === 0" class="text-sm text-muted-foreground">
              This recipe declares no dashboard buttons.
            </div>
            <div
              v-for="button in instance.buttons"
              :key="button.picker_ref"
              class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-sidebar bg-sidebar/40 p-3"
            >
              <div class="min-w-0">
                <div class="font-medium text-foreground">{{ button.label }}</div>
                <div v-if="button.last_result !== null" class="mt-1 text-sm">
                  <span class="text-muted-foreground">Last:</span>
                  <span class="ml-1 text-foreground">{{ button.last_result }}</span>
                  <span v-if="formatRelativeTime(button.last_result_at)" class="ml-2 text-xs text-muted-foreground">
                    ({{ formatRelativeTime(button.last_result_at) }})
                  </span>
                </div>
              </div>
              <Button
                class="cursor-pointer"
                :disabled="firing === buttonKey(instance.id, button.picker_ref)"
                @click="fireButton(instance.id, button.picker_ref)"
              >
                <Loader2 v-if="firing === buttonKey(instance.id, button.picker_ref)" class="h-4 w-4 animate-spin" />
                <Play v-else class="h-4 w-4" />
                <span class="ml-1.5">Fire</span>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
