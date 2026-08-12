import { ref } from 'vue';

export type ConfirmTone = 'danger' | 'neutral';

export interface ConfirmOptions {
  message: string;
  title?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  /**
   * Defaults to 'danger'. Most confirms in the app guard a delete, a revoke or
   * a disconnect, so a forgotten tone should over-warn rather than under-warn.
   * Pass 'neutral' for benign actions like copying a template.
   */
  tone?: ConfirmTone;
}

export type AlertOptions = Omit<ConfirmOptions, 'cancelLabel'>;

interface ResolvedOptions extends Required<ConfirmOptions> {
  /** 'alert' hides the cancel button - there is nothing to decline. */
  variant: 'confirm' | 'alert';
}

const DEFAULTS: ResolvedOptions = {
  message: '',
  title: '',
  confirmLabel: 'Continue',
  cancelLabel: 'Cancel',
  tone: 'danger',
  variant: 'confirm',
};

const show = ref(false);
const options = ref<ResolvedOptions>({ ...DEFAULTS });

// Held between opening the dialog and the user answering. A pending confirm
// that never settles leaves the caller's `await` hanging forever, so every
// exit path - button, Escape, backdrop, or a second dialog opening on top -
// goes through settle().
let resolver: ((answer: boolean) => void) | null = null;

function settle(answer: boolean) {
  const resolve = resolver;
  resolver = null;
  show.value = false;
  resolve?.(answer);
}

function open(overrides: Partial<ResolvedOptions>): Promise<boolean> {
  // Opening a second dialog abandons the first. Answer it false rather than
  // dropping the promise, so the earlier caller unwinds instead of hanging.
  settle(false);

  options.value = { ...DEFAULTS, ...overrides };
  show.value = true;

  return new Promise<boolean>((resolve) => {
    resolver = resolve;
  });
}

export function useConfirm() {
  /**
   * Promise-based replacement for window.confirm(). Resolves true when the
   * user accepts, false on cancel, Escape, or backdrop click.
   *
   *   if (!(await confirm('Delete this?'))) return;
   */
  function confirm(input: string | ConfirmOptions): Promise<boolean> {
    return open(typeof input === 'string' ? { message: input } : input);
  }

  /**
   * Promise-based replacement for window.alert(). One button, nothing to
   * decline. Await it when the next step should wait for acknowledgement.
   */
  function alert(input: string | AlertOptions): Promise<boolean> {
    const next = typeof input === 'string' ? { message: input } : input;
    return open({ confirmLabel: 'OK', tone: 'neutral', ...next, variant: 'alert' });
  }

  return {
    show,
    options,
    confirm,
    alert,
    accept: () => settle(true),
    cancel: () => settle(false),
  };
}
