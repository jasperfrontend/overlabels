<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  CheckCircle,
  Loader2,
  Copy,
  Check,
  AlertTriangle,
  PartyPopper,
  Rocket,
  Shield,
  Zap,
  ArrowRight,
} from 'lucide-vue-next';

interface AlertMapping {
  event_type: string;
  display: string;
}

interface OnboardingStatus {
  kit_forked: boolean;
  tags_status: string;
  alerts_mapped: boolean;
  alert_mappings: AlertMapping[];
  token_created: boolean;
  has_webhook_secret: boolean;
}

defineProps<{
  twitchId: string;
}>();

const step = ref(1);
const status = ref<OnboardingStatus | null>(null);
const loading = ref(true);
const pollInterval = ref<ReturnType<typeof setInterval> | null>(null);

// Step 2 state
const generatingToken = ref(false);
const plainToken = ref<string | null>(null);
const tokenCopied = ref(false);
const tokenSaved = ref(false);

async function fetchStatus() {
  try {
    const response = await fetch(route('onboarding.status'));
    status.value = await response.json();
    loading.value = false;
  } catch {
    loading.value = false;
  }
}

const setupComplete = computed(() => {
  if (!status.value) return false;
  return (
    status.value.kit_forked &&
    status.value.alerts_mapped &&
    (status.value.tags_status === 'completed' || status.value.tags_status === 'not_started')
  );
});

const tagsGenerating = computed(() => {
  if (!status.value) return false;
  return status.value.tags_status === 'pending' || status.value.tags_status === 'processing';
});

function startPolling() {
  pollInterval.value = setInterval(async () => {
    await fetchStatus();
    if (setupComplete.value && pollInterval.value) {
      clearInterval(pollInterval.value);
      pollInterval.value = null;
    }
  }, 3000);
}

onMounted(async () => {
  await fetchStatus();
  if (!setupComplete.value) {
    startPolling();
  }
});

onUnmounted(() => {
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
  }
});

function goToStep2() {
  step.value = 2;
}

async function generateToken() {
  generatingToken.value = true;
  try {
    const response = await fetch(route('onboarding.token'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    });
    const data = await response.json();
    plainToken.value = data.plain_token;
  } catch {
    // token generation failed
  } finally {
    generatingToken.value = false;
  }
}

async function copyToken() {
  if (!plainToken.value) return;
  await navigator.clipboard.writeText(plainToken.value);
  tokenCopied.value = true;
  setTimeout(() => {
    tokenCopied.value = false;
  }, 2000);
}

async function completeOnboarding() {
  try {
    await fetch(route('onboarding.complete'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    });
    step.value = 3;
  } catch {
    // completion failed
  }
}

function dismiss() {
  router.reload();
}
</script>

