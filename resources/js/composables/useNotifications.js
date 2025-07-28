import { ref, computed } from 'vue'

const notifications = ref([])
let notificationId = 0

export function useNotifications() {
  const addNotification = (options) => {
    const notification = {
      id: ++notificationId,
      type: 'info',
      duration: 4000,
      ...options,
      timestamp: Date.now()
    }
    
    notifications.value.push(notification)
    
    // Auto-remove after duration (backup in case toast doesn't auto-close)
    setTimeout(() => {
      removeNotification(notification.id)
    }, notification.duration + 1000)
    
    return notification.id
  }
  
  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
    }
  }
  
  const clearAll = () => {
    notifications.value = []
  }
  
  // Convenience methods
  const success = (message, options = {}) => 
    addNotification({ message, type: 'success', ...options })
  
  const error = (message, options = {}) => 
    addNotification({ message, type: 'error', duration: 6000, ...options })
  
  const warning = (message, options = {}) => 
    addNotification({ message, type: 'warning', ...options })
  
  const info = (message, options = {}) => 
    addNotification({ message, type: 'info', ...options })
  
  return {
    notifications: computed(() => notifications.value),
    addNotification,
    removeNotification,
    clearAll,
    success,
    error,
    warning,
    info
  }
}