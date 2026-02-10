# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**migrate-confluence** is a CLI tool that converts Confluence space exports (XML format) into MediaWiki import-compatible data. It processes Confluence Storage Format XML through a four-stage pipeline: analyze → extract → convert → compose.

**Prerequisites:**
- PHP >= 8.2 with `xml` extension
- Pandoc >= 3.1.6 (must be in PATH)

## Commands

### Building and Installing
```bash
# Install dependencies
composer update --no-dev

# Build PHAR distribution
box compile
# Output: dist/migrate-confluence.phar
```

### Testing
```bash
# Run all unit tests
composer unittest
# Or directly: vendor/phpunit/phpunit/phpunit --configuration .phpunit.xml

# Run single test file
vendor/bin/phpunit tests/phpunit/Converter/Processor/ImageTest.php

# Run single test method
vendor/bin/phpunit --filter testMethodName tests/phpunit/Converter/Processor/ImageTest.php

# Check coding conventions (parallel-lint, minus-x, phpcs)
composer test

# Auto-fix coding style
composer fix

# Run static analysis (Phan)
composer lint
```

### Running the Tool
The tool uses a four-stage pipeline. Each stage must be run in order:
```bash
# Stage 1: Analyze Confluence export, create working files
migrate-confluence analyze --src input/ --dest workspace/

# Stage 2: Extract page contents, attachments, images
migrate-confluence extract --src input/ --dest workspace/

# Stage 3: Convert Confluence XML to MediaWiki WikiText
migrate-confluence convert --src workspace/ --dest workspace/

# Stage 4: Compose final MediaWiki XML import file
migrate-confluence compose --src workspace/ --dest workspace/

# Optional: Validate migration output
migrate-confluence check-result --src workspace/
```

**Configuration:** All commands accept `--config /path/to/config.yaml`. See `doc/config.sample.yaml` for options like namespace prefixes, main page name, categories, and NSFileRepo compatibility.

## Architecture

### Core Conversion Pipeline

The conversion happens in `src/Converter/ConfluenceConverter.php` through a multi-phase pipeline:

```
Raw Confluence XML (.mraw files)
  ↓
IPreprocessor (string-level fixes, e.g., CDATA closing)
  ↓
XPath-based macro replacements (legacy macros handled inline)
  ↓
IProcessor pipeline (43 processors, DOM manipulation)
  ↓
Pandoc HTML → MediaWiki WikiText conversion
  ↓
IPostprocessor pipeline (7 postprocessors, string cleanup)
  ↓
MediaWiki WikiText output
```

### Processor Pattern (The Heart of the Conversion)

**All processors implement `IProcessor`** with a single method: `process(DOMDocument $dom): void`

They manipulate the DOM tree **before** Pandoc conversion. Execution order in `ConfluenceConverter::runProcessors()` matters — processors run sequentially.

#### Three Base Classes for Common Patterns:

1. **`StructuredMacroProcessorBase`** — For macros that become HTML `<div>` elements
   - Finds `<ac:structured-macro ac:name="X">`
   - Extracts parameters as JSON `data-params` attribute
   - Moves `<ac:rich-text-body>` children into replacement `<div class="ac-X">`
   - Used by: Panel, Section, Column, TOC, etc.

2. **`ConvertMacroToTemplateBase`** — For macros that map to MediaWiki templates
   - Converts `<ac:structured-macro>` to `{{TemplateName|param=value|body=...}}`
   - Extracts `<ac:parameter>` elements as template params
   - Uses `###BREAK###` placeholders (Pandoc strips line breaks, these are restored later)
   - Used by: Info, Note, Warning, Tip, Expand, Details, Status, TaskList
   - Subclasses only need to implement `getMacroName()` and `getWikiTextTemplateName()`

3. **`LinkProcessorBase`** — For Confluence links (`<ac:link>`)
   - Finds all `<ac:link>` elements with specific child types (page, attachment, user)
   - Resolves target titles/filenames via `ConversionDataLookup` (uses data buckets)
   - Builds `[[Page|label]]` or `[[Media:file]]` wikitext
   - Inserts `[[Category:Broken_X_link]]` when resolution fails
   - Used by: PageLink, AttachmentLink, UserLink

#### Standalone Processors (Complex Logic):

- **`Image`** — Handles `<ac:image>` with `<ri:url>` (external) or `<ri:attachment>` (file). Resolves via ConversionDataLookup, builds `[[File:name|params]]`, handles image-within-link cases.
- **`PreserveCode`** / **`RestoreCode`** — Wraps code blocks in placeholders to protect from Pandoc, restores as `<syntaxhighlight lang="X">` after conversion.
- **`Emoticon`** — Converts Confluence emoticons to Unicode or images.
- **`ConvertTaskListMacro`** — Converts task lists to checkbox syntax `[x]` / `[]`.

### Data Flow Through the System

**Key data structures (DataBuckets):**
- `page-id-to-title-map` — Maps Confluence page IDs to target MediaWiki titles
- `title-attachments` — Lists attachments per page
- `space-id-to-prefix-map` — Maps space IDs to namespace prefixes
- `filenames-to-filetitles-map` — Original filename → sanitized target filename
- `attachment-confluence-file-key-to-target-filename-map` — Composite key (spaceId---pageTitle---filename) → target file

