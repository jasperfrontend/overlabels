<template>
  <div class="storage-browser h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center space-x-2">
        <button
          v-if="currentPath.length > 0"
          @click="navigateUp"
          class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
          {{ selectedAccount ? selectedAccount.provider_display_name : 'Select Storage' }}
        </h3>
      </div>
      
      <div class="flex items-center space-x-2">
        <!-- Provider Selector -->
        <select
          v-model="selectedAccountId"
          @change="onAccountChange"
          class="text-sm border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
        >
          <option value="">Select Provider</option>
          <option
            v-for="account in accounts"
            :key="account.id"
            :value="account.id"
          >
            {{ account.provider_display_name }} - {{ account.email }}
          </option>
        </select>
        
        <button
          v-if="selectedAccount"
          @click="refreshFiles"
          :disabled="loading"
          class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-50"
        >
          <svg class="w-5 h-5" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Breadcrumb -->
    <div v-if="currentPath.length > 0" class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
        <button @click="navigateToRoot" class="hover:text-gray-900 dark:hover:text-gray-100">
          Root
        </button>
        <template v-for="(part, index) in pathParts" :key="index">
          <span>/</span>
          <button
            @click="navigateToPath(index)"
            class="hover:text-gray-900 dark:hover:text-gray-100"
            :class="{ 'font-medium text-gray-900 dark:text-gray-100': index === pathParts.length - 1 }"
          >
            {{ part }}
          </button>
        </template>
      </div>
    </div>

    <!-- File List -->
    <div class="flex-1 overflow-auto">
      <div v-if="loading && files.length === 0" class="flex items-center justify-center h-32">
        <div class="text-gray-500 dark:text-gray-400">Loading files...</div>
      </div>

      <div v-else-if="error" class="p-4 text-red-600 dark:text-red-400">
        {{ error }}
      </div>

      <div v-else-if="!selectedAccount" class="flex items-center justify-center h-32">
        <div class="text-gray-500 dark:text-gray-400">Select a storage provider to browse files</div>
      </div>

      <div v-else-if="files.length === 0" class="flex items-center justify-center h-32">
        <div class="text-gray-500 dark:text-gray-400">No files found</div>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
        <FilePreview
          v-for="file in files"
          :key="file.id"
          :file="file"
          @click="onFileClick"
          @select="onFileSelect"
          class="cursor-pointer"
        />
      </div>

      <!-- Load More -->
      <div v-if="hasMore && !loading" class="p-4 text-center">
        <button
          @click="loadMore"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-150 ease-in-out"
        >
          Load More
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'
import FilePreview from './FilePreview.vue'

interface StorageAccount {
  id: number
  provider: string
  provider_display_name: string
  email: string
  name: string
}

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

interface FileListResponse {
  files: FileItem[]
  nextCursor?: string
  hasMore: boolean
}

const props = defineProps<{
  accounts: StorageAccount[]
  onFileSelect?: (file: FileItem, downloadUrl: string) => void
}>()

const emit = defineEmits<{
  fileSelect: [file: FileItem, downloadUrl: string]
}>()

const selectedAccountId = ref<number | ''>('')
const currentPath = ref<string>('')
const files = ref<FileItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const nextCursor = ref<string | null>(null)
const hasMore = ref(false)

const selectedAccount = computed(() => {
  if (!selectedAccountId.value) return null
  return props.accounts.find(account => account.id === selectedAccountId.value) || null
})

const pathParts = computed(() => {
  if (!currentPath.value) return []
  return currentPath.value.split('/').filter(Boolean)
})

const onAccountChange = () => {
  currentPath.value = ''
  files.value = []
  nextCursor.value = null
  hasMore.value = false
  error.value = null
  
  if (selectedAccount.value) {
    loadFiles()
  }
}

const loadFiles = async (cursor?: string) => {
  if (!selectedAccount.value) return
  
  loading.value = true
  error.value = null
  
  try {
    const params: any = {
      path: currentPath.value,
    }
    
    if (cursor) {
      params.cursor = cursor
    }
    
    const response = await axios.get(`/storage/accounts/${selectedAccount.value.id}/files`, {
      params,
    })
    
    if (response.data.success) {
      const data: FileListResponse = response.data.data
      
      if (cursor) {
        files.value.push(...data.files)
      } else {
        files.value = data.files
      }
      
      nextCursor.value = data.nextCursor || null
      hasMore.value = data.hasMore
    } else {
      error.value = response.data.error || 'Failed to load files'
    }
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Failed to load files'
  } finally {
    loading.value = false
  }
}

const loadMore = () => {
  if (nextCursor.value && !loading.value) {
    loadFiles(nextCursor.value)
  }
}

const refreshFiles = () => {
  files.value = []
  nextCursor.value = null
  hasMore.value = false
  loadFiles()
}

const navigateUp = () => {
  const parts = pathParts.value
  if (parts.length > 0) {
    currentPath.value = parts.slice(0, -1).join('/')
    refreshFiles()
  }
}

const navigateToRoot = () => {
  currentPath.value = ''
  refreshFiles()
}

const navigateToPath = (index: number) => {
  const parts = pathParts.value
  currentPath.value = parts.slice(0, index + 1).join('/')
  refreshFiles()
}

const onFileClick = (file: FileItem) => {
  if (file.type === 'folder') {
    currentPath.value = file.path || file.id
    refreshFiles()
  }
}

const onFileSelect = async (file: FileItem) => {
  if (file.type === 'folder') return
  
  try {
    const response = await axios.get(`/storage/accounts/${selectedAccount.value!.id}/files/${file.id}/download-url`)
    
    if (response.data.success) {
      const downloadUrl = response.data.data.url
      emit('fileSelect', file, downloadUrl)
      
      if (props.onFileSelect) {
        props.onFileSelect(file, downloadUrl)
      }
    } else {
      error.value = response.data.error || 'Failed to get download URL'
    }
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Failed to get download URL'
  }
}

onMounted(() => {
  if (props.accounts.length === 1) {
    selectedAccountId.value = props.accounts[0].id
    onAccountChange()
  }
})
</script>