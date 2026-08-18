<script setup lang="ts">
import { ref } from 'vue';
import { ImageIcon, Trash2, Upload, Loader2 } from '@lucide/vue';
import { ACCEPT_ATTRIBUTE, parseJsonSafely, uploadErrorMessage, validateImageFile } from '@/utils/imageUpload';

const props = withDefaults(
  defineProps<{
    modelValue: string | null;
    kind: 'template_screenshot' | 'kit_thumbnail';
    compact?: boolean;
  }>(),
  {
    compact: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [url: string | null];
  uploading: [isUploading: boolean];
  error: [message: string];
  clickImage: [];
}>();

const isUploading = ref(false);
const isDragging = ref(false);
const isFocused = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function uploadImage(file: File) {
  // Check before sending, not after. The server re-checks everything, but
  // finding out a 31.6 MB bitmap was never acceptable should not cost a 31.6 MB
  // upload first.
  const problem = validateImageFile(file);
  if (problem) {
    emit('error', problem);
    return;
  }

  isUploading.value = true;
  emit('uploading', true);

  try {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('kind', props.kind);

    const response = await fetch('/images/upload', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
      },
    });

    // Never call response.json() directly. A body can be non-JSON even on a
    // response that is otherwise fine - an HTML error page, or valid JSON with
    // a PHP startup warning glued to the front of it. Letting the parser throw
    // put "JSON.parse: unexpected character at line 1 column 1" in front of a
    // user whose actual problem was an oversized file.
    const payload = await parseJsonSafely(response);

    if (!response.ok) {
      throw new Error(uploadErrorMessage(response.status, response.statusText, payload));
    }

    const url = (payload as { url?: string } | null)?.url;
    if (!url) {
      throw new Error('The upload finished but the server sent back something unreadable. Try again.');
    }

    emit('update:modelValue', url);
  } catch (err: unknown) {
    // A rejected fetch (offline, connection dropped mid-upload) lands here too,
    // and its native message is not something to show a streamer.
    const message = err instanceof Error && err.message ? err.message : 'Upload failed. Check your connection and try again.';
    emit('error', message);
  } finally {
    isUploading.value = false;
    emit('uploading', false);
  }
}

function remove() {
  emit('update:modelValue', null);
}

function extractImageFile(items: DataTransferItemList | DataTransferItem[]): File | null {
  for (const item of items) {
    if (item.type.startsWith('image/')) {
      return item instanceof DataTransferItem ? item.getAsFile() : null;
    }
  }
  return null;
}

function handlePaste(event: ClipboardEvent) {
  const items = event.clipboardData?.items;
  if (!items) return;

  const file = extractImageFile(items);
  if (file) {
    event.preventDefault();
    uploadImage(file);
  }
}

function handleDrop(event: DragEvent) {
  isDragging.value = false;
  const file = event.dataTransfer?.files[0];
  if (!file) return;

  // Anything dropped goes through uploadImage, which speaks up about what is
  // wrong with it. Silently ignoring a dropped PDF looks like a broken drop
  // zone rather than a refused file.
  uploadImage(file);
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (file) {
    uploadImage(file);
  }
  input.value = '';
}
</script>

<template>
  <div class="space-y-4">
    <!-- Current image -->
    <div v-if="props.modelValue && !isUploading" class="space-y-3">
      <img
        :src="props.modelValue"
        alt="Uploaded image"
        :class="[
          compact ? 'h-48 w-auto rounded-lg object-cover' : 'max-h-96 rounded border border-sidebar',
          'cursor-pointer transition-opacity hover:opacity-80',
        ]"
        @click="emit('clickImage')"
      />
      <div class="flex gap-2">
        <button type="button" @click="remove" class="btn btn-cancel btn-sm">
          <Trash2 class="mr-1.5 h-3.5 w-3.5" />
          Remove
        </button>
      </div>
    </div>

    <!-- Upload / paste zone -->
    <div
      v-if="!props.modelValue || isUploading"
      tabindex="0"
      @paste="handlePaste"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @focus="isFocused = true"
      @blur="isFocused = false"
      :class="[
        'flex flex-col items-center justify-center rounded border-2 border-dashed text-center transition-all outline-none',
        compact ? 'p-6' : 'p-12',
        isDragging
          ? 'border-violet-500 bg-violet-500/10'
          : isFocused
            ? 'border-violet-500 bg-violet-500/5 ring-2 ring-violet-500/20'
            : 'border-muted-foreground/25 hover:border-muted-foreground/50',
      ]"
    >
      <template v-if="isUploading">
        <Loader2 class="mb-3 h-10 w-10 animate-spin text-violet-500" />
        <p class="text-sm text-muted-foreground">Uploading...</p>
      </template>
      <template v-else>
        <ImageIcon
          :class="['mb-3 transition-colors', compact ? 'h-8 w-8' : 'h-10 w-10', isFocused ? 'text-violet-500' : 'text-muted-foreground/50']"
        />
        <p v-if="!isFocused" class="mb-1 text-sm font-medium text-accent-foreground">Click here, then paste from clipboard (Ctrl+V)</p>
        <p v-else class="mb-1 animate-pulse text-sm font-medium text-violet-500">Ready — press Ctrl+V to paste your image</p>
        <p class="mb-4 text-xs text-muted-foreground">or drag and drop an image, or use the button below</p>
        <button type="button" @click.stop="fileInput?.click()" class="btn btn-secondary btn-sm">
          <Upload class="mr-1.5 h-3.5 w-3.5" />
          Browse files
        </button>
        <input ref="fileInput" type="file" :accept="ACCEPT_ATTRIBUTE" class="hidden" @change="handleFileSelect" />
      </template>
    </div>
  </div>
</template>
