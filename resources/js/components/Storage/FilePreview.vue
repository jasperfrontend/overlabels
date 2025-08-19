<template>
  <div
    class="group relative bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors duration-200 overflow-hidden"
    @click="$emit('click', file)"
  >
    <!-- File Icon/Thumbnail -->
    <div class="aspect-square bg-gray-50 dark:bg-gray-700 flex items-center justify-center relative">
      <!-- Image Thumbnail -->
      <img
        v-if="file.thumbnailUrl && file.isImage"
        :src="file.thumbnailUrl"
        :alt="file.name"
        class="w-full h-full object-cover"
        @error="onImageError"
      />
      
      <!-- Video Thumbnail -->
      <div v-else-if="file.isVideo" class="w-full h-full bg-gray-900 flex items-center justify-center">
        <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
          <path d="M8 5v14l11-7z"/>
        </svg>
        <img
          v-if="file.thumbnailUrl"
          :src="file.thumbnailUrl"
          :alt="file.name"
          class="absolute inset-0 w-full h-full object-cover opacity-50"
          @error="onImageError"
        />
      </div>
      
      <!-- Folder Icon -->
      <div v-else-if="file.type === 'folder'" class="text-blue-500 dark:text-blue-400">
        <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 24 24">
          <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/>
        </svg>
      </div>
      
      <!-- Generic File Icon -->
      <div v-else class="text-gray-400 dark:text-gray-500">
        <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 24 24">
          <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
        </svg>
      </div>
      
      <!-- File Type Indicator -->
      <div
        v-if="file.type === 'file'"
        class="absolute top-2 right-2 px-2 py-1 bg-black bg-opacity-50 text-white text-xs rounded"
      >
        {{ getFileExtension(file.name) }}
      </div>
      
      <!-- Selection Overlay -->
      <div class="absolute inset-0 bg-blue-500 bg-opacity-20 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
        <div class="absolute inset-0 flex items-center justify-center">
          <button
            v-if="file.type === 'file'"
            @click.stop="$emit('select', file)"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-transform duration-200"
          >
            Select
          </button>
          <div
            v-else
            class="px-4 py-2 bg-gray-800 text-white rounded-md shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-transform duration-200"
          >
            Open
          </div>
        </div>
      </div>
    </div>
    
    <!-- File Info -->
    <div class="p-3">
      <div class="font-medium text-gray-900 dark:text-gray-100 truncate" :title="file.name">
        {{ file.name }}
      </div>
      <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        <span v-if="file.type === 'folder'">Folder</span>
        <span v-else-if="file.size">{{ formatFileSize(file.size) }}</span>
        <span v-else>File</span>
        
        <span v-if="file.modifiedAt" class="block">
          {{ formatDate(file.modifiedAt) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface FileItem {
  id: string
  name: string
  type: 'file' | 'folder'
  mimeType?: string
  size?: number
  thumbnailUrl?: string
  webViewLink?: string
  downloadUrl?: string
  modifiedAt?: string
  iconUrl?: string
  isImage?: boolean
  isVideo?: boolean
  path?: string
}

defineProps<{
  file: FileItem
}>()

defineEmits<{
  click: [file: FileItem]
  select: [file: FileItem]
}>()

const getFileExtension = (filename: string): string => {
  const ext = filename.split('.').pop()?.toUpperCase()
  return ext || 'FILE'
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 B'
  
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

const formatDate = (dateString: string): string => {
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString()
  } catch {
    return dateString
  }
}

const onImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  img.style.display = 'none'
}
</script>