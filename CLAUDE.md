# Tracker (this app)

Self-hosted issue tracker. Laravel + Inertia/Vue, passwordless auth, Sanctum API.
The repo root is this `web/` directory. See [README.md](README.md) for features,
setup, the API, and the deploy pipeline; this file is the short list of things to
know **before changing code**. Global conventions live in `~/.claude/CLAUDE.md`.

## Must know before editing

- **Wayfinder is generated at build.** `resources/js/routes` and `resources/js/actions` are gitignored and produced by `npm run build`. If an `@/routes/...` or `@/actions/...` import won't resolve, run the build. `php artisan wayfinder:generate` does **not** emit the `.form()` variants the Inertia forms rely on, and the output persists across branch switches, so regenerate after switching branches if types complain.
- **Quality gates are strict.** PHPStan (Larastan) **level 9**, Infection **mutation testing** (MSI gate), **Vitest** for non-trivial frontend logic, and Pest. `composer ci:check` runs the lot. Don't lower the level or add baselines/ignores to dodge an error, fix the cause.
- **CI only runs on PRs targeting `main`.** PRs onto any other branch get no CI, so stacked PRs are unverified until retargeted.
- **Deploy is manual.** Merging to `main` does **not** deploy. `.github/workflows/deploy.yml` is `workflow_dispatch` only, so production is deployed by hand (run the workflow, or SSH in). The `production` concurrency group means a newer deploy **cancels an older one**, so let each finish. `optimize:clear` runs **before** `npm run build` on purpose (a stale route cache once shipped a build referencing a not-yet-existing route and took prod down).
- **The app name is pinned in code**, not read from `APP_NAME` (prod `.env` still says `Laravel`). See `AppServiceProvider`.

## Architecture (the short version)

- **Everything is membership-scoped.** `Project::visibleTo($user)` / `Issue::visibleTo($user)` on every read; policies (`ProjectPolicy`, `IssuePolicy`, and the org/label/category ones) authorize every write. A query that forgets this leaks across tenants.
- **Two-tier access model.** An org role (owner/admin/member/**guest**) plus an optional per-project grant (read/write/admin); effective access is the higher of the two. Labels, categories and templates live on the **organisation**.
- Business logic in **Actions** (one `handle()`), validation in **Form Requests**, thin controllers. `CarbonImmutable` for dates, `#[Fillable]`/`#[Hidden]` attributes (not arrays), no inline SQL.
- **Production data changes go through guarded data migrations** (guard on a row existing so fresh/test DBs no-op). See `database/migrations/*backfill*` and `*populate*`.
- Sidebar projects are grouped by a **category tree** (there is no "favorites" feature; it was removed).

## Local dev

- `composer dev` runs server + Vite + **queue worker** + logs together. The queue worker matters: login-code emails are queued.
- No password. `MAIL_MAILER=log` locally, so request an email login code and read the 6-digit code out of `storage/logs/laravel.log`.
- Tests: Pest with `RefreshDatabase` on in-memory SQLite. `tests/Pest.php` exposes `member()`, `joinProjects()`, `organizationWith()`, and `projectInOrganization()` helpers; almost every feature test needs the acting user to be a member of the project under test.
