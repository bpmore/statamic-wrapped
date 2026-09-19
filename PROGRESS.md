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
- [x] Superlatives: longest entry, most-revised page, fastest turnaround, top taxonomy term, longest-untouched page
- [x] Collections: fastest growing, went quiet
- [x] People stats — team by default, individual leaderboard opt-in, never rank from the bottom
- [x] Cards omitted (not guessed) when confidence is too low
- [x] `php please wrapped:generate` with `--year` / `--quarter` / `--site` / `--force`

## Phase 3 — Output
- [x] CP screen — the canonical, accessible version
- [x] Confidence notice when history data is limited
- [x] Dashboard widget + December nudge
- [x] PNG card export via headless Chrome
- [x] Suggested alt text generated with each PNG
- [x] Confetti on first view, once
- [x] `view wrapped` permission; people-stats behind a narrower gate

## Phase 4 — Ship
- [x] README, screenshots, marketplace listing copy
- [x] Test on a large fixture site and on a 3-month-old site (first-year framing)
- [x] Tag 1.0 — target late November 2026 (tagged 13 September 2026)

## Phase 5 — Shareable video (post-1.0)
- [x] Portrait frames (1080x1920): intro, one per card, outro — rendered by the existing image renderer
- [x] `VideoRenderer` interface + `FfmpegRenderer`: frames + crossfade + audio -> MP4, detected like Chrome
- [x] Soundtracks: bundled tracks in `resources/audio/`, site-supplied tracks via config, a registry
- [x] `WrappedVideo`: the editor chooses the cards; a default of up to eight, people unticked; intro/outro; server validates
- [x] CP: card checkboxes with a running-time readout, track picker with preview, "Download video", the video's description
- [x] Tappable story: the same frames as live HTML in the CP, advanced by tap, click or key, music optional — the accessible, self-paced version
- [x] Theme: a site-chosen background, optional accent and logo; text derived for AA contrast; one look across images, video and story
- [x] README + release 1.1 (tagged 14 September 2026)

## Post-1.1
- [x] Video rendering: one browser launch for every frame (30s -> 8s)
- [x] Decision: a public, shareable story URL — **yes**, opt-in and off by default (news sites want to post stats)
- [x] Decision: monthly periods — **yes** (large, high-volume sites)

## Phase 7 — From the control panel (1.3)
- [x] Nav icon: `gift-present-surprise`, one the control panel ships; `sparkles` was not, and rendered blank. A test that the name resolves to a file.
- [x] Build from the screen: `SnapshotBuilder` shared by the command and a new `POST wrapped/generate`; a `generate wrapped` permission; a Build form (period, year, quarter/month) on the Wrapped screen, in the empty state and under an existing Wrapped
- [x] Settings screen at Addons → Wrapped: background, accent, logo (assets picker, container filled in at boot); saved values win over config, an emptied field falls back to it

## Phase 6 — Monthly, and a shareable link
- [x] `Period::Month`: `2026-09` keys, month windows, `--month` on generate, human labels for every period
- [x] Period picker on the Wrapped screen: choose which snapshot to view; story, images and video follow it
- [x] Share links: off by default; publish one snapshot as a frozen copy behind an unguessable, revocable token; people cards opt-in per link
- [x] The public story page: no CP chrome, noindex, the site's theme, same tap-through behaviour
- [x] README + release 1.2

## Phase 8 — A voice and a song for the shared story (1.5)
Both tasks touch the share form. Do the voice first: it is small and has no outside dependency.
- [x] Voice: a `voice` choice on the share form, `we` (default for share links) or `you` (the control panel keeps `you`); every card body, the intro and the closing line get a `we` wording in the lang files; the choice is frozen into the share row's card text like everything else, with `voice` stored so the intro and outro render to match; a test walks every `we` string and fails on any "you" or "your" left behind
- [x] YouTube, paste a link: a "Music (YouTube link)" field on the share form; the video ID is parsed server-side from any youtube.com / youtu.be shape and stored on the share row; title and thumbnail fetched with YouTube's keyless oEmbed endpoint and shown for confirmation (a failed lookup is a validation error, not a stored link)
- [ ] YouTube, the player: on the public story only, a "Play music" button on the intro; tapping it loads the IFrame player from `youtube-nocookie.com` as a pinned tile of at least 200×200 px, looping; the cards resize so nothing ever covers the tile; no autoplay; a "YouTube" line with a link to https://www.youtube.com/t/terms under the tile
- [ ] Wording and docs: under the field, "Plays on the shared web page only. The downloadable video keeps its own soundtrack."; README notes the site's privacy policy should mention Google once a link is set; CHANGELOG
- [ ] Look at it on a phone: screenshot the public story at 390×844 with the tile open and confirm the cards still read and nothing overlaps; then release 1.5

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

### Task 12 — Superlative cards (done)
Five cards. New `Stats\Concerns\DescribesEntries` handles looking an entry up and coping with it having
been deleted since — a superlative names one page, so every card here needs that.

**`longest_entry`** — Partial. Same caveat as total words: measured as the entry reads today.

**`most_revised`** — Partial, and **this resolves the task 4 question about revisions being rated Partial.**
The card guards on the *data*, not the rating: entry data and mtime can only ever emit one Updated event
per entry, so anything below two edits returns nothing. The card therefore disappears on sources that
cannot count edits and works properly on Revisions (Partial) as well as Logbook (High). Requiring High
would have dropped it on the sites best able to answer it. **Publishing is not counted as editing** — it is
a status change with cards of its own. Falls through to the runner-up if the winner has been deleted.

**`fastest_turnaround`** — **High**, so Logbook only. It is the sole source that records a creation.
Only entries both created *and* published inside the period count: something started last December and
published in January had a turnaround, but not one this year can claim. Stored in **seconds** — "42 minutes"
would not translate. A publication timestamped before its own creation is a clock problem, not a fast
writer, and is dropped.

**`top_taxonomy_term`** — Partial. Read through the blueprint: fields of type `terms`, whose `taxonomies`
config says which taxonomy a bare slug belongs to. Statamic stores **bare slugs for a single-taxonomy field
and `taxonomy::slug` for a multi-taxonomy one**, so both shapes are handled; a bare slug on a
multi-taxonomy field is ambiguous and is dropped rather than attributed to the wrong taxonomy.
Stores the handle and slug, **never the term's title** — titles are localised and editable.
Needs at least two uses: one is not what a period was "mostly about".

**`longest_untouched`** — Partial. Warm, never scolding (SPEC.md §3): this is the page nobody has *needed*
to change. Only counts if untouched for the whole period. **Known limit, documented in the class:** an
entry the source has never recorded is invisible here, and is very likely the most untouched page of all.
A site that installed Logbook last year cannot see the page last edited in 2019. "We have no record of it"
is not "nobody touched it", so it is left out rather than claimed.

**Pattern worth keeping:** three of these cards guard on a minimum (2 edits, 2 term uses, untouched for the
whole period) rather than on confidence alone. A superlative that is barely a superlative is not a card.

- Verified: `vendor/bin/pest` 203 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 13 — Collections cards (done)
Both read the collection from `HistoryEvent->meta['collection']`, which **all four sources set**, so these
work whichever source was resolved. Events arriving without one are dropped: an uncounted entry beats a
collection named "".

**`fastest_growing_collection`** — Partial. **"Most" is a count, not a ratio.** A ratio would let a
collection going from one entry to three beat one going from fifty to a hundred and twenty, which is not
what anyone means by "grew the most". `previous` rides along so the screen can say "up from 31", and is
null (not 0) for a site younger than one period. Needs **at least two collections** to have published —
on a single-collection site the answer was never in doubt, and that is not a card.

**`collection_went_quiet`** — Partial. Busy last period, completely silent this one. Two guards keep it
from being noise: it must have gone fully quiet rather than merely slowed down, and it must have published
**at least twice** before — one entry last year and none this year is a collection that barely started, not
one that went quiet. Picks the collection with the most to lose. A site with no history before the period
cannot produce this card, which is correct: nothing can go quiet before it has spoken.
Wording carries the warmth (SPEC.md §3) and belongs to the CP screen; the card supplies only the fact.

**Shared:** `ReadsPublishedEntries::publicationsByCollection()` — counts per collection, biggest first.

