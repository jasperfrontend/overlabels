<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'EventSub Demo',
    href: '/eventsub-demo',
  },
];

// State
const isConnected = ref(false);
const isConnecting = ref(false);
const isWebSocketConnected = ref(false);
const events = ref<Array<any>>([]);
const subscriptionStatus = ref<any>(null);
const echo = ref<Echo | null>(null);

// Auto-scroll container
const eventsContainer = ref<HTMLElement | null>(null);

// Initialize WebSocket connection
const initializeEcho = () => {
  if (echo.value) return;

  console.log('🔄 Initializing Echo connection...');

  // Configure Laravel Echo
  window.Pusher = Pusher;
  
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
  const host = import.meta.env.VITE_REVERB_HOST || 'localhost';
  const port = import.meta.env.VITE_REVERB_PORT || 8080;
  
  console.log('Echo config:', { scheme, host, port });
  
  echo.value = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
    forceTLS: true
  });

  // Listen for connection state changes
  echo.value.connector.pusher.connection.bind('connected', () => {
    console.log('✅ WebSocket connected');
    isWebSocketConnected.value = true;
  });

  echo.value.connector.pusher.connection.bind('disconnected', () => {
    console.log('❌ WebSocket disconnected');
    isWebSocketConnected.value = false;
  });

  echo.value.connector.pusher.connection.bind('failed', () => {
    console.log('💥 WebSocket connection failed');
    isWebSocketConnected.value = false;
  });

  echo.value.connector.pusher.connection.bind('error', (error: any) => {
    console.log('🚨 WebSocket error:', error);
    isWebSocketConnected.value = false;
  });

  // Listen for Twitch events
  echo.value.channel('twitch-events')
    .listen('.twitch.event', (event: any) => {
      console.log('🎉 Received Twitch event:', event);
      
      // Add to events list
      events.value.unshift({
        ...event,
        id: Date.now() + Math.random(),
        receivedAt: new Date().toLocaleTimeString()
      });

      // Keep only last 50 events
      if (events.value.length > 50) {
        events.value = events.value.slice(0, 50);
      }

      // Auto scroll to top
      scrollToTop();
    });

  console.log('🎧 Echo initialized and listening for events');
};

// Scroll to top of events
const scrollToTop = () => {
  if (eventsContainer.value) {
    eventsContainer.value.scrollTop = 0;
  }
};

