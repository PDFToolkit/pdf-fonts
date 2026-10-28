<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Fonts\TrueType\UnicodeText;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Sfnt\SfntFont;

/** Explicit layout result. This model does not imply Unicode script itemization or bidi. */
final readonly class ShapedText
{
    /** @param list<GlyphText> $glyphs */
    public function __construct(public SfntFont $font, public UnicodeText $source, public PositionedGlyphSequence $layout, public array $glyphs)
    {
        if (!array_is_list($glyphs) || count($glyphs) !== count($layout->sequence->glyphs)) {
            throw new \InvalidArgumentException('Every positioned glyph requires explicit extraction text.');
        }
        foreach ($glyphs as $index => $glyph) {
            if (!$glyph instanceof GlyphText || $glyph->glyph->value !== $layout->sequence->glyphs[$index]->value || $glyph->glyph->value >= $font->glyphCount()) {
                throw new \InvalidArgumentException('Extraction text and positioned glyph identities must agree with the font.');
            }
        }
    }
}