- Verified: `vendor/bin/pest` 217 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 14 — People stats (done)
The socially loaded one. SPEC.md §3 is the spec here, not a nice-to-have.

**New `config/wrapped.php`**, which is where the reasoning lives because a site operator reads the config,
not the source. `wrapped.people.enabled` (team stats, **on**) and `wrapped.people.individuals` (stats that
name one person, **off**). Both have env overrides.

**`people`** — team card, on by default, requires Partial. "42 entries from 7 people." Names nobody, so it
cannot single anyone out. **Needs two people**: on a one-person site a "team" card identifies that person
by arithmetic. Reports `unattributed` separately, because the attributable count is not always the
published total.

**`top_contributor`** — off until the site opts in. Returns **exactly one person and their count — never a
ranked list**. A full leaderboard invites reading down it, which is the whole problem. Needs two
contributors, since with one there is nothing to be top of. **Stores the user id, never the name**, so a
snapshot does not freeze somebody's name into the database.

**Never ranks from the bottom.** There is no "least active author" card and there will not be one. There is
a test asserting no registered card handle contains `least`, `quietest`, `leaderboard` or `ranking` — a
guard on the product decision rather than on an implementation detail, so a future addition trips it.

`Stats\Concerns\CountsPeople` holds the two permission checks and `byAuthor()`. Events with no author are
dropped rather than gathered into an "unknown" bucket: an unknown bucket can *win*, and "your top
contributor is nobody" is not a card.

**Packaging note:** added `extra.statamic.slug: wrapped` to composer.json. Statamic derives the addon config
filename and config key from the slug (`Addons\Manifest` reads `extra.statamic.slug`), so without it the
config would have to live at `config/statamic-wrapped.php` and be read as
`config('statamic-wrapped.people.enabled')`. Verified by test: `config('wrapped.people.enabled')` is true.

- Verified: `vendor/bin/pest` 232 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 15 — Cards omitted when confidence is too low (done)
`Stats\CardRunner` is where SPEC.md §1's rule is actually enforced. A card whose `requires()` exceeds the
resolved confidence is **never computed at all** — not computed and hidden, not computed with a caveat.
There is no code path where a card's numbers exist without the history to back them. A test asserts the
card's `compute()` was never called, not merely that its output was dropped.

`Stats\StatsResult` carries four things, and the split matters:
- `stats` — handle => data.
- `omitted` — never computed, the history is not good enough. **This is what the Phase 3 "based on limited
  history data" notice hangs on** (`wasLimited()`).
- `empty` — computed, nothing worth showing. A quiet year, not a limitation. These look identical on a
  finished Wrapped, and a reader has to be able to tell which happened.
- `failed` — threw. Logged at warning with the card handle, and the rest of the Wrapped carries on. One
  awkward entry must not cost someone their whole year, but this is recorded and logged rather than
  swallowed so it surfaces as a bug report instead of a mysteriously missing card.

**Product consequence, verified by test and worth knowing before Phase 3: on an mtime-only site every
single card is omitted and the Wrapped is empty.** Every card this addon ships requires Partial or better,
because nothing honest can be said from file timestamps that a deploy rewrites. That is the spec's position
("nearly worthless on a deployed site"), but the CP screen will need to handle a completely empty Wrapped
gracefully — most likely as "we can't tell you much yet, here is why" rather than a blank page.
Flag for the CP screen task.

Confidence ladder confirmed end to end over the real registry:
- Low → everything omitted.
- Partial → only `assets_uploaded` and `fastest_turnaround` omitted (the two that need Logbook).
- High → nothing omitted, nothing failed.

- Verified: `vendor/bin/pest` 243 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 16 — `wrapped:generate` (done) — **Phase 2 complete**
`src/Console/Commands/GenerateWrapped.php`, registered via `ServiceProvider::$commands`.
`--year` (defaults to the current year, which is what a 1 December run wants), `--quarter` (1-4),
`--site` (defaults to **every** site, one snapshot each — SPEC.md §8), `--force`.

- Invalid input exits `INVALID` and writes nothing; no readable history exits `FAILURE`. A scheduled run
  that quietly succeeds while producing nothing would be worse than a non-zero exit.
- `--force` uses `updateOrCreate` against the unique key, so a rebuild **replaces** the row rather than
  adding a second one. Without it, an existing snapshot is left completely alone — `generated_at` is
  asserted unchanged, not just the row count.
- `Period` gained `window()` alongside `key()`, so the period owns both its stored key format and its
  bounds. `assertQuarter()` throws rather than defaulting: a quarterly period with no quarter would write a
  malformed key or silently build the wrong three months.
- `generated_by` uses `getAuthIdentifier()`, not `id()` — Statamic's user *contract* does not declare
  `id()`, it inherits the identifier from Laravel's `Authenticatable`. Null for an unattended run.
- **An mtime-grade site still gets a row written**, with empty stats. The CP needs it to explain why.

**Real bug found and fixed while writing this task's tests.** `LogbookHistorySource` filtered by
`meta->site`, and `Statamic\Assets\Asset` **has no `site()` method** — asset containers are global in
Statamic, not per site — so Logbook never records a site for an upload. Every asset event was being dropped
by the site filter, which would have left `assets_uploaded` permanently empty on every site, silently.
Fixed: a row that names a site belongs to that site, a row that names none belongs to everybody.
`FakeHistorySource` now mirrors that so fixtures cannot drift from real behaviour again.
This only surfaced because the command is the first thing that passes a concrete site handle all the way
through — worth remembering that the per-card tests could not have caught it.

**Not done, and not in this task's line:**
- SPEC.md §4 also wants the work in a **queued job** and a **1 December schedule**. The command does the
  work synchronously. Neither is in the task list; add them deliberately rather than as a side effect.
- **The addon is not installed in the host site.** `~/Herd/statamic-dev/composer.json` has the path
  repository for `../statamic-wrapped` but never requires the package, so `php please wrapped:generate`
  does not exist there yet. Left alone: it is a change to another repo and outside this task. The command is
  fully covered in-repo through Testbench, which is the addon's real test environment.

- Verified: `vendor/bin/pest` 259 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 17 — CP screen (done) — first of phase 3
**Statamic 6's control panel is Inertia + Vue. There are no Blade CP views any more** — `resources/views/cp`
does not exist in the package. An addon screen is: a Vue page component registered client-side, and
`Inertia::render()` server-side with a matching name.

The wiring, confirmed against the Statamic 6 docs rather than guessed:
- `package.json` depends on `@statamic/cms` via `file:./vendor/statamic/cms/resources/dist-package`.
- `vite.config.js` uses `laravel-vite-plugin` plus `@statamic/cms/vite-plugin`. That plugin **externalises
  Vue to the copy the CP already loaded** — bundling a second one would give the page a different
  reactivity system to the CP around it.
- `resources/js/cp.js` does `Statamic.booting(() => Statamic.$inertia.register('wrapped::Wrapped', Wrapped))`.
  **The name must match `Inertia::render('wrapped::Wrapped')` exactly.**
- `ServiceProvider::$vite` (plain list form — the parent declares `list<string>`, and the array form only
  exists to move the build directory, not worth fighting the type for), so the build goes to `public/build`.
  Assets publish under the addon slug: `php artisan vendor:publish --tag=wrapped`.
- `routes/cp.php` is auto-detected. Nav item via `Nav::extend()`.

**The presenter is the design decision.** Cards store numbers and handles — a month is `7`, a term is a
slug, a contributor is a user id. `Stats\CardPresenter` turns those back into a heading and a sentence at
display time, so the wording lives in a normal Laravel translation file (`resources/lang/en/cards.php`) and
the screen is plain text a screen reader reads straight through. A card with no wording is **left out
rather than rendered as its own translation key**.

Two states the screen has to handle, both tested:
- No snapshot at all — "run `php please wrapped:generate`". Not an error.
- A snapshot whose cards were all omitted — the mtime case flagged in task 15. It explains what it was
  built from instead of showing a blank page. The dedicated confidence notice is the next task.

**Two mistakes worth remembering:**
1. The `$vite` property edit **silently did nothing** — my scripted replace matched a docblock Pint had
   already rewritten (`\Illuminate\Console\Command` -> `Command`). Nothing failed; the property simply was
   not there, and assets did not publish. Caught only by checking `ServiceProvider::$publishGroups` in the
   host site. **Assert on every scripted edit.**
