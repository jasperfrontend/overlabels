<template>
  <Modal :show="show" @close="$emit('close')" max-width="6xl">
    <div class="px-6 py-4">
      <div class="flex items-center space-x-2 mb-4">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z" />
        </svg>
        <span class="text-lg font-medium text-gray-900 dark:text-gray-100">Browse Storage Files</span>
      </div>
      <div class="h-96">
        <div v-if="storageAccounts.length === 0" class="flex flex-col items-center justify-center h-full space-y-4">
          <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z" />
          </svg>
          <div class="text-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No Storage Accounts Connected</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">
              Connect your cloud storage accounts to browse and use your files in templates.
            </p>
            <a
              :href="route('storage.index')"
              class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition duration-150 ease-in-out"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Connect Storage Account
            </a>
          </div>
        </div>

        <StorageBrowser
          v-else
          :accounts="storageAccounts"
          @file-select="onFileSelect"
        />
      </div>

      <div class="flex items-center justify-between w-full mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-4">
          <label class="flex items-center space-x-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Insert as:</span>
          </label>
          <select
            v-model="insertType"
            class="text-sm border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
          >
            <option value="img">Image Tag</option>
            <option value="background">Background CSS</option>
            <option value="url">URL Only</option>
          </select>
        </div>
        
        <div class="flex items-center space-x-3">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Modal from '@/components/Modal.vue'
import StorageBrowser from './StorageBrowser.vue'
import { useStorage } from '@/composables/useStorage'

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

const props = defineProps<{
  show: boolean
  editor?: any
}>()

const emit = defineEmits<{
  close: []
  fileSelect: [file: FileItem, downloadUrl: string, insertType: string]
}>()

const { accounts: storageAccounts, fetchStorageAccounts, insertImageIntoEditor } = useStorage()
const insertType = ref<'img' | 'background' | 'url'>('img')

const onFileSelect = (file: FileItem, downloadUrl: string) => {
  if (props.editor) {
    insertImageIntoEditor(props.editor, file, downloadUrl, insertType.value)
  }
  
  emit('fileSelect', file, downloadUrl, insertType.value)
  emit('close')
}

onMounted(() => {
  fetchStorageAccounts()
})
</script>