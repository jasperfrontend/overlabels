/**
 * Is this keyboard event aimed at somewhere the user is typing?
 *
 * Any window-level keydown handler that binds bare keys has to ask this before
 * acting, or it steals keystrokes out of editors. `isContentEditable` is the
 * load-bearing part: CodeMirror renders a contenteditable <div>, not a
 * <textarea>, so a tagName allowlist waves every keystroke straight through.
 * That is how Backspace in the Builder's CSS editor used to delete the block
 * you had just placed.
 *
 * The property is inherited, so it is true for nodes nested inside an editable
 * region too - no need to walk up the tree.
 */
export function isTextEntryTarget(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement ||
        (target instanceof HTMLElement && target.isContentEditable)
    );
}
