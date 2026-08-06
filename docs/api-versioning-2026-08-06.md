# API versioning and deprecation policy

**Date:** 2026-08-06
**Ticket:** TRACK-214
**Status:** decided

## The problem

The API has no version prefix and no deprecation policy. Until recently that cost nothing,
because the only consumer was the `create-linear-ticket` skill, which is edited by the same
person who edits this repo, on the same afternoon.

That is no longer true:

- **snag** is about to file issues here (TRACK-210, TRACK-211, TRACK-212 landed for it).
- **flare** follows snag.
- `/api/teams` has been carrying an undated alias "for existing API consumers" since the
  projects transition, with nothing recording when it may be removed.
- **TRACK-203 is titled "(breaking)"** and has not shipped. It moves the API off the four-value
  `IssueStatus` enum onto per-project workflow states. `status` is a field every single consumer
  reads.
- **TRACK-206 already broke `GET /api/issues`** in this cycle, changing a bare array to
  `{data, meta}`. That was safe only because no shipped consumer called that endpoint yet, which
  is luck rather than policy.

## Options considered

**1. URL versioning (`/api/v1`, `/api/v2`).** The obvious answer, and the wrong one here. It
duplicates routes and controllers for as long as both versions live, and someone has to
backport fixes into the old one. That price buys the ability to support consumers you cannot
deploy. Every consumer of this API is first-party, in `~/Projects`, deployable by one person.
We would be paying for isolation we do not need.

**2. Dual-serve one route behind a version header.** Cheaper than URL versioning but the same
shape of cost: two response builders per endpoint, and a default to pick. Defaulting to "newest"
breaks silent consumers; defaulting to "oldest" means new consumers quietly get stale shapes.

**3. Accept the break and coordinate the cutover.** Free, and it is what has happened so far.
It works right up until the moment a consumer is deployed on a schedule you do not control, or
you forget one. snag and flare will be running in production against real users.

## Decision

**No URL versioning. Breaking changes ship additive-first, behind a documented deprecation
window.**

Concretely, a change that would break a consumer is split into three steps:

1. **Add.** The new field or endpoint ships alongside the old one. Both are populated and
   correct. Nothing breaks.
2. **Migrate.** Consumers move to the new field. The old one is marked deprecated in the README
   and, where it is a whole endpoint, answers with a `Sunset` header (RFC 8594) carrying the date
   it will be removed.
3. **Remove.** After the sunset date, and only once every consumer in `~/Projects` is verified
   off it.

The window is **30 days** for anything a first-party app reads. That is not about the apps
needing 30 days; it is about the removal being a separate, deliberate act from the addition, so
"I will update snag right after" cannot quietly become never.

### What this means for TRACK-203

TRACK-203 must not replace `status` with workflow states. It should:

- add `workflow_state` (id, name, category) to the issue payloads,
- keep `status` populated, derived from the state's category,
- accept **both** on the status-change endpoint,
- mark `status` deprecated in the README with a sunset date.

Removing `status` becomes its own follow-up ticket, after the skill and snag are on
`workflow_state`. This turns the one genuinely dangerous PR in the epic into two safe ones, and
is the main practical output of this note.

### What this means for `/api/teams`

It gets a sunset date and a `Sunset` header, or it gets removed now. It has no known consumer:
the skill's `--team` flag maps to `--project` before the request is built. Prefer removing it,
under its own ticket, after grepping the estate for callers.

## Follow-ups filed

- **TRACK-216** re-scopes TRACK-203 to ship workflow states additively.
- **TRACK-217** removes `/api/teams` on or after its sunset date.

## Not adopted

- **A version header.** Reconsider only if a consumer outside `~/Projects` ever appears.
- **Retroactive versioning of the TRACK-206 change.** It shipped, its only caller is the skill,
  and TRACK-209 updates the skill against the new shape. Adding a compatibility layer for a
  consumer that does not exist would be pure cost.
