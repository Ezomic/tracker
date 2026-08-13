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
- Projects organised into a hierarchical **category tree** in the sidebar; uncategorised projects sit in their own collapsible group
- Issues with type, priority, status, description, labels, owner (reporter) and assignee
- Epics: one level of parent/child, with progress on the epic
- Board, list and a three-view dashboard (Focus / Metrics / Board), filtering, and a command palette (`⌘K`)
- **Saved views** — store the current filters as a named, reusable view
- **Time tracking** — log time against issues, confirm billable minutes, and report them to Billr; issues can be marked invoiceable
- Organisation-wide **ticket templates** that prefill the description plus default type, priority and labels; the template a ticket was filed from is shown on the issue
- **Recurring templates** — file an issue automatically on a cadence into a target project
- **Notifications and @mentions** on issues and comments
- Auto-archive of done issues, per project (never, 1 day, 1 week, or custom)

**Multi-tenancy**

- Open registration: name and email, no password
- Each signup gets their own organisation (workspace) and sees only projects they belong to
- Two-tier access: an **organisation role** (**Owner**, **Admin**, **Member**, **Guest**) plus an optional **per-project grant** (**Read**, **Write**, **Admin**). The effective access on a project is the higher of the two, so owners/admins get admin everywhere while a Guest is sandboxed to the projects they are granted
- Templates and labels live on the organisation, defined once and shared across its projects
- Invite people by email with a tokenized link that expires; existing accounts join on click, newcomers register first and land back on the invite

**Auth (passwordless)**

- Sign in with Thijssensoftware ID (SSO), a one-time code emailed to you, or a passkey
- There is no password column. Passkey management is re-gated behind a fresh email code rather than a password

**Integration**

- GitHub webhook links pull requests to issues by branch name
- Per-project GitHub repos, production URL, and docs links
- Portal app-switcher in the navbar: jump to the other Thijssensoftware apps you can access, fetched per-user from Thijssensoftware ID
- JSON API with Sanctum tokens
- CSV import/export, and scheduled SQLite backups

## Stack

PHP 8.3+ · Laravel 13 · Inertia 3 · Vue 3 (`<script setup lang="ts">`) · TypeScript · Tailwind v4 · shadcn-vue (Reka UI) · Vite · Laravel Wayfinder · Fortify (passkeys) · Sanctum · Pest (+ Infection mutation testing) · Vitest · PHPStan (Larastan) level 9 · Pint · ESLint + Prettier

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
| `composer types:check` | PHPStan level 9                                               |
| `npm run dev`          | Vite only                                                     |
| `npm run build`        | Build assets, and regenerate Wayfinder routes/actions         |
| `npm run types:check`  | `vue-tsc --noEmit`                                            |

**Wayfinder is generated at build time.** `resources/js/routes` and `resources/js/actions` are gitignored and produced by `npm run build`. If an import from `@/routes/...` or `@/actions/...` fails to resolve, build rather than hunting for the file. Note that `php artisan wayfinder:generate` does **not** emit the `.form()` variants the forms rely on; `npm run build` does.

## Architecture

Conventions worth knowing before changing things:

- **Actions** (`app/Actions`) hold business logic, one public `handle()` each: `CreateIssueAction`, `SendOrganizationInvitationAction`, `ArchiveDoneIssuesAction`.
- **Form Requests** validate; controllers stay thin.
- **Policies** enforce access. `ProjectPolicy` and `IssuePolicy` are the source of truth for who can do what.
- **Everything is membership-scoped.** `Project::visibleTo($user)` and `Issue::visibleTo($user)` scope every read; writes are authorized. A query that forgets this leaks across tenants, so scope first and ask questions later.
- **Guarded data migrations** are how production data gets changed, since there is no shell on the box. They guard on a row existing so fresh and test databases no-op. See `database/migrations/*backfill*` and `*populate*`.
- **`CarbonImmutable`** for dates, `#[Fillable]` attributes over `$fillable` arrays, and no inline SQL.
- **The app name is pinned in code**, not read from `APP_NAME`. See `AppServiceProvider`.

