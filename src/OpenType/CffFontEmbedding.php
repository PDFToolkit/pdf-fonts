<?php

declare(strict_types=1);

namespace PDF\Fonts\OpenType;

use PDF\Document\Fonts\CIDFontType0;
use PDF\Document\Fonts\CIDMetrics;
use PDF\Document\Fonts\CIDWidthArray;
use PDF\Document\Fonts\CIDWidthRun;
use PDF\Document\Fonts\FontDescriptor;
use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Document\Fonts\FontFile3;
use PDF\Document\Fonts\FontFile3Subtype;
use PDF\Document\Fonts\OpenTypeOutline;
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
use PDF\Syntax\DataStructure\Rectangle;
use PDF\Syntax\Filters\FlateDecode;
use PDF\Syntax\Object\IntegerType;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDF\Syntax\Object\StringObject\HexadecimalString;
use PDF\Syntax\Object\StringObject\LiteralString;
use PDFToolkit\OpenType\Value\GlyphId;

/** Fixed repertoire: PDF codes, CIDs, GIDs and extraction sequences remain distinct. */
final readonly class CffFontEmbedding implements PositionedFont
{
    public FontFile3 $file;
    public FontDescriptor $descriptor;
    public CIDFontType0 $descendant;
    public EncodingCMap $encoding;
    public ToUnicodeCMap $toUnicode;
    public Type0Font $font;
    /** @var array<string, HexadecimalString> */
    private array $codes;

    /** @param list<GlyphText> $repertoire */
    public function __construct(public CffFontProgram $program, array $repertoire, FontDescriptorFlags $flags, NumericType $stemV, ?NumericType $capHeight = null, public CffContainer $container = CffContainer::OpenType)
    {
        if (!array_is_list($repertoire) || count($repertoire) > 65535) {
            throw new \InvalidArgumentException('A CFF PDF repertoire is an ordered list of at most 65535 glyph/text pairs.');
        }
        $ros = $program->cff->ros;
        $info = new CMapSystemInfo(new LiteralString($ros->registry ?? 'Adobe'), new LiteralString($ros->ordering ?? 'Identity'), new IntegerType($ros->supplement ?? 0));
        if ($container === CffContainer::CidCffTable && $ros === null) {
            throw new \InvalidArgumentException('CIDFontType0C requires a CID-keyed CFF1 program.');
        }
        $table = $program->font->table(new \PDFToolkit\OpenType\Value\Tag('CFF '));
        $this->file = $container === CffContainer::CidCffTable
            ? new FontFile3($table->bytes(0, $table->size()), FontFile3Subtype::CIDFontType0C, null, new FlateDecode())
            : new FontFile3($program->bytes, FontFile3Subtype::OpenType, $ros === null ? OpenTypeOutline::CffSimple : OpenTypeOutline::CffCid, new FlateDecode());
        $scale = 1000 / $program->header->unitsPerEm;
        $box = $program->header->bounds;
        $capHeight ??= $program->os2->sCapHeight === null ? null : new NumericType($program->os2->sCapHeight * $scale);
        if ($capHeight === null) {
            throw new \InvalidArgumentException('CapHeight must be supplied when absent from OS/2.');
        }
        $hhea = $program->font->table(new \PDFToolkit\OpenType\Value\Tag('hhea'));
        $this->descriptor = new FontDescriptor(
            $this->file,
            new NameType($program->postScriptName),
            $flags,
            new Rectangle(new NumericType($box->xMin * $scale), new NumericType($box->yMin * $scale), new NumericType($box->xMax * $scale), new NumericType($box->yMax * $scale)),
            new NumericType($program->post->italicAngle),
            new NumericType($hhea->int16(4) * $scale),
            new NumericType($hhea->int16(6) * $scale),
            $capHeight,
            $stemV
        );
        $spaces = new CodeSpaces(new CodeSpaceRange(new CMapCode(new HexadecimalString("\0\0")), new CMapCode(new HexadecimalString("\xFF\xFF"))));
        $codes = [];
        $cids = [];
        $unicode = [];
        $widths = [0 => new CIDWidthRun(new IntegerType(0), new NumericType($program->pdfWidth(new GlyphId(0))))];
        foreach ($repertoire as $item) {
            if (!$item instanceof GlyphText) {
                throw new \InvalidArgumentException('Repertoires contain explicit glyph/extraction pairs.');
            }
            if (isset($codes[$item->key()])) {
                continue;
            }
            $cid = $program->cid($item->glyph);
            $code = new HexadecimalString(pack('n', count($codes) + 1));
            $codes[$item->key()] = $code;
            $cids[] = new CidChar(new CMapCode($code), new IntegerType($cid));
            $unicode[] = new UnicodeChar(new CMapCode($code), $item->unicode);
            $widths[$cid] = new CIDWidthRun(new IntegerType($cid), new NumericType($program->pdfWidth($item->glyph)));
        }
        ksort($widths, SORT_NUMERIC);
        $name = 'PDFToolkit-CFF-'.substr(hash('sha256', $program->bytes.implode(',', array_keys($codes))), 0, 16);
        $this->encoding = new EncodingCMap(new CMapName(new NameType($name.'-Encoding')), $info, $spaces, new CidMappings(...$cids), filters: new FlateDecode());
        $this->toUnicode = new ToUnicodeCMap(new UnicodeCMap(new CMapName(new NameType($name.'-Unicode')), $spaces, new UnicodeMappings(...$unicode)), new FlateDecode());
        $this->descendant = new CIDFontType0(
            $this->descriptor,
            $info,
            new CIDMetrics(new IntegerType((int) round($program->pdfWidth(new GlyphId(0)))), new CIDWidthArray(...array_values($widths)))
        );
        $this->font = new Type0Font($this->descendant, $this->toUnicode, $this->encoding);
        $this->codes = $codes;
    }

    public function sourceFont(): \PDFToolkit\OpenType\Sfnt\SfntFont
    {
        return $this->program->font;
    }

    public function unitsPerEm(): int
    {
        return $this->program->header->unitsPerEm;
    }

    public function code(GlyphText $glyph): HexadecimalString
    {
        return $this->codes[$glyph->key()] ?? throw new \OutOfRangeException('The glyph/text pair was not declared in this embedded repertoire.');
    }
}
