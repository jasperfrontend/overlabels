import '../../css/app.css';

// The homepage is a static blade page - this entry only wires up the handful
// of interactive bits: theme switching, the mobile menu, and tab groups.

type Appearance = 'light' | 'dark' | 'sepia' | 'system';

const THEME_CLASSES = ['dark', 'theme-sepia'] as const;

function applyTheme(value: Appearance) {
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

function storedAppearance(): Appearance | null {
    try {
        return localStorage.getItem('appearance') as Appearance | null;
    } catch {
        return null;
    }
}

function wireThemeMenus() {
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
}

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

    // Follow OS theme changes while in system mode, same as initializeTheme().
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        applyTheme(storedAppearance() || 'system');
    });
});
