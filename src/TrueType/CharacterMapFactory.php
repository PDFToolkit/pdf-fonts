<?php

declare(strict_types=1);

namespace PDF\Fonts\TrueType;

use PDF\Document\Fonts\CharacterMap;
use PDF\Document\Fonts\CharacterMapping;
use PDF\Document\Fonts\FontFile2;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;

/** Converts a parsed sfnt repertoire to explicit PDF CIDs, GIDs and 1000-unit widths. */
final readonly class CharacterMapFactory
{
    public function __construct(public CIDFontProgram $program)
    {
    }

    public function create(FontFile2 $fontFile, UnicodeText $repertoire): CharacterMap
    {
        if ($fontFile->data !== $this->program->bytes) {
            throw new \InvalidArgumentException('The FontFile2 bytes must match the converted CID font program.');
        }
        $source = $this->program->source;
        $characters = [];
        foreach ($repertoire->codePoints as $point) {
            if (isset($characters[$point])) {
                continue;
            }
            if (count($characters) === 0xFFFF) {
                throw new \InvalidArgumentException('Identity-H supports at most 65535 distinct mapped characters.');
            }
            $glyph = $source->cmap->glyphId($point);
            if ($glyph === 0) {
                throw new \InvalidArgumentException(sprintf('Font has no glyph for U+%04X.', $point));
            }
            $characters[$point] = new CharacterMapping($point, count($characters) + 1, $glyph, new NumericType($source->metrics->pdfWidth($glyph)));
        }
        return new CharacterMap($fontFile, new NameType($source->postScriptName), new NumericType($source->metrics->pdfWidth(0)), ...array_values($characters));
    }
}
