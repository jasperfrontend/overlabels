<script setup lang="ts">
import { onMounted, onUnmounted, reactive, ref } from 'vue'

onMounted(() => {
  document.documentElement.classList.add('gamejam-fullbleed')
})

onUnmounted(() => {
  document.documentElement.classList.remove('gamejam-fullbleed')
})

interface Block {
  colSpan: number
  rowSpan: number
  colStart: number
  rowStart: number
}

const blocks = ref<Block[]>([])
const form = reactive<Block>({
  colSpan: 1,
  rowSpan: 1,
  colStart: 1,
  rowStart: 1,
})

function spawn() {
  blocks.value.push({ ...form })
}

function clear() {
  blocks.value = []
  form.colSpan = 1
  form.rowSpan = 1
  form.colStart = 1
  form.rowStart = 1
}
</script>

<template>
  <div class="grid grid-cols-23 grid-rows-23 gap-2 p-2 w-full h-full grid-visualizer">
    <div
      v-for="(block, i) in blocks"
      :key="i"
      class="block"
      :style="{
        gridColumn: `${block.colStart} / span ${block.colSpan}`,
        gridRow: `${block.rowStart} / span ${block.rowSpan}`,
      }"
    ></div>

    <div class="control-panel col-start-10 row-start-10 col-span-3 row-span-5">
      <form @submit.prevent="spawn">
        <label>
          col-span
          <input v-model.number="form.colSpan" type="number" min="1" max="11" />
        </label>
        <label>
          row-span
          <input v-model.number="form.rowSpan" type="number" min="1" max="11" />
        </label>
        <label>
          col-start
          <input v-model.number="form.colStart" type="number" min="1" max="11" />
        </label>
        <label>
          row-start
          <input v-model.number="form.rowStart" type="number" min="1" max="11" />
        </label>
        <div class="actions">
          <button type="submit">Spawn</button>
          <button type="button" @click="clear">Clear</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style>
html.gamejam-fullbleed,
html.gamejam-fullbleed body,
html.gamejam-fullbleed #app {
  height: 100%;
  width: 100%;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
.block {
  background: #2a9d90;
}
.control-panel {
  background: #1a1a1a;
  color: #eee;
  padding: 0.5rem;
  border-radius: 4px;
  font-family: system-ui, sans-serif;
  font-size: 0.8rem;
}
.control-panel form {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.control-panel label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}
.control-panel input {
  width: 4rem;
  padding: 0.15rem 0.3rem;
  background: #2a2a2a;
  color: #eee;
  border: 1px solid #444;
  border-radius: 2px;
}
.control-panel .actions {
  display: flex;
  gap: 0.35rem;
  margin-top: 0.25rem;
}
.control-panel button {
  flex: 1;
  padding: 0.3rem;
  background: #2a9d90;
  color: #fff;
  border: none;
  border-radius: 2px;
  cursor: pointer;
}
.control-panel button[type="button"] {
  background: #444;
}
</style>