// Connect to EventSub
const connect = async () => {
  if (isConnecting.value) return;
  
  console.log('🔌 Starting EventSub connection...');
  isConnecting.value = true;
  
  try {
    const response = await fetch('/eventsub/connect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });
    
    const data = await response.json();
    console.log('📡 EventSub API Response:', data);
    
    if (response.ok) {
      console.log('✅ Connected to EventSub:', data);
      
      // Add a connection event to the log
      events.value.unshift({
        id: Date.now(),
        type: 'system',
        data: { message: 'Connected to Twitch EventSub', details: data },
        timestamp: new Date().toISOString(),
        receivedAt: new Date().toLocaleTimeString()
      });
      
      // Check status to determine if we're actually connected
      await checkStatus();
    } else {
      console.error('❌ EventSub connection failed:', data);
      throw new Error(data.error || 'Failed to connect');
    }
  } catch (error) {
    console.error('💥 Connection failed:', error);
    
    events.value.unshift({
      id: Date.now(),
      type: 'error',
      data: { message: 'Failed to connect', error: error.message },
      timestamp: new Date().toISOString(),
      receivedAt: new Date().toLocaleTimeString()
    });
  } finally {
    isConnecting.value = false;
    console.log('🏁 EventSub connection attempt finished');
  }
};

// Disconnect from EventSub
const disconnect = async () => {
  console.log('🔌 Disconnecting from EventSub...');
  
  try {
    const response = await fetch('/eventsub/disconnect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });
    
    const data = await response.json();
    console.log('📡 Disconnect API Response:', data);
    
    if (response.ok) {
      isConnected.value = false;
      console.log('✅ Disconnected from EventSub:', data);
      
      events.value.unshift({
        id: Date.now(),
        type: 'system',
        data: { message: 'Disconnected from Twitch EventSub', details: data },
        timestamp: new Date().toISOString(),
        receivedAt: new Date().toLocaleTimeString()
      });
      
      subscriptionStatus.value = null;
    } else {
      console.error('❌ Disconnect failed:', data);
      throw new Error(data.error || 'Failed to disconnect');
    }
  } catch (error) {
    console.error('💥 Disconnect failed:', error);
    
    events.value.unshift({
      id: Date.now(),
      type: 'error',
      data: { message: 'Failed to disconnect', error: error.message },
      timestamp: new Date().toISOString(),
      receivedAt: new Date().toLocaleTimeString()
    });
  }
};

// Check subscription status
const checkStatus = async () => {
  console.log('🔍 Checking EventSub status...');
  
  try {
    const response = await fetch('/eventsub/status');
    const data = await response.json();
    console.log('📊 Status response:', data);
    
    if (response.ok) {
      subscriptionStatus.value = data;
      isConnected.value = data.total > 0;
      console.log(`📈 EventSub status: ${data.total} active subscriptions`);
    }
  } catch (error) {
    console.error('💥 Status check failed:', error);
  }
};

// Clear events log
const clearEvents = () => {
  console.log('🧹 Clearing events log');
  events.value = [];
};

// Get event type styling
const getEventTypeClass = (type: string) => {
  switch (type) {
    case 'channel.follow':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    case 'channel.subscribe':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
    case 'system':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    case 'error':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
  }
};

// Lifecycle
onMounted(() => {
  console.log('🚀 Component mounted, initializing...');
  initializeEcho();
  checkStatus();
});

onUnmounted(() => {
  console.log('💀 Component unmounting, cleaning up...');
  if (echo.value) {
    echo.value.disconnect();
  }
});
</script>

<template>
  <Head title="EventSub Demo" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <!-- Header Controls -->
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Twitch EventSub Demo</h1>
        
        <div class="flex items-center gap-4">
          <!-- Status Indicators -->
          <div class="flex items-center gap-4">
            <!-- EventSub Status -->
            <div class="flex items-center gap-2">
              <div 
                class="h-3 w-3 rounded-full transition-colors"
                :class="isConnected ? 'bg-green-500' : 'bg-red-500'"
              />
              <span class="text-sm font-medium">
                EventSub: {{ isConnected ? 'Connected' : 'Disconnected' }}
              </span>
            </div>
            
            <!-- WebSocket Status -->
            <div class="flex items-center gap-2">
              <div 
                class="h-2 w-2 rounded-full transition-colors"
                :class="isWebSocketConnected ? 'bg-blue-500' : 'bg-gray-400'"
              />
              <span class="text-xs text-muted-foreground">
                WebSocket: {{ isWebSocketConnected ? 'Connected' : 'Disconnected' }}
              </span>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <Button 
            @click="connect" 
            :disabled="isConnected || isConnecting"
            variant="default"
          >
            {{ isConnecting ? 'Connecting...' : 'Connect' }}
          </Button>
          
          <Button 
            @click="disconnect" 
            :disabled="!isConnected"
            variant="outline"
          >
            Disconnect
          </Button>
          
          <Button 
            @click="clearEvents" 
            variant="outline"
          >
            Clear Events
          </Button>
        </div>
      </div>

      <!-- Subscription Status -->
      <div v-if="subscriptionStatus" class="rounded-lg border bg-muted/50 p-4">
        <h3 class="font-semibold mb-2">Active Subscriptions ({{ subscriptionStatus.total }})</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          <div 
            v-for="sub in subscriptionStatus.subscriptions" 
            :key="sub.id"
            class="text-sm bg-background rounded p-2 border"
          >
            <div class="font-medium">{{ sub.type }}</div>
            <div class="text-muted-foreground text-xs">{{ sub.status }}</div>
          </div>
        </div>
      </div>

      <!-- Events Feed -->
      <div class="flex-1 rounded-lg border bg-background overflow-hidden">
        <div class="border-b bg-muted/50 p-3">
          <h2 class="font-semibold">Live Events Feed</h2>
          <p class="text-sm text-muted-foreground">
            Events will appear here in real-time. Follow your channel or subscribe to see them!
          </p>
        </div>
        
        <div 
          ref="eventsContainer"
          class="h-96 overflow-y-auto p-4 space-y-3"
        >
          <div v-if="events.length === 0" class="text-center text-muted-foreground py-8">
            No events yet. Connect to EventSub and interact with your Twitch channel!
          </div>
          
          <div 
            v-for="event in events" 
            :key="event.id"
            class="border rounded-lg p-3 bg-card"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span 
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getEventTypeClass(event.type)"
                  >
                    {{ event.type }}
                  </span>
                  <span class="text-xs text-muted-foreground">
                    {{ event.receivedAt }}
                  </span>
                </div>
                
                <!-- Event Details -->
                <div class="space-y-1">
                  <div v-if="event.type === 'channel.follow'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> started following!
                  </div>
                  
                  <div v-else-if="event.type === 'channel.subscribe'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> subscribed!
                    <span v-if="event.data.tier" class="text-muted-foreground">
                      (Tier {{ Math.floor(event.data.tier / 1000) }})
                    </span>
                  </div>
                  
                  <div v-else-if="event.type === 'system'" class="text-sm">
                    {{ event.data.message }}
                  </div>
                  
                  <div v-else-if="event.type === 'error'" class="text-sm text-red-600">
                    Error: {{ event.data.message }}
                  </div>
                  
                  <div v-else class="text-sm">
                    Unknown event type: {{ event.type }}
                  </div>
                </div>
                
                <!-- Raw Data (Collapsible) -->
                <details v-if="event.data" class="mt-2">
                  <summary class="text-xs text-muted-foreground cursor-pointer hover:text-foreground">
                    Show raw data
                  </summary>
                  <pre class="text-xs bg-muted rounded p-2 mt-1 overflow-x-auto">{{ JSON.stringify(event.data, null, 2) }}</pre>
                </details>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>