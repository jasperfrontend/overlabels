import { ref, readonly, computed } from 'vue'

export type HealthStatus =
  | 'loading'        // Initial load in progress
  | 'retrying'       // Initial load failed, retrying
  | 'connected'      // Everything is working
  | 'reconnecting'   // Pusher disconnected, trying to recover
  | 'auth_error'     // Token invalid/expired (401) or Twitch connection lost (400)
  | 'server_error'   // Server error after all retries exhausted
  | 'offline'        // Network unreachable after all retries

const RETRY_DELAYS = [2000, 4000, 8000, 16000, 30000] // 5 retries with exponential backoff
const HEALTH_CHECK_INTERVAL = 5 * 60 * 1000            // 5 minutes
const PUSHER_DEAD_THRESHOLD = 2 * 60 * 1000            // 2 minutes before auto-reload
const AUTO_RELOAD_DELAY = 10_000                        // 10 seconds before auto-reload

const status = ref<HealthStatus>('loading')
const statusMessage = ref('')
const retryCountdown = ref(0)
const willAutoReload = ref(false)
const autoReloadIn = ref(0)

let healthCheckTimer: ReturnType<typeof setInterval> | null = null
let pusherDisconnectedAt: number | null = null
let pusherWatchTimer: ReturnType<typeof setInterval> | null = null
let autoReloadTimer: ReturnType<typeof setTimeout> | null = null
let countdownTimer: ReturnType<typeof setInterval> | null = null

const hasError = computed(() =>
  ['auth_error', 'server_error', 'offline', 'reconnecting'].includes(status.value)
)

const isRetrying = computed(() => status.value === 'retrying')

/**
 * Fetch overlay data with exponential backoff retry.
 * Returns the JSON response on success, or null if all retries fail.
 */
async function fetchWithRetry(
  slug: string,
  token: string,
): Promise<{ ok: true; data: any } | { ok: false; status: number; message: string }> {
  const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

  for (let attempt = 0; attempt <= RETRY_DELAYS.length; attempt++) {
    // Wait before retrying (skip delay on first attempt)
    if (attempt > 0) {
      const delay = RETRY_DELAYS[attempt - 1]
      status.value = 'retrying'
      statusMessage.value = `Having trouble connecting to Overlabels. Retrying automatically (${attempt}/${RETRY_DELAYS.length})...`

      // Countdown display
      retryCountdown.value = Math.ceil(delay / 1000)
      await new Promise<void>((resolve) => {
        const interval = setInterval(() => {
          retryCountdown.value--
          if (retryCountdown.value <= 0) {
            clearInterval(interval)
            resolve()
          }
        }, 1000)
      })
    }

    try {
      const response = await fetch('/api/overlay/render', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({ slug, token }),
      })

      if (response.ok) {
        const data = await response.json()
        status.value = 'connected'
        statusMessage.value = ''
        return { ok: true, data }
      }

      // Non-retryable errors: auth failures
      if (response.status === 401 || response.status === 403) {
        status.value = 'auth_error'
        statusMessage.value = 'Your overlay session has expired. Log in again at overlabels.com, grab a fresh overlay link, and right-click this source in OBS > Refresh.'
        scheduleAutoReload()
        return { ok: false, status: response.status, message: statusMessage.value }
      }

      // Twitch connection lost on server side
      if (response.status === 400) {
        status.value = 'auth_error'
        statusMessage.value = 'Your Twitch connection was lost. Log in again at overlabels.com with your Twitch account, then right-click this source in OBS > Refresh.'
        scheduleAutoReload()
        return { ok: false, status: response.status, message: statusMessage.value }
      }

      // Server errors (500, 502, 503) — retryable, continue loop
    } catch {
      // Network error — retryable, continue loop
    }
  }

  // All retries exhausted
  status.value = 'offline'
  statusMessage.value = 'Can\'t reach the Overlabels server right now. This will auto-reload shortly — if it keeps happening, check your internet or try again later.'
  scheduleAutoReload()
  return { ok: false, status: 0, message: statusMessage.value }
}

/**
 * Periodic health check — re-validates the token + Twitch connection.
 */
