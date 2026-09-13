<?php

namespace Bpmore\Wrapped\Stats;

/**
 * A card that is about who did what.
 *
 * A marker, not a contract: the card itself is unchanged, but anything that
 * shows cards can ask "is this one about people?" and gate it behind the
 * narrower `view wrapped people` permission. Implementing this is what puts a
 * card behind that gate, so a new people card cannot be added without it.
 */
interface AboutPeople {}
