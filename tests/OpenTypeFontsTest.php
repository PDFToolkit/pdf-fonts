<?php

declare(strict_types=1);

namespace PDF\Fonts\Tests;

use PDF\Document\Fonts\CMaps\UnicodeSequence;
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Fonts\OpenType\CffFontEmbedding;
use PDF\Fonts\OpenType\CffFontProgram;
use PDF\Fonts\OpenType\GlyphText;
use PDF\Fonts\OpenType\PositionedText;
use PDF\Fonts\OpenType\TextLayout;
use PDF\Fonts\TrueType\UnicodeText;
use PDF\Serialization\SerializationContext;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDF\Syntax\Object\StringObject\HexadecimalString;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenTypeFontsTest extends TestCase
{
    private function program(string $kind = 'Simple'): CffFontProgram
    {
        return new CffFontProgram(file_get_contents(__DIR__.'/Fixtures/fonts/PDFToolkitCff'.$kind.'.otf'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function kinds(): iterable
    {
        yield 'name-keyed CFF uses GID' => ['Simple', 2];
        yield 'CID CFF uses charset CID' => ['Cid', 20];
    }

    #[DataProvider('kinds')]
    public function testDistinctCodesPreserveAliasesAndPdfCidSelection(string $kind, int $cid): void
    {
        $program = $this->program($kind);
        $text = (new TextLayout($program->font))->create(new UnicodeText('AΑ'));
        $embedded = new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        self::assertSame($cid, $program->cid(new GlyphId(2)));
        self::assertSame(2, $text->glyphs[1]->glyph->value);
        self::assertSame('<0001>', (string) $embedded->code($text->glyphs[0]));
        self::assertSame('<0002>', (string) $embedded->code($text->glyphs[1]));
        self::assertStringContainsString('<0001> '.$cid, $embedded->encoding->data);
        self::assertStringContainsString('<0002> '.$cid, $embedded->encoding->data);
        self::assertStringContainsString('<0001> <0041>', $embedded->toUnicode->data);
        self::assertStringContainsString('<0002> <0391>', $embedded->toUnicode->data);
        self::assertFalse($embedded->descendant->has('CIDToGIDMap'));
        self::assertSame('OpenType', $embedded->file->dictionary()->get('Subtype')->value);
        self::assertSame($program->bytes, gzuncompress($embedded->file->bytes(new SerializationContext())));
        self::assertSame('PDFToolkitCff'.$kind, $program->cff->name);
        self::assertSame($kind === 'Cid' ? 'Adobe' : null, $program->cff->ros?->registry);
        self::assertSame($kind === 'Cid' ? 'Identity' : null, $program->cff->ros?->ordering);
    }

    public function testExplicitLayoutPreservesLigatureTextKernAndMarkPositions(): void
    {
        $program = $this->program();
        $features = new FeatureSelection(new Tag('latn'), features: [new Tag('liga'), new Tag('kern'), new Tag('mark')]);
        $text = (new TextLayout($program->font))->create(new UnicodeText('AV fi Á'), [$features], [$features]);
        self::assertSame([2, 3, 1, 6, 1, 2, 7], array_map(static fn ($g) => $g->glyph->value, $text->glyphs));
        self::assertSame('fi', $text->glyphs[3]->unicode->utf8());
        self::assertSame(510.0, $text->layout->positions[0]->xAdvance);
        self::assertSame(-350.0, $text->layout->positions[6]->xOffset);
        self::assertSame(720.0, $text->layout->positions[6]->yOffset);
        $embedded = new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        $operators = (new PositionedText($embedded, $text))->operators(new NameType('F1'), new NumericType(48), new NumericType(30), new NumericType(80));
        $source = implode("\n", array_map(strval(...), $operators));
        self::assertStringContainsString('/ActualText <FEFF00410056002000660069002000410301>', $source);
        self::assertStringContainsString('1 0 0 1 145.68 114.56 Tm', $source);
        self::assertStringContainsString('0 Tc', $source);
        self::assertStringContainsString('0 Tw', $source);
        self::assertStringContainsString('100 Tz', $source);
        self::assertStringContainsString('<0004> <00660069>', $embedded->toUnicode->data);
    }

    public function testTrueTypeUsesTheSameLayoutAndCodeContract(): void
    {
        $source = new \PDF\Fonts\TrueType\FontProgram(file_get_contents(__DIR__.'/Fixtures/fonts/PDFToolkitLayoutTrueType.ttf'));
        $program = new \PDF\Fonts\OpenType\TrueTypeFontProgram($source);
        $selection = new FeatureSelection(new Tag('latn'), features: [new Tag('liga'), new Tag('kern')]);
        $text = (new TextLayout($program->font))->create(new UnicodeText('AV fi Α'), [$selection], [$selection]);
        $embedded = new \PDF\Fonts\OpenType\TrueTypeFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        self::assertSame(510.0, $text->layout->positions[0]->xAdvance);
        self::assertSame('fi', $text->glyphs[3]->unicode->utf8());
        self::assertSame('Identity', $embedded->descendant->get('CIDToGIDMap')->value);
        self::assertArrayNotHasKey('cmap', $program->embedded->tables);
        self::assertStringContainsString('<0004> <00660069>', $embedded->toUnicode->data);
        self::assertNotSame((string) $embedded->code($text->glyphs[0]), (string) $embedded->code($text->glyphs[5]));
        self::assertNotEmpty((new PositionedText($embedded, $text))->operators(new NameType('F1'), new NumericType(24), new NumericType(10), new NumericType(20)));
    }

    public function testCidCffTableIsAnExplicitLosslessAlternativeContainer(): void
    {
        $program = $this->program('Cid');
        $text = (new TextLayout($program->font))->create(new UnicodeText('A'));
        $embedded = new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80), container: \PDF\Fonts\OpenType\CffContainer::CidCffTable);
        $table = $program->font->table(new Tag('CFF '));
        self::assertSame('CIDFontType0C', $embedded->file->dictionary()->get('Subtype')->value);
        self::assertSame($table->bytes(0, $table->size()), gzuncompress($embedded->file->bytes(new SerializationContext())));
        self::assertStringContainsString('<0001> 20', $embedded->encoding->data);
        $this->expectException(\InvalidArgumentException::class);
        new CffFontEmbedding($this->program(), [], new FontDescriptorFlags(4), new NumericType(80), container: \PDF\Fonts\OpenType\CffContainer::CidCffTable);
    }

    public function testFeaturesAreNotSilentlyActivated(): void
    {
        $program = $this->program();
        $text = (new TextLayout($program->font))->create(new UnicodeText('fi'));
        self::assertSame([4, 5], array_map(static fn ($g) => $g->glyph->value, $text->glyphs));
    }

    public function testDifferentParsedFontIdentityIsRejected(): void
    {
        $one = $this->program();
        $text = (new TextLayout($this->program()->font))->create(new UnicodeText('A'));
        $embedded = new CffFontEmbedding($one, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        $this->expectException(\InvalidArgumentException::class);
        new PositionedText($embedded, $text);
    }

    public function testUnknownRepertoireCannotGrowDuringEmission(): void
    {
        $program = $this->program();
        $text = (new TextLayout($program->font))->create(new UnicodeText('A'));
        $embedded = new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        $this->expectException(\OutOfRangeException::class);
        $embedded->code(new GlyphText(new GlyphId(2), new UnicodeSequence(new HexadecimalString("\x03\x91"))));
    }

    public function testMissingUnicodeDoesNotBecomeNotdef(): void
    {
        $program = $this->program();
        $this->expectException(\InvalidArgumentException::class);
        (new TextLayout($program->font))->create(new UnicodeText('é'));
    }

    public function testTextBudgetAppliesBeforeLayout(): void
    {
        $program = $this->program();
        $this->expectException(\InvalidArgumentException::class);
        (new TextLayout($program->font, new ReadLimits(maxOutputGlyphs: 1)))->create(new UnicodeText('AA'));
    }

    public function testInvalidGlyphAndNonPositiveSizeAreRejected(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->program()->cid(new GlyphId(8));
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedPrograms(): iterable
    {
        yield 'restricted OS/2' => ['rights'];
        yield 'missing cmap' => ['cmap'];
        yield 'variable instance required' => ['fvar'];
        yield 'CFF2 is not a static CFF1 instance' => ['CFF2'];
    }

    #[DataProvider('rejectedPrograms')]
    public function testUnsupportedOrRestrictedProgramsAreRejected(string $case): void
    {
        $font = $this->program()->font;
        $records = [];
        foreach ($font->records() as $record) {
            $tag = $record->tag->value;
            if ($case === 'cmap' && $tag === 'cmap') {
                continue;
            }
            $bytes = $record->data()->bytes(0, $record->length);
            if ($case === 'rights' && $tag === 'OS/2') {
                $bytes = substr_replace($bytes, pack('n', 2), 8, 2);
            }
            if ($case === 'CFF2' && $tag === 'CFF ') {
                $tag = 'CFF2';
            }
            $records[] = new \PDFToolkit\OpenType\Sfnt\TableRecord(new Tag($tag), 0, 0, strlen($bytes), new \PDFToolkit\OpenType\Binary\BinaryView($bytes));
        }
        if ($case === 'fvar') {
            $records[] = new \PDFToolkit\OpenType\Sfnt\TableRecord(new Tag('fvar'), 0, 0, 4, new \PDFToolkit\OpenType\Binary\BinaryView("test"));
        }
        $bytes = (new \PDFToolkit\OpenType\Sfnt\SfntSerializer())->write(new \PDFToolkit\OpenType\Sfnt\SfntFont($font->version, $records, $font->glyphCount()));
        $this->expectException($case === 'cmap' ? \PDFToolkit\OpenType\Exception\MalformedFontException::class : \InvalidArgumentException::class);
        new CffFontProgram($bytes);
    }

    public function testZeroFontSizeIsRejected(): void
    {
        $program = $this->program();
        $text = (new TextLayout($program->font))->create(new UnicodeText('A'));
        $embedded = new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
        $this->expectException(\InvalidArgumentException::class);
        (new PositionedText($embedded, $text))->operators(new NameType('F1'), new NumericType(0), new NumericType(0), new NumericType(0));
    }
}
