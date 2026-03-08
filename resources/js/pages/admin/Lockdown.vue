<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

interface LockdownStatus {
  active: boolean;
  activated_at?: string;
  activated_by?: number;
  activated_by_name?: string;
  reason?: string;
  suspended_token_ids?: number[];
}

const props = defineProps<{
  lockdown: LockdownStatus;
  totalTokens: number;
  activeTokens: number;
}>();


// Engage lockdown flow — 3 steps
const engageStep = ref<0 | 1 | 2>(0);
const engageReason = ref('');
const engageConfirmWord = ref('');
const engageConfirmValid = computed(() => engageConfirmWord.value === 'LOCKDOWN');
const engaging = ref(false);

function startEngage() {
  engageStep.value = 1;
}
function engageStep2() {
  engageStep.value = 2;
  engageConfirmWord.value = '';
}
function cancelEngage() {
  engageStep.value = 0;
  engageReason.value = '';
  engageConfirmWord.value = '';
}
function submitEngage() {
  if (!engageConfirmValid.value) return;
  engaging.value = true;
  router.post(route('admin.lockdown.activate'), { reason: engageReason.value }, {
    onFinish: () => { engaging.value = false; cancelEngage(); },
  });
}

// Lift lockdown flow — single confirmation
const liftConfirming = ref(false);
const lifting = ref(false);

function confirmLift() {
  liftConfirming.value = true;
}
function cancelLift() {
  liftConfirming.value = false;
}
function submitLift() {
  lifting.value = true;
  router.post(route('admin.lockdown.deactivate'), {}, {
    onFinish: () => { lifting.value = false; liftConfirming.value = false; },
  });
}

