import { AppPageProps } from '@/types/index';
import Pusher from 'pusher-js';
import Echo from 'laravel-echo';

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
    let route: typeof route;
    
    interface Window {
        Pusher: typeof Pusher;
        Echo: typeof Echo;
        cloudinary: any;
        cloudinaryCloudName: string;
    }
}