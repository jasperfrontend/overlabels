<!-- App.vue -->
<template>
  <ToastProvider :duration="5000" swipe-direction="right">
    <div class="bg-accent p-8">
      <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Reka UI Notifications Demo</h1>
        
        <div class="space-y-4">
          <button 
            @click="showSimpleSuccess"
            class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
          >
            Simple Success
          </button>
          
          <button 
            @click="showErrorWithAction"
            class="w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
          >
            Error with Action
          </button>
          
          <button 
            @click="showWarningWithTitle"
            class="w-full px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700"
          >
            Warning with Title
          </button>
          
          <button 
            @click="showPersistentInfo"
            class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            Persistent Info
          </button>
          
          <button 
            @click="clearAllNotifications"
            class="w-full px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
          >
            Clear All
          </button>
        </div>
      </div>
      
      <!-- Notifications Container -->
      <div class="fixed top-4 right-4 space-y-2 z-50">
        <NotificationToast
          v-for="notification in notifications"
          :key="notification.id"
          :notification="notification"
          :show-progress="true"
          @dismiss="removeNotification"
          @action="handleNotificationAction"
        />
      </div>
    </div>
    
    <!-- Required by Reka UI -->
    <ToastViewport />
  </ToastProvider>
</template>

<script setup>
import { ToastProvider, ToastViewport } from 'reka-ui'
import { useNotifications } from '@/composables/useNotifications.js'
import NotificationToast from '@/components/NotificationToast.vue'

const { 
  notifications, 
  success, 
  error, 
  warning, 
  info, 
  removeNotification, 
  clearAll 
} = useNotifications()

const showSimpleSuccess = () => {
  success('Your changes have been saved successfully!')
}

const showErrorWithAction = () => {
  error('Failed to upload file. Please try again.', {
    title: 'Upload Error',
    action: {
      label: 'Retry',
      altText: 'Retry file upload',
      handler: () => {
        // Handle retry logic
        console.log('Retrying upload...')
        success('Upload retry initiated!')
      }
    }
  })
}

const showWarningWithTitle = () => {
  warning('Your session will expire in 5 minutes.', {
    title: 'Session Warning',
    duration: 8000,
    action: {
      label: 'Extend',
      altText: 'Extend session',
      handler: () => {
        info('Session extended for 30 minutes')
      }
    }
  })
}

const showPersistentInfo = () => {
  info('This is important information that you should read.', {
    title: 'System Update',
    duration: 10000, // 10 seconds
    action: {
      label: 'Learn More',
      altText: 'Learn more about the system update',
      closeAfter: false, // Don't close after clicking
      handler: () => {
        window.open('https://example.com/updates', '_blank')
      }
    }
  })
}

const clearAllNotifications = () => {
  clearAll()
}

const handleNotificationAction = ({ notificationId, action }) => {
  console.log('Notification action triggered:', { notificationId, action })
}
</script>