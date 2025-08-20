<template>
  <AppLayout title="Storage Accounts">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Storage Accounts
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6 sm:px-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="mt-8 text-2xl font-semibold text-gray-900 dark:text-gray-100">
              Connected Storage Accounts
            </div>
            <div class="mt-6 text-gray-500 dark:text-gray-400">
              Connect your cloud storage accounts to use your files in overlay templates.
            </div>
          </div>

          <div class="bg-gray-200 dark:bg-gray-700 bg-opacity-25 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8 p-6 lg:p-8">
            <!-- Google Drive -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <div class="flex items-center">
                <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                  <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12.01 2c-5.5 0-9.99 4.49-9.99 10.02 0 5.53 4.49 10.02 9.99 10.02s10.01-4.49 10.01-10.02c0-5.53-4.51-10.02-10.01-10.02zm-1.99 14.5v-9l7.5 4.5-7.5 4.5z"/>
                  </svg>
                </div>
                <div class="ml-4">
                  <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google Drive</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ getAccountStatus('google_drive') }}
                  </div>
                </div>
              </div>
              <div class="mt-4">
                <button
                  v-if="!hasAccount('google_drive')"
                  @click="connectProvider('google_drive')"
                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out"
                >
                  Connect Google Drive
                </button>
                <div v-else class="space-y-2">
                  <div class="text-sm text-gray-600 dark:text-gray-300">
                    Connected as {{ getAccount('google_drive')?.email }}
                  </div>
                  <div class="flex space-x-2">
                    <button
                      @click="disconnectAccount(getAccount('google_drive'))"
                      class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Disconnect
                    </button>
                    <button
                      @click="removeAccount(getAccount('google_drive'))"
                      class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- OneDrive -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <div class="flex items-center">
                <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                  <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13.5 2A8.5 8.5 0 0 0 5 10.5a5.5 5.5 0 0 0 0 11h13a3 3 0 0 0 3-3 3 3 0 0 0-3-3h-1.5a5.5 5.5 0 0 0-10.5-5.5 8.5 8.5 0 0 1 7.5-8Z"/>
                  </svg>
                </div>
                <div class="ml-4">
                  <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">OneDrive</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ getAccountStatus('onedrive') }}
                  </div>
                </div>
              </div>
              <div class="mt-4">
                <button
                  v-if="!hasAccount('onedrive')"
                  @click="connectProvider('onedrive')"
                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out"
                >
                  Connect OneDrive
                </button>
                <div v-else class="space-y-2">
                  <div class="text-sm text-gray-600 dark:text-gray-300">
                    Connected as {{ getAccount('onedrive')?.email }}
                  </div>
                  <div class="flex space-x-2">
                    <button
                      @click="disconnectAccount(getAccount('onedrive'))"
                      class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Disconnect
                    </button>
                    <button
                      @click="removeAccount(getAccount('onedrive'))"
                      class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dropbox -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
              <div class="flex items-center">
                <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                  <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M7.5 2L12 6l4.5-4 4.5 2.5L16.5 8l-4.5 4 4.5 4-4.5 2.5L12 18l-4.5 4L3 19.5 7.5 16l-4.5-4L7.5 9.5z"/>
                  </svg>
                </div>
                <div class="ml-4">
                  <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">Dropbox</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ getAccountStatus('dropbox') }}
                  </div>
                </div>
              </div>
              <div class="mt-4">
                <button
                  v-if="!hasAccount('dropbox')"
                  @click="connectProvider('dropbox')"
                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out"
                >
                  Connect Dropbox
                </button>
                <div v-else class="space-y-2">
                  <div class="text-sm text-gray-600 dark:text-gray-300">
                    Connected as {{ getAccount('dropbox')?.email }}
                  </div>
                  <div class="flex space-x-2">
                    <button
                      @click="disconnectAccount(getAccount('dropbox'))"
                      class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Disconnect
                    </button>
                    <button
                      @click="removeAccount(getAccount('dropbox'))"
                      class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-3 rounded text-sm transition duration-150 ease-in-out"
                    >
                      Remove
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue';

interface StorageAccount {
  id: number
  provider: string
  provider_display_name: string
  email: string
  name: string
  created_at: string
  token_expires_at: string | null
  needs_refresh: boolean
}

const props = defineProps<{
  accounts: StorageAccount[]
}>()

const hasAccount = (provider: string) => {
  return props.accounts.some(account => account.provider === provider)
}

const getAccount = (provider: string) => {
  return props.accounts.find(account => account.provider === provider)
}

const getAccountStatus = (provider: string) => {
  const account = getAccount(provider)
  if (!account) return 'Not connected'
  if (account.needs_refresh) return 'Needs refresh'
  return 'Connected'
}

const connectProvider = (provider: string) => {
  window.location.href = route('storage.connect', { provider })
}

const disconnectAccount = (account: StorageAccount | undefined) => {
  if (!account) return

  router.patch(route('storage.disconnect', { account: account.id }), {}, {
    preserveScroll: true,
  })
}

const removeAccount = (account: StorageAccount | undefined) => {
  if (!account) return

  if (confirm('Are you sure you want to remove this storage account? This action cannot be undone.')) {
    router.delete(route('storage.destroy', { account: account.id }), {
      preserveScroll: true,
    })
  }
}
</script>
