<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Bot, Check, Circle, TriangleAlert } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';
import HeadingSmall from '@/components/HeadingSmall.vue';

type WireState = 'satisfied' | 'missing' | 'not_applicable';

interface Wire {
  key: string;
  state: WireState;
  label: string;
  message: string;
  route: string;
  cta: string;
}

interface Subject {
  key: string;
  label: string;
  context: string[];
  wires: Wire[];
  missing: number;
  applicable: boolean;
  needsAttention: boolean;
}

interface Circuit {
  key: string;
  label: string;
  outcome: string;
  subject: string;
  subjects: Subject[];
  attention: number;
  total: number;
  status: 'loose_end' | 'complete' | 'not_started';
}

const props = defineProps<{
  circuits: Circuit[];
  looseEnds: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Wiring', href: '/settings/wiring' },
];

function href(wire: Wire): string {
  return route(wire.route);
}

// not_applicable is rendered as plain context, never as a half-tick. It is not
// progress and it is not a gap, so giving it a state icon would read as a soft
// failure for something the streamer never chose to build.
function isFinding(wire: Wire): boolean {
  return wire.state === 'missing';
}

// Shown as muted prose, never with an icon or a button: it explains why the
// question does not arise, which is information, not a task.
function isDormant(wire: Wire): boolean {
  return wire.state === 'not_applicable' && wire.message !== '';
}
</script>

<template>
  <Head>
    <title>Wiring</title>
    <meta name="description" content="What is wired up on your account, and what is built but cannot work." />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <SettingsLayout>
      <div class="flex flex-col gap-6">
        <HeadingSmall title="Wiring" description="What is wired up on your account, and what is built but cannot work." />

        <!-- The headline counts subjects, not areas. This page only ever speaks
           about things that exist, so it can never nag about something the
           streamer chose not to build. -->
        <div v-if="props.looseEnds" class="flex gap-3 border border-amber-500/40 bg-amber-500/10 p-4" role="alert">
          <TriangleAlert class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
          <div>
            <p class="font-medium text-amber-700 dark:text-amber-300">
              {{ props.looseEnds === 1 ? 'One thing is built but cannot work' : `${props.looseEnds} things are built but cannot work` }}
            </p>
            <p class="mt-1 text-sm text-foreground">These exist on your account and something is stopping them doing anything.</p>
          </div>
        </div>

        <div v-else class="flex gap-3 border border-border p-4">
          <Check class="mt-0.5 size-5 shrink-0 text-green-600 dark:text-green-400" />
          <p class="text-foreground">Everything you have built can actually run.</p>
        </div>

        <section v-for="circuit in props.circuits" :key="circuit.key" class="flex flex-col gap-3">
          <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 class="text-lg font-semibold text-foreground">{{ circuit.label }}</h2>
            <span v-if="circuit.status === 'loose_end'" class="text-sm text-amber-600 tabular-nums dark:text-amber-400">
              {{ circuit.attention }} of {{ circuit.total }} need attention
            </span>
          </div>

          <p class="max-w-prose text-sm text-foreground">{{ circuit.outcome }}</p>

          <EmptyState v-if="!circuit.subjects.length" :message="`No ${circuit.subject}s yet.`" />

          <ul v-else class="flex flex-col gap-2">
            <li
              v-for="subject in circuit.subjects"
              :key="subject.key"
              class="collection-row border p-3"
              :class="subject.needsAttention ? 'border-amber-500/40' : 'border-border'"
            >
              <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <TriangleAlert v-if="subject.needsAttention" class="size-4 shrink-0 self-center text-amber-600 dark:text-amber-400" />
                <Check v-else-if="subject.applicable" class="size-4 shrink-0 self-center text-green-600 dark:text-green-400" />
                <!-- Nothing built yet is neither a tick nor a warning. A green
                   mark here would be an award for having done nothing. -->
                <Circle v-else class="size-4 shrink-0 self-center text-muted-foreground" />
                <p class="font-medium text-foreground">{{ subject.label }}</p>
              </div>

              <!-- Context is stated as fact, never as a step you skipped. -->
              <ul v-if="subject.context.length" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 pl-6">
                <li v-for="line in subject.context" :key="line" class="text-sm text-muted-foreground">{{ line }}</li>
              </ul>

              <p v-for="wire in subject.wires.filter(isDormant)" :key="wire.key" class="mt-1 pl-6 text-sm text-muted-foreground">
                {{ wire.message }}
              </p>

              <div
                v-for="wire in subject.wires.filter(isFinding)"
                :key="wire.key"
                class="mt-2 flex flex-col gap-2 pl-6 sm:flex-row sm:items-center sm:gap-3"
              >
                <p class="min-w-0 flex-1 text-sm text-foreground">{{ wire.message }}</p>
                <Link :href="href(wire)" class="btn btn-sm btn-primary shrink-0 cursor-pointer">
                  <Bot class="mr-2 size-4 shrink-0 self-center" />
                  {{ wire.cta }}
                </Link>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
