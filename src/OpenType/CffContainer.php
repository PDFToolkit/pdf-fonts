<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

/** Explicit PDF representation; the CFF table option requires a CID-keyed CFF1 font. */
enum CffContainer
{
    case OpenType;
    case CidCffTable;
}
