<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'submitted'): void;
}>();

const saving = ref(false);
const submitted = ref(false);
const errors = ref<Record<string, string>>({});

const serviceUrl = ref('');
const example = ref('');
const context = ref('');

function reset() {
  serviceUrl.value = '';
  example.value = '';
  context.value = '';
  errors.value = {};
  submitted.value = false;
}

async function submit() {
  errors.value = {};
  saving.value = true;

  try {
    await axios.post('/integration-suggestions', {
      service_url: serviceUrl.value,
      example: example.value,
      context: context.value,
    });
    submitted.value = true;
    emit('submitted');
  } catch (err: any) {
    if (err.response?.status === 422) {
      const errs = err.response.data.errors ?? {};
      for (const [key, msgs] of Object.entries(errs)) {
        errors.value[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
      }
    } else if (err.response?.status === 429) {
      errors.value.general = 'Too many suggestions - please try again later.';
    } else {
      errors.value.general = 'Something went wrong. Please try again.';
    }
  } finally {
    saving.value = false;
  }
}

function close() {
  emit('update:open', false);
  setTimeout(reset, 200);
}
</script>

<template>
  <Dialog :open="open" @update:open="close">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>Suggest an integration</DialogTitle>
        <p class="text-sm text-muted-foreground mt-1">
          Overlays can't embed external content directly, but we can build native integrations.
          Tell us what service you'd like to see supported!
        </p>
      </DialogHeader>

      <div v-if="submitted" class="py-6 text-center">
        <p class="text-foreground font-medium">Thanks for the suggestion!</p>
        <p class="text-sm text-muted-foreground mt-1">We'll take a look and see what we can do.</p>
        <button class="btn btn-primary mt-4 m-auto" @click="close">Sounds good!</button>
      </div>

      <form v-else @submit.prevent="submit" class="space-y-4">
        <div>
          <Label for="service_url">Service URL</Label>
          <Input
            id="service_url"
            v-model="serviceUrl"
            placeholder="https://example.com or the service name"
            class="mt-1"
          />
          <p v-if="errors.service_url" class="text-sm text-destructive mt-1">{{ errors.service_url }}</p>
        </div>

        <div>
          <Label for="example">What does the integration do?</Label>
          <Input
            id="example"
            v-model="example"
            placeholder="e.g. shows donation alerts, displays a live chat widget"
            class="mt-1"
          />
          <p v-if="errors.example" class="text-sm text-destructive mt-1">{{ errors.example }}</p>
        </div>

        <div>
          <Label for="context">Anything else? (optional)</Label>
          <Input
            id="context"
            v-model="context"
            placeholder="e.g. an output URL, API docs link, or overlay URL from the service"
            class="mt-1"
          />
        </div>

        <p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>

        <DialogFooter>
          <button type="button" class="btn btn-sm btn-secondary" @click="close">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary" :disabled="saving || !serviceUrl.trim()">
            {{ saving ? 'Sending...' : 'Send suggestion' }}
          </button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
