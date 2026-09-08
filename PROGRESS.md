# PROGRESS — Wrapped

**Spec:** `wrapped-build-spec.md` · **Package:** `bpmore/statamic-wrapped` · **Free**
**Rule:** do the next unfinished task only, then update this file, verify the build, and commit.

## Phase 1 — History source (do this first; everything depends on it)
- [x] Scaffold the addon package, tests, CI
- [x] `HistorySource` interface + resolver returning a confidence rating
- [x] `LogbookHistorySource` (Statamic Logbook, if installed)
- [ ] `RevisionsHistorySource` (Pro only; note storage/statamic/revisions is git-ignored)
- [ ] `EntryDataHistorySource` (date / updated_at / author)
- [ ] `MtimeHistorySource` (last resort, low confidence)
- [ ] Resolver picks best available and reports which; tests for each

## Phase 2 — Stats
- [ ] `wrapped_snapshots` migration + model
- [ ] One class per stat card, each declaring the history confidence it needs
- [ ] Volume: entries published, total words, assets uploaded
- [ ] Time: busiest month, busiest week, busiest day-of-week + hour, longest streak
- [ ] Superlatives: longest entry, most-revised page, fastest turnaround, top taxonomy term, longest-untouched page
- [ ] Collections: fastest growing, went quiet
- [ ] People stats — team by default, individual leaderboard opt-in, never rank from the bottom
- [ ] Cards omitted (not guessed) when confidence is too low
- [ ] `php please wrapped:generate` with `--year` / `--quarter` / `--site` / `--force`

## Phase 3 — Output
- [ ] CP screen — the canonical, accessible version
- [ ] Confidence notice when history data is limited
- [ ] Dashboard widget + December nudge
- [ ] PNG card export via headless Chrome
- [ ] Suggested alt text generated with each PNG
- [ ] Confetti on first view, once
- [ ] `view wrapped` permission; people-stats behind a narrower gate

## Phase 4 — Ship
- [ ] README, screenshots, marketplace listing copy
- [ ] Test on a large fixture site and on a 3-month-old site (first-year framing)
- [ ] Tag 1.0 — target late November 2026

## Notes
<!-- Record surprises, decisions and blockers here. If a task is wrong or blocked, write why and stop. -->

### Task 1 — Scaffold the addon package, tests, CI (done)
- Namespace is `Bpmore\Wrapped\` over `src/`; tests are `Bpmore\Wrapped\Tests\` over `tests/`.
- Resolved versions: `statamic/cms` v5.74.4, `orchestra/testbench` v10 (Laravel 12), Pest 3.8, PHPStan 2 via larastan 3.
- `composer.lock` is git-ignored (correct for a library), so CI runs `composer update`, not `composer install`.
- PHPStan starts at level 4. Raise it later if it stays quiet; don't raise it mid-feature.
- **Deferred:** no `package.json` / Vite setup yet. No CP assets exist until Phase 3, and an empty build step
  would just be a thing that can break. Add it with the CP screen. `npm install && npm run build` in CLAUDE.md
  will not work until then — that is expected, not a broken environment.
- Verified: `vendor/bin/pest` 2 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.
- **Corrected after task 1:** scaffold resolved `statamic/cms` to v5.74.4. Bumped the constraint to `^6.0`; now on v6.31.0. All specs target Statamic 6.

### Task 2 — `HistorySource` interface + resolver (done)
- Everything lives in `src/History/`: `Confidence`, `HistoryEventType`, `HistoryEvent`, `HistorySource`,
  `ResolvedHistory`, `HistorySourceResolver`.
- `Confidence` and `HistoryEventType` were written but uncommitted before this task; they are covered by tests now.
- Sources flatten whatever they know into a stream of `HistoryEvent`, so the stat layer never branches on source.
  `author` and `site` are nullable — a source that does not know them must leave them null rather than guess.
- The interface is `events(from, to, ?site)` plus `earliestEvent(?site)`. `earliestEvent` exists for the
  first-year framing in SPEC.md §1, not as a convenience.
- Resolver takes highest `Confidence::rank()` among available sources; ties keep the first registered.
  So register best-first (logbook, revisions, entry_data, mtime) in task 6.
- `resolve()` returns null when nothing is available; `confidence()` reports `Low` in that case rather than
  throwing, because "we know almost nothing" is the honest answer, not an error.
- **Not done on purpose:** the resolver is not bound in the container yet. There are no real sources to register
  until tasks 3-6, and an empty binding would be a thing that can rot. Bind it in task 6.
- `tests/Fixtures/FakeHistorySource.php` lets the resolver be tested without a real source.
- Verified: `vendor/bin/pest` 14 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 3 — `LogbookHistorySource` (done)
- Sources live in `src/History/Sources/`. Read against Logbook v2.2.0 source, not from memory.
- Table is `logbook_audit_logs` on its **own** connection named `logbook`, built from
  `config('logbook.db.connection')`. Logbook's `DbConnectionResolver` does exactly that; we replicate the
  three lines rather than depend on their class, so this addon has no hard dependency on Logbook.
- Actions are `statamic.<subject>.<operation>`. Mapped operations: created/uploaded -> Created,
  published -> Published, updated/saved/replaced/unpublished -> Updated, deleted -> Deleted.
  Everything else (logins, two-factor, impersonation) is audit signal, not content history, and is dropped.
- `subject_type` is passed straight through as `itemType` ('entry', 'asset', 'term', 'collection'...).
  The source stays dumb; the stat layer filters.
- Site comes from `meta->site`, which Logbook only sets for site-aware subjects. Rows without it get
  `site = null` and are excluded when a site filter is given. That is the honest answer, not a bug.
- **Availability is decided by the table, not by `class_exists`.** A site that removed Logbook still has
  the history it recorded, and that history is no less true.
- **Verified on SQLite only:** the `where('meta->site', ...)` JSON filter. Laravel generates the MySQL and
  Postgres equivalents but no test covers them here.
- The test file rebuilds Logbook's schema by hand (it is not a dependency). If Logbook's schema drifts,
  `tests/Feature/History/LogbookHistorySourceTest.php` is the file that has to notice.
- **Deferred to Phase 4:** a `suggest` entry for `emran-alhaddad/statamic-logbook` in `composer.json`.
- Still not bound in the container. Registration is task 6, as decided in task 2.
- Verified: `vendor/bin/pest` 37 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.
