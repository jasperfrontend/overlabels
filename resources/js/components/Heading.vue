<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    descriptionClass?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="space-y-0.5">
        <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
        <p v-if="description" :class="descriptionClass">
            {{ description }}
        </p>
    </div>
</template>