## Security

- Passwordless by design: one-time email codes (`random_int`, hashed at rest, short TTL, attempt-capped), passkeys, and Thijssensoftware ID SSO. Invitation links are random tokens stored hashed, with expiry and email binding.
- Access is membership-scoped everywhere (`visibleTo` on reads, policies on writes); labels/categories/templates are scoped to their owning organisation.
- Session cookies are `Secure` by default in production (`config/session.php`), `HttpOnly`, `SameSite=lax`.
- Login-code emails are **queued**, so delivery depends on the queue worker running (`composer dev` locally; a systemd unit in production). If codes stop arriving, check the worker first.
- A white-box source audit lives in [`docs/security-audit-2026-07-27.md`](docs/security-audit-2026-07-27.md).

## API

Authenticate with a Sanctum token (`Authorization: Bearer <token>`). Reads and writes are throttled separately, per user: **300 reads** and **60 writes** per minute.

```
GET    /api/projects
GET    /api/issues?project=TRACK
GET    /api/issues/{identifier}
POST   /api/issues
PATCH  /api/issues/{identifier}          # reparent
PATCH  /api/issues/{identifier}/status
DELETE /api/issues/{identifier}          # soft-archive, reversible
POST   /api/issues/{identifier}/restore  # undo the archive
GET    /api/issues/{identifier}/activity # how it got to be the way it is
```

`GET /api/issues` is paginated and returns `{"data": [...], "meta": {...}}`. It takes these filters, combinable:

| Param      | Takes                                            |
| ---------- | ------------------------------------------------ |
| `project`  | a project key (`TRACK`)                          |
| `search`   | ranked full-text over title, description and comments |
| `status`   | `backlog`, `in_progress`, `in_review`, `done` (deprecated) |
| `workflow_state` | a lane name on the project's type            |
| `state_category` | `backlog`, `unstarted`, `started`, `completed`, `canceled` |
| `type`     | `feature`, `fix`                                 |
| `priority` | `none`, `low`, `medium`, `high`, `urgent`        |
| `label`    | a label name, case-insensitive                   |
| `assignee` | an email, or `none` for unassigned               |
| `parent`   | an epic identifier (`TRACK-200`)                 |
| `source`   | the app that filed it (`snag`, `flare`)          |
| `archived` | `exclude` (default), `include`, or `only`        |
| `per_page` | 1 to 200, default 50                             |
| `page`     | page number                                      |

Each row carries enough to triage without a follow-up request: identifier, title, type, status, `workflow_state`, priority, project, parent, assignee, estimate, labels, `created_at` and `closed_at`.

### Workflow states

Board lanes are per-project-type and freely named, so an issue's real position is its **workflow state**, not the four-value `status`. Every lane maps to a fixed `category` (`backlog`, `unstarted`, `started`, `completed`, `canceled`), which is what to filter on when you want meaning rather than a particular project's wording.

`PATCH /api/issues/{identifier}/status` takes **either** form, and exactly one:

```bash
# by lane, on the project's own type; name is case-insensitive, id also works
curl -X PATCH .../api/issues/TRACK-42/status -d workflow_state="In review"

# the legacy form, still accepted until it sunsets
curl -X PATCH .../api/issues/TRACK-42/status -d status=in_review
```

Both keep `status`, `workflow_state` and `closed_at` consistent with each other, so a consumer on either field sees the same truth. Sending both is a 422 rather than one silently winning.

A project with no type has no lanes: `workflow_state` reads as `null` and only `status` applies. That is why `status` is still populated everywhere rather than removed outright.

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

### Compatibility

