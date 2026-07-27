# Security audit — 2026-07-27

White-box, source-only review of the tracker application (Laravel + Inertia/Vue,
passwordless email-code auth, Sanctum API, GitHub webhook). No dynamic testing
was performed against a live target. Scope: authentication, authorization,
injection, XSS, CSRF, secrets/config, dependencies, and business-logic abuse.

**Outcome: no Critical or High findings.** A handful of Low / informational
items were identified; the actionable ones are remediated in TRACK-189 (this
change). Everything else below was checked and found solid.

## Findings & remediation

| ID  | Severity | Finding                                                                                                                                                                                                                                                                  | Status                                                                                                                        |
| --- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| L1  | Low      | Session cookie `Secure` flag not enforced (`config/session.php` defaulted to `env('SESSION_SECURE_COOKIE')` = null; prod had it unset).                                                                                                                                  | **Fixed** — `secure` now defaults to `true` when `APP_ENV=production` (still env-overridable, stays off for local http dev).  |
| L2  | Low      | `postcss` build dependency advisory GHSA-r28c-9q8g-f849 (source-map path traversal). Build-time only, not runtime-reachable.                                                                                                                                             | **Fixed** — `npm audit fix` bumped postcss to ≥8.5.23.                                                                        |
| L3  | Low      | User-enumeration timing side-channel on the login-code send: `LoginCodeMail` was sent synchronously over SMTP only for existing emails, so existing addresses responded measurably slower.                                                                               | **Fixed** — `LoginCodeMail` now `implements ShouldQueue`; the send happens off-request (prod runs a `database` queue worker). |
| L4  | Low      | Saved-view criteria (`project_id`, `criteria.project_id`, `criteria.label_id`) were validated as existing but not scoped to the user's accessible resources. No issue data leaked (listings stay filtered by `visibleTo`), but a view could reference another org's ids. | **Fixed** — `StoreSavedViewRequest` now constrains these to the user's `visibleTo` projects and their organizations' labels.  |
| I1  | Info     | `.env.example` ships `APP_DEBUG=true`.                                                                                                                                                                                                                                   | No change — prod verified `APP_DEBUG=false`, `APP_ENV=production`; `config/app.php` defaults `debug` to false.                |

### Accepted / not actioned

- Remaining `npm audit` advisories (`glob`, `minimatch`) are transitive **build-time dev dependencies**, not reachable at runtime. Clearing them requires `npm audit fix --force` (breaking major bumps of the Vite/build toolchain) for no runtime benefit, so they are deferred to a deliberate dependency-upgrade pass.

## Verified solid (checked, no issue)

- **Authorization / IDOR** — consistent policy model (resource → its organization → the user's role). Every state-changing web _and_ API route calls `authorize()`; nested route bindings (`{timeEntry}`, `{comment}`) verify `*_id === parent->id` before ownership checks. No IDOR found.
- **Cross-org isolation** — `LabelPolicy` / `CategoryPolicy` resolve the resource's own organization; `IssueTemplateController` additionally guards org membership.
- **Privilege escalation** — member-update role rule excludes `owner`; invitations grant only `member`/`guest`; `guardManageable` blocks editing yourself or an owner.
- **Injection** — all raw SQL (`whereRaw`, `orderByRaw`, `DB::select`) uses bound parameters; no shell/`eval`/dynamic includes; no file uploads (export-only).
- **XSS** — the single `v-html` sink (`MarkdownEditor.vue`) is sanitized with `DOMPurify.sanitize(marked.parse(...))`; no Blade `{!! !!}`.
- **CSRF / webhook** — web group default CSRF; API uses Sanctum tokens; the GitHub webhook verifies HMAC-SHA256 with `hash_equals` and fails closed when the secret is unset.
- **Auth** — passwordless OTP: `random_int` 6-digit, hashed at rest, 10-min TTL, 5-attempt cap per code plus a verify throttle; session regenerated on login (no fixation); enumeration-safe login/registration; invitation tokens are `Str::random(40)` stored as sha256 with expiry + email binding and replay protection.
- **Secrets** — `.env` gitignored and untracked; no hardcoded secrets in `app/` or `config/`; the `User` model hides `login_code_hash`/`remember_token` and limits `#[Fillable]` to `name`/`email`/`locale`.
- **Dependencies** — `composer audit` clean.
