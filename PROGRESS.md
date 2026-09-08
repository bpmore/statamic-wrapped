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
- [x] Resolver picks best available and reports which; tests for each

## Phase 2 — Stats
- [x] `wrapped_snapshots` migration + model
- [x] One class per stat card, each declaring the history confidence it needs
- [x] Volume: entries published, total words, assets uploaded
- [x] Time: busiest month, busiest week, busiest day-of-week + hour, longest streak
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

### Task 7 — Resolver picks best available and reports which (done) — **Phase 1 complete**
- `ServiceProvider::register()` binds `HistorySourceResolver` as a **singleton** with all four sources.
  Singleton because deciding availability means reading the logbook table, the revisions directory, or
  every entry on the site — worth doing once per process, not once per stat card.
- **Registration order is load-bearing**, and lives in `ServiceProvider::$historySources`:
  logbook, revisions, entry_data, mtime. Ties are broken by this order, which is what puts Revisions ahead
  of entry data when both are Partial. Do not reorder without re-reading task 4's confidence note.
- `tests/Feature/History/ResolvesBestSourceTest.php` is the end-to-end proof: real fixtures for all four
  sources, each test adding a better one, asserting the resolver switches to it. It also reads events back
  through whatever it resolved, so the interface is exercised as the stat layer will use it.
- Resolution ladder confirmed by test: nothing -> null/Low · entry files only -> mtime/Low ·
  dated entries -> entry_data/Partial · revision files -> revisions/Partial · logbook table -> logbook/High.
