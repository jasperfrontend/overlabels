<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Heading from '@/components/Heading.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'EventSub Demo',
    href: '/eventsub-demo',
  },
];

// State
const isConnected = ref(false);
const isConnecting = ref(false);
const isLoading = ref(false);
const isWebSocketConnected = ref(false);
const events = ref<Array<any>>([]);
const subscriptionStatus = ref<any>(null);
// @ts-expect-error
const echo = ref<Echo | null>(null);
const isFetchingEvents = ref(false);
const pagination = ref<any>({
  current_page: 1,
  per_page: 15,
  total: 0
});

// Auto-scroll container
const eventsContainer = ref<HTMLElement | null>(null);

// Initialize WebSocket connection
const initializeEcho = () => {
  if (echo.value) return;

  console.log('🔄 Initializing Echo connection...');

  // Configure Laravel Echo
  (window as any).Pusher = Pusher;

  const pusherConfig = {
    broadcaster: 'pusher' as const,
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
    forceTLS: true
  };

  echo.value = new Echo(pusherConfig);

  // Listen for connection state changes
  echo.value.connector.pusher.connection.bind('connected', () => {
    console.log('WebSocket connected');
    isWebSocketConnected.value = true;
  });

  echo.value.connector.pusher.connection.bind('disconnected', () => {
    console.log('WebSocket disconnected');
    isWebSocketConnected.value = false;
  });

  echo.value.connector.pusher.connection.bind('failed', () => {
    console.log('WebSocket connection failed');
    isWebSocketConnected.value = false;
  });

  echo.value.connector.pusher.connection.bind('error', (error: any) => {
    console.log('WebSocket error:', error);
    isWebSocketConnected.value = false;
  });

  // Listen to Twitch events
  echo.value.channel('twitch-events')
    .listen('.twitch.event', (event: any) => {
      console.log('Received Twitch event:', event);

      // Format the real-time event
      const formattedEvent = {
        ...event,
        id: Date.now() + Math.random(), // Temporary ID until we refresh from DB
        receivedAt: new Date().toLocaleTimeString(),
        realtime: true, // Mark as a real-time event
        processed: false
      };

      // Add new events to the events list
      events.value.unshift(formattedEvent);

      // Keep only the last 50 events in the UI
      if (events.value.length > 50) {
        events.value = events.value.slice(0, 50);
      }

      // Auto scroll to top
      scrollToTop();

      // Refresh events from the database after a short delay to get the stored version
      setTimeout(() => {
        fetchEvents(1);
      }, 2000);
    });

  console.log('Echo initialized and listening for events');
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
  isLoading.value = true;
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

    if (response.ok) {
      isLoading.value = false;
      console.log('Connected to EventSub:', data);

      // Add a connection event to the log
      events.value.unshift({
        id: Date.now(),
        type: 'system',
        data: { message: 'Connected to Twitch EventSub', details: data },
        timestamp: new Date().toISOString(),
        receivedAt: new Date().toLocaleTimeString()
      });

      // Check status immediately to get the initial state
      await checkStatus();

      // Set up automatic status refreshing
      // Check again after 5 seconds (when verification should be complete)
      setTimeout(async () => {
        console.log('Auto-refreshing status after verification window...');
        await checkStatus();
      }, 5000);

      // Continue checking every 10 seconds for the first minute
      // (in case verification takes longer)
      let refreshCount = 0;
      const refreshInterval = setInterval(async () => {
        refreshCount++;
        console.log(`Auto-refresh ${refreshCount}/6...`);

        await checkStatus();

        // Stop after 6 refreshes (1 minute total)
        if (refreshCount >= 6) {
          clearInterval(refreshInterval);
          console.log('Auto-refresh complete');
        }
      }, 10000);

    } else {
      console.error('EventSub connection failed:', data);
      isLoading.value = false;

    }
  } catch (error) {
    isLoading.value = false;
    console.error('Connection failed:', error);

    events.value.unshift({
      id: Date.now(),
      type: 'error',
      data: { message: 'Failed to connect', error: error },
      timestamp: new Date().toISOString(),
      receivedAt: new Date().toLocaleTimeString()
    });
  } finally {
    isLoading.value = false;
    isConnecting.value = false;
    console.log('EventSub connection attempt finished');
  }
};

