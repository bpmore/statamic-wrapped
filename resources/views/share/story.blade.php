{{--
    The public page behind a share link: the tap-through story, the same one
    the control panel plays, with nothing of the control panel around it.

    Built as plain HTML with a little script rather than as an Inertia page,
    because Inertia pages live inside the control panel and this is the one
    page that must not. Every frame is in the HTML; the script shows one at a
    time. Without script it reads top to bottom as a plain page in the same
    colours, so the text is there either way.

    The reader sets the pace: nothing advances on a timer (WCAG 2.2.1, 2.2.2).
    Keys are the slideshow convention and nothing more. Home, End, Page Up and
    Page Down stay with the browser; a screen reader user needs them.

    noindex, no sitemap entry: reached by the link and by nothing else.
--}}
@php
    $frames = [
        ['kind' => 'intro', 'title' => __('wrapped::messages.story.intro', ['period' => $share->label]), 'subtitle' => $share->siteName()],
        ...array_map(fn ($card) => ['kind' => 'card'] + $card, $share->cards),
        ['kind' => 'outro', 'title' => \Bpmore\Wrapped\Snapshots\Period::outro($share->period_key)],
    ];
    $count = count($frames);
    $lead = $share->cards[0]['body'] ?? '';
    $rgb = in_array($theme->text, ['#f7f7f8', '#ffffff'], true) ? '247, 247, 248' : '22, 22, 29';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $share->label }} Wrapped · {{ $share->siteName() }}</title>

    {{-- A posted link gets a title and a line, not an image: nothing is hosted. --}}
    <meta property="og:title" content="{{ $share->label }} Wrapped · {{ $share->siteName() }}">
    @if ($lead !== '')
        <meta property="og:description" content="{{ $lead }}">
        <meta name="description" content="{{ $lead }}">
    @endif
    <meta property="og:type" content="website">
    <meta name="theme-color" content="{{ $theme->background }}">

    <style>
        :root {
            --story-bg: {{ $theme->background }};
            --story-text: {{ $theme->text }};
            --story-muted: {{ $theme->muted }};
            --story-label: {{ $theme->accent ?? $theme->muted }};
            --story-rgb: {{ $rgb }};
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; min-height: 100%; }

        body {
            background: var(--story-bg);
            color: var(--story-text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        p { margin: 0; }

        /* Without script: a plain page, every frame in turn. */
        .story { display: flex; flex-direction: column; min-height: 100vh; }
        .story-stage { flex: 1; padding: 48px 32px; outline: none; }
        .story-frame { max-width: 720px; width: 100%; margin: 0 auto 48px; }
        .story-progress, .story-controls { display: none; }

        /* With script: one frame at a time, the story convention. */
        .js .story { position: fixed; inset: 0; }
        .js .story-stage {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 32px 120px;
            cursor: pointer;
        }
        .js .story-stage:focus-visible { box-shadow: inset 0 0 0 3px rgba(var(--story-rgb), 0.5); }
        .js .story-frame { margin: 0; }
        .js .story-frame:not(.is-current) { display: none; }
        .js .story-progress { display: flex; gap: 6px; padding: 16px 20px 0; }
        .js .story-controls { display: flex; }

        .story-progress__bar {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: rgba(var(--story-rgb), 0.2);
        }
        .story-progress__bar.is-done,
        .story-progress__bar.is-current { background: var(--story-text); }

        .story-heading {
            font-size: clamp(14px, 1.6vw, 18px);
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--story-label);
            margin-bottom: 20px;
        }
        .story-body {
            font-size: clamp(28px, 5vw, 52px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }
        .story-title {
            font-size: clamp(36px, 7vw, 72px);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }
        .story-subtitle {
            font-size: clamp(16px, 2vw, 22px);
            color: var(--story-muted);
            margin-top: 16px;
        }
        .story-logo {
            height: clamp(40px, 8vw, 72px);
            width: auto;
            max-width: 60%;
            object-fit: contain;
            display: block;
            margin-bottom: 32px;
        }

        /* Pinned, exactly as in the video. */
        .story-footer {
            position: fixed;
            left: 32px;
            right: 32px;
            bottom: 88px;
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--story-muted);
            pointer-events: none;
        }
        .story-footer__period { font-weight: 600; color: var(--story-text); }

        .story-controls {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            justify-content: center;
            gap: 12px;
            padding: 16px;
            background: linear-gradient(transparent, var(--story-bg));
        }
        .story-button {
            min-width: 44px;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 22px;
            border: 1px solid rgba(var(--story-rgb), 0.25);
            background: rgba(var(--story-rgb), 0.06);
            color: var(--story-text);
            font: inherit;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .story-button--text { font-size: 14px; }
        .story-button:disabled { opacity: 0.35; cursor: default; }
        .story-status {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 76px;
            text-align: center;
            font-size: 14px;
            color: var(--story-muted);
            pointer-events: none;
        }
        .story-button:focus-visible { outline: 3px solid var(--story-text); outline-offset: 2px; }

        /* A soft change between frames, only where motion is welcome. */
        @media (prefers-reduced-motion: no-preference) {
            .js .story-frame.is-current { animation: story-fade 0.35s ease; }
            @keyframes story-fade { from { opacity: 0; } to { opacity: 1; } }
        }
    </style>
</head>
<body>
    <div class="story">
        <div class="story-progress" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $count }}" aria-valuenow="1" aria-valuetext="1 of {{ $count }}">
            @foreach ($frames as $i => $frame)
                <span class="story-progress__bar{{ $i === 0 ? ' is-current' : '' }}"></span>
            @endforeach
        </div>

        {{--
            The stage: tap anywhere to advance, and where focus lands when the
            frame changes so a screen reader reads the new one unprompted.
            Not a button, which would swallow the text as its own label.
        --}}
        <main class="story-stage" tabindex="-1" role="region" aria-label="Frame 1 of {{ $count }}">
            @foreach ($frames as $i => $frame)
                <div class="story-frame{{ $i === 0 ? ' is-current' : '' }}">
                    @if ($frame['kind'] === 'card')
                        <p class="story-heading">{{ $frame['heading'] }}</p>
                        <p class="story-body">{{ $frame['body'] }}</p>
                    @else
                        @if ($theme->logo)
                            <img class="story-logo" src="{{ $theme->logo }}" alt="">
                        @endif
                        @if ($frame['kind'] === 'intro')
                            <h1 class="story-title">{{ $frame['title'] }}</h1>
                        @else
                            <p class="story-title">{{ $frame['title'] }}</p>
                        @endif
                        @if (! empty($frame['subtitle']))
                            <p class="story-subtitle">{{ $frame['subtitle'] }}</p>
                        @endif
                    @endif
                </div>
            @endforeach
        </main>

        <div class="story-footer" aria-hidden="true">
            <span class="story-footer__period">{{ $share->label }}</span>
            <span>{{ $share->siteName() }}</span>
        </div>

        {{-- Visible controls too, for anyone who has not guessed the convention. --}}
        <div class="story-controls">
            <button type="button" class="story-button" data-back aria-label="Previous" disabled>‹</button>
            {{--
                Share: the phone's own share sheet where there is one, the
                clipboard otherwise. What it did is said in the button and in
                the status line, which a screen reader hears.
            --}}
            <button type="button" class="story-button story-button--text" data-share hidden>Share</button>
            <button type="button" class="story-button" data-next aria-label="Next">›</button>
        </div>
        <p class="story-status" data-status role="status" aria-live="polite"></p>
    </div>

    <script>
        (function () {
            document.documentElement.classList.add('js');

            var frames = Array.prototype.slice.call(document.querySelectorAll('.story-frame'));
            var bars = Array.prototype.slice.call(document.querySelectorAll('.story-progress__bar'));
            var progress = document.querySelector('.story-progress');
            var stage = document.querySelector('.story-stage');
            var backButton = document.querySelector('[data-back]');
            var nextButton = document.querySelector('[data-next]');
            var index = 0;

            function show(i) {
                index = i;

                frames.forEach(function (f, n) { f.classList.toggle('is-current', n === i); });
                bars.forEach(function (b, n) {
                    b.classList.toggle('is-done', n < i);
                    b.classList.toggle('is-current', n === i);
                });

                progress.setAttribute('aria-valuenow', i + 1);
                progress.setAttribute('aria-valuetext', (i + 1) + ' of ' + frames.length);
                stage.setAttribute('aria-label', 'Frame ' + (i + 1) + ' of ' + frames.length);
                backButton.disabled = i === 0;
                nextButton.disabled = i === frames.length - 1;

                stage.focus();
            }

            function next() { if (index < frames.length - 1) show(index + 1); }
            function back() { if (index > 0) show(index - 1); }

            stage.addEventListener('click', next);
            nextButton.addEventListener('click', function (e) { e.stopPropagation(); next(); });
            backButton.addEventListener('click', function (e) { e.stopPropagation(); back(); });

            // Share: the phone's own share sheet where there is one, the
            // clipboard otherwise. The clipboard API needs a secure page, so
            // there is an old-style copy underneath it for a site still on
            // plain http; the button only stays hidden if script is off.
            var shareButton = document.querySelector('[data-share]');
            var status = document.querySelector('[data-status]');
            var link = { title: document.title, url: location.href };

            shareButton.hidden = false;

            function say(text) {
                status.textContent = text;
                setTimeout(function () { if (status.textContent === text) status.textContent = ''; }, 3000);
            }

            function copyTheOldWay() {
                var box = document.createElement('textarea');
                box.value = link.url;
                box.setAttribute('readonly', '');
                box.style.position = 'fixed';
                box.style.opacity = '0';
                document.body.appendChild(box);
                box.select();
                var done = false;
                try { done = document.execCommand('copy'); } catch (err) { done = false; }
                document.body.removeChild(box);
                return done;
            }

            shareButton.addEventListener('click', function (e) {
                e.stopPropagation();

                if (navigator.share) {
                    navigator.share(link).catch(function () { /* dismissed; nothing to say */ });
                    return;
                }

                var fallback = function () {
                    say(copyTheOldWay() ? 'Link copied' : 'Could not copy. The link is in the address bar.');
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link.url).then(function () { say('Link copied'); }, fallback);
                } else {
                    fallback();
                }
            });

            window.addEventListener('keydown', function (e) {
                // Leave the buttons alone: Enter or Space on one is its click.
                if (e.target && e.target.closest && e.target.closest('.story-controls')) return;

                if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') { e.preventDefault(); next(); }
                else if (e.key === 'ArrowLeft') { e.preventDefault(); back(); }
            });
        })();
    </script>
</body>
</html>
