<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Fonts\TrueType\EmbeddingRights;
use PDFToolkit\OpenType\Cff\CffFont;
use PDFToolkit\OpenType\Cff\CffReader;
use PDFToolkit\OpenType\Metadata\FontHeader;
use PDFToolkit\OpenType\Metadata\FontHeaderReader;
use PDFToolkit\OpenType\Metadata\PostScriptTable;
use PDFToolkit\OpenType\Metadata\PostScriptReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetrics;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Metrics\Os2Reader;
use PDFToolkit\OpenType\Metrics\Os2Table;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Sfnt\CollectionReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Sfnt\SfntSerializer;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

/** A static OpenType CFF1 face for PDF 1.6+ FontFile3; no implicit variable instance. */
final readonly class CffFontProgram
{
    public string $bytes;
    public SfntFont $font;
    public CffFont $cff;
    public HorizontalMetrics $metrics;
    public FontHeader $header;
    public Os2Table $os2;
    public PostScriptTable $post;
    public string $postScriptName;

    public function __construct(string $bytes, ?int $collectionIndex = null, ReadLimits $limits = new ReadLimits(), OutlineLimits $outlineLimits = new OutlineLimits())
    {
        $font = $collectionIndex === null ? (new SfntReader($limits))->read($bytes)
            : (new CollectionReader($limits))->read($bytes)->font($collectionIndex);
        if ($font->version !== SfntVersion::Cff || !$font->hasTable(new Tag('CFF '))) {
            throw new \InvalidArgumentException('PDF CFF embedding requires a static CFF1 outline font.');
        }
        foreach (['CFF2', 'fvar', 'gvar', 'avar', 'HVAR', 'VVAR', 'MVAR', 'VARC'] as $tag) {
            if ($font->hasTable(new Tag($tag))) {
                throw new \InvalidArgumentException('Variable fonts require an explicitly generated static CFF1 instance before PDF embedding.');
            }
        }
        $font->table(new Tag('cmap')); // Table 126 requires cmap for embedded OpenType CFF.
        $this->cff = (new CffReader($outlineLimits))->read($font);
        $this->header = (new FontHeaderReader())->read($font->table(new Tag('head')));
        $this->metrics = (new HorizontalMetricsReader())->read($font);
        $this->os2 = (new Os2Reader())->read($font->table(new Tag('OS/2')));
        $this->post = (new PostScriptReader())->read($font->table(new Tag('post')), $font->glyphCount());
        if (!(new EmbeddingRights($this->os2->embeddingRights->fsType, $this->os2->version))->permitsOutlineEmbedding()) {
            throw new \InvalidArgumentException('This font does not permit outline embedding.');
        }
        $name = $this->cff->name;
        if ($name === null || preg_match('/^[!-~]{1,127}$/D', $name) !== 1 || strpbrk($name, '[](){}<>/%') !== false) {
            throw new \InvalidArgumentException('The CFF Name INDEX must contain a valid PostScript font name.');
        }
        $this->postScriptName = $name;
        $this->font = $font;
        $this->bytes = $collectionIndex === null ? $bytes : (new SfntSerializer($limits))->write($font);
    }

    public function cid(GlyphId $glyph): int
    {
        $charset = $this->cff->characterSet ?? throw new \LogicException('CFF1 requires a charset.');
        if ($glyph->value >= $this->font->glyphCount()) {
            throw new \OutOfBoundsException('The selected glyph is outside this CFF font.');
        }

        return $charset->cidKeyed ? $charset->identifiers[$glyph->value] : $glyph->value;
    }

    public function pdfWidth(GlyphId $glyph): float
    {
        return $this->metrics->advanceWidth($glyph) * 1000 / $this->metrics->unitsPerEm;
    }
}