2. `loadTranslationsFrom()` in `bootAddon()` was redundant — Statamic's own `bootTranslations()` already
   picks up `resources/lang` and registers the `wrapped-translations` publish group. Removed.

**Verified in the host site**, not just in tests: route registers as `statamic.cp.wrapped.index`, and
`vendor:publish --tag=wrapped` copies the built manifest into `public/vendor/statamic-wrapped/build`.

- Verified: `vendor/bin/pest` 277 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean.

### Task 18 — Confidence notice (done)
`Stats\ConfidenceNotice`. A Wrapped now **never appears without saying what it was built from**, and the
wording escalates only as far as the truth requires — SPEC.md §1.

Three levels, in `resources/lang/en/messages.php`:
- **complete** — "Built from Statamic Logbook." A quiet line, nothing more.
- **limited** — names the source and says how many cards were left out *rather than guessed at*.
  Pluralised through `trans_choice`, so it reads "One card", never "1 cards".
- **empty** — "There is not enough history here to say anything yet... and most deploys rewrite those."
  This is the mtime case flagged back in task 15, now with a real explanation instead of a blank page.

**The omitted count is derived, not stored.** The snapshot records the confidence it had and every card
declares the confidence it needs, so the answer falls out of the two. That avoids a schema change, stays
correct as cards are added, and answers the more useful question anyway: what would this site get if its
history improved.

**Sources are named, not handled.** "mtime" means nothing to a reader; "file modification times" does.
An unrecognised handle falls back to "an unknown source" rather than leaking the handle.

**The suggestion is offered once and never nags.** Logbook is suggested only when the history is limited
*and* the site is not already running Logbook. A complete Wrapped gets no upsell.

On the screen it is a `<p>`, not an alert. Thin history is not an error and not the reader's fault.

**Props moved:** `confidence` and `historySource` are now under `snapshot.history` alongside the notice.
Two task 17 tests asserted the old paths and were updated.

- Verified: `vendor/bin/pest` 292 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished to the host site.

### Task 19 — Dashboard widget and December nudge (done)
`src/Widgets/WrappedWidget.php`, registered via `ServiceProvider::$widgets`. A Statamic widget returns
`VueComponent::render('name', $props)`, so the Vue side is an **ordinary component** registered with
`Statamic.$components.register()` — not `$inertia.register()`, which is only for pages.

- **Shows nothing at all when there is no Wrapped.** `component()` returns null rather than advertising
  itself on a dashboard with nothing to show.
- **It only calls itself a nudge in December, for the year that is ending** — a Year-period snapshot whose
  key is the current year, in month 12. A quarter never nudges, and neither does last year's Wrapped.
  The rest of the year it is a quiet link to something that already exists.
- The headline leads with `entries_published` when present, because that is the fact people came for,
  falling back to the first card and then to the **confidence notice**. A thin site gets "there is not
  enough history here" rather than an empty line.

**Dismissal is `localStorage`, keyed by period.** A per-browser convenience does not deserve a table, an
endpoint and per-user state to get wrong, and keying by period means next year's Wrapped nudges again on
its own. Reads and writes are wrapped in try/catch — private windows and blocked site data throw. Only the
December nudge is dismissible; the quiet link is not in anybody's way.

**Not done here:** the widget does not check a `view wrapped` permission yet — that is the last task in
this phase and will need to cover the screen, the widget and the nav item together.

- Verified: `vendor/bin/pest` 302 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished to the host site.

### Task 20 — PNG card export (done)
`Export\ImageRenderer` (interface), `Export\ChromeRenderer`, `Export\CardImages`, two Blade templates,
and two CP download routes.

**Chrome is driven directly — no Puppeteer, no Browsershot.** Those would put Node and a
several-hundred-megabyte browser download between a free addon and a picture of a number, and card layout
is only HTML and CSS. `--headless=new --screenshot` against a `file://` URL is the whole mechanism.
The binary comes from `wrapped.images.chrome`, else the usual install locations are checked.
**A missing browser hides the download; it never fails a request** — the screen is the real version.

**Rendered at `--force-device-scale-factor=2`**, so a 1200x630 card comes out 2400x1260 and stays sharp
when a social platform re-encodes it. Summary is 1200x1500.

**No hosted URL, ever.** The routes return `Content-Disposition: attachment`. Nothing is written to a
public directory and no link is minted: a URL leaking a team's internal publishing stats is a support
problem and a privacy problem for a free addon — SPEC.md §5.

**The templates are deliberately self-contained**: inline CSS, system fonts, no network. The renderer loads
them from `file://` with no internet, so anything fetched would simply be missing from the picture. Tests
assert the HTML contains no `http://`, no `https://` and no `<script`, and that card text is escaped rather
than becoming markup.

**Verified for real, not just mocked.** One test runs actual Chrome and asserts PNG bytes (skipped when no
browser is present). Rendered against the seeded dev site and inspected the output image: it reads
"WHEN YOU PUBLISH / You publish most on Tuesday, and most often around 2 pm. / 2026 · default".

- Verified: `vendor/bin/pest` 318 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished · both routes registered in the host site.

### Task 21 — Suggested alt text (done)
`Export\AltText`, with the wording in `resources/lang/en/messages.php` under `alt.*`.

**The alt text is the card's own words**, plus where they came from:
"When you publish: You publish most on Tuesday, and most often around 2 pm. From the 2026 Wrapped for
default." A card image is text made of pixels, and there is nothing in it a sighted reader gets that a
described reader should not.

**Deliberately not "an image of a card showing...".** Alt text carries the information, not a description
of the medium — telling somebody they are looking at a picture tells them what they are missing instead of
telling them the thing. A test asserts the output contains none of "image of", "screenshot" or "picture of".

The summary's alt text lists **every** card. Long, because the image is long: a truncated description of a
summary is worse than none.

**On screen it is written out, not hidden behind the button.** A `<details>` under each download shows the
suggested text so it can be read and edited before it goes anywhere, with a Copy button beside it. The
clipboard call is wrapped — access can be refused — and the text stays selectable either way.

Fixed while testing: an empty Wrapped produced a trailing space where the cards should have been.

- Verified: `vendor/bin/pest` 324 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · checked against the seeded dev site.

### Task 22 — Confetti on first view, once (done)
`resources/js/celebrate.js`, using `canvas-confetti` (1.9.4, bundled by Vite — no runtime dependency for
the site). Two short bursts from the lower corners, then done: a moment, not a screensaver.

The decision is one plain function, `shouldCelebrate(periodKey, cardCount)`, with every reason it might say
no in one place:
- **Nothing to celebrate.** An empty Wrapped gets an explanation, not a party.
- **Already seen.** `localStorage`, keyed by period — the same per-browser nicety as the widget dismissal —
  so next year's Wrapped celebrates again on its own.
- **Reduced motion.** `prefers-reduced-motion: reduce` means never. A burst of moving particles is exactly
  what that setting asks not to see, and the Wrapped is complete without it.
- **No storage.** If localStorage throws (private window, blocked site data) it declines. No way to
  remember means it would fire on every visit, which is the wrong side of that coin.

"Seen" is marked **before** the burst fires, so an error mid-animation still counts as seen rather than
replaying on every reload.

**Testing honesty:** this repo has no JavaScript test runner and adding one for a single function is not
worth the toolchain. The seven guard cases were checked with an ad hoc node script stubbing `window`
(all pass) and the result recorded here; they are not part of `vendor/bin/pest`. Visual confirmation is
manual: load `/cp/wrapped` on the seeded dev site, once.

The bundle grew from 6.9 kB to 18.3 kB gzipped-7.4 kB. Acceptable for a control panel page loaded on demand.

- Verified: `vendor/bin/pest` 324 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `npm run build` clean · assets republished.

### Task 23 — Permissions (done) — **Phase 3 complete**
Two permissions, registered under a "Wrapped" group and **nested** so the second cannot be granted
without the first: `view wrapped` (the screen, the widget, the downloads — meant to be granted broadly)
and `view wrapped people` (the cards about who did what — a separate decision, for the reasons in
`config/wrapped.php`). Labels and descriptions in `resources/lang/en/permissions.php`.

