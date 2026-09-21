# Changelog

What changed in each release, and what you have to do about it. Versions are
`MAJOR.MINOR.PATCH`. Every entry says whether an upgrade needs a migration or
a republish of the control panel assets.

Upgrade steps, when an entry calls for them:

```bash
php artisan migrate
php artisan vendor:publish --tag=wrapped --force
```

## 1.6.5 - 2026-09-21

### Fixed

- The addon now asks for its saved settings by package name, the way
  Statamic's own settings screen does. Statamic is fixing the lookup that
  1.6.4 worked around (statamic/cms#15495), and once that ships, asking by
  slug would have come back empty: the look, your soundtracks and the
  public-links switch would have quietly fallen back to the config file.
  Right on both sides of the fix. No upgrade steps.

## 1.6.4 - 2026-09-21

### Fixed

- The settings screen now shows what was saved on it. Statamic writes an
  addon's settings under its slug (`wrapped`) and reads them back under its
  package name (`statamic-wrapped`), so on a plain site every save looked
  lost the moment the page reloaded, though the images and the story were
  already using it. The addon now finds its settings under the name they
  were saved with. Nothing saved is lost or moved. No upgrade steps.

## 1.6.3 - 2026-09-21

### Fixed

- A top contributor with no display name no longer has their email shown in
  their place. With individual stats on and a public link made with people
  cards included, that email could have reached the public page, its
  `og:description` and the PNG. The card now reads as it does for a deleted
  user. No upgrade steps.
- The README's one-line summary of outside requests now matches NOTICE.md:
  the logo fetch, the YouTube title lookup, and the reader's own browser
  loading YouTube's player.

## 1.6.2 - 2026-09-20

### Changed

- American spelling throughout: color, license, recognize, gray, behavior.
  Settings screen instructions, control panel text, the public page, the
  docs. Nothing else changed. Needs a republish of the control panel assets
  (`php artisan vendor:publish --tag=wrapped --force`); no migration.

## 1.6.1 - 2026-09-20

### Fixed

- A logo or soundtrack chosen on the settings screen is now read back from
  the container the form offered, before any other. On a site with two or
  more asset containers holding the same relative path, the other file could
  have been picked up. One ordering is now shared by the form and the
  read-back. No upgrade steps.

## 1.6.0 - 2026-09-20

Your own music: uploaded on the settings screen, in the video, and on a
public link. Needs `php artisan migrate` (one new migration, a `soundtrack`
column on `wrapped_shares`) and a republish of the control panel assets.

### Added

- Your own music, uploaded. A "Your soundtracks" field on the settings screen
  (Addons → Wrapped → Settings) takes MP3 and other audio files; anything
  uploaded there appears at the top of the track list for the video and the
  control panel story, named from its title or filename. A file that is not
  audio is ignored. The config-file route still works and lists first. An
  asset container on S3 or another remote disk works too: the file is copied
  to the server for the length of a video render and removed after.
- Music on a public link is now a choice: none, a track (any the video can
  have, bundled or your own), or a YouTube video. A track plays as a quiet
  loop behind the story, streamed from your site under the link's own token,
  so it stops when the link does. The bundled tracks may be used this way;
  the README says what rights your own music needs.

## 1.5.1 - 2026-09-19

### Fixed

- `NOTICE.md` now lists the two requests 1.5 added: the server's one call to
  YouTube's oEmbed endpoint when a song is pasted, and the reader's own
  browser loading YouTube's player when they press Play. Documentation only.
- The share form's field labels ("Written as", "Music", "Stops working
  after") take the same small label style as the Build form. Needs a
  republish of the control panel assets.

## 1.5.0 - 2026-09-19

Public links get a voice and a song. Needs `php artisan migrate` (one new
migration: `voice` and the `youtube_*` columns on `wrapped_shares`) and a
republish of the control panel assets. Nothing changes for a site that
does not use public links.

### Added

- A voice for public links. A share link can be written as "we" (the site
  speaking for itself: "we published 42 entries", "that was our year") or as
  "you" (as the control panel reads). "We" is the default for new links; the
  control panel itself still says "you". Chosen on the share form, frozen
  into the link like everything else. Links made before this keep saying
  "you".
- A song for a public link: paste a YouTube link on the share form. The
  video is looked up once (YouTube's keyless oEmbed; no API key, no quota)
  and kept by id with its title and thumbnail, which the control panel shows
  under the link. A link YouTube does not recognize is refused. The song
  plays on the shared web page only, never in the downloadable video.
- The player on the public page: a "Play music" button on the first frame
  and a "Music" button in the controls. Pressing one opens a small YouTube
  player (privacy-enhanced domain, looping) in its own tile, at the top on
  a phone and bottom-right on a wider screen; the cards make room for it
  rather than sit under it. Nothing loads from YouTube until it is pressed,
  and a page whose link has no song says nothing about YouTube at all.
  Under the tile: the song's title and a link to YouTube's terms. If your
  privacy policy does not already mention embedded YouTube players, the
  README says what to add and why.

## 1.4.2 - 2026-09-17

Support is GitHub issues; the notice names the Suno plan the music was made
on. Nothing else changed. No upgrade steps.

## 1.4.1 - 2026-09-17

Marketplace readiness. No change to what a Wrapped says or does.

### Fixed

- The story's progress bars sat behind the control panel's own header. The
  story now starts below it, measured rather than guessed.

### Added

- `LICENSE.md`, `NOTICE.md` (the bundled music, third-party code, and the one
  optional external request) and this changelog.
- A support path (GitHub issues) in `composer.json` and the README.
- Development files are left out of the Composer download.

### Changed

- README: the dashboard widget has to be added to `config/statamic/cp.php`;
  it never appeared on its own. Upgrade steps are spelled out.
- Screenshots retaken from the shipped release.

Republish the control panel assets. No migration.

## 1.4.0 - 2026-09-14

### Added

- A **Share** button on the public story page: the phone's own share sheet
  where there is one, "Link copied" otherwise, said in a status line a screen
  reader hears. Works on a site still on plain http.
- **Allow public links** on the settings screen (Addons → Wrapped → Settings).
  Once saved it wins over `config/wrapped.php`; off stops every link at once.
  The toggle starts where the config file is, so saving the screen to change a
  color does not switch sharing off on a site that enabled it in `.env`.

Republish the control panel assets. No migration.

## 1.3.0 - 2026-09-14

### Added

- **Build from the control panel.** A Build form on the Wrapped screen (period,
  year, quarter or month), behind a new **Build a Wrapped** permission. The
  command and the form share one builder.
- **Settings screen** at Addons → Wrapped → Settings: background, accent and
  logo, with an asset picker. Saved values win over the config file; an
  emptied field falls back to it.
- The site's **name** rather than its handle wherever a person reads it: the
  screen, the story, the images and the video.

### Fixed

- The nav item now has an icon the control panel ships (`sparkles` was not one,
  and drew blank).

Republish the control panel assets. No migration.

## 1.2.0 - 2026-09-13

### Added

- **Monthly Wrapped**: `php please wrapped:generate --month=9`. Every period
  reads in words ("September 2026", "Q3 2026", "2026"); a month compares
  against the whole previous month and closes with "That was your month."
- **A period picker** in the header when a site has more than one Wrapped. The
  story, images and video follow it. The dashboard widget links to its own
  period and, in December, always shows the year.
- **Public links, off by default.** `WRAPPED_SHARE_ENABLED=true` plus a new
  **Share publicly** permission puts a panel on the Wrapped screen. Each link
  is a frozen copy behind a 40-character random token, leaves the people cards
  out unless ticked for that link, can expire, and can be revoked. The public
  page is the tap-through story, in your theme, `noindex`, with nothing of the
  control panel around it.

### Changed

- Video rendering is about four times faster: every frame from one browser
  launch rather than one launch per frame. Output is unchanged.

### Fixed

- The test suite no longer fails on a machine without FFmpeg.

Run the migration (new table `wrapped_shares`) and republish the assets.

## 1.1.0 - 2026-09-13

### Added

- **Play it**: a tap-through story in the control panel. Tap, click or arrow
  key to advance; nothing moves until the reader says so; works from a
  keyboard; each fact is read by a screen reader as it appears.
- **Make a video**: a short vertical MP4 for Reels, Stories and TikTok. The
  editor ticks the cards (six by default, people cards never by default), picks
  a track, downloads. Each card stays up as long as its words take to read
  (WCAG 2.1 AA pacing); the period and site are pinned over every frame.
- **Music**: five bundled instrumental tracks, made with Suno and shipped
  exactly as downloaded, or your own through config. See NOTICE.md.
- **Your look**: a background color with the text color worked out to clear
  4.5:1 contrast; your logo on the opening and closing frames.

### Changed

- Control panel screens use Statamic's own header and button and carry their
  own CSS.

Republish the control panel assets. No migration.

## 1.0.0 - 2026-09-13

First release.

- Sixteen cards across volume, time, superlatives, collections and people.
- A confidence notice on every Wrapped saying what it was built from: Statamic
  Logbook, revisions, entry dates or file modification times, in that order.
  Cards the data cannot support are left out, not guessed.
- Every card downloadable as a PNG sized for social, with suggested alt text.
- Dashboard widget with a December nudge. Yearly and quarterly, one Wrapped
  per site. Two permissions: view Wrapped, view people stats. "Since June"
  framing for a site younger than the period.
