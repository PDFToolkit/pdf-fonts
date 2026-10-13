<?php

declare(strict_types=1);

namespace PDF\Fonts\TrueType;

use PDF\Document\Fonts\FontDescriptor;
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Document\Fonts\FontFile2;
use PDF\Syntax\DataStructure\Rectangle;
use PDF\Syntax\Object\DictionaryObject\Entry;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;

/** Extracts sfnt metadata and converts its font units into explicit PDF descriptor values. */
final readonly class FontDescriptorFactory
{
    public function __construct(public CIDFontProgram $program)
    {
    }

    public function create(FontFile2 $fontFile, FontDescriptorFlags $flags, NumericType $stemV, ?NumericType $capHeight = null, Entry ...$entries): FontDescriptor
    {
        if ($fontFile->data !== $this->program->bytes) {
            throw new \InvalidArgumentException('The FontFile2 bytes must match the converted CID font program.');
        }
        $source = $this->program->source;
        $metrics = $source->metrics;
        $capHeight ??= $source->capHeight === null ? null : new NumericType($metrics->scale($source->capHeight));
        if ($capHeight === null) {
            throw new \InvalidArgumentException('A CapHeight must be supplied when absent from the font metrics.');
        }
        $box = array_map(static fn (int $value): NumericType => new NumericType($metrics->scale($value)), $source->boundingBox);
        return new FontDescriptor(
            $fontFile,
            new NameType($source->postScriptName),
            $flags,
            new Rectangle(...$box),
            new NumericType($source->italicAngle),
            new NumericType($metrics->scale($source->ascent)),
            new NumericType($metrics->scale($source->descent)),
            $capHeight,
            $stemV,
            ...$entries,
        );
    }
}
