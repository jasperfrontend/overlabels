import '../../css/app.css';
import '../../css/product-page.css';
import '../../css/welcome-checkin.css';
import '../../css/welcome-demos.css';
import '../../css/welcome.css';
import { wireThemeMenus } from '../utils/themeMenu';
import { initCheckinDemo } from './checkinDemo';

// The homepage is a static blade page - this entry only wires up the handful
// of interactive bits: theme switching, the mobile menu, tab groups and the
// product jump bar.

function wireMobileMenu() {
  const toggle = document.querySelector<HTMLElement>('[data-mobile-menu-toggle]');
  const menu = document.querySelector<HTMLElement>('[data-mobile-menu]');
  if (!toggle || !menu) return;

  const openIcon = toggle.querySelector<HTMLElement>('[data-mobile-menu-icon="open"]');
  const closeIcon = toggle.querySelector<HTMLElement>('[data-mobile-menu-icon="close"]');

  const setOpen = (open: boolean) => {
    menu.classList.toggle('hidden', !open);
    openIcon?.classList.toggle('hidden', open);
    closeIcon?.classList.toggle('hidden', !open);
  };

  toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));
  menu.querySelectorAll<HTMLElement>('[data-mobile-menu-link]').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });
}

function wireTabs() {
  const ACTIVE = ['border-sky-500', 'text-sky-500'];
  const INACTIVE = ['border-transparent', 'text-muted-foreground', 'hover:text-foreground'];

  document.querySelectorAll<HTMLElement>('[data-tabs]').forEach((group) => {
    const buttons = group.querySelectorAll<HTMLElement>('[data-tab]');
    const panels = group.querySelectorAll<HTMLElement>('[data-tab-panel]');

    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        buttons.forEach((b) => {
          const active = b === btn;
          ACTIVE.forEach((c) => b.classList.toggle(c, active));
          INACTIVE.forEach((c) => b.classList.toggle(c, !active));
        });
        panels.forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== btn.dataset.tab));
      });
    });
  });
}

// The site nav is sticky and grows a second row below lg, so its height is
// measured rather than assumed: the product jump bar sticks under it, and
// anchors scroll clear of it, through --ol-nav-h.
function trackNavHeight() {
  const nav = document.querySelector<HTMLElement>('[data-site-nav]');
  if (!nav) return;
  const set = () => document.documentElement.style.setProperty('--ol-nav-h', `${nav.offsetHeight}px`);
  set();
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(set).observe(nav);
}

// The product jump bar marks the row being read: the last row whose top has
// passed a line just under the bar. Above the first row nothing is marked.
// On a phone the bar scrolls sideways, so the marked link is kept in view.
function wireProductNav() {
  const bar = document.querySelector<HTMLElement>('[data-product-nav]');
  if (!bar) return;
  const list = bar.querySelector<HTMLElement>('ul');
  const links = Array.from(bar.querySelectorAll<HTMLAnchorElement>('[data-product-nav-link]'));
  const rows = Array.from(document.querySelectorAll<HTMLElement>('[data-product-row]'));
  if (!rows.length) return;

  let current: string | null = null;
  let queued = false;

  const update = () => {
    queued = false;
    const line = bar.getBoundingClientRect().bottom + window.innerHeight * 0.25;
    let next: string | null = null;
    for (const row of rows) {
      if (row.getBoundingClientRect().top <= line) next = row.id;
    }
    if (next === current) return;
    current = next;
    links.forEach((link) => {
      const active = link.dataset.productNavLink === current;
      if (active) {
        link.setAttribute('aria-current', 'true');
        // Only when the bar overflows (a phone), and instant, so it never
        // competes with the page's own smooth scroll to the anchor.
        if (list && list.scrollWidth > list.clientWidth) {
          list.scrollLeft = link.offsetLeft - (list.clientWidth - link.offsetWidth) / 2;
        }
      } else {
        link.removeAttribute('aria-current');
      }
    });
  };

  const queue = () => {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(update);
  };

  window.addEventListener('scroll', queue, { passive: true });
  window.addEventListener('resize', queue);
  update();
}

document.addEventListener('DOMContentLoaded', () => {
  wireThemeMenus();
  wireMobileMenu();
  wireTabs();
  trackNavHeight();
  wireProductNav();
  initCheckinDemo();
});
