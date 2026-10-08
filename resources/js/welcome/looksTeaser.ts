// The three-knob designer on the Twitch Chat Overlay product page
// (resources/views/products/_looks-teaser.blade.php).
//
// The preview frame holds the overlay's own stylesheet, so the knobs change it
// the way the real designer does: a look is the skin/layout/background classes
// on .ol-chat plus its custom properties, an accent is --accent, a size is
// --size. Chat is made up and kept here; nothing leaves the page.
//
// It plays itself - a cursor picks a look, then an accent, then a size - for as
// long as nobody touches it. Pointer or keyboard focus inside hands it over;
// it picks the loop back up a few seconds after the visitor leaves. With
// reduced motion it never plays, and stays fully usable by hand.

type Preset = { key: string; label: string; blurb: string; values: Record<string, string> };
type Data = { skinKey: string; presets: Preset[] };
type Line = { name: string; color: string; text: string; badge?: 'mod' | 'vip' | 'sub'; first?: boolean; broadcaster?: boolean };

const LINES: Line[] = [
  { name: 'pixel_kat', color: '#1e90ff', text: 'hello from the night shift 👋', badge: 'sub' },
  { name: 'rivermoss', color: '#ff7f50', text: 'that jump was clean', badge: 'mod' },
  { name: 'lena_streams', color: '#9acd32', text: 'found you through a raid, staying', first: true },
  { name: 'quietfox', color: '#a970ff', text: 'chat looks so good on stream 🔥', badge: 'vip' },
  { name: 'tea_and_raids', color: '#ff69b4', text: 'gg' },
  { name: 'noodlebyte', color: '#daa520', text: 'lets gooo 🎉', badge: 'sub' },
  { name: 'moss_ttv', color: '#5f9ea0', text: 'what font is that' },
  { name: 'jasperdiscovers', color: '#9146ff', text: 'welcome in everyone', broadcaster: true },
  { name: 'kettle_', color: '#2e8b57', text: 'no way 😂' },
  { name: 'sunroof', color: '#ff4500', text: 'clip it', badge: 'sub' },
  { name: 'dana_plays', color: '#00ced1', text: 'first time here, love the vibe', first: true },
  { name: 'bytesized', color: '#c71585', text: 'that was insane' },
];

