# Marketplace listing

Copy for the Statamic Marketplace. Kept in the repo so it is versioned with the
thing it describes.

## Name

Wrapped

## Tagline (one line)

A year in review for your content team.

## Short description (under 160 characters)

What you published, when you were busiest, and which page has quietly done its job since 2021. A dozen honest cards, each one a shareable image.

## Long description

Wrapped gives your content team its own year in review. Entries published, words written, busiest month, the day of the week and hour you publish most, your longest streak, the page edited 47 times, the one nobody has needed to touch since 2021. A dozen cards on a control panel screen, each a sentence rather than a chart, and each downloadable as an image sized for social with suggested alt text alongside.

**It only says what it can stand behind.** Statamic does not reliably know its own edit history, so Wrapped reads the best source your site has (Statamic Logbook, revisions, entry dates, or file modification times, in that order), tells you which one it used, and leaves out any card the data cannot honestly support. Confidently wrong numbers are worse than fewer numbers, and this is a product people screenshot.

**It is careful about people.** Team stats are on by default and name nobody. The one card that names a person is off until you switch it on, shows exactly one person as a celebration, and never a ranked list. Nothing ranks from the bottom. A separate permission decides who may see people stats at all.

**It stays out of the way.** One command builds it, one table caches it, one dashboard widget nudges in December. Yearly, quarterly or monthly. No emailing, no analytics, no AI summaries, and no public links unless you switch them on, in which case each one is a frozen copy behind an unguessable token that you can expire or revoke.

Free. Statamic 6, PHP 8.2+.

## Feature list

- Volume: entries published (with last year alongside), words published, files uploaded
- Time: busiest month, busiest week, when you publish, longest streak
- Superlatives: longest entry, most revised, fastest turnaround, most used term, quietly doing its job
- Collections: fastest growing, took the period off
- People: the team (default on), top contributor (opt-in)
- Reads Statamic Logbook, revisions, entry data or file times, and says which
- Cards left out rather than guessed when history is thin
- Shareable PNG per card and for the whole thing, with suggested alt text
- A tap-through story: one fact per screen, at the reader's pace, keyboard and screen-reader friendly
- A short vertical video for Reels, Stories and TikTok, with bundled instrumental music or your own, uploaded on the settings screen
- Your own background colour and logo, with text contrast worked out for you
- Dashboard widget with a December nudge
- Yearly, quarterly and monthly, with a picker to move between them
- Public links, off by default: a frozen, revocable, noindex copy of the story behind a random token, written as "we" for its readers, with optional music: one of the tracks (bundled or yours) as a quiet loop, or a published song by YouTube link in YouTube's own player
- Multisite: one Wrapped per site
- Three permissions: view Wrapped, view people stats, share publicly

## Screenshots, in order

All taken from the shipped release on a seeded Statamic 6 site: 1.4 for most, 1.5 for the two public-links shots (7 and 8).

1. `docs/screenshots/cp-screen.png` — the Wrapped screen, with the period picker and Play it
2. `docs/screenshots/story.png` — the tap-through story in the control panel
3. `docs/screenshots/card-busiest_time.png` — "You publish most on Tuesday"
4. `docs/screenshots/card-longest_untouched.png` — "Quietly doing its job"
5. `docs/screenshots/video-maker.png` — choosing cards and music for the video
6. `docs/screenshots/settings.png` — the settings screen: look, and the public-links switch
7. `docs/screenshots/share-panel.png` — making and revoking public links: the voice choice, the music field, a live link with its song
8. `docs/screenshots/public-story.png` — the public page on a phone, written as "we", with the YouTube tile open and the Share button
9. `docs/screenshots/summary.png` — the whole thing as one image
10. `docs/screenshots/dashboard-widget.png` — the dashboard widget

## Requirements line

Statamic 6 · PHP 8.2+ · Chrome or Chromium for images and FFmpeg for video (both optional) · Statamic Logbook recommended for the full set of cards (optional, free)

## Support line

GitHub issues. Free addon, one maintainer; a reply within a few days.

## Licence line

MIT. Bundled music made with Suno and owned by Had A Farm, LLC; see NOTICE.md.

## Categories

Utilities · Control Panel
