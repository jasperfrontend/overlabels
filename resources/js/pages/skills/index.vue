<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Check, CircleDashed, TriangleAlert } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

interface Skill {
  key: string;
  label: string;
  summary: string;
  missing: string;
  route: string;
  cta: string;
  satisfied: boolean;
}

interface Skillset {
  key: string;
  label: string;
  outcome: string;
  skills: Skill[];
  satisfied: number;
  total: number;
  missing: number;
  status: 'loose_end' | 'not_started' | 'complete';
}

const props = defineProps<{
  skillsets: Skillset[];
  looseEnds: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Skills', href: '/skills' }];

// The server already sorted these; the page must not re-sort and quietly
// disagree with the ranking that is the whole point of the feature.
const looseEnds = computed(() => props.skillsets.filter((s) => s.status === 'loose_end'));

function href(skill: Skill): string {
  return route(skill.route);
}
</script>

<template>
  <Head>
    <title>Skills</title>
    <meta name="description" content="What is wired up on your account, and what is one step short of working." />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <PageHeader
        title="Skills"
        description="What is wired up on your account, and what is one step short of working."
        title-class="text-2xl font-bold"
      />

      <!-- The headline is the loose ends, not a total. A count of everything
           you could possibly set up is a score; a count of things that are
           configured but silent is a to-do list. -->
      <div v-if="looseEnds.length" class="flex gap-3 rounded border border-amber-500/40 bg-amber-500/10 p-4" role="alert">
        <TriangleAlert class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <div>
          <p class="font-medium text-amber-700 dark:text-amber-300">
            {{ looseEnds.length === 1 ? 'One thing is set up but not working' : `${looseEnds.length} things are set up but not working` }}
          </p>
          <p class="mt-1 text-sm text-foreground">You have started these and stopped a step short, so they look configured but do nothing yet.</p>
        </div>
      </div>

      <div v-else class="flex gap-3 rounded border border-border p-4">
        <Check class="mt-0.5 size-5 shrink-0 text-green-600 dark:text-green-400" />
        <p class="text-foreground">Nothing is half-finished. Everything you have set up is actually working.</p>
      </div>

      <section v-for="set in props.skillsets" :key="set.key" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
          <h2 class="text-lg font-semibold text-foreground">{{ set.label }}</h2>
          <span
            class="text-sm tabular-nums"
            :class="{
              'text-amber-600 dark:text-amber-400': set.status === 'loose_end',
              'text-green-600 dark:text-green-400': set.status === 'complete',
              'text-muted-foreground': set.status === 'not_started',
            }"
          >
            <template v-if="set.status === 'complete'">Working</template>
            <template v-else-if="set.status === 'not_started'">Not set up</template>
            <template v-else>{{ set.missing }} step{{ set.missing === 1 ? '' : 's' }} to go</template>
          </span>
        </div>

        <p class="max-w-prose text-sm text-foreground">{{ set.outcome }}</p>

        <ul class="flex flex-col gap-2">
          <li
            v-for="skill in set.skills"
            :key="skill.key"
            class="collection-row flex flex-col gap-2 rounded border border-border p-3 sm:flex-row sm:items-center sm:gap-3"
          >
            <Check v-if="skill.satisfied" class="size-4 shrink-0 text-green-600 dark:text-green-400" />
            <CircleDashed v-else class="size-4 shrink-0 text-muted-foreground" />

            <div class="min-w-0 flex-1">
              <p class="font-medium text-foreground" :class="{ 'text-muted-foreground line-through': skill.satisfied }">
                {{ skill.label }}
              </p>
              <p class="text-sm text-foreground">
                {{ skill.satisfied ? skill.summary : skill.missing }}
              </p>
            </div>

            <Link v-if="!skill.satisfied" :href="href(skill)" class="btn btn-sm btn-secondary shrink-0 cursor-pointer">
              {{ skill.cta }}
            </Link>
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>
