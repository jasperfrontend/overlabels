<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Package } from '@lucide/vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

// Opened by the dashboard on a new account's first visit (see
// DashboardController::claimWelcomeDialog). The server decides when; this only
// owns the open state, so a later re-render cannot pop it open again.
const props = defineProps<{ show: boolean }>();

const open = ref(props.show);
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <div class="mb-2 grid h-11 w-11 place-items-center rounded-[10px] bg-green-500/20 text-green-700 dark:text-green-400">
          <Package class="h-5 w-5" />
        </div>
        <DialogTitle class="text-2xl">Welcome to Overlabels</DialogTitle>
        <DialogDescription class="text-base">Free Twitch Chat Games, Chat Overlay and Donation Alerts</DialogDescription>
      </DialogHeader>

      <div class="space-y-3 text-sm leading-relaxed text-foreground">
        <p>Want to get started using Overlabels? You'll need 1 or more products installed on your account.</p>
        <p>
          Easily install <strong>Chat Checkin</strong>, <strong>Chat Emote Bubbles</strong>, <strong>Chat Tower</strong> or
          <strong>Follower Bowling</strong> on your stream in just a few clicks. You'll be guided during the installation, it's easy - and free.
        </p>
      </div>

      <DialogFooter class="mt-2 gap-2">
        <button type="button" class="btn btn-sm btn-secondary" @click="open = false">Maybe later</button>
        <Link :href="route('products.index', { from: 'welcome-dialog' })" class="btn btn-sm btn-primary">
          Go check out the products
          <ArrowRight class="ml-1.5 h-4 w-4" />
        </Link>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
