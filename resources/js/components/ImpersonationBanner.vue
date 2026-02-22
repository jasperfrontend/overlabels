<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const impersonating = computed(() => page.props.impersonating);
const auth = computed(() => page.props.auth);

function stopImpersonating() {
  router.post(route('admin.impersonate.stop'));
}
</script>

<template>
  <div
    v-if="impersonating"
    class="flex items-center justify-between bg-yellow-400 px-4 py-2 text-sm font-medium text-yellow-900"
  >
    <span>
      You are impersonating <strong>{{ auth.user?.name }}</strong>.
      Actions you take affect their account.
    </span>
    <button
      @click="stopImpersonating"
      class="ml-4 rounded border border-yellow-700 bg-yellow-300 px-3 py-1 text-xs font-semibold hover:bg-yellow-200"
    >
      Stop impersonating
    </button>
  </div>
</template>
