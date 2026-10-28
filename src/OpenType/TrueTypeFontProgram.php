<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Fonts\TrueType\CIDFontProgram;
use PDF\Fonts\TrueType\FontProgram;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntReader;

/** Reuse the static TrueType importer; retain cmap for layout, omit it from FontFile2. */
final readonly class TrueTypeFontProgram
{
    public SfntFont $font;
    public CIDFontProgram $embedded;

    public function __construct(public FontProgram $source, ReadLimits $limits = new ReadLimits())
    {
        $this->font = (new SfntReader($limits))->read($source->bytes);
        $this->embedded = new CIDFontProgram($source);
    }
}
