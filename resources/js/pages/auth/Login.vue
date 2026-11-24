<script setup lang="ts">
import LoginSocial from '@/components/LoginSocial.vue';
import ProductIcon from '@/components/product-icons/ProductIcon.vue';
import { hasProductIcon } from '@/components/product-icons/registry';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';

// Set when the visitor came here from a product's install page, so the page
// can say why it wants their Twitch account. The server only sends a listed
// product, by its catalogue name.
defineProps<{
  installing?: { slug: string; name: string } | null;
}>();
</script>

<template>
  <AuthBase title="Connect with Twitch" description="Overlabels has been created for the Twitch community">
    <Head title="Connect with Twitch" />
    <div
      v-if="installing"
      class="mt-6 flex max-w-sm items-start gap-4 border border-green-600/40 bg-green-500/10 p-4 text-left dark:border-green-400/40"
    >
      <div
        v-if="hasProductIcon(installing.slug)"
        class="flex size-12 shrink-0 items-center justify-center bg-green-500 text-green-950 dark:bg-green-400"
      >
        <ProductIcon :slug="installing.slug" class="size-7" />
      </div>
      <p class="text-sm leading-relaxed text-foreground">
        To install <strong class="font-semibold">{{ installing.name }}</strong
        >, you'll need to connect your Twitch account with Overlabels. Connecting is safe and free, and you can revoke it anytime in your
        <a
          href="https://www.twitch.tv/settings/connections"
          target="_blank"
          rel="noopener noreferrer"
          class="cursor-pointer text-green-600 hover:underline dark:text-green-400"
          >Twitch settings</a
        >.
      </p>
    </div>
    <LoginSocial :green="!!installing" />
  </AuthBase>
</template>
