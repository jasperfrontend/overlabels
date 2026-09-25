import '../../css/app.css';
import '../../css/welcome-checkin.css';
import '../../css/welcome-demos.css';
import '../../css/welcome.css';
import { wireThemeMenus } from '../utils/themeMenu';
import { initCheckinDemo } from './checkinDemo';

// The homepage is a static blade page - this entry only wires up the handful
// of interactive bits: theme switching, the mobile menu, and tab groups.

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

// The hero showcase: one tab per product, auto-advancing at the end of each
// demo's loop until the visitor picks one themselves. A programmatic click
// is not trusted, a real one is, which is how the two are told apart. A
// panel hidden with display:none restarts its CSS animations when shown, so
// every tab opens at the start of its loop.
function wireShowcase() {
  document.querySelectorAll<HTMLElement>('[data-showcase]').forEach((group) => {
    const buttons = Array.from(group.querySelectorAll<HTMLElement>('[data-tab]'));
    const panels = Array.from(group.querySelectorAll<HTMLElement>('[data-tab-panel]'));
    if (buttons.length < 2) return;

    let timer = 0;
    let manual = false;

    const schedule = () => {
      window.clearTimeout(timer);
      if (manual) return;
      const active = panels.find((p) => !p.classList.contains('hidden'));
      const seconds = Number(active?.dataset.duration) || 12;
      timer = window.setTimeout(() => {
        const index = buttons.findIndex((b) => b.dataset.tab === active?.dataset.tabPanel);
        buttons[(index + 1) % buttons.length]?.click();
      }, seconds * 1000);
    };

    buttons.forEach((btn) => {
      btn.addEventListener('click', (event) => {
        if (event.isTrusted) manual = true;
        // wireTabs() swapped the panels in its own listener, registered first.
        schedule();
      });
    });

    schedule();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  wireThemeMenus();
  wireMobileMenu();
  wireTabs();
  wireShowcase();
  initCheckinDemo();
});
