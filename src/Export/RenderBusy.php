<?php

namespace Bpmore\Wrapped\Export;

use Exception;

/**
 * Another render holds this site. An Exception and not a RuntimeException
 * on purpose: the controllers turn a renderer's RuntimeException into a
 * 404, and this is a 429 with a Retry-After.
 */
class RenderBusy extends Exception {}
