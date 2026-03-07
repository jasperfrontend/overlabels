<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ImageIcon, Trash2, Upload, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
  screenshotUrl: string | null;
  templateId: number;
}>();

const emit = defineEmits<{
  saved: [];
  removed: [];
  error: [message: string];
}>();

const isUploading = ref(false);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const dropZone = ref<HTMLDivElement | null>(null);

const CLOUD_NAME = window.cloudinaryCloudName;
const UPLOAD_PRESET = 'overlabels-overlay-screenshots';
const UPLOAD_URL = `https://api.cloudinary.com/v1_1/${CLOUD_NAME}/image/upload`;

async function uploadToCloudinary(file: File) {
  isUploading.value = true;

  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_preset', UPLOAD_PRESET);
    formData.append('folder', 'overlays/screenshots');

    const response = await fetch(UPLOAD_URL, {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`Upload failed: ${response.statusText}`);
    }

    const data = await response.json();
    saveScreenshotUrl(data.secure_url);
  } catch (err: any) {
    isUploading.value = false;
    emit('error', err.message || 'Upload failed');
  }
}

function saveScreenshotUrl(url: string) {
  router.put(
    route('templates.screenshot', props.templateId),
    { screenshot_url: url },
    {
      preserveScroll: true,
      onSuccess: () => {
        isUploading.value = false;
        emit('saved');
      },
      onError: () => {
        isUploading.value = false;
        emit('error', 'Failed to save screenshot');
      },
    },
  );
}

function removeScreenshot() {
  router.put(
    route('templates.screenshot', props.templateId),
    { screenshot_url: null },
    {
      preserveScroll: true,
      onSuccess: () => emit('removed'),
      onError: () => emit('error', 'Failed to remove screenshot'),
    },
  );
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
    uploadToCloudinary(file);
  }
}

function handleDrop(event: DragEvent) {
  isDragging.value = false;
  const file = event.dataTransfer?.files[0];
  if (file && file.type.startsWith('image/')) {
    uploadToCloudinary(file);
  }
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (file) {
    uploadToCloudinary(file);
  }
  // Reset so the same file can be re-selected
  input.value = '';
}
</script>

<template>
  <div class="space-y-4">
    <!-- Current screenshot -->
    <div v-if="props.screenshotUrl && !isUploading" class="space-y-3">
      <p class="text-sm text-muted-foreground">Current screenshot</p>
      <img
        :src="props.screenshotUrl"
        alt="Overlay screenshot"
        class="max-h-96 rounded border border-sidebar"
      />
      <div class="flex gap-2">
        <button type="button" @click="removeScreenshot" class="btn btn-cancel btn-sm">
          <Trash2 class="mr-1.5 h-3.5 w-3.5" />
          Remove
        </button>
      </div>
    </div>

    <!-- Upload / paste zone -->
    <div
      v-if="!props.screenshotUrl || isUploading"
      ref="dropZone"
      tabindex="0"
      @paste="handlePaste"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      :class="[
        'flex flex-col items-center justify-center rounded border-2 border-dashed p-12 text-center transition-colors outline-none',
        isDragging
          ? 'border-violet-500 bg-violet-500/10'
          : 'border-muted-foreground/25 hover:border-muted-foreground/50',
      ]"
    >
      <template v-if="isUploading">
        <Loader2 class="mb-3 h-10 w-10 animate-spin text-violet-500" />
        <p class="text-sm text-muted-foreground">Uploading screenshot...</p>
      </template>
      <template v-else>
        <ImageIcon class="mb-3 h-10 w-10 text-muted-foreground/50" />
        <p class="mb-1 text-sm font-medium text-accent-foreground">
          Click here, then paste from clipboard (Ctrl+V)
        </p>
        <p class="mb-4 text-xs text-muted-foreground">
          or drag and drop an image, or use the button below
        </p>
        <button type="button" @click="fileInput?.click()" class="btn btn-secondary btn-sm">
          <Upload class="mr-1.5 h-3.5 w-3.5" />
          Browse files
        </button>
        <input
          ref="fileInput"
          type="file"
          accept="image/*"
          class="hidden"
          @change="handleFileSelect"
        />
      </template>
    </div>

    <!-- Replace button when screenshot exists -->
    <div v-if="props.screenshotUrl && !isUploading">
      <p class="mb-2 text-xs text-muted-foreground">Replace screenshot:</p>
      <div
        ref="dropZone"
        tabindex="0"
        @paste="handlePaste"
        @drop.prevent="handleDrop"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        :class="[
          'flex items-center justify-center gap-3 rounded border-2 border-dashed p-4 text-center transition-colors outline-none',
          isDragging
            ? 'border-violet-500 bg-violet-500/10'
            : 'border-muted-foreground/25 hover:border-muted-foreground/50',
        ]"
      >
        <p class="text-xs text-muted-foreground">Click here and paste (Ctrl+V), drag an image, or</p>
        <button type="button" @click="fileInput?.click()" class="btn btn-secondary btn-xs">
          <Upload class="mr-1 h-3 w-3" />
          Browse
        </button>
        <input
          ref="fileInput"
          type="file"
          accept="image/*"
          class="hidden"
          @change="handleFileSelect"
        />
      </div>
    </div>
  </div>
</template>
