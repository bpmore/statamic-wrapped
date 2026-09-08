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

];