There is no version prefix, deliberately. Breaking changes ship **additive-first**: the new field
lands alongside the old, consumers migrate, and the old one is removed no sooner than **30 days**
later. Anything deprecated is marked here and answers with a `Sunset` header carrying its removal
date. The reasoning is in [`docs/api-versioning-2026-08-06.md`](docs/api-versioning-2026-08-06.md).

| Deprecated                  | Use instead                          | Removal      |
| --------------------------- | ------------------------------------ | ------------ |
| `GET /api/teams`            | `GET /api/projects`                  | 2026-09-05   |
| `status` on issue payloads  | `workflow_state`                     | 2026-09-30   |
| `status` on `PATCH .../status` | `workflow_state`                  | 2026-09-30   |

### Filing from another app

An app that files issues automatically can pass `source` and `external_ref` together to make the call idempotent. If an issue in that project already carries the same pair, it is returned with `200` instead of a second one being created with `201`:

```bash
curl -X POST https://tracker.thijssensoftware.nl/api/issues \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -d project=TRACK -d title="Total is wrong on the invoice screen" -d type=fix \
  -d source=snag -d external_ref=123
```

Retries are therefore safe. Issues filed by hand leave both fields null and are unaffected.

`external_reporter` records who reported it when that person has no account here: a name, or a pseudonym the host app computed. It is never resolved against users.

### Webhooks out

A project can push status changes to another app instead of being polled: **Projects → Webhooks**, project admins only. Endpoints must be `https`.

Each delivery is queued, retried with backoff (4 attempts over roughly ten minutes, then dropped), and carries an HMAC SHA-256 of the body in `X-Tracker-Signature-256` plus the event name in `X-Tracker-Event`. Verify the signature the same way tracker verifies GitHub's on the way in. The payload carries `source` and `external_ref`, so a consumer matches it to its own record without storing tracker identifiers.

The signing secret is shown once at creation. The settings page reports the last delivery, its status code and any error, and can send a `ping` to check an endpoint before an issue ever moves.

### Service accounts

An app that files issues gets a **service account** rather than someone's personal token (Settings → Service accounts, organisation admins only). A service account:

- is granted write on exactly the projects you pick, and can see nothing else
- holds a token limited to `issues:create`, `issues:read` and `projects:read`, so the rest of the API answers 403
- can never sign in: no login code is issued for its address, and no login path can give it a session

Revoking the account deletes its tokens with it.

`GET /api/projects` returns `key`, `name`, `color`, `category_id` and `archived_at`, and takes `archived=exclude` (default), `include` or `only`. Without that, an archived project is indistinguishable from one that never existed.

Beyond issues, the API also exposes CRUD for **projects** and their **members**, plus the organisation's **templates**, **categories** and **labels** (`/api/projects`, `/api/projects/{key}/members`, `/api/templates`, `/api/categories`, `/api/labels`, `/api/members`).

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

Deploys are **manual**. Merging to `main` does not ship anything. The "Deploy to production" workflow is `workflow_dispatch` only, so a release is a deliberate act: run the workflow from the Actions tab (or deploy over SSH by hand).

The workflow does maintenance mode, `composer install`, `npm ci`, `optimize:clear`, `npm run build`, `migrate --force`, `optimize`, then back up.

Two things that bite:

- The workflow uses a `production` concurrency group, so **a newer deploy cancels an older one**. Let each deploy finish before starting the next.
- `optimize:clear` runs **before** `npm run build` deliberately. Wayfinder generates from the route list at build time, and a stale route cache once produced a build referencing a route that did not exist yet, taking production down.

Tests and linting run on every pull request targeting `main`. Note that pull requests targeting **any other branch get no CI at all**, so stacked pull requests are unverified until retargeted.

## Testing

```bash
composer test              # lint + PHPStan + Pest
./vendor/bin/pest --filter "invitation"
```

Pest, with `RefreshDatabase` against in-memory SQLite. `tests/Pest.php` exposes `member()` and `joinProjects()` helpers, since almost every feature test needs the acting user to be a member of the project under test.
