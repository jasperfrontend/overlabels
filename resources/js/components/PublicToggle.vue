<script setup lang="ts">
import { CheckCheck, Square } from 'lucide-vue-next';

const model = defineModel<boolean>({ required: true });

const props = defineProps<{
  label: string;
}>();

function toggle() {
  model.value = !model.value;
}
</script>

<template>
  <div
    class="flex items-center justify-between border p-4 cursor-pointer hover:bg-background"
    :class="model ? 'border-green-500 bg-green-300/5' : 'border-border bg-background-50/20'"
    @click="toggle"
  >
    <div class="space-y-0.5">
      <div v-if="model">Public {{ props.label }} <small>(Click to make private)</small></div>
      <div v-else>Private {{ props.label }} <small>(Click to make public)</small></div>
      <p v-if="model" class="text-sm text-green-500">
        Public {{ props.label.toLowerCase() }}s can be discovered and copied by other users.
      </p>
      <p v-else class="text-sm text-muted-foreground">
        Private {{ props.label.toLowerCase() }}s are only visible to you and cannot be copied by other users.
      </p>
    </div>

    <CheckCheck v-if="model" class="h-5 w-5 text-green-500" />
    <Square v-else class="h-5 w-5 text-muted-foreground" />

    <input type="checkbox" class="hidden" name="is_public" id="is_public" v-model="model" />
  </div>
</template>
