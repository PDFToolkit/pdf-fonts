<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Syntax\Object\StringObject\HexadecimalString;
use PDFToolkit\OpenType\Sfnt\SfntFont;

/** The PDF repertoire contract is independent of the outline representation. */
interface PositionedFont
{
    public function sourceFont(): SfntFont;

    public function unitsPerEm(): int;

    public function code(GlyphText $glyph): HexadecimalString;
}