**The gate is one class, `Stats\CardGate`, checked in one place: `CardPresenter::present()`.** The screen,
the widget, the image export and the alt text all present cards through it, so a new caller cannot forget
to gate. Verified by test that a viewer without the people permission gets no people card on screen, cannot
download one **even by guessing the URL** (404, not 403 — it does not exist for them), gets a summary image
without them on it, and alt text that does not mention them.

**Which cards are "about people" is declared by the cards themselves**, via a marker interface
`Stats\AboutPeople` on `PeopleCard` and `TopContributorCard`. Implementing it is what puts a card behind the
gate, so a future people card cannot be added without it.

**Checked through Laravel's Gate, not the user object.** Statamic hooks its permissions into the Gate
(`Gate::after` in its AuthServiceProvider: supers pass, then `hasPermission`), and `Gate::allows()` is the
same path the `can:` route middleware takes, so the route and the cards on it can never disagree.
`Statamic\Contracts\Auth\User` does not declare `can()` — another thin contract — which is what pushed
this toward the Gate in the first place.

**Nobody logged in means nobody sees people.** `Gate::allows()` is false with no user, so the CLI and
any future unauthenticated context get the safe default without a special case.

**Statamic behaviour worth knowing:** the control panel turns an authorization failure into a **redirect to
the dashboard with an "unauthorized" error flash**, not a bare 403. The tests assert that, because it is
what a user actually sees. The gate was working the whole time; the first assertion was just wrong about
Statamic's presentation of it.

Test harness: non-super users with specific permissions need a file-backed `Role`; the tests point
`statamic.users.repositories.file.paths.roles` at a temp file and `Stache::clear()`.

**Deferred, and now the only Phase 3 leftover:** nothing. The people-stats *config* switch (`people.enabled`,
`people.individuals`) and the people-stats *permission* are deliberately separate layers — config decides
whether the cards are computed at all; the permission decides who is shown them.

- Verified: `vendor/bin/pest` 338 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · both permissions register in the host site.

### Task 24 — README, screenshots, marketplace copy (done) — first of phase 4
- `README.md`: what it shows, install, **where the numbers come from** (the source/confidence table is the
  most important section — it is the one real engineering problem, and a reader should understand why a
  card can be missing), generating and scheduling, images and alt text, people stats, permissions.
- `docs/marketplace.md`: tagline, short and long descriptions, feature list, screenshot order, requirements
  line. Versioned with the thing it describes.
- `docs/screenshots/`: seven images, **all real** — four card exports and the summary rendered by Chrome
  from the seeded dev site, plus the control panel screen and dashboard captured with Playwright while
  logged in. Nothing mocked up. ~1 MB total.
- `composer.json` gained the `suggest` for `emran-alhaddad/statamic-logbook` deferred from task 3.
  `composer update --lock` refreshed the (git-ignored) lock so `composer validate` is clean.
