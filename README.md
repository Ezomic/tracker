# Tracker

A self-hosted issue tracker. Multi-tenant, passwordless, and built to get out of the way: every project has its own key and independent numbering, and every issue hands you a ready-to-use git branch name.

Production: [tracker.thijssensoftware.nl](https://tracker.thijssensoftware.nl)

## Why it exists

It replaced Linear once that hit its free-tier active-issue cap. The goals were narrow on purpose:

- **Own the data.** Self-hosted, SQLite, backed up on a schedule.
- **Fit the git workflow.** Issues carry a branch name (`feature/TRACK-12-short-slug`), and pull requests link back automatically.
- **Be scriptable.** A JSON API so tickets can be filed from tooling rather than a browser.

## Features

**Projects and issues**

- Projects with a short key (`TRACK`, `CMS`) and per-project sequential numbering (`TRACK-42`)
- Issues with type, priority, status, description, labels, owner (reporter) and assignee
- Epics: one level of parent/child, with progress on the epic
- Board and list views, filtering, and a command palette (`⌘K`)
- Ticket templates per project, prefilling the description plus default type, priority and labels
- Auto-archive of done issues, per project (never, 1 day, 1 week, or custom)

**Multi-tenancy**

- Open registration: name and email, no password
- Each signup gets their own workspace and sees only their own projects
- Per-project roles: **Owner** (full control), **Admin** (settings, members, issues), **Member** (issues)
- Invite people by email with a tokenized link that expires; existing accounts join on click, newcomers register first and land back on the invite

**Auth (passwordless)**

- Sign in with Thijssensoftware ID (SSO), a one-time code emailed to you, or a passkey
- There is no password column. Passkey management is re-gated behind a fresh email code rather than a password

**Integration**

- GitHub webhook links pull requests to issues by branch name
- Per-project GitHub repos, production URL, and docs links
- JSON API with Sanctum tokens
- CSV import/export, and scheduled SQLite backups

## Stack

PHP 8.3+ · Laravel 13 · Inertia 3 · Vue 3 (`<script setup lang="ts">`) · TypeScript · Tailwind v4 · shadcn-vue (Reka UI) · Vite · Laravel Wayfinder · Fortify (passkeys) · Sanctum · Pest · PHPStan (Larastan) level 7 · Pint · ESLint + Prettier

SQLite by default. The database is a file; back it up and you have moved the app.

## Getting started

Requires PHP 8.3+, Composer, Node, and [Herd](https://herd.laravel.com) (or any local PHP server).

```bash
git clone git@github.com:Ezomic/tracker.git
cd tracker
composer setup   # install, .env, key, migrate, npm install, build
```

`composer setup` copies `.env.example`, generates a key, migrates, and builds the frontend. Then:

```bash
composer dev     # runs server, vite, queue worker and logs together
```

Or symlink it into Herd to serve it at `tracker.test`:

```bash
ln -s /path/to/tracker ~/Herd/tracker
```

There is no password to log in with. Locally, `MAIL_MAILER=log`, so request an email login code and read the code out of `storage/logs/laravel.log`.

## Everyday commands

| Command                | Does                                                          |
| ---------------------- | ------------------------------------------------------------- |
| `composer dev`         | Serve the app, Vite, queue worker, and logs together          |
| `composer test`        | Clear config, lint, PHPStan, then Pest                        |
| `composer ci:check`    | Everything CI runs: JS lint, format, types, and the PHP suite |
| `composer lint`        | Pint (fix)                                                    |
| `composer types:check` | PHPStan level 7                                               |
| `npm run dev`          | Vite only                                                     |
| `npm run build`        | Build assets, and regenerate Wayfinder routes/actions         |
| `npm run types:check`  | `vue-tsc --noEmit`                                            |

**Wayfinder is generated at build time.** `resources/js/routes` and `resources/js/actions` are gitignored and produced by `npm run build`. If an import from `@/routes/...` or `@/actions/...` fails to resolve, build rather than hunting for the file. Note that `php artisan wayfinder:generate` does **not** emit the `.form()` variants the forms rely on; `npm run build` does.

## Architecture

Conventions worth knowing before changing things:

- **Actions** (`app/Actions`) hold business logic, one public `handle()` each: `CreateIssueAction`, `SendProjectInvitationAction`, `ArchiveDoneIssuesAction`.
- **Form Requests** validate; controllers stay thin.
- **Policies** enforce access. `ProjectPolicy` and `IssuePolicy` are the source of truth for who can do what.
- **Everything is membership-scoped.** `Project::visibleTo($user)` and `Issue::visibleTo($user)` scope every read; writes are authorized. A query that forgets this leaks across tenants, so scope first and ask questions later.
- **Guarded data migrations** are how production data gets changed, since there is no shell on the box. They guard on a row existing so fresh and test databases no-op. See `database/migrations/*backfill*` and `*populate*`.
- **`CarbonImmutable`** for dates, `#[Fillable]` attributes over `$fillable` arrays, and no inline SQL.
- **The app name is pinned in code**, not read from `APP_NAME`. See `AppServiceProvider`.

## API

Authenticate with a Sanctum token (`Authorization: Bearer <token>`). Rate limited to 60 requests per minute.

```
GET    /api/projects
GET    /api/issues?project=TRACK
GET    /api/issues/{identifier}
POST   /api/issues
PATCH  /api/issues/{identifier}          # reparent
PATCH  /api/issues/{identifier}/status
DELETE /api/issues/{identifier}          # soft-archive, reversible
```

Creating an issue stamps the token's user as the owner (reporter) and returns the branch name:

```bash
curl -X POST https://tracker.thijssensoftware.nl/api/issues \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -d project=TRACK -d title="Fix the thing" -d type=fix \
  -d assignee=someone@example.com   # optional, must be a project member

# {"identifier":"TRACK-42","url":"...","branch_name":"fix/TRACK-42-fix-the-thing", ...}
```

Only projects you are a member of are visible, on the API as much as in the UI.

## Artisan commands

| Command                                 |                                                                                                 |
| --------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `issues:archive-done`                   | Archive done issues past each project's duration (scheduled hourly)                             |
| `backup:database`                       | Copy the SQLite database into `storage/app/private/backups`, pruning old ones (scheduled daily) |
| `issues:export {path}`                  | Export all issues to CSV                                                                        |
| `issues:import {path}`                  | Import issues from CSV matching the export schema                                               |
| `issues:reassign {path}`                | Re-key issues into other projects, renumbering. Irreversible                                    |
| `teams:seed {key} {name} {next_number}` | Create or update a project with a counter floor, never lowering an existing counter             |

## Deployment

Pushing to `main` deploys via GitHub Actions over SSH: maintenance mode, `composer install`, `npm ci`, `optimize:clear`, `npm run build`, `migrate --force`, `optimize`, then back up.

Two things that bite:

- The workflow uses a `production` concurrency group, so **a newer deploy cancels an older one**. Merge and let each deploy finish before starting the next.
- `optimize:clear` runs **before** `npm run build` deliberately. Wayfinder generates from the route list at build time, and a stale route cache once produced a build referencing a route that did not exist yet, taking production down.

Tests and linting run on every pull request targeting `main`. Note that pull requests targeting **any other branch get no CI at all**, so stacked pull requests are unverified until retargeted.

## Testing

```bash
composer test              # lint + PHPStan + Pest
./vendor/bin/pest --filter "invitation"
```

Pest, with `RefreshDatabase` against in-memory SQLite. `tests/Pest.php` exposes `member()` and `joinProjects()` helpers, since almost every feature test needs the acting user to be a member of the project under test.
