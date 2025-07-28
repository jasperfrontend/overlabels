<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  twitchWidgetData: {
    type: Object,
    required: true,
  }
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Create Widgets',
    href: '/widgets',
  },
];

</script>

<template>
  <Head title="Create Twitch Widgets" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col items-center gap-8 px-4 py-10">
      <div class="w-full">
        <h1 class="text-4xl font-extrabold tracking-tight text-center mb-6">
          Create Twitch Widgets
        </h1>
        <div class="flex relative">
          <div class="w-1/3 flex-initial sticky top-8">
            <div class="bg-accent shadow-2xl rounded-2xl p-4 m-2">
              <h2 class="text-2xl font-bold mb-4">Your Template Tags</h2>
              <h3 class="text-xl font-bold mt-8 mb-4">Grouped Template Tags</h3>

              <div class="widget-loop widget-followed_channels mb-5">
                <div class="title flex justify-between rounded-md bg-slate-200 dark:bg-slate-700 py-0.5 px-2.5 
                            border border-transparent text-sm dark:text-white transition-all shadow-sm"
                >
                  <span>[[[channel_followers]]]</span> <span class="group-data rounded-full text-xs bg-amber-500 py-0.5 px-1.5">group. no output</span>
                </div>
                <div class="title flex justify-between rounded-md bg-slate-200 dark:bg-slate-700 py-0.5 px-2.5 
                            border border-transparent text-sm dark:text-white transition-all shadow-sm"
                >
                  <span>[[[channel_followers=10]]]</span> <span class="group-data rounded-full text-xs bg-amber-500 py-0.5 px-1.5">10 latest channel_followers</span>
                </div>
                <div class="body flex flex-col gap-1 mt-1 ml-3">
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel_followers.total]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">1534</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel_followers.latest]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">JasperSpins</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel_followers.latest.followed_at="d-m-y H:i"]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">27-07-2025 13:32</span>
                  </div>
                </div>
              </div>
              

              <div class="widget-loop widget-followed_channels mb-5">
                <div class="title flex justify-between rounded-md bg-slate-200 dark:bg-slate-700 py-0.5 px-2.5 
                            border border-transparent text-sm dark:text-white transition-all shadow-sm"
                >
                  <span>[[[subscribers]]]</span> <span class="group-data rounded-full text-xs bg-amber-500 py-0.5 px-1.5">group. no output</span>
                </div>
                <div class="title flex justify-between rounded-md bg-slate-200 dark:bg-slate-700 py-0.5 px-2.5 
                            border border-transparent text-sm dark:text-white transition-all shadow-sm"
                >
                  <span>[[[subscribers=10]]]</span> <span class="group-data rounded-full text-xs bg-amber-500 py-0.5 px-1.5">10 subscribers</span>
                </div>
                <div class="body flex flex-col gap-1 mt-1 ml-3">
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[subscribers.total]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">7</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[subscribers.points]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">12</span>
                  </div>
                </div>
              </div>

              <div class="widget-loop widget-channel mb-5">
                <div class="title flex justify-between rounded-md bg-slate-200 dark:bg-slate-700 py-0.5 px-2.5 
                            border border-transparent text-sm dark:text-white transition-all shadow-sm"
                >
                  <span>[[[channel]]]</span> <span class="group-data rounded-full text-xs bg-amber-500 py-0.5 px-1.5">group. no output</span>
                </div>
                <div class="body flex flex-col gap-1 mt-1 ml-3">
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.id]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">73327367</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.login]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">jasperdiscovers</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.name]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">JasperDiscovers</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.language]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">en</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.game_name]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">Just Chatting</span>
                  </div>
                  <div class="flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                    <span>[[[channel.title]]]</span> 
                    <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">Come hang out! We're live with @xQc</span>
                  </div>
                </div>
              </div>
              <h3 class="text-xl font-bold mt-8 mb-4">Single Template Tags</h3>
              <div class="widget-broadcaster_name flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                <span>[[[broadcaster_name]]]</span> 
                <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">JasperDiscovers</span>
              </div>
              <div class="widget-broadcaster_avatar flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                <span>[[[broadcaster_avatar]]]</span> 
                <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">profile_image-300x300.png</span>
              </div>
              <div class="widget-broadcaster_avatar-[70] flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                <span>[[[broadcaster_avatar=70]]]</span> 
                <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">profile_image-70x70.png</span>
              </div>
              <div class="widget-broadcaster_avatar-[600] flex justify-between rounded-md bg-slate-300 dark:bg-slate-800 py-0.5 px-2.5 border border-transparent text-sm dark:text-white transition-all shadow-sm">
                <span>[[[broadcaster_avatar=600]]]</span> 
                <span class="representing-data rounded-full text-xs bg-green-600 py-0.5 px-1.5">profile_image-600x600.png</span>
              </div>
            </div>
          </div>
          <div class="w-2/3 flex-initial">
            <div class="bg-accent shadow-2xl rounded-2xl p-4 m-2">
              <h2 class="text-2xl font-bold mb-4">Twitch API Data</h2>
              <p>Below a is representation of your Twitch API data. 
                Clicking each object below will render a 
                <kbd class="bg-black text-white p-1 px-2 rounded-full">[[[TAG]]]</kbd> you can use in your templates (eventually, lol).</p>
              <code><pre>{{ props.twitchWidgetData }}</pre></code>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>