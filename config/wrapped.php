<?php

return [

    /*
    |--------------------------------------------------------------------------
    | People
    |--------------------------------------------------------------------------
    |
    | Who did what is the most socially loaded thing this addon knows, and the
    | defaults here are deliberately cautious.
    |
    | "Most prolific author" is fun at a five-person agency and politically
    | loaded in a university communications office, where someone's director may
    | screenshot it into a performance conversation. Word counts and entry
    | counts are poor proxies for contribution: the person who wrote three
    | careful pages may have done more than the person who published thirty.
    | Wrapped should not accidentally become the tool that says otherwise.
    |
    | So:
    |
    | - `enabled` covers team-level stats, which name nobody. On by default.
    |   Set it to false to keep people out of the Wrapped entirely.
    |
    | - `individuals` covers stats that name one person. Off by default, and
    |   opt-in on purpose: a site should have to decide it is the kind of place
    |   where that is a celebration.
    |
    | There is no setting that ranks people from the bottom, and there will not
    | be one. No "least active author", no full leaderboard, no "who fell
    | behind". The only individual stat is the top contributor, framed as a
    | celebration. That is a product decision, not an oversight — see SPEC.md §3.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Shareable images
    |--------------------------------------------------------------------------
    |
    | Cards are rendered to PNG by taking a screenshot of some HTML with
    | headless Chrome. Chrome is driven directly rather than through Node and
    | Puppeteer: that is a large dependency to add to a free addon for a picture
    | of a number.
    |
    | Leave `chrome` null and the usual install locations are checked. If no
    | browser is found the download is simply unavailable — the control panel
    | screen is the real version and works regardless.
    |
    */

    'images' => [

        'chrome' => env('WRAPPED_CHROME_PATH'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Shareable video
    |--------------------------------------------------------------------------
    |
    | The frames are stitched into an MP4 by FFmpeg, found the same way Chrome
    | is. Leave `ffmpeg` null to check the usual places. Without it the video
    | download is unavailable; the screen and the images still work.
    |
    */

    'video' => [

        'ffmpeg' => env('WRAPPED_FFMPEG_PATH'),

        // The music that ships with the addon. Set false to offer only your
        // own tracks below.
        'bundled' => (bool) env('WRAPPED_BUNDLED_TRACKS', true),

        // Your own music. Each needs a name and a path to a file PHP can read;
        // the description is optional and shown beside the name in the picker.
        // Your tracks are listed before the bundled ones.
        //
        //     'tracks' => [
        //         'house-theme' => [
        //             'name' => 'House Theme',
        //             'description' => 'The jingle from the podcast.',
        //             'path' => resource_path('audio/house-theme.mp3'),
        //         ],
        //     ],
        'tracks' => [],

    ],

    'people' => [

        'enabled' => (bool) env('WRAPPED_PEOPLE_ENABLED', true),

        'individuals' => (bool) env('WRAPPED_PEOPLE_INDIVIDUALS', false),

    ],

];
