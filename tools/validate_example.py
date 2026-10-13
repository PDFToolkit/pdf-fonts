"""Independently inspect the generated font PDF with pypdf and the original sfnt bytes."""

import argparse
from pathlib import Path
import struct

from pypdf import PdfReader


ROOT = Path(__file__).resolve().parents[1]
LINES = [
    "Une police incorporée, objet par objet.",
    "Français : été, cœur, Noël. Ελληνικά : Αθήνα.",
    "Chaque caractère conserve son identité Unicode.",
]


def require(condition, message):
    if not condition:
        raise ValueError(message)


def sfnt_tables(data):
    require(data[:4] == b"\0\1\0\0", "Expected a TrueType sfnt program")
    count = struct.unpack_from(">H", data, 4)[0]
    tables = {}
    for index in range(count):
        tag, checksum, offset, length = struct.unpack_from(">4sIII", data, 12 + index * 16)
        require(offset + length <= len(data), "Font table exceeds its program")
        require(tag not in tables, "Duplicate font table")
        tables[tag] = data[offset:offset + length]
    return tables


def glyph_id(cmap, point):
    candidates = []
    for index in range(struct.unpack_from(">H", cmap, 2)[0]):
        platform, encoding, offset = struct.unpack_from(">HHI", cmap, 4 + index * 8)
        format_number = struct.unpack_from(">H", cmap, offset)[0]
        if (platform == 0 or (platform == 3 and encoding in [1, 10])) and format_number in [4, 12]:
            candidates.append((format_number, offset))
    require(bool(candidates), "The demonstration font needs a Unicode cmap")
    format_number, offset = max(candidates)
    table = cmap[offset:]
    if format_number == 12:
        for index in range(struct.unpack_from(">I", table, 12)[0]):
            first, last, glyph = struct.unpack_from(">III", table, 16 + index * 12)
            if first <= point <= last:
                return glyph + point - first
        return 0
    if point > 0xFFFF:
        return 0
    count = struct.unpack_from(">H", table, 6)[0] // 2
    for index in range(count):
        last = struct.unpack_from(">H", table, 14 + index * 2)[0]
        first = struct.unpack_from(">H", table, 16 + count * 2 + index * 2)[0]
        if first <= point <= last:
            delta = struct.unpack_from(">h", table, 16 + count * 4 + index * 2)[0]
            range_position = 16 + count * 6 + index * 2
            range_offset = struct.unpack_from(">H", table, range_position)[0]
            if range_offset == 0:
                return (point + delta) & 0xFFFF
            glyph = struct.unpack_from(">H", table, range_position + range_offset + (point - first) * 2)[0]
            return 0 if glyph == 0 else (glyph + delta) & 0xFFFF
    return 0


def validate(path):
    reader = PdfReader(path, strict=True)
    require(len(reader.pages) == 1, "Font example must have exactly one page")
    text = reader.pages[0].extract_text()
    for expected in LINES:
        require(expected in text, "Unicode extraction failed: " + expected)
    font = reader.pages[0]["/Resources"]["/Font"]["/F1"]
    require(font["/Subtype"] == "/Type0" and font["/Encoding"] == "/Identity-H", "Incorrect Type0 font encoding")
    descendant = font["/DescendantFonts"][0].get_object()
    require(descendant["/Subtype"] == "/CIDFontType2", "Expected a CIDFontType2 descendant")
    descriptor = descendant["/FontDescriptor"]
    require(font["/BaseFont"] == descendant["/BaseFont"] == descriptor["/FontName"], "Font names differ")
    require(font["/ToUnicode"].get_data().startswith(b"/CIDInit"), "Missing ToUnicode CMap")
    original = sfnt_tables((ROOT / "examples/assets/fonts/NotoSans-Regular.ttf").read_bytes())
    font_file = descriptor["/FontFile2"]
    data = font_file.get_data()
    require(font_file["/Length1"] == len(data), "FontFile2 Length1 differs from its decoded data")
    embedded = sfnt_tables(data)
    require(set(embedded) == set(original) - {b"cmap", b"DSIG"}, "Unexpected CID font table removal")
    for tag in embedded:
        actual, expected = embedded[tag], original[tag]
        if tag == b"head":
            actual = actual[:8] + b"\0" * 4 + actual[12:]
            expected = expected[:8] + b"\0" * 4 + expected[12:]
        require(actual == expected, "A complete font table changed: " + repr(tag))
    padded = data + b"\0" * ((-len(data)) % 4)
    require(sum(struct.unpack(">" + "I" * (len(padded) // 4), padded)) & 0xFFFFFFFF == 0xB1B0AFBA,
            "Incorrect independently calculated sfnt checksum")
    glyphs = [0] + [glyph_id(original[b"cmap"], ord(character)) for character in dict.fromkeys("".join(LINES))]
    require(all(glyph > 0 for glyph in glyphs[1:]), "The demonstration font lacks a required glyph")
    require(descendant["/CIDToGIDMap"].get_data() == struct.pack(">" + "H" * len(glyphs), *glyphs),
            "CIDToGIDMap differs from the original font cmap")
    units_per_em = struct.unpack_from(">H", original[b"head"], 18)[0]
    number_of_metrics = struct.unpack_from(">H", original[b"hhea"], 34)[0]
    expected_widths = [struct.unpack_from(">H", original[b"hmtx"], min(glyph, number_of_metrics - 1) * 4)[0] * 1000 / units_per_em for glyph in glyphs]
    widths = descendant["/W"]
    require(len(widths) == 2 and widths[0] == 0 and len(widths[1]) == len(expected_widths), "Incorrect CID width array")
    require(all(abs(float(actual) - expected) < 1e-8 for actual, expected in zip(widths[1], expected_widths)),
            "PDF glyph widths differ from the original hmtx metrics")
    for key, offset in [("/Ascent", 68), ("/Descent", 70), ("/CapHeight", 88)]:
        expected = struct.unpack_from(">h", original[b"OS/2"], offset)[0] * 1000 / units_per_em
        require(abs(float(descriptor[key]) - expected) < 1e-8, "Incorrect descriptor metric: " + key)
    expected_box = [value * 1000 / units_per_em for value in struct.unpack_from(">hhhh", original[b"head"], 36)]
    require(all(abs(float(actual) - expected) < 1e-8 for actual, expected in zip(descriptor["/FontBBox"], expected_box)),
            "Incorrect font bounding box")
    require(descriptor["/StemV"] == 80 and descriptor["/Flags"] == 4, "Explicit descriptor values changed")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pdf", type=Path)
    validate(parser.parse_args().pdf)
    print("Independent font checks passed: Unicode, PDF graph, original glyph mappings/metrics, full sfnt program and checksum.")
