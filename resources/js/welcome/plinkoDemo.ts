/**
 * The Twitch Chat Plinko demo, for real: the product's own board
 * (resources/js/plinko/plinkoBoard.ts) mounted into the demo scene, made-up
 * viewers typing !drop in the chat column, and a chat box under the scene
 * where the visitor drops their own ball and chases the top score. The board
 * runs its real rules: chat order, one ball per chatter at a time, the same
 * slots and points.
 *
 * The board is a lazy chunk here exactly as in the overlay: the import only
 * fires once the demo scrolls near the viewport. If it fails, the hero
 * artwork the partial rendered stays and the chat box never appears.
 *
 * Nothing leaves the page. Everything the visitor types is written with
 * textContent, and the board sets names with fillText and textContent too.
 */

import type { PlinkoInstance } from '@/plinko/plinkoBoard';
import { isDropCommand } from '@/plinko/plinkoTag';

const VISITOR = { name: 'you', login: 'you', color: '#1daaff' } as const;
const CHAT_LINES_KEPT = 10;

/**
 * Taken in turn, so a viewer comes round again only after everyone else
 * (16 drops, well over the time a ball takes to land). The board ignores a
 * second !drop while the first ball is still in play, and the demo should
 * never show a line the board then ignores.
 */
const VIEWERS = [
  { name: 'tea_and_raids', color: '#f97316' },
  { name: 'noodlebyte', color: '#a3e635' },
  { name: 'pixel_kat', color: '#38bdf8' },
  { name: 'MidnightSnackAttack', color: '#f472b6' },
  { name: 'quietfox', color: '#8A2BE2' },
  { name: 'kettle_', color: '#FF69B4' },
  { name: 'moss_ttv', color: '#5F9EA0' },
  { name: 'dana_plays', color: '#DAA520' },
  { name: 'sunroof', color: '#2E8B57' },
  { name: 'bytesized', color: '#FF4500' },
  { name: 'lurkerbee', color: '#14b8a6' },
  { name: 'wafflesaur', color: '#f59e0b' },
  { name: 'juniper_tv', color: '#22c55e' },
  { name: 'ghostpepper', color: '#a78bfa' },
  { name: 'cactusjack', color: '#fb923c' },
  { name: 'pressF_', color: '#84cc16' },
];

const VIEWER_LINES = ['!drop', '!drop', '!drop', '!drop come on edges', '!drop', '!drop 1000 or bust', '!drop', '!drop lets go'];

function reducedMotion(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

class PlinkoDemo {
  private board: PlinkoInstance | null = null;
  private visible = true;
  private seedTimer: number | null = null;
  private nextViewer = 0;
  private dropCount = 0;

  private readonly chat: HTMLElement | null;
  private readonly boardEl: HTMLElement;
  private readonly form: HTMLFormElement | null;
  private readonly input: HTMLInputElement | null;

  constructor(
    private readonly root: HTMLElement,
    boardEl: HTMLElement,
  ) {
    this.boardEl = boardEl;
    this.chat = root.querySelector<HTMLElement>('[data-plinko-demo-chat]');
    this.form = root.querySelector<HTMLFormElement>('[data-plinko-demo-form]');
    this.input = root.querySelector<HTMLInputElement>('[data-plinko-demo-input]');
  }

  async mount(): Promise<void> {
    const mod = await import('@/plinko/plinkoBoard');
    this.board = mod.mountPlinkoBoard(this.boardEl);
    this.root.querySelector('[data-plinko-demo-fallback]')?.remove();
    this.root.classList.add('is-live');

    this.wireForm();
    // A background tab pauses the viewers; coming back picks them up again.
    document.addEventListener('visibilitychange', () => this.setVisible(this.visible));
    // Reduced motion: nothing moves until the visitor drops a ball themselves.
    if (!reducedMotion()) this.scheduleSeed(600);
  }

  /** Viewers only type while the demo is on screen; a board nobody is watching stays quiet. */
  setVisible(visible: boolean): void {
    this.visible = visible;
    if (visible && !document.hidden && this.board && this.seedTimer === null && !reducedMotion()) this.scheduleSeed(400);
  }

  private scheduleSeed(delay: number): void {
    this.seedTimer = window.setTimeout(() => {
      this.seedTimer = null;
      if (!this.visible || document.hidden) return;
      this.seed();
      // Mostly a steady trickle, now and then two in quick succession, the
      // way a real chat clusters.
      this.scheduleSeed(Math.random() < 0.2 ? 250 : 700 + Math.random() * 1100);
    }, delay);
  }

  private seed(): void {
    const viewer = VIEWERS[this.nextViewer];
    this.nextViewer = (this.nextViewer + 1) % VIEWERS.length;
    const text = VIEWER_LINES[Math.floor(Math.random() * VIEWER_LINES.length)];
    this.addLine(viewer.name, viewer.color, text);
    this.drop(viewer.name, viewer.name.toLowerCase(), viewer.color);
  }

  private wireForm(): void {
    if (!this.form || !this.input) return;
    this.form.hidden = false;
    this.form.addEventListener('submit', (event) => {
      event.preventDefault();
      this.submit();
    });
  }

  private submit(): void {
    if (!this.input) return;
    const text = this.input.value.trim();
    if (text === '') return;

    this.addLine(VISITOR.name, VISITOR.color, text, true);
    if (isDropCommand(text)) this.drop(VISITOR.name, VISITOR.login, VISITOR.color);

    // Ready for the next one: Enter again is another drop.
    this.input.value = '!drop';
    this.input.focus({ preventScroll: true });
  }

  private drop(name: string, login: string, color: string): void {
    this.dropCount += 1;
    this.board?.drop({ id: `demo-${this.dropCount}`, userId: login, login, name, color });
  }

  private addLine(name: string, color: string, text: string, isVisitor = false): void {
    if (!this.chat) return;
    const line = document.createElement('div');
    line.className = isVisitor ? 'ol-pk__line ol-pk__line--you' : 'ol-pk__line';

    const author = document.createElement('span');
    author.className = 'ol-pk__name';
    author.style.color = color;
    author.textContent = name;

    const cmd = document.createElement('span');
    cmd.className = 'ol-pk__cmd';
    cmd.textContent = text;

    line.append(author, ': ', cmd);
    this.chat.appendChild(line);
    while (this.chat.children.length > CHAT_LINES_KEPT) this.chat.firstElementChild?.remove();
  }
}

export function initPlinkoDemo(): void {
  document.querySelectorAll<HTMLElement>('[data-plinko-demo]').forEach(wirePlinkoDemo);
}

function wirePlinkoDemo(root: HTMLElement): void {
  const boardEl = root.querySelector<HTMLElement>('[data-plinko-demo-board]');
  if (!boardEl) return;

  if (root.dataset.plinkoDemo === 'wired') return;
  root.dataset.plinkoDemo = 'wired';

  let started = false;
  let demo: PlinkoDemo | null = null;
  const start = (): void => {
    if (started) return;
    started = true;
    demo = new PlinkoDemo(root, boardEl);
    demo.mount().catch((err: unknown) => {
      console.warn('plinko demo board failed to load', err);
    });
  };

  if (typeof IntersectionObserver === 'undefined') {
    start();
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries.some((entry) => entry.isIntersecting);
      if (visible) start();
      demo?.setVisible(visible);
    },
    { rootMargin: '200px 0px' },
  );
  observer.observe(root);
}
