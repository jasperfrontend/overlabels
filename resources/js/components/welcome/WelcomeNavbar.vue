<script setup lang="ts">
import { ArrowRight, Menu, X } from 'lucide-vue-next';
import LoginSocial from '@/components/LoginSocial.vue';
import DarkModeToggle from '@/components/DarkModeToggle.vue';
import { Link } from '@inertiajs/vue3';
import TwitchIcon from '@/components/TwitchIcon.vue';
import { Badge } from '@/components/ui/badge';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const mobileMenuOpen = ref(false);

</script>

<template>
  <nav class="sticky top-0 z-50 border-b border-sidebar-accent bg-sidebar-accent/80 backdrop-blur-lg">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <Link href="/" class="flex items-center gap-2.5">
          <img src="/favicon-light.svg" alt="" class="h-8 w-8 dark:hidden" /><img src="/favicon.png" alt=""
                                                                                  class="hidden h-8 w-8 dark:block" />
          <span class="text-lg font-bold tracking-tight">Overlabels</span>
          <Badge variant="outline" class="text-xs">Beta</Badge>
        </Link>
        <div class="hidden text-foreground items-center gap-6 lg:flex">
          <a href="#tags" class="text-sm hover:text-sky-500">Tags</a>
          <a href="#playground" class="text-sm hover:text-sky-500">Playground</a>
          <a href="#controls"
             class="text-sm hover:text-sky-500">Controls</a>
          <a href="#conditionals" class="text-sm hover:text-sky-500">Conditionals</a>
          <a href="#events" class="text-sm hover:text-sky-500">Events</a>
          <a href="#integrations" class="text-sm hover:text-sky-500">Integrations</a>
          <a href="#kits" class="text-sm hover:text-sky-500">Kits</a>
          <Link href="/help" class="text-sm hover:text-sky-500">Help</Link>
          <Link href="/help/manifesto" class="text-sm hover:text-sky-500">
            Why Overlabels
          </Link>
          <DarkModeToggle />
          <Link v-if="page.props.auth.user" :href="route('dashboard.index')" class="btn btn-primary text-sm">
            Dashboard
            <ArrowRight class="ml-1.5 h-4 w-4" />
          </Link>
          <div v-else class="flex items-center gap-2">
            <Link href="/login" class="btn btn-primary text-sm gap-2">
              <TwitchIcon size="size-4" />
              Connect
            </Link>
          </div>
        </div>
        <div class="flex items-center gap-3 lg:hidden">
          <DarkModeToggle />
          <button @click="mobileMenuOpen = !mobileMenuOpen"
                  class="flex h-9 w-9 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:text-foreground">
            <X v-if="mobileMenuOpen" class="h-5 w-5" />
            <Menu v-else class="h-5 w-5" />
          </button>
        </div>
      </div>
      <div class="container mx-auto px-4 pb-3 sm:px-6 lg:hidden">
        <Link v-if="$page.props.auth.user" :href="route('dashboard.index')"
              class="btn btn-primary text-sm flex w-full justify-center">
          Dashboard
          <ArrowRight class="ml-1.5 h-4 w-4" />
        </Link>
        <LoginSocial v-else class="flex! w-full justify-center" />
      </div>
    </div>
    <!-- Mobile menu -->
    <div v-if="mobileMenuOpen" class="border-t border-sidebar-accent bg-sidebar-accent/95 backdrop-blur-lg lg:hidden">
      <div class="container mx-auto space-y-1 px-4 py-4 sm:px-6">
        <a
          v-for="item in [
              { href: '#tags', label: 'Tags' },
              { href: '#playground', label: 'Playground' },
              { href: '#controls', label: 'Controls' },
              { href: '#conditionals', label: 'Conditionals' },
              { href: '#events', label: 'Events' },
              { href: '#integrations', label: 'Integrations' },
              { href: '#kits', label: 'Kits' },
            ]"
          :key="item.href"
          :href="item.href"
          class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          @click="mobileMenuOpen = false"
        >{{ item.label }}</a>
        <div class="my-2 border-t border-sidebar-accent"></div>
        <Link href="/help"
              class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              @click="mobileMenuOpen = false">Help
        </Link>
        <Link href="/help/manifesto"
              class="block rounded-sm px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              @click="mobileMenuOpen = false">Why Overlabels
        </Link>
      </div>
    </div>
  </nav>
</template>

<style scoped>

</style>