<template>
  <Card class="border-purple-500/30 bg-gradient-to-br from-purple-950/20 to-slate-900/40">
    <!-- Step 1: Setup Summary -->
    <template v-if="step === 1">
      <CardHeader>
        <div class="flex items-center gap-3">
          <Rocket class="h-6 w-6 text-purple-400" />
          <CardTitle class="text-xl">Welcome to Overlabels!</CardTitle>
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
          We've set up the essentials for you. Here's what's ready:
        </p>
      </CardHeader>
      <CardContent class="space-y-4">
        <div v-if="loading" class="flex items-center gap-3 py-8 justify-center">
          <Loader2 class="h-5 w-5 animate-spin text-purple-400" />
          <span class="text-sm text-muted-foreground">Checking setup status...</span>
        </div>

        <template v-else-if="status">
          <!-- Starter Kit -->
          <div class="flex items-start gap-3">
            <CheckCircle v-if="status.kit_forked" class="mt-0.5 h-5 w-5 text-green-500 shrink-0" />
            <Loader2 v-else class="mt-0.5 h-5 w-5 animate-spin text-yellow-500 shrink-0" />
            <div>
              <p class="text-sm font-medium">Starter Kit forked</p>
              <p class="text-xs text-muted-foreground">Alert templates copied to your account</p>
            </div>
          </div>

          <!-- Alert Mappings -->
          <div class="flex items-start gap-3">
            <CheckCircle v-if="status.alerts_mapped" class="mt-0.5 h-5 w-5 text-green-500 shrink-0" />
            <Loader2 v-else class="mt-0.5 h-5 w-5 animate-spin text-yellow-500 shrink-0" />
            <div>
              <p class="text-sm font-medium">Alert events assigned</p>
              <template v-if="status.alerts_mapped && status.alert_mappings.length > 0">
                <div class="mt-1 flex flex-wrap gap-1.5">
                  <span
                    v-for="mapping in status.alert_mappings"
                    :key="mapping.event_type"
                    class="inline-flex items-center rounded-full bg-purple-500/15 px-2.5 py-0.5 text-xs font-medium text-purple-300"
                  >
                    {{ mapping.display }}
                  </span>
                </div>
              </template>
              <p v-else class="text-xs text-muted-foreground">Twitch events mapped to your templates</p>
            </div>
          </div>

          <!-- Tags -->
          <div class="flex items-start gap-3">
            <CheckCircle
              v-if="status.tags_status === 'completed'"
              class="mt-0.5 h-5 w-5 text-green-500 shrink-0"
            />
            <Loader2
              v-else-if="tagsGenerating"
              class="mt-0.5 h-5 w-5 animate-spin text-yellow-500 shrink-0"
            />
            <CheckCircle v-else class="mt-0.5 h-5 w-5 text-slate-500 shrink-0" />
            <div>
              <p class="text-sm font-medium">
                Template tags
                <span v-if="tagsGenerating" class="text-yellow-400">(generating...)</span>
                <span v-else-if="status.tags_status === 'completed'" class="text-green-400">ready</span>
              </p>
              <p class="text-xs text-muted-foreground">Dynamic data tags for your overlays</p>
            </div>
          </div>

          <!-- Webhook Secret -->
          <div class="flex items-start gap-3">
            <CheckCircle v-if="status.has_webhook_secret" class="mt-0.5 h-5 w-5 text-green-500 shrink-0" />
            <Loader2 v-else class="mt-0.5 h-5 w-5 animate-spin text-yellow-500 shrink-0" />
            <div>
              <p class="text-sm font-medium">Webhook secret generated</p>
              <p class="text-xs text-muted-foreground">Securely verifies incoming Twitch events</p>
            </div>
          </div>

          <!-- Next button -->
          <div class="pt-4">
            <Button
              :disabled="!setupComplete && !status.token_created"
              class="gap-2"
              @click="goToStep2"
            >
              Next: Create Your Secure Token
              <ArrowRight class="h-4 w-4" />
            </Button>
            <p v-if="tagsGenerating" class="mt-2 text-xs text-muted-foreground">
              Tags are still generating in the background. You can continue anyway - they'll be ready soon.
            </p>
          </div>
        </template>
      </CardContent>
    </template>

    <!-- Step 2: Token Creation -->
    <template v-if="step === 2">
      <CardHeader>
        <div class="flex items-center gap-3">
          <Shield class="h-6 w-6 text-purple-400" />
          <CardTitle class="text-xl">Generate Your Secure Token</CardTitle>
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
          This token authenticates your OBS browser source with Overlabels. It's shown only once.
        </p>
      </CardHeader>
      <CardContent class="space-y-5">
        <!-- Token not yet generated -->
        <template v-if="!plainToken">
          <Button
            size="lg"
            :disabled="generatingToken"
            class="gap-2 w-full"
            @click="generateToken"
          >
            <Loader2 v-if="generatingToken" class="h-4 w-4 animate-spin" />
            <Zap v-else class="h-4 w-4" />
            {{ generatingToken ? 'Generating...' : 'Generate Secure Token' }}
          </Button>
        </template>

        <!-- Token generated -->
        <template v-else>
          <div class="space-y-3">
            <div
              class="flex items-center gap-2 rounded-lg border border-green-500/30
               bg-green-950/20 p-4 font-mono text-sm break-all"
            >
              <span class="flex-1 select-all text-green-300">{{ plainToken }}</span>
              <button
                class="shrink-0 rounded-md p-2 hover:bg-green-500/10 transition"
                title="Copy to clipboard"
                @click="copyToken"
              >
                <Check v-if="tokenCopied" class="h-4 w-4 text-green-400" />
                <Copy v-else class="h-4 w-4 text-green-400" />
              </button>
            </div>

            <!-- Warning -->
            <div
              class="flex gap-2 rounded-md border border-amber-300/40 dark:border-amber-700/40
               bg-amber-50/60 dark:bg-amber-900/20 p-3"
            >
              <AlertTriangle class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
              <p class="text-sm text-amber-800 dark:text-amber-300">
                This token is shown <strong>only once</strong>. Copy it now and store it somewhere safe.
                Never share it or show it on stream.
              </p>
            </div>

            <!-- Confirmation checkbox -->
            <label class="flex items-center gap-3 cursor-pointer">
              <Checkbox v-model="tokenSaved" />
              <span class="text-sm">I've saved this token somewhere safe</span>
            </label>

            <Button
              :disabled="!tokenSaved"
              class="gap-2"
              @click="completeOnboarding"
            >
              Complete Setup
              <ArrowRight class="h-4 w-4" />
            </Button>
          </div>
        </template>
      </CardContent>
    </template>

    <!-- Step 3: Celebration -->
    <template v-if="step === 3">
      <CardHeader>
        <div class="flex items-center gap-3">
          <PartyPopper class="h-6 w-6 text-purple-400" />
          <CardTitle class="text-xl">You're all set!</CardTitle>
        </div>
        <p class="mt-2 text-sm text-muted-foreground">
          Your overlays are ready to go. Here's what was set up:
        </p>
      </CardHeader>
      <CardContent class="space-y-5">
        <ul class="space-y-2 text-sm">
          <li class="flex items-center gap-2">
            <CheckCircle class="h-4 w-4 text-green-500" />
            Starter kit forked with alert templates
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle class="h-4 w-4 text-green-500" />
            Twitch events auto-assigned to alerts
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle class="h-4 w-4 text-green-500" />
            Secure overlay token created
          </li>
          <li class="flex items-center gap-2">
            <CheckCircle class="h-4 w-4 text-green-500" />
            Webhook secret generated
          </li>
        </ul>

        <div class="flex flex-wrap gap-3 pt-2">
          <Button variant="outline" as="a" :href="route('testing.index')" class="gap-2">
            Testing Guide
          </Button>
          <Button variant="outline" as="a" :href="route('events.index')" class="gap-2">
            Review Alert Mappings
          </Button>
          <Button @click="dismiss" class="gap-2">
            Let's go!
            <Rocket class="h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </template>
  </Card>
</template>
