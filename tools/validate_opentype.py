#!/usr/bin/env python3
"""Independent CFF PDF selection, extraction, HarfBuzz positioning and raster oracle."""
import argparse
import hashlib
import importlib.metadata
import json
from pathlib import Path
import re
import subprocess

from fontTools.ttLib import TTFont, TTCollection
from fontTools.pens.basePen import BasePen
import uharfbuzz as hb
from pypdf import PdfReader, PdfWriter
from pypdf.generic import DecodedStreamObject, NameObject
from PIL import Image, ImageChops, ImageFilter


class OutlinePdfPen(BasePen):
    def __init__(self, glyph_set):
        super().__init__(glyph_set)
        self.commands = []

    def point(self, p):
        return ' '.join(format(v, '.8g') for v in p)

    def _moveTo(self, p):
        self.commands.append(self.point(p) + ' m')

    def _lineTo(self, p):
        self.commands.append(self.point(p) + ' l')

    def _curveToOne(self, a, b, c):
        self.commands.append(' '.join(self.point(p) for p in [a, b, c]) + ' c')

    def _closePath(self):
        self.commands.append('h')

    def _endPath(self):
        pass


class CffPdfOracle:
    def __init__(self, php, output, bootstrap):
        self.root = Path(__file__).resolve().parents[1]
        self.php = [php] + (['-d', 'auto_prepend_file=' + str(Path(bootstrap).resolve())] if bootstrap else [])
        self.output = output.resolve()
        self.output.mkdir(parents=True, exist_ok=True)

    def run(self, kind):
        path = self.root / 'tests/Fixtures/fonts' / ('PDFToolkitLayoutTrueType.ttf' if kind == 'TrueType' else 'PDFToolkitCff' + kind + '.otf')
        pdf = self.output / (kind.lower() + '.pdf')
        report_path = pdf.with_suffix('.json')
        subprocess.run([*self.php, str(self.root / 'examples/opentype-layout.php'), str(path), str(pdf), str(report_path)], check=True, capture_output=True)
        report = json.loads(report_path.read_text())
        font = TTFont(path)
        raw = path.read_bytes()
        self.require(report['fontSha256'] == hashlib.sha256(raw).hexdigest(), 'source hash')
        # Independent GSUB/GPOS engine, with explicit script/direction/features.
        hbfont = hb.Font(hb.Face(raw))
        hbfont.scale = (font['head'].unitsPerEm, font['head'].unitsPerEm)
        buffer = hb.Buffer()
        buffer.add_codepoints([ord(c) for c in report['source']])
        buffer.direction, buffer.script, buffer.language = 'ltr', 'latn', 'en'
        hb.shape(hbfont, buffer, {'liga': True, 'kern': True, 'mark': True})
        expected = [{'gid': i.codepoint, 'position': {'xAdvance': p.x_advance, 'yAdvance': p.y_advance,
                    'xOffset': p.x_offset, 'yOffset': p.y_offset}} for i, p in zip(buffer.glyph_infos, buffer.glyph_positions)]
        actual = [{key: item[key] for key in ['gid', 'position']} for item in report['glyphs']]
        self.require(expected == actual, 'HarfBuzz glyph/advance/offset mismatch')
        # pypdf independently resolves the emitted PDF object graph and decoded streams.
        page = PdfReader(pdf, strict=True).pages[0]
        composite = page['/Resources']['/Font']['/F1'].get_object()
        descendant = composite['/DescendantFonts'][0].get_object()
        top = None if kind == 'TrueType' else font['CFF '].cff.topDictIndex[0]
        if kind == 'TrueType':
            self.require(descendant['/Subtype'] == '/CIDFontType2' and descendant['/CIDToGIDMap'] == '/Identity', 'TrueType identity descendant')
            data = descendant['/FontDescriptor']['/FontFile2'].get_object().get_data()
            from io import BytesIO
            rebuilt = TTFont(BytesIO(data), checkChecksums=2)
            self.require('cmap' not in rebuilt and 'DSIG' not in rebuilt, 'TrueType omitted cmap and DSIG')
            self.require(rebuilt.getGlyphOrder() == font.getGlyphOrder(), 'TrueType glyph order preserved')
            for tag in font.reader.tables:
                if tag not in ['cmap', 'head', 'DSIG']:
                    self.require(font.getTableData(tag) == rebuilt.getTableData(tag), 'TrueType preserved table ' + tag)
        else:
            self.require(descendant['/Subtype'] == '/CIDFontType0' and '/CIDToGIDMap' not in descendant, 'CFF descendant kind')
            embedded = descendant['/FontDescriptor']['/FontFile3'].get_object()
            expected_subtype = '/CIDFontType0C' if kind == 'Cid' else '/OpenType'
            expected_bytes = font.getTableData('CFF ') if kind == 'Cid' else raw
            self.require(embedded['/Subtype'] == expected_subtype and embedded.get_data() == expected_bytes, 'FontFile3 bytes/subtype')
            self.require(hasattr(top, 'ROS') == (kind == 'Cid'), 'fixture CID identity')
        encoding = composite['/Encoding'].get_object().get_data().decode('ascii')
        to_unicode = composite['/ToUnicode'].get_object().get_data().decode('ascii')
        for item in report['glyphs']:
            name = font.getGlyphName(item['gid'])
            cid = int(name[3:]) if hasattr(top, 'ROS') and item['gid'] != 0 else item['gid']
            self.require(item['cid'] == cid, 'charset CID/GID mapping')
            self.require(re.search(r'<'+item['code']+r'>\s+'+str(cid)+r'\b', encoding, re.I) is not None, 'encoding CMap CID')
            unicode_hex = item['unicode'].encode('utf-16-be').hex()
            self.require(re.search(r'<'+item['code']+r'>\s+<'+unicode_hex+r'>', to_unicode, re.I) is not None, 'ToUnicode source sequence')
        aliases = [g for g in report['glyphs'] if g['unicode'] in ['A', 'Α']]
        self.require(len({g['code'] for g in aliases}) == 2 and len({g['cid'] for g in aliases}) == 1, 'aliases require distinct codes and same CID')
        self.require(any(g['unicode'] == 'fi' for g in report['glyphs']), 'ligature Unicode sequence')
        text = subprocess.check_output(['pdftotext', str(pdf), '-']).decode().strip('\n\f')
        self.require(text == report['source'], 'Poppler source text extraction')
        # A second PDF contains only independent fontTools outlines, placed using HarfBuzz.
        reference = pdf.with_name(pdf.stem + '-outline-reference.pdf')
        self.reference(font, buffer, report, reference)
        raster = self.compare_raster(pdf, reference)
        return {'kind': kind, 'fontSha256': report['fontSha256'], 'pdfSha256': hashlib.sha256(pdf.read_bytes()).hexdigest(),
                'glyphs': len(actual), 'text': text, 'raster': raster, 'status': 'passed'}

    def reference(self, font, buffer, report, path):
        glyph_set = font.getGlyphSet()
        advance_x = advance_y = 0
        scale = report['size'] / font['head'].unitsPerEm
        commands = []
        for info, position in zip(buffer.glyph_infos, buffer.glyph_positions):
            pen = OutlinePdfPen(glyph_set)
            glyph_set[font.getGlyphName(info.codepoint)].draw(pen)
            x = report['origin'][0] + (advance_x + position.x_offset) * scale
            y = report['origin'][1] + (advance_y + position.y_offset) * scale
            commands.extend(['q', f'{scale:.8g} 0 0 {scale:.8g} {x:.8g} {y:.8g} cm', *pen.commands, 'f', 'Q'])
            advance_x += position.x_advance
            advance_y += position.y_advance
        writer = PdfWriter()
        page = writer.add_blank_page(400, 150)
        stream = DecodedStreamObject()
        stream.set_data(('\n'.join(commands)+'\n').encode('ascii'))
        page[NameObject('/Contents')] = writer._add_object(stream)
        writer.write(path)

    def compare_raster(self, actual, reference):
        masks = []
        for pdf in [actual, reference]:
            subprocess.run(['pdftoppm', '-r', '576', '-singlefile', '-png', str(pdf), str(pdf.with_suffix(''))], check=True, capture_output=True)
            with Image.open(pdf.with_suffix('.png')) as image:
                self.require(image.size == (3200, 1200), 'raster dimensions')
                masks.append(image.convert('L').point(lambda value: 255 if value < 128 else 0))
        intersection = sum(v != 0 for v in ImageChops.darker(*masks).get_flattened_data())
        union = sum(v != 0 for v in ImageChops.lighter(*masks).get_flattened_data())
        self.require(union > 500, 'nonempty outline image')
        iou = intersection / union
        # Font scan conversion and path scan conversion can differ on their one-pixel edges.
        outside = [sum(v != 0 for v in ImageChops.subtract(a, b.filter(ImageFilter.MaxFilter(3))).get_flattened_data())
                   for a, b in [masks, list(reversed(masks))]]
        self.require(iou >= 0.96 and outside == [0, 0], f'outline raster mismatch: IoU={iou}, outside={outside}')
        return {'dpi': 576, 'width': 3200, 'height': 1200, 'intersectionOverUnion': iou, 'pixelsBeyondOnePixelEdge': outside}

    def require(self, condition, label):
        if not condition:
            raise ValueError(label)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--bootstrap', help='Optional development autoloader; never needed for stable installations.')
    args = parser.parse_args()
    oracle = CffPdfOracle(args.php, args.output, args.bootstrap)
    cases = [oracle.run(kind) for kind in ['Simple', 'Cid', 'TrueType']]
    report = {'status': 'passed', 'oracles': {name: importlib.metadata.version(name) for name in ['fonttools', 'uharfbuzz', 'pypdf', 'Pillow']},
              'harfbuzz': hb.version_string(), 'poppler': subprocess.run(['pdftoppm', '-v'], capture_output=True, text=True, check=True).stderr.splitlines()[0],
              'cases': cases, 'limits': ['Original simple/CID CFF and TrueType fixtures only; no universal shaping, hinting, rasterizer or complex-script claim.',
              'Raster comparison uses independent fontTools paths at HarfBuzz positions and Poppler for both PDF renderings.']}
    (args.output / 'report.json').write_text(json.dumps(report, indent=2, ensure_ascii=False)+'\n')
    print(json.dumps(report, indent=2, ensure_ascii=False))


if __name__ == '__main__':
    main()
