// Theme switching for the Blade pages that have no Vue: the homepage and every
// page under /help. Both include `welcome.theme-toggle` and call
// `wireThemeMenus()` from their entry. The Inertia app has its own copy of the
// class logic in `composables/useAppearance.ts`; this one is the same rules
// without Vue, so the two must agree on the class names and storage keys.

type Appearance = 'light' | 'dark' | 'sepia' | 'system';

const THEME_CLASSES = ['dark', 'theme-sepia'] as const;

export function applyTheme(value: Appearance) {
  const root = document.documentElement;
  THEME_CLASSES.forEach((c) => root.classList.remove(c));
  if (value === 'sepia') {
    // Sepia rides on .dark so all dark: variants keep working (see useAppearance.ts).
    root.classList.add('dark', 'theme-sepia');
  } else if (value === 'dark') {
    root.classList.add('dark');
  } else if (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    root.classList.add('dark');
  }
}

export function storedAppearance(): Appearance | null {
  try {
    return localStorage.getItem('appearance') as Appearance | null;
  } catch {
    return null;
  }
}

export function wireThemeMenus() {
  document.querySelectorAll<HTMLElement>('[data-theme-menu]').forEach((menu) => {
    const toggle = menu.querySelector<HTMLElement>('[data-theme-menu-toggle]');
    const options = menu.querySelector<HTMLElement>('[data-theme-menu-options]');
    if (!toggle || !options) return;

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      options.classList.toggle('hidden');
    });

    options.querySelectorAll<HTMLElement>('[data-theme-choice]').forEach((choice) => {
      choice.addEventListener('click', () => {
        const value = (choice.dataset.themeChoice ?? 'system') as Appearance;
        try {
          localStorage.setItem('appearance', value);
        } catch {
          // storage blocked; cookie still persists the choice
        }
        document.cookie = `appearance=${value};path=/;max-age=${365 * 24 * 60 * 60};SameSite=Lax`;
        applyTheme(value);
        options.classList.add('hidden');
      });
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll<HTMLElement>('[data-theme-menu-options]').forEach((el) => el.classList.add('hidden'));
  });

  // Follow OS theme changes while in system mode, same as initializeTheme().
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    applyTheme(storedAppearance() || 'system');
  });
}
