import '../../css/app.css';
import { wireThemeMenus } from '../utils/themeMenu';

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

document.addEventListener('DOMContentLoaded', () => {
  wireThemeMenus();
  wireMobileMenu();
  wireTabs();
});
