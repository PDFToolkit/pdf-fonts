<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Graphics\CoordinateSystems\Matrix;
use PDF\Syntax\ContentStreamsResources\Operator;
use PDF\Syntax\ContentStreamsResources\Operators\BeginMarkedContentWithProperties;
use PDF\Syntax\ContentStreamsResources\Operators\EndMarkedContent;
use PDF\Syntax\ContentStreamsResources\Operators\BeginTextObject;
use PDF\Syntax\ContentStreamsResources\Operators\EndTextObject;
use PDF\Syntax\ContentStreamsResources\Operators\SetCharacterSpacing;
use PDF\Syntax\ContentStreamsResources\Operators\SetWordSpacing;
use PDF\Syntax\ContentStreamsResources\Operators\SetHorizontalTextScaling;
use PDF\Syntax\ContentStreamsResources\Operators\SetTextRise;
use PDF\Syntax\ContentStreamsResources\Operators\SetTextFontAndSize;
use PDF\Syntax\ContentStreamsResources\Operators\SetTextMatrix;
use PDF\Syntax\ContentStreamsResources\Operators\ShowText;
use PDF\Syntax\Object\DictionaryObject\Entry;
use PDF\Syntax\Object\DictionaryType;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDF\Syntax\Object\StringObject\HexadecimalString;

/** Absolute glyph positions preserve both GPOS axes; ActualText preserves source order. */
final readonly class PositionedText
{
    public function __construct(public PositionedFont $embedding, public ShapedText $text)
    {
        if ($embedding->sourceFont() !== $text->font) {
            throw new \InvalidArgumentException('Positioned text and its embedded program must share the exact parsed font.');
        }
        foreach ($text->glyphs as $glyph) {
            $embedding->code($glyph);
        }
    }

    /** @return list<Operator> Call outside any existing text object. Coordinates are in user space. */
    public function operators(NameType $resource, NumericType $size, NumericType $x, NumericType $y): array
    {
        if ($size->value <= 0) {
            throw new \InvalidArgumentException('Positioned text requires a positive font size.');
        }
        $actual = iconv('UTF-8', 'UTF-16BE', $this->text->source->utf8);
        if ($actual === false) {
            throw new \LogicException('The source text was already validated.');
        }
        $operators = [new BeginMarkedContentWithProperties(new NameType('Span'), new DictionaryType(new Entry(new NameType('ActualText'), new HexadecimalString("\xFE\xFF".$actual)))),
            new BeginTextObject(), new SetTextFontAndSize($resource, $size), new SetCharacterSpacing(new NumericType(0)), new SetWordSpacing(new NumericType(0)), new SetHorizontalTextScaling(new NumericType(100)), new SetTextRise(new NumericType(0))];
        $scale = $size->value / $this->embedding->unitsPerEm();
        $advanceX = $advanceY = 0.0;
        foreach ($this->text->glyphs as $index => $glyph) {
            $position = $this->text->layout->positions[$index];
            $operators[] = new SetTextMatrix(new Matrix(e: new NumericType($x->value + ($advanceX + $position->xOffset) * $scale), f: new NumericType($y->value + ($advanceY + $position->yOffset) * $scale)));
            $operators[] = new ShowText($this->embedding->code($glyph));
            $advanceX += $position->xAdvance;
            $advanceY += $position->yAdvance;
        }
        $operators[] = new EndTextObject();
        $operators[] = new EndMarkedContent();

        return $operators;
    }
}
