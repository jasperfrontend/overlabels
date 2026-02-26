# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 + Vue 3 application for managing Twitch overlays. It uses Inertia.js for seamless frontend/backend integration, TypeScript for type safety, and TailwindCSS v4 with Shadcn/Vue components for UI.

## Essential Commands

### Development
```bash
# Start full development environment (server + queue + vite)
composer run dev

# Run individual services
php artisan serve         # Laravel server
npm run dev              # Vite dev server
php artisan queue:work   # Queue worker
```

### Testing & Quality
```bash
# Run tests
php artisan test         # PHP tests (Pest framework)

# Code quality
npm run lint             # ESLint with auto-fix
npm run format           # Prettier formatting
php artisan pint         # PHP code style fixes
```

### Build & Deploy
```bash
npm run build            # Production build
php artisan migrate      # Run database migrations
php artisan optimize     # Cache configuration
```

## Architecture Overview

This app is being built on a Windows 10 machine. Do NOT use linux commands for file manipulation and handling, but use the
Windows equivalents.

`php` is available on PATH — use `php artisan ...` directly.

This project uses Postgres.

### Core Systems

**Twitch Integration**: The app integrates deeply with Twitch through OAuth and EventSub webhooks. User authentication is based on `twitch_id` (not email). The `TwitchApiService` handles all API interactions including token refresh.

**Overlay System**: Templates are stored in `overlay_templates` table with a custom tag system that parses Twitch data dynamically. Access is controlled through tokens (`OverlayAccessToken`) or hash-based public links (`OverlayHash`).

**Frontend Stack**: Vue 3 components live in `/resources/js/`. Inertia.js eliminates the need for separate API endpoints for most operations. Pages are in `/Pages/`, reusable components in `/components/`, and UI primitives in `/components/ui/`.

### Key Patterns

**Route Organization**: Routes are split across multiple files in `/routes/`: `web.php` (main app), `api.php` (public API), `auth.php` (authentication), `settings.php` (user settings).

**Database**: Uses SQLite by default but supports PostgreSQL. Migrations follow Laravel conventions with proper rollback support. Models use factories for testing.

**API Endpoints**: 
- Public overlay rendering: `/api/overlay/render` (rate-limited)
- Twitch webhook: `/api/twitch/webhook` 
- Template operations require authentication through Inertia

**Testing**: Feature tests in `/tests/Feature/`, unit tests in `/tests/Unit/`. Use Pest framework with Laravel-specific helpers.

## Development Workflow

### Setting Up Twitch Integration
1. Create Twitch app at dev.twitch.tv
2. Set `TWITCH_CLIENT_ID` and `TWITCH_CLIENT_SECRET` in `.env`
3. For local webhook testing, use ngrok and update webhook URL in Twitch settings

### Working with Templates
Templates use a custom tag system (e.g., `{{follower_count}}`) parsed by `TemplateParserService`. Tags are validated against available Twitch data. The template editor uses CodeMirror with custom syntax highlighting.

### Frontend Development
- Components follow Shadcn/Vue patterns in `/resources/js/components/ui/`
- Use composables in `/resources/js/composables/` for shared logic
- TypeScript types are in `/resources/js/types/`
- Tailwind v4 with CSS layers for styling

### Database Changes
Always create migrations for schema changes. Test rollback before committing. Use seeders for test data generation.

## Important Services

- `TwitchApiService`: All Twitch API interactions
- `TemplateParserService`: Template tag parsing and validation  
- `OverlayAccessService`: Access control for overlays
- Queue workers handle background tasks (EventSub processing)

## Environment Variables

Critical variables:
- `TWITCH_CLIENT_ID`, `TWITCH_CLIENT_SECRET`: Required for Twitch integration
- `APP_URL`: Must be correct for webhooks
- `DB_CONNECTION`: sqlite (default) or pgsql
- `TELESCOPE_ENABLED`: Enable debugging tools (dev only)
