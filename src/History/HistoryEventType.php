<?php

namespace Bpmore\Wrapped\History;

/**
 * What happened to a thing. Sources report whichever of these they can honestly
 * distinguish; file mtimes, for example, only ever produce Updated.
 */
enum HistoryEventType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Published = 'published';
    case Deleted = 'deleted';
}
