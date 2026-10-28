<?php

declare(strict_types=1);

use PDF\Document\Fonts\FontDescriptorFlags;
use PDF\Document\ResourceDictionary;
use PDF\Document\Resources\Font;
use PDF\FileStructure;
use PDF\Fonts\OpenType\CffFontEmbedding;
use PDF\Fonts\OpenType\CffFontProgram;
use PDF\Fonts\OpenType\CffContainer;
use PDF\Fonts\OpenType\TrueTypeFontProgram;
use PDF\Fonts\OpenType\TrueTypeFontEmbedding;
use PDF\Fonts\TrueType\FontProgram;
use PDF\Fonts\OpenType\PositionedText;
use PDF\Fonts\OpenType\TextLayout;
use PDF\Fonts\TrueType\UnicodeText;
use PDF\Syntax\ContentStreamsResources\ContentStreams;
use PDF\Syntax\DataStructure\Rectangle;
use PDF\Syntax\DocumentStructure\DocumentCatalog;
use PDF\Syntax\DocumentStructure\PageTree\PageObject;
use PDF\Syntax\DocumentStructure\PageTree\PageTreeNode;
use PDF\Syntax\FileStructure\FileBody;
use PDF\Syntax\FileStructure\FileHeader;
use PDF\Syntax\Object\DictionaryObject\Entry;
use PDF\Syntax\Object\DictionaryType;
use PDF\Syntax\Object\IndirectReference;
use PDF\Syntax\Object\NameType;
use PDF\Syntax\Object\NumericType;
use PDF\Syntax\Object\StreamObject;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

if (!isset($argv) || count($argv) < 3) {
    throw new InvalidArgumentException('Usage: php examples/opentype-layout.php FONT.otf|FONT.ttf OUTPUT.pdf [REPORT.json]');
}
$input = realpath($argv[1]);
if ($input === false || !is_file($input)) {
    throw new RuntimeException('The font must be a readable filesystem file.');
}
$destinations = [$input];
foreach (array_slice($argv, 2, 2) as $path) {
    $parent = realpath(dirname($path));
    if (str_contains($path, '://') || $parent === false) {
        throw new RuntimeException('Outputs require existing filesystem parent directories.');
    }
    $destination = realpath($path) ?: $parent.DIRECTORY_SEPARATOR.basename($path);
    if (in_array($destination, $destinations, true)) {
        throw new RuntimeException('The input and all output destinations must be distinct.');
    }
    $destinations[] = $destination;
}
$bytes = file_get_contents($input);
if ($bytes === false) {
    throw new RuntimeException('Cannot read the font.');
}
$program = str_starts_with($bytes, 'OTTO') ? new CffFontProgram($bytes) : new TrueTypeFontProgram(new FontProgram($bytes));
// The application selects the script and features. This is not an Arabic/bidi shaper.
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('liga'), new Tag('kern'), new Tag('mark')]);
$text = (new TextLayout($program->font))->create(new UnicodeText('AV fi Á Α'), [$selection], [$selection]);
$embedding = $program instanceof CffFontProgram
    ? new CffFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80), container: $program->cff->ros === null ? CffContainer::OpenType : CffContainer::CidCffTable)
    : new TrueTypeFontEmbedding($program, $text->glyphs, new FontDescriptorFlags(4), new NumericType(80));
$resource = new NameType('F1');
$resources = new ResourceDictionary(new Font(new DictionaryType(new Entry($resource, new IndirectReference($embedding->font)))));
$operators = (new PositionedText($embedding, $text))->operators($resource, new NumericType(48), new NumericType(30), new NumericType(80));
$page = new PageObject(new Rectangle(new NumericType(0), new NumericType(0), new NumericType(400), new NumericType(150)), $resources, new StreamObject(new ContentStreams(...$operators)));
$catalog = new DocumentCatalog((new PageTreeNode())->addKid($page));
$file = new FileStructure(new FileHeader('1.7'), new FileBody($catalog), new IndirectReference($catalog));
$file->writeTo($argv[2]);
if (isset($argv[3])) {
    $glyphs = [];
    foreach ($text->glyphs as $index => $glyph) {
        $glyphs[] = ['gid' => $glyph->glyph->value, 'cid' => $program instanceof CffFontProgram ? $program->cid($glyph->glyph) : $glyph->glyph->value, 'code' => bin2hex($embedding->code($glyph)->value),
            'unicode' => $glyph->unicode->utf8(), 'position' => get_object_vars($text->layout->positions[$index])];
    }
    file_put_contents($argv[3], json_encode(['source' => $text->source->utf8, 'fontSha256' => hash('sha256', $bytes), 'unitsPerEm' => $embedding->unitsPerEm(), 'container' => $embedding instanceof CffFontEmbedding ? $embedding->container->name : 'TrueType', 'size' => 48, 'origin' => [30, 80], 'glyphs' => $glyphs], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
}
echo $argv[2]."\n";
