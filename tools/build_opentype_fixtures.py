#!/usr/bin/env python3
"""Build MIT CFF simple/CID fonts with nonidentity CIDs, aliases and layout rules."""
import argparse
from pathlib import Path
import tempfile
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.t2CharStringPen import T2CharStringPen
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.cffLib import FDArrayIndex, FDSelect, FontDict
from fontTools.feaLib.builder import addOpenTypeFeaturesFromString


def build(path: Path, cid: bool, truetype: bool = False) -> None:
    labels = ['.notdef', 'space', 'A', 'V', 'f', 'i', 'fi', 'acute']
    names = ['.notdef'] + [f'cid{10*i:05d}' for i in range(1, len(labels))] if cid else labels
    mapping = dict(zip(labels, names))
    widths = dict(zip(labels, [500, 250, 600, 600, 350, 250, 550, 0]))
    shapes = {
        '.notdef': [(50, 0), (450, 0), (450, 700), (50, 700)],
        'space': [], 'A': [(40, 0), (300, 700), (560, 0), (450, 0), (300, 430), (150, 0)],
        'V': [(40, 700), (150, 700), (300, 170), (450, 700), (560, 700), (300, 0)],
        'f': [(50, 0), (50, 650), (300, 700), (300, 600), (150, 550), (150, 0)],
        'i': [(70, 0), (170, 0), (170, 450), (70, 450)],
        'fi': [(50, 0), (50, 650), (300, 700), (300, 600), (150, 550), (150, 0), (450, 0), (450, 450), (350, 450), (350, 0)],
        'acute': [(0, 0), (120, 100), (190, 100), (70, 0)],
    }
    chars = {}
    for label, name in mapping.items():
        pen = TTGlyphPen(None) if truetype else T2CharStringPen(widths[label], None)
        points = shapes[label]
        if points:
            pen.moveTo(points[0])
            for point in points[1:]:
                pen.lineTo(point)
            pen.closePath()
        chars[name] = pen.glyph() if truetype else pen.getCharString()
    fb = FontBuilder(1000, isTTF=truetype)
    fb.setupGlyphOrder(names)
    fb.setupCharacterMap({32: mapping['space'], 65: mapping['A'], 913: mapping['A'], 86: mapping['V'],
                         102: mapping['f'], 105: mapping['i'], 0x301: mapping['acute']})
    fb.setupHorizontalMetrics({mapping[label]: (width, 0) for label, width in widths.items()})
    fb.setupHorizontalHeader(ascent=900, descent=-200)
    label = 'PDFToolkitLayoutTrueType' if truetype else ('PDFToolkitCffCid' if cid else 'PDFToolkitCffSimple')
    fb.setupNameTable({'familyName': label, 'styleName': 'Regular', 'uniqueFontIdentifier': label,
                       'fullName': label, 'psName': label, 'version': 'Version 1.0'})
    fb.setupOS2(sTypoAscender=900, sTypoDescender=-200, usWinAscent=900, usWinDescent=200,
                sCapHeight=700, fsType=0)
    fb.setupPost()
    if truetype:
        fb.setupGlyf(chars)
        fb.setupMaxp()
    else:
        fb.setupCFF(label, {'FullName': label}, chars, {'defaultWidthX': 0, 'nominalWidthX': 0})
    if cid:
        cff = fb.font['CFF '].cff
        top = cff.topDictIndex[0]
        top.ROS = ('Adobe', 'Identity', 0)
        top.CIDCount = 71
        fd = FontDict(strings=top.strings, GlobalSubrs=cff.GlobalSubrs)
        fd.Private = top.Private
        fd.FontName = label
        top.FDArray = FDArrayIndex()
        top.FDArray.append(fd)
        top.FDSelect = FDSelect(format=3)
        top.FDSelect.gidArray = [0] * len(names)
        top.CharStrings.fdArray = top.FDArray
        top.CharStrings.fdSelect = top.FDSelect
        for name in names:
            top.CharStrings[name].fdSelectIndex = 0
        del top.Private
        if hasattr(top, 'Encoding'):
            del top.Encoding
    fea = f'''languagesystem DFLT dflt;
languagesystem latn dflt;
feature liga {{ sub {mapping['f']} {mapping['i']} by {mapping['fi']}; }} liga;
feature kern {{ pos {mapping['A']} {mapping['V']} -90; }} kern;
markClass {mapping['acute']} <anchor 0 0> @TOP;
feature mark {{ pos base {mapping['A']} <anchor 250 720> mark @TOP; }} mark;
'''
    addOpenTypeFeaturesFromString(fb.font, fea)
    fb.font['head'].created = fb.font['head'].modified = 2082844800
    fb.font.recalcTimestamp = False
    path.parent.mkdir(parents=True, exist_ok=True)
    fb.save(path)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1] / 'tests/Fixtures/fonts'
    for name, cid, truetype in [('PDFToolkitCffSimple.otf', False, False), ('PDFToolkitCffCid.otf', True, False), ('PDFToolkitLayoutTrueType.ttf', False, True)]:
        path = root / name
        if args.check:
            with tempfile.TemporaryDirectory() as temporary:
                candidate = Path(temporary) / name
                build(candidate, cid, truetype)
                if candidate.read_bytes() != path.read_bytes():
                    raise ValueError(f'{name} differs from its generator')
        else:
            build(path, cid, truetype)
        print(name)


if __name__ == '__main__':
    main()
