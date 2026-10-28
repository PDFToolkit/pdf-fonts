<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Document\Fonts\CIDFontType2;
use PDF\Document\Fonts\CIDIdentityGlyphMap;
use PDF\Document\Fonts\CIDMetrics;
use PDF\Document\Fonts\CIDWidthArray;
use PDF\Document\Fonts\CIDWidthRun;
use PDF\Document\Fonts\FontDescriptor;
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Document\Fonts\FontFile2;
use PDF\Document\Fonts\ToUnicodeCMap;
use PDF\Document\Fonts\Type0Font;
use PDF\Document\Fonts\CMaps\CMapCode;
use PDF\Document\Fonts\CMaps\CMapName;
use PDF\Document\Fonts\CMaps\CMapSystemInfo;
use PDF\Document\Fonts\CMaps\CidChar;
use PDF\Document\Fonts\CMaps\CidMappings;
use PDF\Document\Fonts\CMaps\CodeSpaceRange;
use PDF\Document\Fonts\CMaps\CodeSpaces;
use PDF\Document\Fonts\CMaps\EncodingCMap;
use PDF\Document\Fonts\CMaps\UnicodeChar;
use PDF\Document\Fonts\CMaps\UnicodeCMap;
use PDF\Document\Fonts\CMaps\UnicodeMappings;
use PDF\Fonts\TrueType\FontDescriptorFactory;
use PDF\Syntax\Filters\FlateDecode;
use PDF\Syntax\Object\IntegerType;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDF\Syntax\Object\StringObject\HexadecimalString;
use PDF\Syntax\Object\StringObject\LiteralString;
use PDFToolkit\OpenType\Sfnt\SfntFont;

/** Explicit code-to-GID CMap preserves aliases with CIDToGIDMap /Identity. */
final readonly class TrueTypeFontEmbedding implements PositionedFont
{
    public FontFile2 $file;
    public FontDescriptor $descriptor;
    public CIDFontType2 $descendant;
    public EncodingCMap $encoding;
    public ToUnicodeCMap $toUnicode;
    public Type0Font $font;
    /** @var array<string, HexadecimalString> */
    private array $codes;

    /** @param list<GlyphText> $repertoire */
    public function __construct(public TrueTypeFontProgram $program, array $repertoire, FontDescriptorFlags $flags, NumericType $stemV, ?NumericType $capHeight = null)
    {
        if (!array_is_list($repertoire) || count($repertoire) > 65535) {
            throw new \InvalidArgumentException('A PDF repertoire is an ordered list of at most 65535 glyph/text pairs.');
        }
        $info = new CMapSystemInfo(new LiteralString('Adobe'), new LiteralString('Identity'), new IntegerType(0));
        $this->file = new FontFile2($program->embedded->bytes, new FlateDecode());
        $this->descriptor = (new FontDescriptorFactory($program->embedded))->create($this->file, $flags, $stemV, $capHeight);
        $spaces = new CodeSpaces(new CodeSpaceRange(new CMapCode(new HexadecimalString("\0\0")), new CMapCode(new HexadecimalString("\xFF\xFF"))));
        $codes = $cids = $unicode = [];
        $metrics = $program->source->metrics;
        $widths = [0 => new CIDWidthRun(new IntegerType(0), new NumericType($metrics->pdfWidth(0)))];
        foreach ($repertoire as $item) {
            if (!$item instanceof GlyphText || $item->glyph->value >= $program->source->glyphCount) {
                throw new \InvalidArgumentException('The repertoire must contain glyphs from this font.');
            }
            if (isset($codes[$item->key()])) {
                continue;
            }
            $gid = $item->glyph->value;
            $code = new HexadecimalString(pack('n', count($codes) + 1));
            $codes[$item->key()] = $code;
            $cids[] = new CidChar(new CMapCode($code), new IntegerType($gid));
            $unicode[] = new UnicodeChar(new CMapCode($code), $item->unicode);
            $widths[$gid] = new CIDWidthRun(new IntegerType($gid), new NumericType($metrics->pdfWidth($gid)));
        }
        ksort($widths, SORT_NUMERIC);
        $name = 'PDFToolkit-TT-'.substr(hash('sha256', $program->embedded->bytes.implode(',', array_keys($codes))), 0, 16);
        $this->encoding = new EncodingCMap(new CMapName(new NameType($name.'-Encoding')), $info, $spaces, new CidMappings(...$cids), filters: new FlateDecode());
        $this->toUnicode = new ToUnicodeCMap(new UnicodeCMap(new CMapName(new NameType($name.'-Unicode')), $spaces, new UnicodeMappings(...$unicode)), new FlateDecode());
        $this->descendant = new CIDFontType2($this->descriptor, new CIDIdentityGlyphMap(), new CIDMetrics(new IntegerType((int) round($metrics->pdfWidth(0))), new CIDWidthArray(...array_values($widths))), $info);
        $this->font = new Type0Font($this->descendant, $this->toUnicode, $this->encoding);
        $this->codes = $codes;
    }

    public function sourceFont(): SfntFont
    {
        return $this->program->font;
    }

    public function unitsPerEm(): int
    {
        return $this->program->source->metrics->unitsPerEm;
    }

    public function code(GlyphText $glyph): HexadecimalString
    {
        return $this->codes[$glyph->key()] ?? throw new \OutOfRangeException('The glyph/text pair was not declared in this embedded repertoire.');
    }
}
