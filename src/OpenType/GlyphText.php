<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Document\Fonts\CMaps\UnicodeSequence;
use PDFToolkit\OpenType\Value\GlyphId;

/** Distinct source sequences may intentionally select the same glyph. */
final readonly class GlyphText
{
    public function __construct(public GlyphId $glyph, public UnicodeSequence $unicode)
    {
    }

    public function key(): string
    {
        return $this->glyph->value.':'.bin2hex($this->unicode->value->value);
    }
}
