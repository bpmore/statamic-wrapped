<?php

namespace Bpmore\Wrapped\Snapshots;

use RuntimeException;

/**
 * Nothing on this site can say when anything happened, so there is nothing to
 * build a Wrapped from. Every history source reported itself unavailable.
 */
class NoReadableHistory extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('There is no readable history on this site, so there is nothing to build.');
    }
}