// Disconnect from EventSub
const disconnect = async () => {
  isLoading.value = true;
  console.log('Disconnecting from EventSub...');

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
      isLoading.value = false;
      console.log('Disconnected from EventSub:', data);

      events.value.unshift({
        id: Date.now(),
        type: 'system',
        data: { message: 'Disconnected from Twitch EventSub', details: data },
        timestamp: new Date().toISOString(),
        receivedAt: new Date().toLocaleTimeString()
      });

      subscriptionStatus.value = null;
    } else {
      console.error('Disconnect failed:', data);
      isLoading.value = false;
      console.error(data.error || 'Failed to disconnect');
    }
  } catch (error) {
    isLoading.value = false;
    console.error('Disconnect failed:', error);

    events.value.unshift({
      id: Date.now(),
      type: 'error',
      data: { message: 'Failed to disconnect', error: error },
      timestamp: new Date().toISOString(),
      receivedAt: new Date().toLocaleTimeString()
    });
  }
};

// Check subscription status
const checkStatus = async () => {

  try {
    const response = await fetch('/eventsub/check-status');
    const data = await response.json();

    if (response.ok) {
      subscriptionStatus.value = data;
      isConnected.value = data.total > 0;
    }
  } catch (error) {
    console.error('Status check failed:', error);
  }
};

// Clear events log
const clearEvents = () => {
  events.value = [];
};

// Get event type styling
const getEventTypeClass = (type: string) => {
  switch (type) {
    case 'channel.follow':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
    case 'channel.subscribe':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
    case 'channel.cheer':
      return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
    case 'extension.bits_transaction.create':
      return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200';
    case 'stream.online':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    case 'channel.raid':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
    case 'system':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
    case 'error':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
  }
};

// Fetch events from the database
const fetchEvents = async (page = 1) => {
  if (isFetchingEvents.value) return;

  isFetchingEvents.value = true;

  try {
    const response = await fetch(`/api/twitch/events?page=${page}&per_page=${pagination.value.per_page}`, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include', // Include cookies for authentication
    });

    if (!response.ok) {
      console.error('Failed to fetch events');
    }

    const data = await response.json();

    // Update pagination info
    pagination.value = {
      current_page: data.current_page,
      per_page: data.per_page,
      total: data.total,
      last_page: data.last_page
    };

    // Format events to match the structure expected by the UI


    // Replace events with database events
    events.value = data.data.map((event: any) => ({
      id: event.id,
      type: event.event_type,
      data: event.event_data,
      timestamp: event.twitch_timestamp,
      receivedAt: new Date(event.created_at).toLocaleTimeString(),
      fromDatabase: true,
      processed: event.processed
    }));

  } catch (error) {
    console.error('Error fetching events:', error);

    // Add error event
    events.value.unshift({
      id: Date.now(),
      type: 'error',
      data: { message: 'Failed to fetch events from database', error },
      timestamp: new Date().toISOString(),
      receivedAt: new Date().toLocaleTimeString()
    });
  } finally {
    isFetchingEvents.value = false;
  }
};

// Load the next page of events
const loadNextPage = () => {
  if (pagination.value.current_page < pagination.value.last_page) {
    fetchEvents(pagination.value.current_page + 1);
  }
};

// Load the previous page of events
const loadPreviousPage = () => {
  if (pagination.value.current_page > 1) {
    fetchEvents(pagination.value.current_page - 1);
  }
};

// Mark an event as processed
const markAsProcessed = async (eventId:any) => {
  try {
    const response = await fetch(`/api/twitch/events/${eventId}/process`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'include', // Include cookies for authentication
    });

    if (!response.ok) {
      console.error('Failed to mark event as processed');
    }

    // Update the event in the local state
    const eventIndex = events.value.findIndex(e => e.id === eventId);
    if (eventIndex !== -1) {
      events.value[eventIndex].processed = true;
    }

  } catch (error) {
    console.error('Error marking event as processed:', error);
  }
};

