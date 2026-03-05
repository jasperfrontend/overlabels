<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const lockdown = computed(() => page.props.lockdown);
const isActive = computed(() => lockdown.value?.active === true);
const isAdmin = computed(() => page.props.isAdmin);
</script>

<template>
  <div
    v-if="isActive"
    class="flex items-center justify-between bg-red-600 px-4 py-2 text-sm font-medium text-white"
  >
    <span>
      <strong>System lockdown active.</strong>
      All overlays are offline and access tokens have been suspended.
      <span v-if="lockdown?.reason" class="ml-1 opacity-80">Reason: {{ lockdown.reason }}</span>
    </span>
    <Link
      v-if="isAdmin"
      :href="route('admin.lockdown.index')"
      class="ml-4 rounded border border-red-300 bg-red-700 px-3 py-1 text-xs font-semibold hover:bg-red-800"
    >
      Manage lockdown
    </Link>
  </div>
</template>
