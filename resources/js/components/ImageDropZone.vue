<script setup lang="ts">
import { ref } from 'vue';
import { ImageIcon, Trash2, Upload, Loader2 } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
  modelValue: string | null;
  uploadPreset: string;
  folder: string;
  compact?: boolean;
}>(), {
  compact: false,
});

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

const CLOUD_NAME = window.cloudinaryCloudName;
const UPLOAD_URL = `https://api.cloudinary.com/v1_1/${CLOUD_NAME}/image/upload`;

async function uploadToCloudinary(file: File) {
  isUploading.value = true;
  emit('uploading', true);

  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_preset', props.uploadPreset);
    formData.append('folder', props.folder);

    const response = await fetch(UPLOAD_URL, {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`Upload failed: ${response.statusText}`);
    }

    const data = await response.json();
    emit('update:modelValue', data.secure_url);
  } catch (err: any) {
    emit('error', err.message || 'Upload failed');
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
        :class="[compact ? 'h-48 w-auto rounded-lg object-cover' : 'max-h-96 rounded border border-sidebar', 'cursor-pointer hover:opacity-80 transition-opacity']"
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
        <ImageIcon :class="['mb-3 transition-colors', compact ? 'h-8 w-8' : 'h-10 w-10', isFocused ? 'text-violet-500' : 'text-muted-foreground/50']" />
        <p v-if="!isFocused" class="mb-1 text-sm font-medium text-accent-foreground">
          Click here, then paste from clipboard (Ctrl+V)
        </p>
        <p v-else class="mb-1 animate-pulse text-sm font-medium text-violet-500">
          Ready — press Ctrl+V to paste your image
        </p>
        <p class="mb-4 text-xs text-muted-foreground">
          or drag and drop an image, or use the button below
        </p>
        <button type="button" @click.stop="fileInput?.click()" class="btn btn-secondary btn-sm">
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

    <!-- Replace zone when image exists -->
    <div v-if="props.modelValue && !isUploading">
      <p class="mb-2 text-xs text-muted-foreground">Replace image:</p>
      <div
        tabindex="0"
        @paste="handlePaste"
        @drop.prevent="handleDrop"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @focus="isFocused = true"
        @blur="isFocused = false"
        :class="[
          'flex items-center justify-center gap-3 rounded border-2 border-dashed p-4 text-center transition-all outline-none',
          isDragging
            ? 'border-violet-500 bg-violet-500/10'
            : isFocused
              ? 'border-violet-500 bg-violet-500/5 ring-2 ring-violet-500/20'
              : 'border-muted-foreground/25 hover:border-muted-foreground/50',
        ]"
      >
        <p v-if="!isFocused" class="text-xs text-muted-foreground">Click here and paste (Ctrl+V), drag an image, or</p>
        <p v-else class="animate-pulse text-xs font-medium text-violet-500">Press Ctrl+V to paste, or</p>
        <button type="button" @click.stop="fileInput?.click()" class="btn btn-secondary btn-xs">
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
