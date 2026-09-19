<?php

/*
|--------------------------------------------------------------------------
| The confidence notice
|--------------------------------------------------------------------------
|
| A Wrapped always says what it was built from. SPEC.md §1: a Wrapped built on
| file modification times should show a quiet line reading "based on limited
| history data" rather than confidently reporting a busiest week that is
| actually the date of the last deploy.
|
| Three levels, escalating only as far as the truth requires. None of them
| scold: a site with thin history has not done anything wrong.
|
*/

return [

    // The heading for a site younger than the period it was wrapped for. A
    // site that began in June did not have a 2026; it had "since June".
    'since' => 'Since :month',

    // How a period key reads on screen. Months come from Carbon, translated.
    'period' => [
        'quarter' => 'Q:quarter :year',
    ],

    // The toast after the Build button on the Wrapped screen has done its work.
    'generate' => [
        'built' => 'Built the :label Wrapped.',
    ],

    'source' => [
        'logbook' => 'Statamic Logbook',
        'revisions' => 'Statamic revisions',
        'entry_data' => 'the dates on your entries',
        'mtime' => 'file modification times',
        'unknown' => 'an unknown source',
    ],

    'notice' => [

        // Everything that could be worked out, was.
        'complete' => 'Built from :source.',

        // Some cards need better history than this site keeps.
        'limited' => 'Built from :source, which is limited history. One card was left out rather than guessed at.|Built from :source, which is limited history. :count cards were left out rather than guessed at.',

        // Nothing at all could be said.
        'empty' => 'There is not enough history here to say anything yet. This site has only :source to go on, and most deploys rewrite those.',

        // What would help, offered once and without nagging.
        'suggestion' => 'Statamic Logbook records who changed what and when, and would give a much fuller picture next time.',

    ],

    /*
    |--------------------------------------------------------------------------
    | Suggested alt text
    |--------------------------------------------------------------------------
    |
    | Handed over with every downloaded image so whoever posts it can paste it
    | in. It carries the card's own words rather than describing the picture:
    | "an image showing..." tells a reader what they are missing instead of
    | telling them the thing itself.
    |
    */

    'alt' => [

        'card' => ':heading: :body From the :period Wrapped for :site.',

        'summary' => 'The :period Wrapped for :site. :cards',

        // The video's description opens with this; the cards' own sentences
        // follow, in the order they play.
        'video' => 'A short video of the :period Wrapped for :site, one fact per screen, with music.',

    ],

    'video' => [

        // The last frame. Quiet credit, not a call to action. One per kind
        // of period, because "your year" on a monthly Wrapped is wrong. The
        // `_we` lines are for a public page, where the site speaks for itself.
        'outro' => [
            'year' => 'That was your year.',
            'quarter' => 'That was your quarter.',
            'month' => 'That was your month.',
            'year_we' => 'That was our year.',
            'quarter_we' => 'That was our quarter.',
            'month_we' => 'That was our month.',
        ],

    ],

    'story' => [

        // The first frame of the tap-through story.
        'intro' => ':period Wrapped',

    ],

    'widget' => [

        // December, for the year that is ending.
        'ready' => 'Your :period Wrapped is ready.',

        // The rest of the year: a quiet link to something that already exists.
        'title' => 'Your :period Wrapped',

    ],

];
