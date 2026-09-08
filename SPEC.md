# Wrapped — Build Spec

**For:** handoff to Claude Code
**Date:** September 8, 2026
**Product:** `bpmore/statamic-wrapped` — year in review for a Statamic content team
**Price:** Free
**Size:** A weekend. Keep it that way — this spec is deliberately shorter than the others.
**Target release:** late November 2026, so it lands before people want their December recap

---

## 1. The one real engineering problem

**Statamic doesn't reliably know its own edit history**, and every interesting Wrapped stat depends on it. Solve this first; everything else is queries and layout.

Four possible sources, in descending quality:

| Source | Gives you | Catch |
|---|---|---|
| **Statamic Logbook** (free addon) | Who changed what, when, with before/after diffs | Only if installed |
| **Revisions** | Author, timestamp, action, notes per save | **Pro only**, stored in `storage/statamic/revisions`, and **git-ignored by default** — so a deploy that rebuilds storage can wipe the history |
| **Entry data** | `date`, `updated_at`, `author` if the blueprint has one | `author` and `updated_at` are not guaranteed to exist |
| **File mtime** | Approximate last-touched | Destroyed by git checkout, rsync and most deploys. Nearly worthless on a deployed site. |

**Build a `HistorySource` interface with an implementation per source and a resolver that picks the best available.** Then — and this is the part that matters — **say which one you used, in the UI.** A Wrapped built on file mtimes should show a quiet line reading "based on limited history data" rather than confidently reporting a busiest week that's actually the date of the last deploy. Confidently wrong stats are worse than fewer stats, and this is a product people screenshot.

**Related: the first-year problem.** A site installed three months ago has no year. Detect the earliest reliable date and frame accordingly — "since you started in June" instead of "2026."

---

## 2. The stat set

Curated, not exhaustive. Wrapped works because it's a small number of surprising, human facts. Roughly a dozen cards, in four groups.

**Volume**
- Entries published this year, with last year's number alongside
- Total words published
- Assets uploaded, and total size

**Time**
- Busiest month, busiest week
- Busiest day of week and hour — "your team publishes most on Tuesday afternoons" is the single most delightful card in the set
- Longest publishing streak (this is **Streaks**, folded in rather than shipped separately)

**Superlatives**
- Longest entry
- Most-revised page — "this page was edited 47 times"
- Fastest turnaround from created to published
- Most-used taxonomy term
- The page nobody has touched since [year]

**Collections**
- Which collection grew most
- Which one went quiet

**People** — see the next section before building this.

If Constellation ships later, add a most-linked-to page card. If Lifecycle ships, the untouched-page card can pull from real review data rather than timestamps.

---

## 3. The tone problem, which is a real design constraint

Two ways this product can go wrong, and both are social rather than technical.

**Individual leaderboards can become performance metrics.** "Most prolific author" is fun at a five-person agency and politically loaded in a university communications office, where someone's director may screenshot it into a performance conversation. Word counts are a terrible proxy for contribution, and Wrapped should not accidentally become the tool that says otherwise.

So: **team stats on by default, individual leaderboards opt-in, and never rank from the bottom.** No "least active author," ever. Show the top contributor as a celebration if enabled, and let a site disable people-stats entirely in config. Document why.

**Everything else should be warm, never scolding.** "This page has been quietly doing its job since 2021" is charming. "You neglected 600 pages" is an accusation, and it belongs in Lifecycle, which is a paid product people opted into for exactly that. Wrapped is the fun one. Keep the judgment out of it.

---

## 4. Architecture

One table. Aggregating across 40,000 entries on page load will not do.

```
wrapped_snapshots
  id, site, period(year|quarter), period_key('2026', '2026-Q3')
  history_source(logbook|revisions|entry_data|mtime)
  confidence(high|partial|low)
  stats(json), generated_at, generated_by
```

Compute in a queued job, cache the JSON, regenerate on demand. Reuse A11y Report's batch pattern if the site is large; a simple job is fine otherwise.

```
php please wrapped:generate [--year=2026] [--quarter=] [--site=] [--force]
```

Schedule it to run on 1 December so it's warm when people go looking.

---

## 5. Output

**CP screen** — the cards, at `/cp/wrapped`. This is the canonical, accessible version.

**Shareable PNGs** — this is where the marketing value lives. Render each card as an image sized for social, and a combined summary card. Use the headless Chrome you already have from A11y Report; card layout is HTML and CSS.

Two constraints on the export, both quick:

- **The PNG must not be the only version.** Cards rendered as images are invisible to screen readers, so the CP screen stays the real artifact and the export is a convenience. An accessibility vendor shipping image-only output is the screenshot you don't want.
- **Generate suggested alt text with each PNG** and put it on the download so someone posting it can paste it in. Ten minutes of work, entirely on brand.

**No public hosted URL.** A shareable link that leaks a team's internal publishing stats is a support problem and a privacy problem for a free addon. Download and share deliberately.

**Dashboard nudge** in December: "Your 2026 Wrapped is ready." One widget, dismissible.

**Confetti** on first view. Once, not every time.

---

## 6. Explicitly out of scope

No analytics integration — traffic-based stats are Ghost Town's problem and would drag in a dependency on whichever of the fifteen analytics addons the site runs. No cross-site comparison. No historical backfill beyond what the history source honestly supports. No emailing. No AI-generated summaries.

Any one of these turns a weekend into a month.

---

## 7. First session for Code

1. **`HistorySource` interface plus the four implementations and the resolver.** Everything else is downstream of this, and the resolver's confidence rating is what keeps the product honest.
2. **The stat computation layer** — one class per card, each independently testable, each declaring which history source quality it needs. Cards that can't be computed reliably are omitted rather than guessed.
3. **`wrapped:generate`** writing a snapshot, tested against a large fixture site.
4. Then the CP screen. Then the PNG export last, since it's pure presentation.

---

## 8. Open questions

- **Word counting** across Bard, markdown and text fields — reuse Plain's text extraction if `readability-core` exists by then. Don't write it twice.
- **Multisite**: one Wrapped per site, or a combined one? Recommend per site with an optional roll-up, matching how everything else in the line is scoped.
- **Quarterly mode** is nearly free once yearly works, and it gives the addon four moments a year instead of one. Worth including in v1.
- **Permissions**: who can see Wrapped? Recommend a `view wrapped` permission, granted broadly by default, but gate the people-stats behind something narrower.
- **PHP floor**: 8.2. No reason for anything higher.

---

## Sources

- [Statamic Revisions](https://statamic.dev/revisions) — Pro only, `storage/statamic/revisions`, git-ignored by default
- [Statamic Logbook](https://statamic.com/addons/emran-alhaddad/statamic-logbook) — free audit logs with before/after diffs
