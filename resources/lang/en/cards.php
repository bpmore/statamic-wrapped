<?php

/*
|--------------------------------------------------------------------------
| Card wording
|--------------------------------------------------------------------------
|
| One heading and one sentence per card. The sentences are written to be warm
| and never scolding — see SPEC.md §3. "This page has been quietly doing its
| job" is charming; "you neglected 600 pages" is an accusation and belongs in a
| product people opted into for exactly that.
|
| Cards that can be phrased two ways (with or without a previous period to
| compare against) have a second key. `CardPresenter` picks between them.
|
| A key ending `_we` is the same line for a public page, where the site speaks
| for itself: "we published", "our busiest month". Only the lines that say
| "you" or "your" need one; `Voice` falls back to the plain key otherwise.
|
*/

return [

    'entries_published' => [
        'heading' => 'Entries published',
        'body' => 'You published :count entries.',
        'compared' => 'You published :count entries, against :previous the period before.',
        'body_we' => 'We published :count entries.',
        'compared_we' => 'We published :count entries, against :previous the period before.',
    ],

    'total_words' => [
        'heading' => 'Words published',
        'body' => ':words words, across :entries entries.',
    ],

    'assets_uploaded' => [
        'heading' => 'Files uploaded',
        'body' => ':count files, :size in all.',
    ],

    'busiest_month' => [
        'heading' => 'Busiest month',
        'body' => ':month was your busiest month, with :count entries.',
        'body_we' => ':month was our busiest month, with :count entries.',
    ],

    'busiest_week' => [
        'heading' => 'Busiest week',
        'body' => 'The week of :week, with :count entries.',
    ],

    'busiest_time' => [
        'heading' => 'When you publish',
        'body' => 'You publish most on :day, and most often around :hour.',
        'heading_we' => 'When we publish',
        'body_we' => 'We publish most on :day, and most often around :hour.',
    ],

    'longest_streak' => [
        'heading' => 'Longest streak',
        'body' => ':days days running, from :from to :to.',
    ],

    'longest_entry' => [
        'heading' => 'Longest entry',
        'body' => '":title" was your longest, at :words words.',
        'body_we' => '":title" was our longest, at :words words.',
    ],

    'most_revised' => [
        'heading' => 'Most revised',
        'body' => '":title" was edited :edits times.',
    ],

    'fastest_turnaround' => [
        'heading' => 'Fastest turnaround',
        'body' => '":title" went from first draft to published in :duration.',
    ],

    'top_taxonomy_term' => [
        'heading' => 'Most used term',
        'body' => 'You wrote about :term most, :count times.',
        'body_we' => 'We wrote about :term most, :count times.',
    ],

    'longest_untouched' => [
        'heading' => 'Quietly doing its job',
        'body' => '":title" has needed no changes since :date.',
    ],

    'fastest_growing_collection' => [
        'heading' => 'Fastest growing',
        'body' => ':collection grew most, with :count entries.',
        'compared' => ':collection grew most, with :count entries against :previous the period before.',
    ],

    'collection_went_quiet' => [
        'heading' => 'Took the period off',
        'body' => ':collection published :previous entries the period before, and none this time.',
    ],

    'people' => [
        'heading' => 'The team',
        'body' => ':entries entries, from :people people.',
    ],

    'top_contributor' => [
        'heading' => 'Top contributor',
        'body' => ':name published :count entries.',
    ],

];
