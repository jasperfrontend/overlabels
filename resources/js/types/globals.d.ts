import { AppPageProps } from '@/types/index';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
  interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    [key: string]: string | boolean | undefined;
  }

  interface ImportMeta {
    readonly env: ImportMetaEnv;
    readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
  }
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $inertia: typeof Router;
    $page: Page;
    $headManager: ReturnType<typeof createHeadManager>;
  }
}

declare global {
  // Compile-time constant injected by Vite's `define` (see vite.config.mts).
  const __COMMIT_HASH__: string;

  let route: typeof route;

  interface Window {
    Pusher: typeof Pusher;
    Echo: InstanceType<typeof Echo>;
  }
}