// Badges drawn as small solid tiles in each badge's colour: the shape of a
// Twitch badge without fetching Twitch's artwork from a product page.
const BADGE_COLORS = { mod: '#00ad03', vip: '#e005b9', sub: '#8205b4' } as const;
const badgeSrc = (color: string) =>
  `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 18"><rect width="18" height="18" rx="3" fill="${color}"/></svg>`)}`;

const KEEP = 12;
const EVERY_MS = 1700;
const RESUME_MS = 5000;

export function initLooksTeasers(): void {
  document.querySelectorAll<HTMLElement>('[data-looks-teaser]').forEach(wire);
}

function wire(root: HTMLElement): void {
  const raw = root.querySelector('[data-looks-teaser-data]')?.textContent;
  const frame = root.querySelector<HTMLIFrameElement>('[data-looks-frame]');
  const cursor = root.querySelector<SVGElement>('[data-teaser-cursor]');
  const blurb = root.querySelector<HTMLElement>('[data-look-blurb]');
  const size = root.querySelector<HTMLInputElement>('[data-size]');
  const sizeOut = root.querySelector<HTMLElement>('[data-size-out]');
  const nameInput = root.querySelector<HTMLInputElement>('[data-name]');
  const lookButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-look]'));
  const accentButtons = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-accent]'));
  if (!raw || !frame || !size) return;

  const data = JSON.parse(raw) as Data;
  // The broadcaster's line carries whoever is reading: a logged-in streamer's
  // own name arrives in the field, anyone else types one. Empty falls back.
  const streamer = () => nameInput?.value.trim() || 'jasperdiscovers';
  let chat: HTMLElement | null = null;
  let current = data.presets[0];
  let next = 0;
  const shown: Line[] = [];

  /* ---- the overlay ---- */

  const render = (line: Line): HTMLElement => {
    const doc = chat!.ownerDocument;
    const msg = doc.createElement('div');
    msg.className = 'msg' + (line.broadcaster ? ' is-broadcaster' : '') + (line.first ? ' is-first' : '');
    if (current.values.show_badges === '1' && line.badge) {
      const badges = doc.createElement('span');
      badges.className = 'badges';
      const img = doc.createElement('img');
      img.src = badgeSrc(BADGE_COLORS[line.badge]);
      img.alt = '';
      badges.append(img);
      msg.append(badges);
    }
    const name = doc.createElement('span');
    name.className = 'name';
    name.textContent = line.broadcaster ? streamer() : line.name;
    if (current.values.twitch_colors === '1') name.style.color = line.color;
    msg.append(name);
    if (line.first) {
      const chip = doc.createElement('span');
      chip.className = 'chip';
      chip.textContent = 'first message';
      msg.append(chip);
    }
    const body = doc.createElement('span');
    body.className = 'body';
    body.textContent = line.text;
    msg.append(body);
    return msg;
  };

  const push = () => {
    if (!chat) return;
    const line = LINES[next++ % LINES.length];
    shown.push(line);
    chat.append(render(line));
    while (shown.length > KEEP) {
      shown.shift();
      chat.firstElementChild?.remove();
    }
  };

  const rerender = () => {
    if (!chat) return;
    chat.replaceChildren(...shown.map(render));
  };

  const setVar = (name: string, value: string) => chat?.style.setProperty(name, value);

  /* ---- the knobs ---- */

  const press = (buttons: HTMLButtonElement[], match: (b: HTMLButtonElement) => boolean) =>
    buttons.forEach((b) => b.setAttribute('aria-pressed', String(match(b))));

  const setSize = (px: number) => {
    size.value = String(px);
    if (sizeOut) sizeOut.textContent = String(px);
    setVar('--size', `${px}px`);
  };

  const setAccent = (hex: string) => {
    setVar('--accent', hex);
    press(accentButtons, (b) => b.dataset.accent?.toLowerCase() === hex.toLowerCase());
  };

  function applyLook(key: string) {
    const preset = data.presets.find((p) => p.key === key);
    if (!preset || !chat) return;
    current = preset;
    const v = preset.values;
    chat.className = `ol-chat skin-${v[data.skinKey]} layout-${v.layout} bg-${v.background}`;
    setVar('--font', v.font);
    setVar('--name', v.name_color);
    setVar('--text', v.text_color);
    setVar('--bg', v.background_color);
    setVar('--emote', `${v.emote_size}px`);
    setAccent(v.accent);
    setSize(Number(v.font_size));
    // A ticker is one line: give it a strip at the bottom, as OBS would.
    frame!.style.height = v.layout === 'ticker' ? '5rem' : '100%';
    press(lookButtons, (b) => b.dataset.look === key);
    if (blurb) {
      const title = document.createElement('span');
      title.className = 'font-semibold text-foreground';
      title.textContent = `${preset.label}.`;
      blurb.replaceChildren(title, ` ${preset.blurb}`);
    }
    rerender();
  }

  lookButtons.forEach((b) => b.addEventListener('click', () => applyLook(b.dataset.look!)));
  accentButtons.forEach((b) => b.addEventListener('click', () => setAccent(b.dataset.accent!)));
  size.addEventListener('input', () => setSize(Number(size.value)));
  // Renamed in place rather than re-rendered, so typing does not replay
  // every message's entrance.
  nameInput?.addEventListener('input', () => {
    chat?.querySelectorAll('.is-broadcaster .name').forEach((n) => (n.textContent = streamer()));
  });

  // Wired only now, below every helper it calls: the frame can already be
  // loaded when this runs, and an earlier call reaches setVar before its
  // const exists. Listening on every load, not once, because the frame's
  // placeholder about:blank may fire one before the srcdoc arrives.
  const ready = () => {
    if (chat) return;
    chat = frame.contentDocument?.querySelector<HTMLElement>('.ol-chat') ?? null;
    if (!chat) return;
    applyLook(current.key);
    for (let i = 0; i < 5; i++) push();
  };
  frame.addEventListener('load', ready);
  ready();

  /* ---- chat keeps talking while the section is on screen ---- */

  let visible = false;
  window.setInterval(() => {
    if (visible && document.visibilityState === 'visible') push();
  }, EVERY_MS);
  new IntersectionObserver((entries) => {
    visible = entries.some((e) => e.isIntersecting);
  }).observe(root);

  /* ---- autoplay ---- */

  if (!cursor || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  let held = false;
  let resumeAt = 0;
  const sleep = (ms: number) => new Promise((r) => window.setTimeout(r, ms));
  const idle = () => !held && Date.now() >= resumeAt && visible && document.visibilityState === 'visible';

  const hand = () => {
    held = true;
    cursor.classList.add('hidden');
  };
  root.addEventListener('pointerenter', hand);
  root.addEventListener('focusin', hand);
  root.addEventListener('pointerleave', () => {
    held = false;
    resumeAt = Date.now() + RESUME_MS;
  });

  // The cursor is placed with `translate`, never `transform`: the click
  // squeeze animates `scale`, which the browser applies BEFORE `transform`
  // but after `translate`. On `transform` it scaled the offset itself, and
  // the cursor shot toward the top-left corner on every click.
  const moveTo = async (el: Element, x = 0.5) => {
    const r = el.getBoundingClientRect();
    const box = root.getBoundingClientRect();
    cursor.classList.remove('hidden');
    cursor.style.translate = `${r.left - box.left + r.width * x}px ${r.top - box.top + r.height / 2}px`;
    await sleep(750);
  };

  const click = async () => {
    cursor.animate([{ scale: '1' }, { scale: '0.8' }, { scale: '1' }], { duration: 220 });
    await sleep(120);
  };

  const pick = <T>(list: T[], not?: (t: T) => boolean) => {
    const pool = not ? list.filter((t) => !not(t)) : list;
    return pool[Math.floor(Math.random() * pool.length)];
  };

  const sizePos = (px: number) => (px - Number(size.min)) / (Number(size.max) - Number(size.min));

  (async function play() {
    let look = 0;
    for (;;) {
      await sleep(2600);
      if (!idle()) continue;

      look = (look + 1) % lookButtons.length;
      await moveTo(lookButtons[look]);
      if (!idle()) continue;
      await click();
      applyLook(lookButtons[look].dataset.look!);

      await sleep(2400);
      if (!idle()) continue;
      const accent = pick(accentButtons, (b) => b.getAttribute('aria-pressed') === 'true');
      await moveTo(accent);
      if (!idle()) continue;
      await click();
      setAccent(accent.dataset.accent!);

      await sleep(2000);
      if (!idle()) continue;
      const from = Number(size.value);
      const to = Math.max(Number(size.min), Math.min(Number(size.max), from + pick([-6, -4, 4, 6, 8])));
      await moveTo(size, sizePos(from));
      if (!idle()) continue;
      const steps = Math.abs(to - from);
      for (let i = 1; i <= steps && idle(); i++) {
        const px = from + Math.sign(to - from) * i;
        setSize(px);
        const r = size.getBoundingClientRect();
        const box = root.getBoundingClientRect();
        cursor.style.transition = 'none';
        cursor.style.translate = `${r.left - box.left + r.width * sizePos(px)}px ${r.top - box.top + r.height / 2}px`;
        await sleep(70);
      }
      cursor.style.transition = '';
    }
  })();
}
