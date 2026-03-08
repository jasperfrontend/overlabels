import { ref, onMounted, onUnmounted } from 'vue';

const POLL_INTERVAL_MS = 60_000; // 60 seconds

const hasNewVersion = ref(false);
let currentVersion: string | null = null;
let intervalId: ReturnType<typeof setInterval> | null = null;
let activeInstances = 0;

async function fetchVersion(): Promise<string | null> {
  try {
    const res = await fetch('/api/version', { cache: 'no-store' });
    if (!res.ok) return null;
    const data = await res.json();
    return data.version ?? null;
  } catch {
    return null;
  }
}

async function checkVersion() {
  const version = await fetchVersion();
  if (!version) return;

  if (currentVersion === null) {
    currentVersion = version;
    return;
  }

  if (version !== currentVersion) {
    hasNewVersion.value = true;
  }
}

export function useVersionCheck() {
  onMounted(() => {
    activeInstances++;
    if (activeInstances === 1) {
      checkVersion();
      intervalId = setInterval(checkVersion, POLL_INTERVAL_MS);
    }
  });

  onUnmounted(() => {
    activeInstances--;
    if (activeInstances === 0 && intervalId !== null) {
      clearInterval(intervalId);
      intervalId = null;
    }
  });

  const refresh = () => window.location.reload();

  return { hasNewVersion, refresh };
}