// Lifecycle
onMounted(() => {
  initializeEcho();
  checkStatus();
  fetchEvents(); // Fetch events from the database when component mounts
});

onUnmounted(() => {
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
        <Heading title="Twitch EventSub Demo" description="A demo of the Twitch EventSub API." />
        <div class="flex items-center gap-4">
          <!-- Status Indicators -->
          <div class="flex flex-col items-start">
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
                class="h-3 w-3 rounded-full transition-colors"
                :class="isWebSocketConnected ? 'bg-blue-500' : 'bg-gray-400'"
              />
              <span class="text-sm font-medium">
                WebSocket: {{ isWebSocketConnected ? 'Connected' : 'Disconnected' }}
              </span>
            </div>

          </div>

          <span v-if="isLoading" class="text-sm inline-block text-left text-muted-foreground animate-ping">
            <span role="status" class="w-2 h-2 rounded-full inline-block bg-purple-500">
              <span class="sr-only">Loading...</span>
            </span>
          </span>
          <button
            @click="connect"
            :disabled="isConnected || isConnecting || isLoading"
            class="btn btn-primary"
          >
            {{ isConnecting ? 'Connecting...' : 'Connect' }}
          </button>

          <button
            @click="disconnect"
            :disabled="!isConnected || isLoading"
            class="btn btn-danger"
          >
            Disconnect
          </button>

          <button
            @click="clearEvents"
            :disabled="events.length === 0 || isLoading"
            class="btn btn-cancel"
          >
            Clear Events
          </button>
        </div>
      </div>

      <!-- Subscription Status -->
      <div v-if="subscriptionStatus" class="rounded-2xl mt-2 border bg-accent/20 p-4">
        <Heading title="Active Subscriptions" :description="`Total: ${subscriptionStatus.total}`" />
        <div class="grid grid-cols-1 mt-4 md:grid-cols-2 gap-2">
          <div
            v-for="sub in subscriptionStatus.subscriptions"
            :key="sub.id"
            class="text-sm bg-background rounded p-2 border"
          >
            <div class="font-medium">{{ sub.type }}</div>
            <div class="text-green-400 text-xs"
            v-if="sub.status === 'enabled'"
            >{{ sub.status }}</div>
            <div v-else class="text-muted-foreground text-xs">{{ sub.status }}</div>
          </div>
        </div>
      </div>

      <!-- events Feed -->
      <div class="flex-1 rounded-2xl border mt-2 overflow-hidden">
        <div class="border-b bg-muted/50 p-4">
          <Heading title="Events Feed" description="Events are stored in the database and will appear here." />
          <!-- Pagination Controls -->
          <div class="flex items-center justify-between mt-2">
            <div class="text-sm text-muted-foreground">
              <span v-if="pagination.total > 0">
                Showing {{ events.length }} of {{ pagination.total }} events
              </span>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="fetchEvents(1)"
                class="px-2 py-1 text-xs rounded border hover:bg-muted"
                :disabled="isFetchingEvents || pagination.current_page === 1"
              >
                First
              </button>

              <button
                @click="loadPreviousPage()"
                class="px-2 py-1 text-xs rounded border hover:bg-muted"
                :disabled="isFetchingEvents || pagination.current_page === 1"
              >
                Previous
              </button>

              <span class="text-xs text-muted-foreground">
                Page {{ pagination.current_page }} of {{ pagination.last_page || 1 }}
              </span>

              <button
                @click="loadNextPage()"
                class="px-2 py-1 text-xs rounded border hover:bg-muted"
                :disabled="isFetchingEvents || pagination.current_page === pagination.last_page"
              >
                Next
              </button>

              <button
                @click="fetchEvents(pagination.last_page)"
                class="px-2 py-1 text-xs rounded border hover:bg-muted"
                :disabled="isFetchingEvents || pagination.current_page === pagination.last_page"
              >
                Last
              </button>

              <button
                @click="fetchEvents(pagination.current_page)"
                class="px-2 py-1 text-xs rounded border hover:bg-muted"
                :disabled="isFetchingEvents"
              >
                <span v-if="isFetchingEvents">Loading...</span>
                <span v-else>Refresh</span>
              </button>
            </div>
          </div>
        </div>

        <div
          ref="eventsContainer"
          class="h-96 overflow-y-auto p-4 space-y-3"
        >

          <div v-if="events.length === 0" class="text-center text-muted-foreground py-8">
            No events yet. Connect to EventSub and interact with your Twitch channel!
          </div>

          <div v-if="isFetchingEvents && events.length === 0" class="text-center text-muted-foreground py-8">
            Loading events...
          </div>

          <div
            v-for="event in events"
            :key="event.id"
            class="border rounded-lg p-3 bg-card"
            :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': event.realtime }"
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

                  <!-- Real-time indicator -->
                  <span
                    v-if="event.realtime"
                    class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  >
                    Real-time
                  </span>

                  <!-- Database indicator -->
                  <span
                    v-if="event.fromDatabase"
                    class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                  >
                    Database
                  </span>

                  <!-- Processing status -->
                  <span
                    v-if="event.fromDatabase"
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="event.processed
                      ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                      : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'"
                  >
                    {{ event.processed ? 'Processed' : 'Unprocessed' }}
                  </span>

                  <span class="text-xs text-muted-foreground">
                    {{ event.receivedAt }}
                  </span>
                </div>

                <!-- Event Details -->
                <div class="space-y-1">
                  <div v-if="event.type === 'channel.follow'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> started following!
                    <span v-if="event.data.followed_at" class="text-muted-foreground text-xs block">
                      {{ new Date(event.data.followed_at).toLocaleString() }}
                    </span>
                  </div>

                  <div v-else-if="event.type === 'channel.subscribe'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> subscribed!
                    <span v-if="event.data.tier" class="text-muted-foreground">
                      (Tier {{ Math.floor(event.data.tier / 1000) }})
                    </span>
                  </div>

                  <div v-else-if="event.type === 'stream.online'" class="text-sm">
                    🔴 <strong>Stream went live!</strong>
                    <span v-if="event.data.type" class="text-muted-foreground">
                      ({{ event.data.type }})
                    </span>
                  </div>

                  <div v-else-if="event.type === 'channel.raid'" class="text-sm">
                    <strong>{{ event.data.from_broadcaster_user_name }}</strong> raided with {{ event.data.viewers }} viewers!
                  </div>

                  <div v-else-if="event.type === 'channel.cheer'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> cheered {{ event.data.bits }} bits: {{ event.data.message }}
                  </div>

                  <div v-else-if="event.type === 'extension.bits_transaction.create'" class="text-sm">
                    <strong>{{ event.data.user_name }}</strong> just cheered {{ event.data.product.bits }} bits using {{ event.data.product.name }}!
                  </div>

                  <div v-else-if="event.type === 'system'" class="text-sm">
                    {{ event.data.message }}
                  </div>

                  <div v-else-if="event.type === 'error'" class="text-sm text-red-600">
                    Error: {{ event.data.message }}
                  </div>

                  <div v-else class="text-sm">
                    {{ event.type }}: {{ JSON.stringify(event.data).substring(0, 100) }}...
                  </div>
                </div>

                <!-- Raw Data (Collapsible) -->
                <details v-if="event.data" class="mt-2">
                  <summary class="text-xs text-muted-foreground cursor-pointer hover:text-foreground">
                    Show raw data
                  </summary>
                  <pre class="text-xs bg-muted rounded p-2 mt-1 overflow-x-auto">{{ JSON.stringify(event.data, null, 2) }}</pre>
                </details>

                <!-- Process Button (only for unprocessed database events) -->
                <div v-if="event.fromDatabase && !event.processed" class="mt-3 flex justify-end">
                  <button
                    @click="markAsProcessed(event.id)"
                    class="px-3 py-1 text-xs font-medium rounded bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800"
                  >
                    Mark as Processed
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
