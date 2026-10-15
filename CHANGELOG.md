# Changelog

English | [Français](CHANGELOG.fr.md)

## Unreleased

## 0.1.9 - 2026-10-15

- Accept stable core ^0.8 while retaining the previously supported releases.
- Lock validation to published core 0.7.1; verify compatibility with the core 0.8 candidate and retain the existing converter API and bounded parsing.
- Update installation documentation in English and French; PHP 8.3–8.5.


## 0.1.8 — 2026-10-14

### Compatibility and scope

- Accepts stable core `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5 || ^0.6 || ^0.7`.
- Locks publication checks to released core `0.6.2`, retaining stable production dependencies and existing development tool versions.
- Preserves bounded static TrueType conversion and existing CID/Unicode mappings. PDF 2.0 metadata, tagging and security remain core writer mechanisms; font shaping is not added.
- Requires PHP 8.3 or later; CI validates PHP 8.3, 8.4 and 8.5 and the independent PDF oracles.

## 0.1.7 — 2026-10-14

### Compatibility

- Accepts stable core `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5 || ^0.6`.
- Locks release validation to published core `0.5.5` without changing development tool versions or commits.
- Checks the unchanged module against the core 0.6 candidate on PHP 8.3, 8.4 and 8.5.
- Preserves the TrueType conversion API, supported programs and limits; output PDF version rules remain in the core.

## 0.1.6 — 2026-10-14

### Bilingual documentation

- Makes English the default language for the README, guides and complete release history.
- Adds complete French counterparts with reciprocal language links and French documentation entrypoints.
- Translates explanatory example comments while preserving executable tokens and output data.
- Locks validation to published core 0.5.4 without changing other dependency versions or commits.
- Preserves API identifiers, original fixtures and runtime behaviour.
## 0.1.5 — 2026-10-14

### Installation and publication

- Publishes the package on Packagist and reduces installation to one
  `composer require` command in an application.
- Uses default Composer repositories and connects package metadata to its
  documentation at `https://pdftoolkit.github.io/`.
- Locks checks to stable core `roukmoute/pdf:0.5.3` without changing development
  tool versions.
- Verifies indexing of published versions and installation without VCS or
  local repositories. Preserves the PHP API, existing mechanisms and limits.

## 0.1.4 — 2026-10-14

### Documentation

- Connects shared documentation to `https://pdftoolkit.github.io/` and adds
  the stable fonts entrypoint at `/modules/fonts/`.
- Entrusts shared guides and site publication to
  `PDFToolkit/pdftoolkit.github.io`, with module sources pinned to their
  published versions and exact commits.
- Corrects README compatibility information to reflect the existing
  `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5` constraint.
- Preserves the API, conversions, dependencies and their locked versions;
  this release changes documentation only.

## 0.1.3 — 2026-10-13

### Compatibility

- Accepts stable core `roukmoute/pdf:^0.2 || ^0.3 || ^0.4 || ^0.5`.
- Locks checks to published core `0.4.0` under PHP 8.3, 8.4 and 8.5.
- Preserves the fonts API and limits; no additional font-format processing
  is added by this compatibility patch.

## 0.1.2 — 2026-10-13

### Compatibility

- Accepts stable core `roukmoute/pdf:^0.2 || ^0.3 || ^0.4`.
- Preserves the module API and external-data processing boundary.
- Checks stable core 0.3.0 and new core 0.4 objects in development
  under PHP 8.3, 8.4 and 8.5.

## 0.1.1 — 2026-10-13

### Compatibility

- Accepts core `roukmoute/pdf:^0.2 || ^0.3`, allowing installation with new
  PDF objects in the 0.3 series without changing the module API.
- Revalidates tests and independent PDFs with stable core `0.2.1`.
  No reading or conversion mechanism is added by this patch.
## 0.1.0 — 2026-10-13

First release of `pdftoolkit/pdf-fonts`, under `PDF\Fonts\TrueType`.
The module depends on core `roukmoute/pdf:^0.2`, which retains normalised PDF
objects; the core does not depend on font converters.

### Added

- Thirteen components to parse an external TrueType program and explicitly
  produce its PDF values.
- Reading sfnt tables, horizontal metrics, `glyf` outlines, Unicode `cmap`
  mappings and font names.
- Validation of table bounds and overlaps, glyph references, cycles and
  composite glyph depth.
- Configurable input budget with `FontProgramLimits` and UTF-8 text validation;
  no implicit file or URL access.
- `fsType` embedding permission checks.
- Program conversion for `/CIDFontType2`, preserving all glyphs while removing
  `cmap` and `DSIG`, then rebuilding checksums.
- Instantiated factories for descriptors and CID/GID mappings, with metric
  conversion to PDF units and embedded program verification.
- Explicit Identity-H encoding of the declared Unicode repertoire, without
  implicit font mutation.
- Complete example with OFL-licensed Noto Sans and a synthetic MIT fixture.
- Tests of invalid inputs, limits and PDF output; checks on PHP 8.3, 8.4 and 8.5.

Supported programs are static TrueType fonts with `glyf` outlines, on 64-bit
PHP. Collections, CFF, WOFF and variable fonts are rejected. Subsetting,
contextual shaping, ligatures, kerning and vertical writing are not implemented.
