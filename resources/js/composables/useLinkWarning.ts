import { ref } from 'vue'

const show = ref(false)
const warningText = ref('')
const action = ref<() => void>(() => {})

export function useLinkWarning() {
  function triggerLinkWarning(doThis: () => void, warning: string) {
    warningText.value = warning
    action.value = doThis
    show.value = true
  }

  function confirm() {
    action.value()
    reset()
  }

  function reset() {
    show.value = false
    warningText.value = ''
    action.value = () => {}
  }

  return {
    show,
    warningText,
    triggerLinkWarning,
    confirm,
    reset,
  }
}