function formatDate(iso?: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString();
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Admin', href: route('admin.dashboard') }, { title: 'Lockdown', href: route('admin.lockdown.index') }]">
    <div class="mx-auto max-w-2xl space-y-8 p-6">

      <!-- Status card -->
      <div
        :class="props.lockdown.active
          ? 'border-red-500 bg-red-50 dark:bg-red-950/30'
          : 'border-green-500 bg-green-50 dark:bg-green-950/30'"
        class="rounded-lg border-2 p-6"
      >
        <div class="flex items-center gap-3">
          <span
            :class="props.lockdown.active ? 'bg-red-500' : 'bg-green-500'"
            class="inline-block h-3 w-3 rounded-full"
          />
          <h2 class="text-xl font-bold">
            {{ props.lockdown.active ? 'LOCKDOWN ACTIVE' : 'System operational' }}
          </h2>
        </div>

        <template v-if="props.lockdown.active">
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex gap-2">
              <dt class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">Activated by</dt>
              <dd>{{ props.lockdown.activated_by_name ?? lockdown.activated_by ?? '—' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">Activated at</dt>
              <dd>{{ formatDate(props.lockdown.activated_at) }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">Reason</dt>
              <dd>{{ props.lockdown.reason || 'No reason provided' }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="font-medium text-gray-600 dark:text-gray-400 w-32 shrink-0">Tokens suspended</dt>
              <dd>{{ props.lockdown.suspended_token_ids?.length ?? '—' }}</dd>
            </div>
          </dl>
        </template>

        <template v-else>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            All overlays are rendering normally. {{ activeTokens }} active token{{ activeTokens !== 1 ? 's' : '' }} in use.
          </p>
        </template>
      </div>

      <!-- Lift lockdown -->
      <div v-if="props.lockdown.active" class="space-y-4">
        <div v-if="!liftConfirming">
          <Button
            @click="confirmLift"
            class="rounded bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700"
          >
            Lift lockdown
          </Button>
        </div>

        <div v-else class="rounded-lg border border-green-400 bg-green-50 dark:bg-green-950/30 p-5 space-y-4">
          <p class="font-medium">Lifting lockdown will:</p>
          <ul class="list-disc list-inside text-sm space-y-1 text-gray-700 dark:text-gray-300">
            <li>Re-enable all {{ lockdown.suspended_token_ids?.length ?? 0 }} suspended overlay access tokens</li>
            <li>Allow overlays to render again (within ~5 minutes)</li>
            <li>Resume Twitch and external webhook processing</li>
          </ul>
          <div class="flex gap-3">
            <Button
              @click="submitLift"
              :disabled="lifting"
              class="rounded bg-green-600 px-5 py-2 font-semibold text-white hover:bg-green-700 disabled:opacity-50"
            >
              {{ lifting ? 'Lifting…' : 'Confirm — lift lockdown' }}
            </Button>
            <Button @click="cancelLift" class="rounded border px-5 py-2 text-sm font-medium">
              Cancel
            </Button>
          </div>
        </div>
      </div>

      <!-- Engage lockdown -->
      <div v-else class="space-y-4">

        <!-- Step 0: Initial button -->
        <div v-if="engageStep === 0">
          <Button
            @click="startEngage"
            size="lg"
            class="rounded bg-red-600 px-5 py-2.5 font-semibold text-white hover:bg-red-700"
          >
            Engage lockdown
          </Button>
        </div>

        <!-- Step 1: Consequences + reason -->
        <div v-else-if="engageStep === 1" class="rounded-lg border border-red-400 bg-red-50 dark:bg-red-950/30 p-5 space-y-4">
          <h3 class="font-bold text-red-700 dark:text-red-400">Engaging lockdown will immediately:</h3>
          <ul class="list-disc list-inside text-sm space-y-1 text-gray-700 dark:text-gray-300">
            <li>Deactivate all <strong>{{ props.activeTokens }}</strong> overlay access tokens</li>
            <li>Return 503 to all overlay render requests — OBS sources will show an error banner</li>
            <li>Stop processing all Twitch and external webhook events</li>
            <li>Flush all non-admin user sessions</li>
            <li>Show a lockdown banner to all logged-in users</li>
          </ul>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            No overlay data or user content will be deleted. Everything can be restored when lockdown is lifted.
          </p>
          <div class="space-y-2">
            <label class="block text-sm font-medium">Reason <span class="text-gray-400">(optional)</span></label>
            <input
              v-model="engageReason"
              type="text"
              maxlength="500"
              placeholder="e.g. Security incident, maintenance"
              class="w-full rounded border px-3 py-2 text-sm dark:bg-gray-900 dark:border-gray-700"
            />
          </div>
          <div class="flex gap-3">
            <Button
              @click="engageStep2"
              class="rounded bg-red-600 px-5 py-2 font-semibold text-white hover:bg-red-700"
            >
              Continue
            </Button>
            <Button @click="cancelEngage" class="rounded border px-5 py-2 text-sm font-medium ">
              Cancel
            </Button>
          </div>
        </div>

        <!-- Step 2: Type LOCKDOWN to confirm -->
        <div v-else-if="engageStep === 2" class="rounded-lg border-2 border-red-600 bg-red-50 dark:bg-red-950/30 p-5 space-y-4">
          <h3 class="font-bold text-red-700 dark:text-red-400">Final confirmation</h3>
          <p class="text-sm">
            Type <strong class="font-mono tracking-widest">LOCKDOWN</strong> below to enable the engage button.
          </p>
          <input
            v-model="engageConfirmWord"
            type="text"
            autocomplete="off"
            spellcheck="false"
            placeholder="LOCKDOWN"
            class="w-full rounded border-2 px-3 py-2 font-mono text-sm uppercase tracking-widest dark:bg-gray-900"
            :class="engageConfirmValid ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'"
          />
          <div class="flex gap-3">
            <Button
              @click="submitEngage"
              :disabled="!engageConfirmValid || engaging"
              class="rounded bg-red-600 px-5 py-2 font-semibold text-white hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {{ engaging ? 'Engaging…' : 'Engage lockdown now' }}
            </Button>
            <Button @click="cancelEngage" class="rounded border px-5 py-2 text-sm font-medium ">
              Cancel
            </Button>
          </div>
        </div>
      </div>

      <!-- Info box -->
      <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-sm text-gray-600 dark:text-gray-400 space-y-2">
        <p class="font-medium text-gray-800 dark:text-gray-200">Emergency CLI commands</p>
        <p>If this admin panel is unreachable, you can also engage/release lockdown via artisan:</p>
        <pre class="rounded bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs font-mono">php artisan lockdown:engage "reason here"
php artisan lockdown:release</pre>
      </div>

    </div>
  </AppLayout>
</template>