- "Reports which" is `ResolvedHistory::handle()` (for the snapshot's `history_source` column),
  `name()` (for the UI line) and `confidence` (for the `confidence` column and card gating).
  Showing it on screen is Phase 3.

### Phase 1 summary — what the stat layer can rely on
- **No source produces a Created event.** Logbook does when the site has it, but the other three cannot,
  and the resolver may hand you any of them. Do not design a card around creation dates.
- **Only Logbook produces Deleted events.**
- **Only Logbook and Revisions have a real edit trail.** entry_data gives at most two events per entry;
  mtime gives exactly one and never an author.
- **Only Logbook covers assets and taxonomy terms.** The other three are entries-only.
- Confidence available in practice: High (logbook), Partial (revisions, entry_data), Low (mtime).

### Task 8 — `wrapped_snapshots` migration + model (done) — first of phase 2
- `database/migrations/2026_09_08_000000_create_wrapped_snapshots_table.php`, **loaded** by the service
  provider rather than published, so `php artisan migrate` in the host site picks it up with no install step.
- Columns exactly as SPEC.md §4: site, period, period_key, history_source, confidence, stats(json),
  generated_at, generated_by. Default connection; no separate database.
- **Unique on (site, period, period_key).** Regenerating replaces the row rather than adding a second one,
  which is what `--force` will rely on. A duplicate insert throws `UniqueConstraintViolationException`.
- `history_source` and `confidence` are **stored on the row, not derived on read.** They describe the run
  that made this snapshot. A site that installs Logbook in March does not retroactively improve the Wrapped
  it generated in January, and the UI must not claim otherwise. `Snapshot::supports()` mirrors
  `ResolvedHistory::supports()` so a card can be gated from a stored snapshot as well as a live resolve.
- New `Snapshots\Period` enum (year|quarter) with `key(int $year, ?int $quarter)` — the **only** place the
  `'2026'` / `'2026-Q3'` format is written, because it is a stored value that must stay stable across
  releases. Throws on a quarterly key with no usable quarter rather than writing a malformed key.
- `$timestamps = false`: `generated_at` is the only time the row has, and regenerating updates it, so a
  created_at/updated_at pair would say nothing new.
- Test env note: testbench's default connection is sqlite `:memory:`, so `RefreshDatabase` runs the
  migration with no extra setup.
- Verified: `vendor/bin/pest` 107 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 9 — Stat card shape: interface, context, registry (done)
- `Stats\StatCard`: `handle()` (the stable key in the snapshot's stats JSON — stored data, do not rename
  after release), `requires(): Confidence`, `compute(StatContext): ?array`.
- **`compute()` returning null means "computed, and there is no story here"** — a year with no entries, a
  site with one collection. It does **not** mean "not allowed to compute". Confidence gating happens before
  `compute()` is called, and that is a later task ("Cards omitted when confidence is too low").
- `Stats\StatContext` carries the resolved history, the window and the site, and **reads each window from
  the source exactly once**. A dozen cards each asking for the same window would read the logbook table a
  dozen times; on a large site that is the difference between a job and a timeout. `events($from, $to)`
  lets a card ask for another window (last year's number alongside this year's) and caches that separately.
- The context deliberately does **not** expose entries, assets or terms. Several cards will need entry
  content (word counts, taxonomy terms) — let each fetch what it needs rather than guess at a shared API
  before any card exists.
- `Stats\StatCardRegistry` **throws on a duplicate handle.** Two cards sharing one would silently overwrite
  each other in the stats JSON, which is a data bug that would never surface as an error.
- Bound as a singleton in `ServiceProvider::$statCards`, currently empty — unlike the history sources in
  task 2, which were left unbound. The difference: cards land in the very next task, and the list property
  is the extension point the next task needs.
- `tests/Fixtures/FakeHistorySource` gained a public `$reads` counter so the context's caching is proved
  rather than assumed. `tests/Fixtures/FakeStatCard` stands in for a real card.
- Gotcha: a Pest helper named `event()` collides with Laravel's global helper and fatals. Named `anEvent()`.
- Verified: `vendor/bin/pest` 119 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 10 — Volume cards (done)
Three cards in `src/Stats/Cards/`, registered in `ServiceProvider::$statCards`.

**`entries_published`** — requires Partial.
- Counts **distinct entries, not events.** An entry unpublished and put back out was published once as far
  as a reader is concerned; counting events would double it.
- `previous` is **null, not 0**, when the source has no history before the window. A site three months old
  did not publish nothing last year, it did not exist. `StatContext::hasHistoryBefore()` decides this.
  A genuine quiet year still reports 0.
- Returns null (no card) when the count is 0. "You published 0 entries" is a scolding — SPEC.md §3.

**`total_words`** — requires Partial.
- **Counted from entries as they stand today, not as they stood when published.** An entry published in
  January and rewritten in June counts at its June length. No source can give us the January one.
  Entries since deleted cannot be counted at all, which is why the card reports `entries` alongside
  `words` — that number is not always the published count.
- Word counting lives in `Stats\Support\WordCounter` so the heuristic can be argued with in one place.
  `readability-core` does not exist (checked on packagist), so this is written here; SPEC.md §8 says replace
  it rather than keep both if that package ever ships.
- Two rules keep it honest: only fieldtypes the blueprint says are prose are counted
  (text/textarea/markdown/bard/replicator/grid/group — so a select value or taxonomy handle is not
  "words someone wrote"), and inside those, structural keys are skipped. **The structural key list exists
  because of Bard**: every ProseMirror node carries `type`, and counting "paragraph" as a word would
  badly inflate a long article.

**`assets_uploaded`** — requires **High**, so it only appears on a site running Logbook.
- Nothing else records an upload: revisions and entry data are entries-only, and an asset's file mtime is
  the date of the last deploy on most sites. Counting uploads from a directory listing would give a
  confident number that is simply wrong, so the card is left off instead. This is the clearest example so
  far of "omit rather than guess", and it gives sites a real reason to install Logbook.
- Reports `count`, `bytes` and `weighed`. An upload since deleted still happened, but no longer has a size,
  so `weighed` says how many of the count the byte total actually covers.

**Also in this task:** `StatContext` gained `period`, `previousWindow()` and `hasHistoryBefore()` — the
"last year's number alongside" requirement needs them, and shifting by the period (not by the window's
length) keeps leap years and uneven quarters landing on the right dates.

**Test-harness notes:** shared card helpers now live in `tests/Pest.php`, because Pest files share one
global namespace and a helper defined in two files is a fatal redeclaration. Names are prefixed
(`cardContext`, `wasPublished`, `wrappedEvent`) — `context()` and `event()` are already Laravel globals and
both fataled before being renamed. A Pest dataset entry of `[]` spreads to zero arguments; wrap every case
in an array.

- Verified: `vendor/bin/pest` 156 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 11 — Time cards (done)
Four cards, all requiring Partial, all built on publications rather than edits.

**`busiest_month`** — stores `year` + `month` as **numbers, never a name**. A stored "September" would be
wrong on a French site and impossible to change later; the CP screen translates it. Ties go to the earlier
month (countBy fills in oldest-first order and `sortDesc` is stable), which is deterministic and documented.

**`busiest_week`** — keyed on the **Monday the week starts**, not the ISO number alone. ISO week 1 can fall
in either of two calendar years, so a bare "week 1" is ambiguous in stored data. The number is reported too
because that is what people say.

**`busiest_time`** — the delightful one from SPEC.md §2.
- **The important decision: events landing exactly on 00:00:00 are ignored.** A dated collection stores a
  date with no time, so `Entry::date()` returns the start of the day. Counting those would report that
  every site on earth publishes at midnight — a card that is confidently, uniformly wrong. Treating exact
  midnight as "no time recorded" costs the occasional genuine midnight publication and saves the card.
  `from` reports how many publications actually carried a time, so the UI need not imply more.
- Day and hour are counted **independently, not as a pair**. Over a year one day-and-hour combination is
  thin enough to be noise, while "Tuesdays" and "the afternoon" are each well supported — and it still
  reads as one sentence.
- Day is ISO (1 = Monday), hour is 0-23 local.

**`longest_streak`** — consecutive calendar days with something published. Three entries on one day is a
busy day, not a three-day streak. **Only shown from two days upward**: a streak of one is every site that
has ever published anything.

**Refactor:** `Stats\Concerns\ReadsPublishedEntries` now owns the "entries only, Published only" filter and
`firstPublications()`, which keeps the **earliest** event per entry so a republished entry counts once and
lands in the month it first appeared. `EntriesPublishedCard` and `TotalWordsCard` moved onto it.

**Process note:** one test in this task was written with expectations I was unsure of and marked `skip`.
That is dead weight, not caution — it was rewritten as a case that actually discriminates (without
deduplication September wins with three events; with it, March wins with two) and the skip removed.
No skipped tests in this suite.

- Verified: `vendor/bin/pest` 177 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.