- `.playwright-mcp/` (Playwright's scratch output) added to `.gitignore`.

Playwright note: Herd's self-signed cert fails `ERR_CERT_COMMON_NAME_INVALID` in Playwright's browser;
the plain `http://` address works and Herd serves it.

- Verified: `vendor/bin/pest` 338 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · `composer validate` clean.

### Task 25 — Large site and three-month-old site (done)
`tests/Feature/Scenarios/LargeSiteTest.php` and `YoungSiteTest.php`. Both through **real sources**, not fakes,
where it matters.

**The first-year framing was missing, and this task built it.** Cards already handled a young site
(`previous` null, no "went quiet"), but the screen still said "2026" for a site that began in June. SPEC.md
§1 asks for "since June". Now:
- `HistorySourceResolver::earliestKnown()` — the oldest moment any **trustworthy** source knows about.
  Two things make it honest: it looks across every available source rather than only the resolved one
  (a site that installed Logbook in June but has entries dated 2019 is an *old site with new history*,
  and the entry dates say so), and it **ignores Low-confidence sources** (file mtimes are always available
  and on a deployed site their earliest is last week's deploy, which would make every production site
  look brand new). Both cases are tests.
- `wrapped_snapshots.started_at`, nullable, set by the generate command only when the earliest known
  moment is inside the period. **Migration edited in place** — nothing has shipped. The dev site's table
  got the column by hand.
- The screen shows **"Since June 2026"** instead of "2026" when set. Label built server-side like all
  wording (`messages.since`).

**Large site: 40,000 audit rows generate in ~3.9s**, well inside the 30s / 256 MB budget the test holds
it to. 1,500 real flat-file entries through `EntryDataHistorySource` in ~12s (the time is Statamic
writing files in the fixture, not the addon reading them). Budgets are deliberately loose: a slow CI box
passes, a real regression fails.

**Found and fixed by the large-site test:** `LogbookHistorySource` ran a "does the table exist?" schema
query **before every read** — 7 of 12 queries in a generate run were that check. The table does not
come and go mid-run, so `isAvailable()` is now memoised for the life of the source, and `earliestEvent()`
per site with it. A generate run is now **6 reads: one existence check, then the period, the previous
period, the all-time window for the untouched card, and one earliest-event probe.** A test holds it there,
so "sixteen cards each reading the log" can never creep back in.

Two fixture mistakes worth remembering, both mine: the 40k fixture interleaved action and author on the
same modulus so every "published" row had one author (team card rightly declined), and it expected
`most_revised` from rows with no entries on disk (the card rightly found nothing to name). Neither was a
product bug; both were the product being correct about bad data.

`FakeHistorySource::earliestEvent()` was still dropping null-site events after task 16 fixed `events()`.
Aligned. The real Logbook source was already consistent.

- Verified: `vendor/bin/pest` 348 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · regenerated on the dev site (`started_at` correctly null: it has 2025 history).

### Task 26 — Tag 1.0 (done) — **all tasks complete**
- Tagged `v1.0.0` on `main`, two months ahead of the late-November target, at the owner's call.
- `build/phase-1` was fast-forwarded into `main`: `main` was still the initial commit and every commit on
  the branch descends from it, so the history stays linear and the branch is left in place.
- Annotated tag plus a GitHub release with notes drawn from `docs/marketplace.md`.
- Every gate green at the tag: 348 tests, Pint, PHPStan, `composer validate`, and `npm run build`
  reproduces the committed bundle byte for byte.
- No version field in `composer.json`, on purpose: Packagist and the Statamic Marketplace read it from
  the tag.

**Not in the release, still true:**
- `.claude/settings.json` carries a local, uncommitted change (git push moved from deny to allow).
  It is dev tooling, not the product, and was left out of the tag deliberately.
- SPEC.md §4's queued job and 1 December schedule are still a site's own `routes/console.php` line, as
  the README shows. Nothing in the addon schedules itself.
- Statamic Pro was never enabled on the dev site, so `RevisionsHistorySource` is covered by tests against
  real revision files but has not been exercised against a Pro site's live revisions.

### Phase 5 — Shareable video

Decided 13 September 2026 after 1.0. The model is Spotify Wrapped: one fact per full-screen frame,
music underneath, 30-45 seconds, vertical. Built as "pictures, then FFmpeg" on top of the image export
that already exists, rather than a second way of drawing a card.

**Music licensing, checked against Suno's terms (not guessed):** on the Premier plan Suno *assigns*
ownership of Output to the subscriber, so bundling the owner's own tracks in the addon is within their
rights. Conditions that matter: tracks must come through Suno's own download; do not strip the embedded
watermark/metadata; no Remix-feature tracks (no commercial rights); stay inside the monthly download
allowance. Suno keeps the right to say publicly the tracks were AI-made; the README will say so anyway.
`docs/suno-brief.md` is the brief handed to another Claude to write the twelve Suno prompts.

### Task 27 — Portrait frames (done)
- `resources/views/export/frame.blade.php`: one template, three kinds (intro / card / outro), 1080x1920.
  Rendered at 2x like the cards, so 2160x3840 PNGs. Same rules: inline CSS, system fonts, nothing fetched,
  everything escaped — tested.
- **Text sits in the middle band on purpose.** On a phone the top tenth is under the app's chrome and the
  bottom fifth under its caption and buttons. Padding is 240px top / 300px bottom so nothing important
  lands where it will be covered.
- `Export\VideoFrames` renders through the existing `ImageRenderer`, so the fake covers it in tests and
  real Chrome covers it on the dev site. Verified by eye at phone scale: intro, card and outro all read.
- The outro is "That was your year." — a quiet credit, not a call to action. A test asserts it contains
  neither "install" nor "download".
- First-year framing carries through: a young site's frames say "Since June 2026".

- Verified: `vendor/bin/pest` 354 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · three frames rendered with real Chrome from the seeded site.

### Task 28 — FFmpeg renderer (done)
`Export\VideoRenderer` (interface), `Export\VideoSpec` (size, seconds per frame, crossfade, fps, and the
running-time arithmetic), `Export\FfmpegRenderer`. Bound in the provider; `wrapped.video.ffmpeg` in config,
usual install paths checked otherwise, **missing FFmpeg means no video, never a failed request** — same
contract as Chrome.

**The output is H.264 + AAC in an MP4 with `+faststart`.** That is the one combination every phone and
every social platform accepts without re-encoding. 1080x1920 at 30fps.

**The filter graph, in plain words:** each still is one looped input held for its on-screen time, scaled
with lanczos, given a very slight push-in via `zoompan` (`+0.0006` per frame up to 1.06x — a still that
does not move reads as a slideshow, one that moves too much reads as a screensaver), then chained through
`xfade=fade` with each fade's offset at `i × seconds − i × crossfade`. Audio is trimmed to the video's
length, faded in over the intro and out over the last two seconds. Written out by hand rather than through
a library so what runs is exactly what can be read in `filterGraph()`.

**Verified for real, three ways:** the seeded site's intro/card/outro frames plus a synthesised tone
produced a 555 KB MP4 in 1.6s; `ffprobe` confirmed h264 1080x1920 30fps + aac at exactly the 7.8s the spec
computed; and a frame pulled from t=2.7s shows "2026 Wrapped" dissolving into the card, so the crossfade is
real and not just accepted by FFmpeg.

Tests read the duration from the MP4's own `mvhd` box rather than shelling out to `ffprobe`, so they
depend on one binary, not two. Real-FFmpeg tests skip cleanly where it is absent.

- Verified: `vendor/bin/pest` 364 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 29 — Soundtrack registry (done)
`Export\Soundtrack` (value object), `Export\Soundtracks` (registry, singleton), `resources/audio/tracks.php`
(the manifest), and `wrapped.video.bundled` / `wrapped.video.tracks` in config.

- **Bundled tracks are found by handle, not by filename-with-extension.** The manifest lists `open-road`;
  the registry looks for `open-road.<any audio extension>`. A format change is a file swap, not an edit.
- **A track whose file is missing is left out and logged, never offered.** Offering it would put a broken
  choice in front of somebody and fail their download after they made it.
- **Site tracks come first** and become the default; a site that added its own music wants to see it.
  `wrapped.video.bundled => false` offers only the site's own.
- `Soundtrack::mimeType()` states the type from the extension rather than leaving the browser to sniff,
  because **the Suno files are Opus inside an M4A box**, which is not what `.m4a` usually means.
  Downstream: older Safari will not play the picker preview for these. The picker task has to handle that
  honestly (feature-detect, or transcode a preview copy). The finished video is unaffected: FFmpeg reads
  Opus and the output is AAC.
- **Two tests guard the shipped set:** every manifest entry has a file and every audio file has a manifest
  entry (nothing ships unlabelled), and every bundled file still contains Suno's `made with suno` tag in its
  first 64 KB (a file that lost it was converted somewhere it should not have been, which the terms forbid).
- Five tracks in so far: soft-landing, open-road, ipanema-morning, kingston-slow-sunday, seoul-rooftop.
  **Descriptions are blank in the manifest** pending the wording from the Suno brief output.

**Size, flagged for a decision:** five full-length tracks are 15 MB; twelve will be ~36 MB, all inside a
Composer package every site downloads. Options: ship as-is; trim each to ~60s with `-c copy` (no re-encode,
metadata mapped through, but it is an edit of the file and "exactly as downloaded" is the safer reading of
Suno's terms); or ship a few and offer the rest as a separate download. Not decided here.

Pest gotchas hit and recorded: `toContain()` cannot handle a binary string (use `str_contains`);
`test()->prop[] = x` is an "indirect modification" error (use a static in a function — a Pest file has no
true globals); PHPUnit reserves the group names `small`/`medium`/`large`, so the scenario tests are now
`->group('scenario')`, which also silenced three warnings on every run.

- Verified: `vendor/bin/pest` 377 passed, no warnings · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · a real video rendered with `open-road.m4a` (AAC out, 7.8s).

### Task 30 — `WrappedVideo`: the editor chooses (done)
**Design change, at the owner's suggestion:** the editor picks the cards, not the addon. "Best eight" was
always going to be a guess at what is interesting; the editor knows their audience. What the addon does
instead is offer a default and check the choice.

`Export\WrappedVideo`:
- `choices()` — every card this viewer may see, in screen order, each marked `people` and `selected`.
- **The default ticks up to eight non-people cards.** Eight cards plus intro and outro is about 30 seconds,
  where attention runs out on the platforms this is for. **People cards are never ticked by default**: a
  video gets forwarded in a way a screen does not, so putting a person in one should be a decision.
- `duration(n)` — running time for n cards including intro and outro, so the picker can say so live.
- `render(snapshot, handles, track)` — intro, the chosen cards in the order given, outro; the named
  soundtrack or the default; silent when there are no soundtracks at all.
- **The server validates.** A handle not on the Wrapped throws. A people card a viewer may not see is
  simply not in the presented list to be found, so it cannot be smuggled in through a URL — tested with
  a non-super viewer holding only `view wrapped`.

**Verified for real:** the seeded site's eight default cards, real Chrome frames, real FFmpeg, and
`ipanema-morning.m4a` produced a 24.6-second, 2.3 MB MP4 — exactly the predicted length. Sent to the owner.

**Performance note for later, not now:** that render took 32s, almost all of it Chrome starting up once
per frame (ten frames, ~2.5s each). One Chrome session rendering all frames would cut it to a few
seconds. Fine as a synchronous download for v1.1; worth doing if anyone complains.

- Verified: `vendor/bin/pest` 390 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Task 30b — Pacing for WCAG 2.1 AA, and a pinned footer (done)
Owner's review of the first real video: transitions too fast, the video must meet WCAG 2.1 AA, and the
period/site footer must never move.

**Timing is now per frame, from the words on it.** WCAG gives no seconds-per-word figure; the principle
(2.2.1, 2.2.2) is that a reader must have enough time, and reading speed for a phone in a feed is well
below prose speed. `VideoSpec::secondsFor(text)` = 1.5s + 0.4s per word, floored at 4s, capped at 9s.
Twelve words is 6.3s; "12 files, 7.2 MB in all." is 4s. Crossfade 0.6 -> 0.8s. Each `Frame` now carries
its own seconds, and `WrappedVideo::duration()` is worked out from the actual card text so **the picker's
readout and the file always agree** (a test renders and compares).

**Contrast checked, not assumed:** the grey heading/footer (#8b8b96 on #16161d) is **5.34:1**, above AA's
4.5:1 even for small text.

**The footer is an overlay, pinned.** It is rendered once on a transparent ground (Chrome
`--default-background-color=00000000`; confirmed RGBA out) and laid over the finished stitch with FFmpeg's
`overlay` filter, after the zoom and crossfades. It never moves because it is never part of what moves.
**Proved on the real render, numerically:** the footer strip cropped at five moments across the video
(including mid-dissolve) differs by 0.01–0.07/255 on average with 2–3 pixels out of 151,200 above a
threshold of 16 — H.264 noise on letter edges — while the content area at the same moments differs on
120,824 pixels. An MD5 comparison was tried first and is the wrong tool for lossy video.

**Decided: six cards, and under thirty seconds** — 28.6s on the seeded site. Got there without touching
the per-word reading pace, through two things that were over-serving: the intro and outro are title cards
(a glance, not a read) and now get 2.5s instead of a card's 4s floor; and cards are timed on the sentence
rather than sentence-plus-heading, since the heading is a two-word uppercase label the eye takes in at once
and the 4s floor already covers it. A test holds the default under 30s on the fixture Wrapped. A wordy
Wrapped can still run over — that is correct, and the running-time readout in the picker will say so.

**Third request, added as a task:** a tappable story, YouTube-Music style. An MP4 cannot be tapped
through; this is a different artefact — the same frames as live HTML in the CP, advanced by tap, click or
key, with music optional. It is also the properly accessible version: self-paced (2.2.1/2.2.2 by design),
keyboard operable, real text for a screen reader, reduced-motion respected. Recommended as the canonical
form, with the MP4 as the shareable export.

- Verified: `vendor/bin/pest` 399 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · real 47.2s render sent to the owner.

### Task 31 — The video maker in the control panel (done)
`resources/js/components/VideoMaker.vue`, mounted on the Wrapped screen when FFmpeg is present and there
is something to put in a video. Two new routes under the same `view wrapped` gate: `wrapped.video`
(the MP4, editor's choice in the query, `Content-Disposition: attachment`) and `wrapped.soundtrack/{handle}`
(streams a track for the preview button from wherever it lives — never copied into `public/`).

- **Checkboxes, six ticked by default, people cards unticked and badged.** Screen order is kept whatever
  order boxes are ticked, so the video reads the way the page does.
- **Running time, live**, from the same numbers the server uses: per-card seconds plus the two constants
  are sent as props and added up client-side, so the readout and the file agree. `aria-live="polite"` so
  a screen reader hears it change. Over thirty seconds gets a one-line warning, not a block.
  Verified in the real CP: default reads "About 29 seconds", ticking a seventh card reads 34 with the warning.
- **Preview asks rather than assumes.** `canPlayType('audio/mp4; codecs="opus"')`, because the bundled
  files are Opus in an M4A box and older Safari cannot play that. Where it can't, the button says so
  instead of showing a broken player. One `<audio>` element, reused; paused on unmount and on track change.
- **The description is server-authored, client-joined.** The lead sentence and every card sentence come
  from the lang file via props; the client only concatenates them in play order.
- **Accessibility bug caught in the live snapshot:** the "names people" badge ran into the heading, so a
  screen reader would say "The teamnames people". Fixed with a leading space in the text (a margin alone
  does not separate words for assistive tech).
- JSON turns `4.0` into `4`; the client's arithmetic does not care, and the test asserts the JSON form.

- Verified: `vendor/bin/pest` 410 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · exercised in the real CP with Playwright: checkboxes, live readout, previews, download link.

### Task 32 — The tappable story (done)
`/cp/wrapped/story`, an Inertia page (`resources/js/pages/Story.vue`) reached from a "Play it" button on the
Wrapped screen. Same permission gate. The same frames as the video — intro, one per visible card, outro,
the pinned footer — as live HTML, one at a time, advanced by the reader.

**This is the accessible form, and arguably the canonical one; the MP4 is its shareable export.**
- Nothing advances on a timer. WCAG 2.2.1 and 2.2.2 are met by design rather than by a pacing formula.
- Tap or click the stage, or ArrowRight / Space / Enter; ArrowLeft goes back; Escape leaves.
  **Deliberately few keys.** Home, End, Page Up and Page Down are how a screen reader user moves around a
  page, and Backspace once meant "go back"; taking those over would break more than it helps. The first
  draft bound them and the Playwright tooling — which sends End/PageDown while capturing — jumped the story
  around, which is exactly what an AT user would have experienced. Removed.
- Visible Previous / Next buttons too (44px targets), for switch users and anyone who has not guessed the
  convention. Enter on "Done" follows the link; the key handler leaves the controls alone.
- **Focus moves to the new frame on every change** (`tabindex="-1"` region, labelled "Frame 4 of 16"), so a
  screen reader reads the new fact without being asked. A progressbar carries `aria-valuetext`.
- The footer is `aria-hidden`: it never changes, and hearing "2026 default" sixteen times helps nobody.
- Music is **off until asked**, behind a Music button: autoplay with sound is blocked anyway and a page that
  starts singing at someone is bad manners. Same `canPlayType` check as the picker; loops; stops on leave.
- `prefers-reduced-motion` removes the fade.
- The stage is a `div`, not a `<button>`: a button would swallow the frame's text as its own label.

**Verified in the real CP with the Playwright MCP**, by script rather than by its snapshot tool: Home/End/
PageDown ignored; ArrowRight, Space, Enter, click each move exactly one frame; ArrowLeft one back; focus on
the stage after each change; Previous disabled on the first frame; region and progressbar labelled.
The tooling's screenshot times out on the fixed full-screen overlay (twice); skipped — the behaviour is
what was being verified, and it is.

**No JS unit tests still.** Server props are tested (frames, intro/outro, young-site label, people gate,
404 before a Wrapped exists, the link from the screen).

- Verified: `vendor/bin/pest` 415 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Fix — text flush against the card borders (done)
Owner's screenshot: no padding inside the cards. **Cause, confirmed by grepping the CP's built stylesheet:
Statamic's control panel ships only the Tailwind utility classes its own screens happen to use.** `p-4` and
`p-6` exist; `p-5`, which the cards used, does not. Every other utility I had leaned on was working by luck.

Fix: the Wrapped page, the video maker and the widget now carry their own CSS (`wrapped-*` classes),
written out, using the CP's colour tokens (`--color-gray-*`) so dark mode follows the site. Nothing
layout-critical depends on a utility that may or may not be in the bundle. The story page already did this.

Verified in the live CP: cards padded, video section padded 20px with its border, badge coloured, readout
intact. Screenshot refreshed.

Lesson for any Statamic 6 addon screen: **do not use Tailwind utilities in addon Vue for anything that
matters. The CP's bundle is purged to its own usage, and an addon gets whatever survived.**
Mistake of my own while fixing it: a scripted replace matched the *inner* `</template>` (the `v-else` block)
and left the outer one dangling. Build failed loudly, so it cost a minute, not a release.

- Verified: `vendor/bin/pest` 415 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors.

### Fix — "Play it" looked like a visitor (done)
Owner's screenshot: my hand-styled black pill sat flush against the top bar and looked out of place.
**Statamic exposes its own `Header` and `Button` to addons** (`@statamic/cms/ui`), which is how its native
screens get their title row and their blue primary button. Swapped both in — the page header, and the
video maker's download — and deleted my own button CSS. Now the screen sits in the CP the way core screens
do. Lesson alongside the utilities one: **for chrome the CP already has, use the CP's component, not a
lookalike.** Screenshot refreshed.

### Task 33 — Theme (done)
`Export\Theme`, bound as a singleton from `wrapped.theme` (`background`, `accent`, `logo`). One look across
the card images, the summary, the video frames and the story page (which gets it as CSS variables).

**A site chooses the background; the addon chooses the text.** That is the whole design. Text is
off-white or near-black, whichever reads better, and the muted label colour is the text blended toward
the background only as far as 4.5:1 allows. Tests hold text and muted at AA across ten backgrounds from
white to black to saturated colours.

**The tests caught a real gap while writing them:** a saturated rose (`#e11d48`) gets only 4.39:1 with
off-white and less with near-black — mid-brightness colours can defeat *both* soft options. Pure white or
pure black always clears 4.5:1 (worst case, around 18% luminance, is 4.56:1), so those are the fallback
when the soft pair cannot make it. Ten backgrounds are now in the test, including that one.

**An accent is used only where it can be read** — on the small uppercase labels — and dropped with a log
warning if it fails AA against the background. Colours are validated as `#rgb`/`#rrggbb` and anything else
falls back to the default; nothing that is not a colour gets near a stylesheet.

**The logo is embedded as a data: URI**, because the frames render from a `file://` page with no network
and a URL would simply be a missing image. A path on disk, a URL under the site's public directory, or a
full URL (fetched once, 3s timeout, skipped on failure). Falls back to `statamic.cp.custom_logo_url`, the
one standard place a Statamic site keeps a logo. On the intro and outro frames and the summary image only.

**Not done, deliberately:** reading a "site colour" from a starter kit. Statamic has no standard place for
one; it would work on one kit and silently do nothing on the next. The README says so.

**Bug caught by rendering, not by tests:** the frame body is a flex column, which stretched the logo to
full width regardless of its proportions. `align-self: flex-start` + `object-fit: contain`. The unit tests
could not have seen this; the real Chrome render with a light background, accent and logo did.

- Verified: `vendor/bin/pest` 449 passed · `vendor/bin/pint --test` clean · `vendor/bin/phpstan analyse` no errors · real render on `#fef3c7` with accent `#b45309`: text 16.17:1, muted 4.56:1, accent 4.51:1, logo square.

### Task 34 — Release 1.1 (done) — **all tasks complete**
- README gained "The story, and the video" (with the FFmpeg env var, the music section and the Suno
  disclosure promised in task 29) and the "Look" section from task 33; the requirements line now says both
  Chrome and FFmpeg are optional. Marketplace copy updated to match.
- Every gate green at the tag: 449 tests, no warnings, Pint, PHPStan, `composer validate`, and
  `npm run build` reproduces the committed bundle byte for byte.
- `build/phase-1` fast-forwarded into `main` the same way as 1.0 (`git push origin build/phase-1:main`,
  then `git fetch origin main:main`), because `.claude/settings.json` still carries the local, uncommitted
  git-push change and a checkout would trip on it. Tagged `v1.1.0` on `main`, GitHub release with notes.

**Shipped in 1.1, in one line each:**
- The story: tap-through, self-paced, keyboard and screen-reader friendly. The accessible canonical form.
- The video: vertical MP4, editor-chosen cards, reading-pace timing, pinned footer, under 30s by default.
- Five bundled Suno tracks (of a planned twelve — the rest drop into `resources/audio/` and the manifest).
- A site-chosen theme with text derived for AA contrast, and the site's logo on the title frames.
- The control panel screens use Statamic's own Header and Button and carry their own CSS.

**Still true, still not in the release:**
- `.claude/settings.json`'s local change. Dev tooling, left out on purpose.
- Rendering is ~30s for a six-card video, almost all Chrome starting once per frame. One Chrome session
  for all frames would make it a few seconds. Written down since task 30; do it if anyone complains.
- The repo is private and not on Packagist. Making it public is the owner's call.
- Seven more Suno tracks to come. Each is a file drop plus a manifest entry; two tests enforce the pairing.

### Post-1.1 — Video rendering fix (done)
**30.1s -> 8.2s for the same six-card video, identical output.** The time was never the drawing; it was
Chrome starting once per frame (ten launches at ~2.5s). Now every page — intro, cards, outro and the
footer — goes on one contact sheet of `<iframe srcdoc>` cells, Chrome captures it once at 1x (a video
frame is output size already; 2x on ten frames would exceed what Chrome will screenshot in one go), and
FFmpeg slices it back into cells in a single process (`split` + one `crop` per cell, alpha kept).

- `ImageRenderer::renderSheet()` — many documents, one PNG. Iframes keep each page's styles to itself.
- `VideoRenderer::split()` — the slicing lives on the video renderer because that is the path that has
  FFmpeg. GD was the alternative and is present here, but a 66 MB image in a web request at a 128 MB
  memory limit is a bad bet; FFmpeg's memory is its own.
- The sheet is captured with alpha so the footer cell stays transparent; every other page paints its own
  opaque background and is unaffected.
- `VideoFrames::sheet()` is the new path; the single-frame methods stay for the card image and tests.
- **Verified the same three ways as before:** duration exact (28.6s), a frame pulled mid-card reads
  correctly, and the pinned-footer measurement still holds (0.07/255 mean drift, 2 pixels).
  Real Chrome and real FFmpeg both have sheet tests that skip cleanly where absent.

**Holding at five tracks**, at the owner's call. The manifest and tests are ready for more.

### Assessment — a public, shareable story URL (not built; owner's decision)
SPEC.md §5 says no public URL, and gives the reason: a link that leaks a team's internal publishing
stats is a support problem and a privacy problem for a free addon. That reasoning still stands. What has
changed is that the story is now genuinely worth sharing, so the question is whether it can be done
without the harm. It can, opt-in, with guardrails:
- Off by default; a site turns it on in config, and an editor publishes one snapshot at a time.
- An unguessable token in the URL, revocable from the CP, with the option of an expiry.
- People cards never included unless the editor ticks them for that link, same as the video.
- `noindex`, no sitemap, no CP chrome, the site's theme.
- Published means a copy of the snapshot at that moment, so a regenerate does not silently change a
  link somebody already sent.
Roughly a day. Recommendation: yes, built that way — but it is a reversal of the spec, so it is the
owner's call, not mine.

### Assessment — monthly periods (not built; owner's decision)
Quarterly already exists. Monthly is cheap at the data layer: a `Period::Month` case, a `2026-09` key,
`window()` and `previousWindow()` by month; every card's thresholds still make sense at a month.
The real cost is the screen: it shows the most recent snapshot, so a site generating monthly *and*
yearly would see September's Wrapped in December instead of the year's. Monthly needs a period picker on
the screen, the widget nudging only for the yearly one, and the story/video labelled "September 2026".
About half a day. Recommendation: yes, if the owner wants four-plus moments a year; the picker is the
part to design first.

### Task 35 — `Period::Month` (done)
- `Period` now owns the whole calendar: `key()`, `window()`, `previous()` (moved out of `StatContext`,
  which just delegates) and a static `label()`. `assertQuarter()` became `assertPart()` with the count
  per period (1, 4, 12), so the same guard covers months.
- `previous()` for a month recomputes the end of the previous month rather than subtracting a month
  from both ends: 31 March minus a month is 3 March in Carbon's no-overflow mode, not 28 February.
  Tested with March → February and January → December.
- Keys zero-pad the month (`2026-09`) so text sorting and the unique index behave.
- `--month` on `wrapped:generate`, refused together with `--quarter`, refused outside 1–12.
- `Period::label()` turns keys into words: "2026", "Q3 2026", "September 2026" (Carbon's translated
  month, so the CP locale decides). Used everywhere a key reached a person: the screen label, the
  widget title, alt text, the card/summary image footers, the video description lead and the video
  intro. Filenames keep the raw key (`wrapped-2026-09-default.mp4`), because those must stay stable.
- Real run on the dev site: `--month=8` built `2026-08` from Logbook, 12 cards. **The screen now shows
  August 2026, not 2026**, because it picks the newest snapshot — exactly the reason task 36 (the
  period picker) is next. Left the August snapshot in place as test data for it.
- 474 tests, Pint and PHPStan clean.

### Task 36 — The period picker (done)
- `?period=2026-09` on every Wrapped route (screen, story, image, video). No period means the newest
  build, as before. A period the site has not built is a 404, not a fallback to something else.
- The screen now sends `periods` (key, label, url, current), years first, then quarters, then months,
  each newest first, so the year stays at the top however many months pile up. Every URL the page
  hands out (`storyUrl`, `videoUrl`, `summaryImageUrl`, each card's `imageUrl`) carries the period, so
  nothing on the page can quietly revert to the newest build. The Vue side no longer builds URLs from
  `window.location`.
- The picker is Statamic's own `Select` from `@statamic/cms/ui`, in the `Header`'s `actions` slot next
  to "Play it". It only appears when there is more than one snapshot. Choosing is navigation via the
  Inertia router; the URL is the state. Its default `w-full` pushed the button onto a second row, so it
  sits in a 12rem wrapper.
- `Period::fromKey()` is the inverse of `key()`; `Snapshot::startsOn()` uses it for ordering.
- Widget: links to its own period, and in December prefers the current year's snapshot over a month
  or quarter built after it. Outside December, newest build as before.
- Checked in the browser on the dev site with three snapshots (2026, Q3 2026, August 2026): picker
  lists them, switching changes every link, the story's Done goes back to the same period, and
  `?period=2031` is a 404.
- 490 tests, Pint and PHPStan clean, build reproducible.

### Task 37 — Share links (done)
- `config('wrapped.share.enabled')`, **false by default**, with the reasoning in the config comment.
  `share.path` is the first URL segment (`/wrapped/{token}`).
- A third permission, `share wrapped` ("Share publicly"), child of `view wrapped`. Making a link needs
  the setting on *and* the permission; a super with the setting off gets 403.
- `wrapped_shares` table: 40-hex token from `random_bytes` (unique), site, period_key, label, the cards
  frozen as JSON (handle/heading/body), `people`, created_by/at, expires_at, revoked_at. Not a foreign key
  to the snapshot on purpose: regenerating never changes a page somebody already posted (tested).
- People cards: left out unless ticked for that link, and never included when the maker cannot see
  them (`canViewPeople`), even if the request says so. A link can never show more than its maker could.
- Expiry optional, 1–400 days. Revoke is soft (`revoked_at`); the public page is a 404 at once. Off
  in config stops every link at once, no cache clear, because the setting is checked per request.
  Unknown, revoked, expired and switched-off all give the same 404: a dead link does not explain why.
- The public route is registered always but gated per request. First tried registering it only when
  enabled; that needs the setting at boot, which Pest cannot do per directory (`uses()` refuses two
  base classes for one path). Always-registered with a `[a-f0-9]{40}` constraint is the boring
  answer and claims nothing a site would use.
- Statamic turns a denied CP request into a redirect to `/cp` with "Unauthorized.", not a 403; the
  tests assert that, and a test role needs `access cp` or every CP request redirects.
- `Snapshot::label()` now owns the "Since June 2026" wording; the controller and VideoFrames use it.
- CP: a "Share publicly" panel (only when on and permitted): live links with Copy and Revoke, a
  people checkbox (only for those who may see them), an expiry select, "Make a public link". Checked
  in the browser: made a link, opened it, revoked it, 404.
- The public page for now is plain themed HTML with `noindex`: real text, every card. Task 38 turns it
  into the tap-through story.
- 518 tests, Pint and PHPStan clean. Dev site: migrated, `WRAPPED_SHARE_ENABLED=true` in its `.env`.

### Fix — "That was your year." on a quarter or a month (done)
- Reported by the owner on the Q3 and August stories. The closing line was one fixed string.
- Now `Period::outro($key)`: year, quarter or month wording from `messages.video.outro.*`. Used by the
  story, the video (both `outro()` and the sheet the video is actually cut from) and the share page.
- A key this version cannot read falls back to the yearly line.

### Task 38 — The public story page (done)
- `resources/views/share/story.blade.php`: plain HTML plus ~40 lines of script, because Inertia pages
  live inside the control panel and this is the one page that must not. No CP assets, no CSRF, no
  links into `/cp/` (tested by string).
- Every frame is in the HTML; the script adds a `js` class and shows one at a time. Without script it
  reads top to bottom in the same colours, so the text is there either way.
- Same behaviour as the CP story: tap the stage or ArrowRight/Space/Enter for next, ArrowLeft for
  back, Previous/Next buttons, one progress bar per frame, focus moves to the stage on each change,
  footer pinned and `aria-hidden`, fade only under `prefers-reduced-motion: no-preference`. Home,
  End, PageUp, PageDown untouched. No Escape (there is nowhere to go back to).
- No music. The soundtracks are streamed from a CP route behind login, and a public page that starts
  playing is bad manners anyway. Reconsider only if asked.
- Theme as CSS variables from `Theme`; `theme-color` meta; `og:title` and `og:description` (the
  first card) so a posted link gets a preview line. No `og:image`: nothing is hosted.
- Browser check on the dev site: loads on frame 1; ArrowRight moves exactly one frame and focus
  lands on the stage; Previous goes back and disables on the first frame. The Playwright screenshot
  and evaluate tools advance the story by themselves (they click or key the page), same as they did
  with the CP story; the accessibility snapshot straight after navigation is the honest reading.
- 523 tests, Pint and PHPStan clean.

### Task 39 — README + release 1.2 (done) — **all tasks complete**
- README: `--month`, a monthly schedule example, the picker, a Public links section, three
  permissions, "what it will not do" reworded. Marketplace copy and feature list updated.
- SPEC.md §5 "No public hosted URL" kept, with an amendment block saying what 1.2 changed and that
  off is still the default. The spec stays honest about what it originally said.
- Release: main fast-forwarded to build/phase-1, tag v1.2.0, GitHub release with notes.
- `.claude/settings.json` (git push allowed) still deliberately uncommitted.

### Post-1.3 — Share button, and the public-links switch on the settings screen (done)
- Public story page: a Share button between Previous and Next. `navigator.share` where it exists
  (phones), else the clipboard API, else an old-style `execCommand('copy')` so a site still on plain
  http gets it too. "Link copied" goes into a `role="status"` line a screen reader hears. The click
  does not leak to the stage (checked: frame stays put).
- Settings screen: an "Allow public links" toggle in its own section, read by `ShareSettings::enabled()`
  ahead of the config file once the screen has been saved, same rule as the look. The toggle is drawn
  with the config file's value as its default, so saving the screen for the background does not turn
  sharing off on a site that enabled it in `.env`. Checked in the browser: off → link 404 at once,
  on → 200.
- README updated. 559 tests.

### Phase 8 — Decisions (planned 19 September 2026)
- **Why YouTube, not Spotify or Apple.** Checked against YouTube's Developer Policies and Embedded Player Requirements, both "Last updated 2026-09-14". Allowed, with conditions: the player must be visible and at least 200×200 px (Player Requirements, Minimum Size); nothing may overlay it; no background or hidden player, no audio-only, no separating audio from video (Developer Policies III.I.6 to III.I.9); no autoplay until more than half the player is on screen; one autoplaying player per page. Spotify tightened new-app API access in 2025 and Apple needs a paid developer account. Not legal advice; a plain reading of the current pages.
- **Paste a link, not search.** `search.list` on the Data API is capped at 100 calls a day per key, and using it brings the Data API terms with it. oEmbed (`https://www.youtube.com/oembed?url=…&format=json`) is keyless and gives title and thumbnail. So: no API key, no quota, no per-site setup.
- **Web only, never the MP4.** Baking a published song into a video we hand out needs a sync licence a small addon cannot get. The video export keeps the bundled Suno tracks and site-supplied files. The share form says so under the field.
- **Voice is frozen at share time.** Share rows already store card text, not stats, so the wording is chosen when the link is made and never drifts. `we` is the default for share links because the public reader did not publish those entries; the control panel stays `you`.
- **Cost to watch.** A 200×200 tile is a real chunk of a phone screen. It only appears after the viewer taps Play, so the story reads clean by default. The last task is to look at it on a phone before release.

### Phase 8, task 1 — Voice (done)
- `Voice` enum (`you` | `we`) in `src/Stats`. `Voice::key()` maps a translation key to its `_we` twin when one exists, else the plain key, so only lines with a pronoun need a second wording: six card lines, one heading (`When we publish`), three closing lines.
- `CardPresenter::present($stats, $voice)` and `Period::outro($key, $voice)` take the voice; both default to `you`, so nothing in the control panel changed.
- `wrapped_shares.voice` column, default `you`, in a new migration (1.2 shipped the table, so no editing in place). Rows from before the column keep saying `you`, proven by a test that inserts a 1.2-shaped row.
- `ShareLinks::publish()` defaults to `we`; the controller validates `voice` with `Rule::enum`. The share form has a "Written as" radio pair, "we" ticked; each live link says which it was written in.
- The sweep test (`VoiceTest`) renders every card line and every closing line in `we` and fails on any `you`/`your` left over. Checked that it fails when a `_we` line is removed.
- Verified on statamic-dev: made a Q3 link with the default, curled the public page, every pronoun line reads we/our and the page has no "you" or "your" anywhere in its text. The 1.2-era link on that site now reads `written as "you"`.

### Phase 8, task 2 — YouTube, paste a link (done)
- `YouTubeVideo::idFrom()` reads the 11-character id out of watch, youtu.be, shorts, embed, live and bare-id shapes, with or without scheme, www/m/music, and ignores the rest of the query string. A lookalike host (`youtube.com.evil.example`) is not YouTube. Fifteen shapes and eight non-links in the tests.
- `YouTube::resolve()` asks oEmbed once (5 s timeout, no key) and throws a `ValidationException` on `music` with one of two messages: not a YouTube link, or YouTube did not recognise it (401/403/404/5xx, unreachable, or an answer with no title). Either way no share is made.
- Three nullable columns on `wrapped_shares`: `youtube_id`, `youtube_title`, `youtube_thumbnail`. Added to the 1.5 migration alongside `voice` (it had not shipped; rolled back and re-run on statamic-dev). `Share::music()` gives them back as a `YouTubeVideo` or null.
- Share form: a "Music (YouTube link)" `Input`, hint under it saying web page only, the validation message in its place. Each live link shows the song's thumbnail and YouTube's own title, linked, so a wrong paste is visible and can be revoked.
- Verified on statamic-dev against real YouTube: a Vimeo URL showed the message and made no link; `https://youtu.be/dQw4w9WgXcQ?si=test` made a link listed as "Music: Rick Astley - Never Gonna Give You Up (Official Video) (4K Remaster)" with its thumbnail.
- The public page does not play anything yet; that is task 3.
