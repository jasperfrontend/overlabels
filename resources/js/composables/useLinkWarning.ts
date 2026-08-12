import { useConfirm } from '@/composables/useConfirm';

/**
 * Callback-flavoured wrapper around useConfirm(), kept for the link-warning
 * call sites that read naturally as "do this, after warning about that".
 * There is one dialog implementation underneath - see ConfirmDialog.vue.
 */
export function useLinkWarning() {
  const { confirm } = useConfirm();

  async function triggerLinkWarning(doThis: () => void, warning: string) {
    if (await confirm(warning)) {
      doThis();
    }
  }

  return { triggerLinkWarning };
}
