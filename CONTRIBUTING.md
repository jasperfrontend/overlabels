# Contributing to Overlabels

Thanks for being here. Overlabels is a small project maintained by one person, so the most useful
thing you can do before writing code is talk to me first.

## Before you write code

**Open an issue before starting anything substantial.** Bug fixes and small corrections can go
straight to a pull request. Anything larger - a new feature, a refactor, a change to how a page
looks - is worth a conversation first, because I may already have a direction in mind or a reason
the current shape is the way it is.

One thing worth knowing up front: **Overlabels grows by accretion, not redesign.** Fixing one thing
that is clearly wrong is always welcome. Rewriting a working area of the UI because it could be
organised differently is usually not, however good the new version is. Restraint is deliberate here.

## Getting set up

The setup steps, required environment variables and integration credentials are in the
[Self-Hosting section of the README](README.md#self-hosting). No support is provided for
self-hosting, but those instructions are what a development environment needs.

Once you are set up:

```bash
composer run dev      # server, queue worker and vite together
php artisan test      # Pest test suite
npm run lint          # ESLint with auto-fix
npm run format        # Prettier
php artisan pint      # PHP code style
```

## The workflow

1. Branch off `main`. Use a prefix that matches what you are doing: `feat/`, `fix/`, `docs/`,
   `refactor/` or `chore/`. For example `fix/alert-duration-overflow`.
2. Commit your work. Please sign off your commits (see [Licensing](#licensing-and-sign-off) below).
3. Open a pull request against `main`. Never push directly to `main`.
4. Fill in the pull request template. The checklist is short and every item on it is something that
   has bitten this project before.

## Before you open a pull request

- `php artisan test` passes
- `npm run lint` and `php artisan pint` are clean
- Any new migration has been rolled back and re-run at least once
- Frontend changes work at every screen size, not just yours
- There is an entry in `docs/changelog/changelog-YYYY-MM.md` for the current month

## House rules for anything users read

These are small and I am strict about them, so it is faster if you know them going in.

- **No em dashes** in user-facing copy, comments or documentation. Use a hyphen with spaces instead.
- **Say "Copy", never "Fork"**, anywhere in the interface. Forking is the git concept; copying is
  what the feature does for a streamer.
- **Keep copy gender-neutral.** No "dude", "man", "guys" or "bro" anywhere a user can read it,
  including bot replies.
- **Render nothing when data is missing.** No em dash placeholders, no "N/A", no zero standing in
  for absence.
- **Body copy uses `text-foreground`**, not `text-muted-foreground`.
- **Every clickable element gets `cursor-pointer`.**

## Licensing and sign-off

Overlabels is licensed under [AGPL-3.0-or-later](LICENSE), and contributions are accepted under the
same terms. You keep the copyright in what you write.

Please sign off your commits to certify that you have the right to submit the work, following the
[Developer Certificate of Origin](https://developercertificate.org/):

```bash
git commit -s -m "fix: your message here"
```

That adds a `Signed-off-by:` line using your git name and email. It is a statement about provenance,
not a transfer of anything.

## Security

Please do not open a public issue for anything you suspect is a vulnerability. [SECURITY.md](SECURITY.md)
explains how to report it privately and what to expect.

## Code of conduct

Participation is covered by the [Code of Conduct](CODE_OF_CONDUCT.md). It applies to issues, pull
requests and any other space that belongs to this project.

~ JasperDiscovers
