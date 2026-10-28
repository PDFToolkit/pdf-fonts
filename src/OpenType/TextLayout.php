<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Document\Fonts\CMaps\UnicodeSequence;
use PDF\Fonts\TrueType\UnicodeText;
use PDF\Syntax\Object\StringObject\HexadecimalString;
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;

/** Execute caller-selected horizontal GSUB/GPOS stages; no guessed script, casing or direction. */
final readonly class TextLayout
{
    public function __construct(private SfntFont $font, private ReadLimits $limits = new ReadLimits())
    {
    }

    /** @param list<FeatureSelection> $substitutionStages
     * @param list<FeatureSelection> $positioningStages
     */
    public function create(UnicodeText $text, array $substitutionStages = [], array $positioningStages = []): ShapedText
    {
        if (count($text->codePoints) > $this->limits->maxOutputGlyphs || !array_is_list($substitutionStages) || !array_is_list($positioningStages)
            || count($substitutionStages) + count($positioningStages) > $this->limits->maxLayoutRecords
        ) {
            throw new \InvalidArgumentException('Layout inputs exceed explicit budgets or are not ordered stage lists.');
        }
        foreach ([...$substitutionStages, ...$positioningStages] as $stage) {
            if (!$stage instanceof FeatureSelection || $stage->coordinates !== null) {
                throw new \InvalidArgumentException('This static layout adapter requires explicit non-variable feature selections.');
            }
        }
        foreach (['fvar', 'CFF2'] as $tag) {
            if ($this->font->hasTable(new Tag($tag))) {
                throw new \InvalidArgumentException('This adapter requires a static font instance.');
            }
        }
        $map = (new UnicodeCmapReader($this->limits))->read($this->font->table(new Tag('cmap')), $this->font->glyphCount());
        $glyphs = [];
        foreach ($text->codePoints as $point) {
            $glyph = $map->glyphFor(new CodePoint($point));
            if ($glyph->value === 0) {
                throw new \InvalidArgumentException(sprintf('No glyph for U+%04X.', $point));
            }
            $glyphs[] = $glyph;
        }
        $sequence = new GlyphSequence($glyphs);
        $definition = $this->font->hasTable(new Tag('GDEF')) ? (new GdefReader($this->limits))->read($this->font->table(new Tag('GDEF')), $this->font->glyphCount()) : null;
        if ($substitutionStages !== [] && $this->font->hasTable(new Tag('GSUB'))) {
            $table = (new GsubReader($this->limits))->read($this->font->table(new Tag('GSUB')), $this->font->glyphCount());
            foreach ($substitutionStages as $stage) {
                $sequence = (new SubstitutionEngine($this->limits))->apply($table, $stage, $sequence, $definition);
            }
        }
        $metrics = (new HorizontalMetricsReader())->read($this->font);
        $positions = array_map(static fn ($glyph): GlyphPosition => new GlyphPosition($metrics->advanceWidth($glyph)), $sequence->glyphs);
        $positioned = new PositionedGlyphSequence($sequence, $positions);
        if ($positioningStages !== [] && $this->font->hasTable(new Tag('GPOS'))) {
            $table = (new GposReader($this->limits))->read($this->font->table(new Tag('GPOS')), $this->font->glyphCount());
            foreach ($positioningStages as $stage) {
                $positioned = (new PositioningEngine($this->limits))->apply($table, $stage, $positioned, $definition);
            }
        }
        $seen = [];
        $mapped = [];
        foreach ($sequence->glyphs as $index => $glyph) {
            $bytes = '';
            foreach ($sequence->componentClusters[$index] as $cluster) {
                if (!isset($seen[$cluster])) {
                    $point = $text->codePoints[$cluster] ?? throw new \LogicException('Layout provenance exceeds source text.');
                    $bytes .= $point < 65536 ? pack('n', $point) : pack('nn', 0xD800 + (($point - 0x10000) >> 10), 0xDC00 + (($point - 0x10000) & 1023));
                    $seen[$cluster] = true;
                }
            }
            $mapped[] = new GlyphText($glyph, new UnicodeSequence(new HexadecimalString($bytes)));
        }

        return new ShapedText($this->font, $text, $positioned, $mapped);
    }
}