**Lookup pattern:**
```php
$dataLookup = ConversionDataLookup::newFromBuckets($dataBuckets);
$targetTitle = $dataLookup->getTargetTitleFromConfluencePageKey($key);
$targetFile = $dataLookup->getTargetFileTitleFromConfluenceFileKey($key);
```

Buckets are loaded from workspace PHP files created during the `analyze` stage, allowing manual adjustments before conversion.

### Postprocessors (After Pandoc)

**`IPostprocessor`** operates on the WikiText string after Pandoc conversion:
```php
public function postprocess(string $wikiText): string;
```

Examples:
- **`RestoreCode`** — Restores preserved code blocks as `<syntaxhighlight>`
- **`NestedHeadings`** — Fixes `* == Heading ==` patterns (lists with headings)
- **`FixLineBreakInHeadings`** — Removes line breaks from heading syntax
- **`RestorePStyleTag`** — Restores preserved `<p>` tags with special attributes

### Composer Stage

**`ConfluenceComposer`** assembles the final output:
- Reads converted `.wiki` files from workspace
- Bundles into MediaWiki XML format (`<mediawiki><page><revision><text>`)
- Includes 27 template pages from `src/Composer/_defaultpages/Template/`
- Includes 4 SVG icons (Info, Note, Tip, Warning)
- Outputs to `result/output.xml` and `result/images/`

### Pandoc Integration

The tool extends `HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML` (vendor library). Pandoc is invoked as:
```bash
pandoc -f html -t mediawiki $path
```

To change output format (e.g., for Wiki.js Markdown instead of MediaWiki), you'd need to:
1. Override `PandocHTML` to change `-t mediawiki` to `-t gfm` or `-t commonmark_x`
2. Rewrite processors to output Markdown syntax instead of WikiText
3. Replace `ConfluenceComposer` to output individual `.md` files instead of MediaWiki XML

## File Structure

```
src/
├── Analyzer/              # Stage 1: Parse Confluence export
├── Extractor/             # Stage 2: Extract content/attachments
├── Converter/             # Stage 3: Transform content
│   ├── Processor/         # 43 processors for macros/links/images
│   ├── Preprocessor/      # Pre-conversion string fixes
│   ├── Postprocessor/     # Post-Pandoc string cleanup
│   └── ConfluenceConverter.php  # Main conversion orchestrator
├── Composer/              # Stage 4: Final output assembly
│   └── _defaultpages/     # MediaWiki templates and icons
├── Command/               # CLI command handlers (5 commands)
└── Utility/               # Helpers (TitleBuilder, FilenameBuilder, CQLParser, etc.)

tests/phpunit/
├── Converter/Processor/   # 23+ processor tests
└── Utility/               # Utility tests
```

## Important Patterns

### Adding a New Macro Processor

For a simple macro-to-template conversion:
```php
class ConvertMyMacro extends ConvertMacroToTemplateBase {
    protected function getMacroName(): string { return 'my-macro'; }
    protected function getWikiTextTemplateName(): string { return 'MyTemplate'; }
}
```
Then add to the processor list in `ConfluenceConverter::runProcessors()`.

### Category-Based Error Tracking

When a processor can't convert something, it inserts a category tag:
```php
$replacement .= '[[Category:Broken_macro/macro-name]]';
```
This allows users to find and manually fix issues post-migration using MediaWiki's category system.

### Processor Execution Order

Order matters! Early processors set up structure, later ones refine:
1. Preserve/convert macros → Templates
2. Structural elements → Sections, Panels, Columns
3. Links and images (require lookup data)
4. Code blocks (preserve before Pandoc, restore after)

See `ConfluenceConverter::runProcessors()` line 234+ for the canonical order.

## Testing Strategy

Tests are in `tests/phpunit/`. Key patterns:
- Processor tests use real Confluence XML fragments as input
- Compare actual output against expected MediaWiki syntax
- Test data in `tests/phpunit/data/`
- Use `DOMDocument` for XML manipulation in tests, just like production code

## Configuration

The optional YAML config file supports:
- `mainpage` — Main page title (default: "Main Page")
- `space-prefix` — Map space keys to namespace prefixes (e.g., `ABC: "NS:ABC/"`)
- `categories` — Global categories to add to all pages
- `ext-ns-file-repo-compat` — Enable NSFileRepo extension compatibility

## Output for MediaWiki Import

**Result directory structure:**
```
result/
├── output.xml              # MediaWiki XML import file
├── images/                 # All attachments and images
├── Template/               # 27 template pages
└── File/                   # 4 SVG icon files
```

**Import into MediaWiki:**
```bash
php maintenance/importImages.php /path/to/result/images/
php maintenance/importDump.php /path/to/result/output.xml
```

## Known Limitations

Not migrated:
- User identities (migration creates placeholder pages)
- Comments on pages
- Blog posts
- Space-level files not attached to pages
- Some macros (see `Broken_macro/*` categories after import)
