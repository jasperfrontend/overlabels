<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Check, Circle, TriangleAlert } from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

type SkillState = 'satisfied' | 'missing' | 'not_applicable';

interface Skill {
  key: string;
  state: SkillState;
  label: string;
  message: string;
  route: string;
  cta: string;
}

interface Subject {
  key: string;
  label: string;
  context: string[];
  skills: Skill[];
  missing: number;
  applicable: boolean;
  needsAttention: boolean;
}

interface Skillset {
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
  skillsets: Skillset[];
  looseEnds: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Skills', href: '/skills' }];

function href(skill: Skill): string {
  return route(skill.route);
}

// not_applicable is rendered as plain context, never as a half-tick. It is not
// progress and it is not a gap, so giving it a state icon would read as a soft
// failure for something the streamer never chose to build.
function isFinding(skill: Skill): boolean {
  return skill.state === 'missing';
}

// Shown as muted prose, never with an icon or a button: it explains why the
// question does not arise, which is information, not a task.
function isDormant(skill: Skill): boolean {
  return skill.state === 'not_applicable' && skill.message !== '';
}
</script>

<template>
  <Head>
    <title>Skills</title>
    <meta name="description" content="What is wired up on your account, and what is built but cannot work." />
  </Head>

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex flex-col gap-6 p-4">
      <PageHeader
        title="Skills"
        description="What is wired up on your account, and what is built but cannot work."
        title-class="text-2xl font-bold"
      />

      <!-- The headline counts subjects, not areas. This page only ever speaks
           about things that exist, so it can never nag about something the
           streamer chose not to build. -->
      <div v-if="props.looseEnds" class="flex gap-3 rounded border border-amber-500/40 bg-amber-500/10 p-4" role="alert">
        <TriangleAlert class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        <div>
          <p class="font-medium text-amber-700 dark:text-amber-300">
            {{ props.looseEnds === 1 ? 'One thing is built but cannot work' : `${props.looseEnds} things are built but cannot work` }}
          </p>
          <p class="mt-1 text-sm text-foreground">These exist on your account and something is stopping them doing anything.</p>
        </div>
      </div>

      <div v-else class="flex gap-3 rounded border border-border p-4">
        <Check class="mt-0.5 size-5 shrink-0 text-green-600 dark:text-green-400" />
        <p class="text-foreground">Everything you have built can actually run.</p>
      </div>

      <section v-for="set in props.skillsets" :key="set.key" class="flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
          <h2 class="text-lg font-semibold text-foreground">{{ set.label }}</h2>
          <span v-if="set.status === 'loose_end'" class="text-sm text-amber-600 tabular-nums dark:text-amber-400">
            {{ set.attention }} of {{ set.total }} need attention
          </span>
        </div>

        <p class="max-w-prose text-sm text-foreground">{{ set.outcome }}</p>

        <EmptyState v-if="!set.subjects.length" :message="`No ${set.subject}s yet.`" />

        <ul v-else class="flex flex-col gap-2">
          <li
            v-for="subject in set.subjects"
            :key="subject.key"
            class="collection-row rounded border p-3"
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

            <p v-for="skill in subject.skills.filter(isDormant)" :key="skill.key" class="mt-1 pl-6 text-sm text-muted-foreground">
              {{ skill.message }}
            </p>

            <div
              v-for="skill in subject.skills.filter(isFinding)"
              :key="skill.key"
              class="mt-2 flex flex-col gap-2 pl-6 sm:flex-row sm:items-center sm:gap-3"
            >
              <p class="min-w-0 flex-1 text-sm text-foreground">{{ skill.message }}</p>
              <Link :href="href(skill)" class="btn btn-sm btn-secondary shrink-0 cursor-pointer">
                {{ skill.cta }}
              </Link>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </AppLayout>
</template>