function startHealthChecks(slug: string, token: string) {
  if (healthCheckTimer) clearInterval(healthCheckTimer)

  healthCheckTimer = setInterval(async () => {
    // Only run health checks when we think we're connected
    if (status.value !== 'connected') return

    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

    try {
      const response = await fetch('/api/overlay/render', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'include',
        body: JSON.stringify({ slug, token }),
      })

      if (response.ok) {
        // Still healthy — nothing to do
        return
      }

      if (response.status === 401 || response.status === 403) {
        status.value = 'auth_error'
        statusMessage.value = 'Your overlay session has expired. Log in again at overlabels.com, grab a fresh overlay link, and right-click this source in OBS > Refresh.'
        scheduleAutoReload()
      } else if (response.status === 400) {
        status.value = 'auth_error'
        statusMessage.value = 'Your Twitch connection was lost. Log in again at overlabels.com with your Twitch account, then right-click this source in OBS > Refresh.'
        scheduleAutoReload()
      }
      // Transient server errors during health check — don't escalate immediately,
      // next health check will retry
    } catch {
      // Network error during health check — don't escalate immediately
    }
  }, HEALTH_CHECK_INTERVAL)
}

/**
 * Monitor Pusher connection state.
 * If disconnected for longer than PUSHER_DEAD_THRESHOLD, auto-reload.
 */
function startPusherMonitoring() {
  // Monitor connection from the Echo instance created in app.js
  const echo = (window as any).Echo
  if (!echo?.connector?.pusher?.connection) return

  const connection = echo.connector.pusher.connection

  connection.bind('connected', () => {
    pusherDisconnectedAt = null
    // Only restore to connected if we were in a reconnecting state
    if (status.value === 'reconnecting') {
      status.value = 'connected'
      statusMessage.value = ''
      cancelAutoReload()
    }
  })

  connection.bind('disconnected', () => {
    if (!pusherDisconnectedAt) {
      pusherDisconnectedAt = Date.now()
    }
  })

  connection.bind('unavailable', () => {
    if (!pusherDisconnectedAt) {
      pusherDisconnectedAt = Date.now()
    }
    if (status.value === 'connected') {
      status.value = 'reconnecting'
      statusMessage.value = 'Live connection lost — reconnecting automatically. Your overlay may not update until this is resolved.'
    }
  })

  connection.bind('failed', () => {
    if (status.value === 'connected' || status.value === 'reconnecting') {
      status.value = 'reconnecting'
      statusMessage.value = 'Live connection failed — reconnecting automatically. Your overlay may not update until this is resolved.'
      if (!pusherDisconnectedAt) {
        pusherDisconnectedAt = Date.now()
      }
    }
  })

  connection.bind('error', () => {
    if (!pusherDisconnectedAt) {
      pusherDisconnectedAt = Date.now()
    }
  })

  // Periodically check how long Pusher has been disconnected
  if (pusherWatchTimer) clearInterval(pusherWatchTimer)
  pusherWatchTimer = setInterval(() => {
    if (pusherDisconnectedAt && status.value !== 'auth_error') {
      const elapsed = Date.now() - pusherDisconnectedAt

      if (elapsed > PUSHER_DEAD_THRESHOLD) {
        status.value = 'reconnecting'
        statusMessage.value = 'Live connection has been down for too long. Auto-reloading to try to fix this...'
        scheduleAutoReload()
      } else if (status.value === 'connected') {
        status.value = 'reconnecting'
        statusMessage.value = 'Live connection lost — reconnecting automatically. Your overlay may not update until this is resolved.'
      }
    }
  }, 10_000) // Check every 10 seconds
}

/**
 * Schedule an auto-reload of the page.
 */
function scheduleAutoReload() {
  if (autoReloadTimer) return // Already scheduled

  willAutoReload.value = true
  autoReloadIn.value = Math.ceil(AUTO_RELOAD_DELAY / 1000)

  countdownTimer = setInterval(() => {
    autoReloadIn.value--
    if (autoReloadIn.value <= 0 && countdownTimer) {
      clearInterval(countdownTimer)
    }
  }, 1000)

  autoReloadTimer = setTimeout(() => {
    window.location.reload()
  }, AUTO_RELOAD_DELAY)
}

/**
 * Cancel a pending auto-reload (e.g. when Pusher reconnects).
 */
function cancelAutoReload() {
  if (autoReloadTimer) {
    clearTimeout(autoReloadTimer)
    autoReloadTimer = null
  }
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
  willAutoReload.value = false
  autoReloadIn.value = 0
}

/**
 * Clean up all timers.
 */
function destroy() {
  if (healthCheckTimer) clearInterval(healthCheckTimer)
  if (pusherWatchTimer) clearInterval(pusherWatchTimer)
  cancelAutoReload()
  healthCheckTimer = null
  pusherWatchTimer = null
}

export function useOverlayHealth() {
  return {
    status: readonly(status),
    statusMessage: readonly(statusMessage),
    retryCountdown: readonly(retryCountdown),
    willAutoReload: readonly(willAutoReload),
    autoReloadIn: readonly(autoReloadIn),
    hasError,
    isRetrying,
    fetchWithRetry,
    startHealthChecks,
    startPusherMonitoring,
    destroy,
  }
}
