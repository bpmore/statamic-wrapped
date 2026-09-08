# PROGRESS — Wrapped

**Spec:** `wrapped-build-spec.md` · **Package:** `bpmore/statamic-wrapped` · **Free**
**Rule:** do the next unfinished task only, then update this file, verify the build, and commit.

## Phase 1 — History source (do this first; everything depends on it)
- [x] Scaffold the addon package, tests, CI
- [x] `HistorySource` interface + resolver returning a confidence rating
- [x] `LogbookHistorySource` (Statamic Logbook, if installed)
- [x] `RevisionsHistorySource` (Pro only; note storage/statamic/revisions is git-ignored)
- [x] `EntryDataHistorySource` (date / updated_at / author)
- [x] `MtimeHistorySource` (last resort, low confidence)
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

### Task 4 — `RevisionsHistorySource` (done)
- Read against the installed `vendor/statamic/cms` v6.31.0 source, not from docs.
- **Only entries are revisable.** `Statamic\Revisions\Revisable` is used by `Entry` and nothing else in
  Statamic 6, so `itemType` is always `entry`. No assets, terms or globals from this source, ever.
- Layout is `<revisions dir>/collections/<collection>/<site>/<entry id>/<timestamp>.yaml`. The filename is
  the unix timestamp, so the date window and the site are decided from the path before a file is opened.
  The site segment is the entry's locale, so site filtering here is exact rather than best-effort.
- Directory comes from `Statamic\Facades\Revision::directory()` rather than rebuilding it from the three
  config keys that feed it (`STATAMIC_REVISIONS_PATH`, `statamic.revisions.path`, the stache store).
- Actions: `publish` -> Published, `unpublish` -> Updated, `revision` (the default) -> Updated.
  `working.yaml` is the unsaved working copy — a draft, not something that happened — and is skipped by name.
- **There is no Created event from revisions.** Statamic records no creation action, and the oldest revision
  is not a creation date (revisions can be switched on years into a site's life). Phase 2 must get creation
  dates from entry data, not from here.
- **Decision — rated Partial, not High.** The trail itself is real (who, what, when), which by the enum's
  own wording is High. But revisions are opt-in *per collection*, and nothing on disk says which collections
  had them on. A site with revisions on the blog only would report "40 entries published" for a site that
  published 400 — confidently wrong, which SPEC.md §1 says is worse than saying less. Reverse this only if
  the source is taught to report its own collection coverage.
  Phase 2 note: set card requirements knowing revisions is Partial, or cards revisions *can* answer well
  (most-revised page) will be omitted for no good reason.
- `earliestEvent()` reads every file rather than taking the lowest filename, so an unparseable or
  unrecognised revision cannot set the date the whole Wrapped is framed around. Same contract as Logbook.
- Availability is decided by the files, not by `Statamic::pro()`. A site that dropped to Solo still has the
  revisions it wrote.
- PHPStan rejected the defensive `is_array()`/`is_string()` guards around `YAML::parse()` and
  `Revision::directory()` as always-true — the facade docblocks declare those return types. Removed rather
  than suppressed; the try/catch around the calls is the real guard.
- Verified: `vendor/bin/pest` 55 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 5 — `EntryDataHistorySource` (done)
- At most **two events per entry**: it went live (`date`), and it was last touched (`updated_at`).
  Every edit before the last one is gone. That is the whole shape of this source.
- **No Created events, ever.** Statamic stores no creation timestamp. An entry's `date` is when it is
  published or displayed, not when someone first typed into it. **No Deleted events either** — a deleted
  entry is not there to read. Phase 2 must not expect creation dates from any source we have so far.
- Reads `$entry->get('updated_at')` **directly, never `lastModified()`**. `TracksLastModified::lastModified()`
  falls back to file mtime, which would launder a Low-confidence signal into a Partial-confidence source.
  This is the single most important line in the class.
- `updated_by` is the author of the Updated event; the blueprint's `author` field is the author of the
  Published event. An `author` field can hold several users — the first is taken, the rest dropped rather
  than double-counted.
- **Decision — a future-dated entry is not counted as published.** Statamic's default
  `futureDateBehavior: public` means `status()` returns 'published' for an entry dated next month. Wrapped
  reports what has happened, so `date->isFuture()` is excluded. Drafts are excluded via `status()`;
  'expired' is included, because it did go out and its date has merely passed.
- `isAvailable()` is "does any entry actually yield an event", not "do entries exist". A site with no dated
  collections and no `updated_at` has entries but no history in them, and must fall through to mtime.
  It short-circuits on the first entry that yields something.
- **Known leak, documented in the class:** on a dated collection, `Entry::date()` falls back to
  last-modified and from there to mtime for an entry with no date of its own. Rare (the date normally comes
  from the filename) but it is the one way mtime can reach this source's Partial rating.
- PHPStan: `Statamic\Contracts\Entries\Entry` and `...\QueryBuilder` are both **empty interfaces**, so
  they give the analyser nothing. Entries are type-hinted as the concrete `Statamic\Entries\Entry` (which
  the eloquent driver extends), and the query builder is narrowed with a real `instanceof` check that
  returns an empty result for a driver we cannot drive — which makes the source unavailable and lets the
  resolver fall through. Not a suppression; it is the correct runtime behaviour.
- Tests build real entries in a temporary content directory by repointing the `collections`/`entries`
  stache stores and calling `Stache::clear()`. Multisite tests need `Site::setSites([...])` first or
  `slug()` fatals on a null site.
- Verified: `vendor/bin/pest` 72 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 6 — `MtimeHistorySource` (done)
- **Updated events only, and that is the point.** An mtime says a file was written; it does not say anything
  was published. Emitting a Published event from one is exactly the "busiest week that's actually the date
  of the last deploy" SPEC.md §1 warns about. No author either — a filesystem does not record who.
  One event per entry: only the most recent write survives.
- Reads the mtime with `is_file()` / `filemtime()` rather than Statamic's `fileLastModified()`, which
  **returns `Carbon::now()` when the file does not exist**. That would turn "no file" into "edited today"
  and invent a busiest day. Same class of trap as `lastModified()` in task 5 — Statamic's convenience
  accessors around dates fall back rather than fail, and this addon must not.
- An entry with no file behind it (eloquent driver, or a file moved out from under the Stache) yields
  nothing, which correctly makes this source unavailable on an eloquent-driver site.
- **Refactor:** the entry-walking query moved to `src/History/Sources/Concerns/IteratesEntries.php`, shared
  with `EntryDataHistorySource` — second occurrence, so it was time. The `instanceof QueryBuilder` narrowing
  is the part that mattered to share; it is a subtle invariant that must not drift between the two.
  The small `string()` caster is still duplicated across all four sources. Left alone deliberately: eight
  obvious lines, and extracting it would touch every source for no real gain.
- **Test harness gotcha, cost real time:** `Collection::sites()` silently collapses to `[Site::default()]`
  unless `config('statamic.system.multisite')` is true — `Site::setSites([...])` alone is not enough, and
  `Site::hasMultiple()` returning true is misleading. Both multisite test helpers now set the config first.
- Verified: `vendor/bin/pest` 84 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.
