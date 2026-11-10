<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/EmptyState.vue';
import FeaturedKitCard, { type FeaturedKit } from '@/components/kits/FeaturedKitCard.vue';
import type { BreadcrumbItem } from '@/types';

// Where a new account lands after its first login (see the Twitch callback in
// routes/web.php): the official ready-made kits, one click from playing.
defineProps<{ kits: FeaturedKit[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pick your alerts', href: '/start' }];
</script>

<template>
  <Head title="Pick your alerts" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4">
      <h1 class="text-2xl font-semibold text-foreground">Pick your alerts</h1>
      <p class="mt-2 max-w-prose text-sm text-foreground">
        Start with a ready-made set. Each one plays on its own page before you copy it. Copy one, assign its alerts to your events and add one overlay
        to OBS, all from that page.
      </p>

      <ul v-if="kits.length" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <FeaturedKitCard v-for="kit in kits" :key="kit.id" :kit="kit" />
      </ul>
      <EmptyState v-else class="mt-6" message="No ready-made kits yet." />

      <div class="mt-8 flex">
        <Link href="/dashboard" class="btn btn-sm btn-cancel cursor-pointer">Skip for now</Link>
      </div>
    </div>
  </AppLayout>
</template>
