# Wiki.js Migration Implementation Summary

This document summarizes the changes made to convert the migrate-confluence tool from MediaWiki XML output to Wiki.js Markdown output.

## Overview

The tool now outputs **GitHub Flavored Markdown (GFM)** files with YAML frontmatter for Wiki.js import, instead of MediaWiki XML. The core conversion pipeline remains the same (analyze → extract → convert → compose), but the output format has been completely restructured.

## Key Changes

### 1. Pandoc Converter (NEW)
**File:** `src/Converter/PandocMarkdown.php`

- New converter class that extends `ConverterBase`
- Uses `pandoc -f html -t gfm` instead of `-t mediawiki`
- Outputs GitHub Flavored Markdown (includes tables, task lists, strikethrough)

### 2. Macro Conversion Base Class (NEW)
**File:** `src/Converter/Processor/ConvertMacroToMarkdownBase.php`

- Replaces `ConvertMacroToTemplateBase` for Wiki.js output
- Converts Confluence macros to HTML `<blockquote>` elements with CSS classes
- Pandoc then converts these to Markdown blockquote syntax with Wiki.js styling

**Example transformation:**
```
Input (Confluence):
<ac:structured-macro ac:name="info">
  <ac:parameter ac:name="title">Important</ac:parameter>
  <ac:rich-text-body><p>Content</p></ac:rich-text-body>
</ac:structured-macro>

Output (Markdown):
> **Important**
> Content
{.is-info}
```

### 3. Updated Macro Processors

#### Info/Note/Tip/Warning Macros
**Files:**
- `src/Converter/Processor/ConvertInfoMacro.php`
- `src/Converter/Processor/ConvertNoteMacro.php`
- `src/Converter/Processor/ConvertTipMacro.php`
- `src/Converter/Processor/ConvertWarningMacro.php`

**Changes:**
- Now extend `ConvertMacroToMarkdownBase` instead of `ConvertMacroToTemplateBase`
- Map to Wiki.js blockquote classes:
  - `info` → `{.is-info}` (blue)
  - `note` → `{.is-warning}` (orange)
  - `tip` → `{.is-success}` (green)
  - `warning` → `{.is-danger}` (red)

#### Expand and Details Macros
**Files:**
- `src/Converter/Processor/ExpandMacro.php`
- `src/Converter/Processor/DetailsMacro.php`

**Changes:**
- Convert to HTML `<details>/<summary>` elements
- These work natively in Markdown (HTML pass-through)

**Example:**
```markdown
<details>
  <summary>Click to expand</summary>
  Content here
</details>
```

### 4. Link Processors

#### Page Links
**File:** `src/Converter/Processor/PageLink.php`

**Changes:**
- `makeLink()` now outputs `[label](/path)` instead of `[[Page|label]]`
- New `convertTitleToPath()` method converts MediaWiki titles to Wiki.js paths
  - `NS:Prefix/Page_Title` → `/ns/prefix/page-title`
- Broken links use HTML comments instead of categories

**Example:**
```markdown
Before: [[NS:Docs/Installation|Install Guide]]
After:  [Install Guide](/ns/docs/installation)
```

#### Attachment Links
**File:** `src/Converter/Processor/AttachmentLink.php`

**Changes:**
- Outputs `[label](/uploads/filename)` instead of `[[Media:file|label]]`
- All attachments go to `/uploads/` directory

**Example:**
```markdown
Before: [[Media:document.pdf|Download PDF]]
After:  [Download PDF](/uploads/document.pdf)
```

#### User Links
**File:** `src/Converter/Processor/UserLink.php`

**Changes:**
- Outputs `@username` (mention style) instead of `[[User:name]]`
- Alternative: Could output `[name](/user/name)` if Wiki.js has user profiles

### 5. Image Processor
**File:** `src/Converter/Processor/Image.php`

**Changes:**
- `getImageReplacement()` completely rewritten for Markdown syntax
- Outputs `![alt](/uploads/image.jpg){width=300}` instead of `[[File:image.jpg|300px]]`
- Supports Wiki.js extended syntax for width attributes
- Images with links: `[![alt](/uploads/img)](/page)`
- New `convertTitleToPath()` method for link targets

**Example:**
```markdown
Before: [[File:screenshot.png|300px|thumb|center|Screenshot]]
After:  ![Screenshot](/uploads/screenshot.png){width=300}
```

### 6. Code Block Processors

#### Restore Code (Postprocessor)
**File:** `src/Converter/Postprocessor/RestoreCode.php`

**Changes:**
- Outputs fenced code blocks instead of `<syntaxhighlight>` tags
- Format: `` ```lang\ncode\n``` ``
- Broken code macros use HTML comments

**Example:**
```markdown
Before: <syntaxhighlight lang="php">code</syntaxhighlight>
After:  ```php
        code
        ```
```

### 7. Main Converter
**File:** `src/Converter/ConfluenceConverter.php`

**Changes:**
- Now extends `PandocMarkdown` instead of `PandocHTML`
- `postprocessWikiText()` method updated:
  - Removed MediaWiki-specific replacements (template braces, list syntax)
  - Kept line break restoration (`###BREAK###`)
  - Added decoding for `<details>/<summary>` tags
  - Updated attachment section to use Markdown heading `## Attachments`
- `buildMediaExcludeList()` updated to parse Markdown link syntax
- `addAdditionalAttachments()` creates Markdown list instead of template

### 8. Wiki.js Composer (NEW)
**File:** `src/Composer/WikiJsComposer.php`

**Replaces:** `ConfluenceComposer.php` (which is no longer used)

**Key features:**
- Creates individual `.md` files instead of single MediaWiki XML file
- Adds YAML frontmatter to each page:
  ```yaml
  ---
  title: Page Title
  description: ''
  tags:
    - namespace
    - category
  author: username
  date: 2024-01-01T12:00:00Z
  ---
  ```
- Organizes pages by path: `pages/ns/prefix/page-name.md`
- Copies all attachments to `result/uploads/` directory
- No template pages needed (Wiki.js doesn't have templates)
- No SVG icons needed (blockquotes have built-in styling)

**Output structure:**
```
result/
├── pages/
│   ├── main-page.md
│   ├── ns/
│   │   └── prefix/
│   │       └── page-name.md
│   └── ...
└── uploads/
    ├── image1.jpg
    ├── document.pdf
    └── ...
```

### 9. Compose Command
**File:** `src/Command/Compose.php`

**Changes:**
- `processFiles()` method overridden to use `WikiJsComposer` directly
- No longer uses MediaWiki XML builder
- Creates `result/uploads/` directory instead of `result/images/`

## What Was Removed

### Templates (27 files)
All MediaWiki templates from `src/Composer/_defaultpages/Template/` are no longer needed:
- Info, Note, Tip, Warning (handled by blockquotes)
- Panel, SimplePanel (handled by HTML or blockquotes)
- Expand, Excerpt (handled by `<details>`)
- Jira, Drawio, Gliffy (converted to direct links or images)
- SMW queries (PageTree, RecentlyUpdated, SpaceDetails, ContentByLabel)

### SVG Icons (4 files)
Wiki.js blockquotes have built-in icons, no need for custom SVGs.

### MediaWiki-Specific Code
- Template syntax generation (`{{Template|param=value}}`)
- Category tags (`[[Category:Name]]`) - replaced with YAML tags
- Namespace syntax (`NS:Title`) - replaced with path format (`/ns/title`)
- `<syntaxhighlight>` tags - replaced with fenced code blocks
- MediaWiki XML builder integration

## Error Tracking

**Before (MediaWiki):**
```wikitext
[[Category:Broken_page_link]]
[[Category:Broken_image]]
[[Category:Broken_macro/code]]
```

**After (Wiki.js):**
```markdown
<!-- Broken page link -->
<!-- Broken image -->
<!-- Broken code macro: category-name -->
```

Broken items can be found by searching for HTML comments in the output Markdown files.

## Importing into Wiki.js

Wiki.js supports multiple import methods:

### Option 1: Git Sync (Recommended)
1. Push the `result/pages/` directory to a Git repository
2. Configure Wiki.js to sync from that repository
3. Upload files from `result/uploads/` to Wiki.js storage

### Option 2: GraphQL API
Create a script that:
1. Reads each `.md` file
2. Parses YAML frontmatter for metadata
3. Creates pages via `mutation { pages { create(...) } }`
4. Uploads files from `result/uploads/`

### Option 3: Filesystem Storage
1. Place `result/pages/*.md` files in Wiki.js data directory
2. Upload `result/uploads/` files to Wiki.js storage
3. Trigger Wiki.js to scan and import

## Testing Status

⚠️ **Unit tests need updating** - Task #15 is still pending. All existing tests expect MediaWiki wikitext output and will need their expected outputs updated to Markdown format.

To update tests:
1. Run each test to see the actual Markdown output
2. Verify the Markdown is correct
3. Update test expectations
4. Re-run tests to ensure they pass

## Remaining Work

### High Priority
1. **Update unit tests** (Task #11, #15)
   - Update expected outputs in all processor tests
   - Verify Markdown output is correct
   - Test files are in `tests/phpunit/Converter/Processor/`

2. **Update structural macro processors** (Task #10)
   - StructuredMacroToc (simplify or remove - Wiki.js auto-generates TOC)
   - StructuredMacroJira (convert to direct links)
   - StructuredMacroNoFormat (use fenced code blocks)
   - Panel/Section processors (adapt for Markdown/HTML)

3. **Update postprocessors** (Task #11)
   - NestedHeadings (adapt for `#` syntax instead of `==`)
   - FixLineBreakInHeadings (adapt for Markdown)
   - FixImagesWithExternalUrl (adapt for Markdown image syntax)

### Medium Priority
- Test full pipeline with sample Confluence export
- Verify YAML frontmatter parsing
- Test Wiki.js import (Git sync or API)
- Document configuration options for Wiki.js

### Low Priority
- Update documentation in README
- Add Wiki.js-specific configuration options
- Create migration guide for users

## Configuration

The tool still accepts YAML config via `--config` flag:

```yaml
# Optional configuration
categories:
  - global-tag1
  - global-tag2

# Space prefixes (used in path generation)
space-prefix:
  ABC: "docs/"
  XYZ: "wiki/"

# Main page title
mainpage: "Home"
```

## Dependencies

- **PHP >= 8.2** with `xml` extension
- **Pandoc >= 3.1.6** (now using `-t gfm` flag)
- **symfony/yaml** component (for YAML frontmatter generation)

## Summary of Files Changed

### New Files (3)
- `src/Converter/PandocMarkdown.php`
- `src/Converter/Processor/ConvertMacroToMarkdownBase.php`
- `src/Composer/WikiJsComposer.php`

### Modified Files (14)
- `src/Converter/ConfluenceConverter.php`
- `src/Converter/Processor/PageLink.php`
- `src/Converter/Processor/AttachmentLink.php`
- `src/Converter/Processor/UserLink.php`
- `src/Converter/Processor/Image.php`
- `src/Converter/Processor/ConvertInfoMacro.php`
- `src/Converter/Processor/ConvertNoteMacro.php`
- `src/Converter/Processor/ConvertTipMacro.php`
- `src/Converter/Processor/ConvertWarningMacro.php`
- `src/Converter/Processor/ExpandMacro.php`
- `src/Converter/Processor/DetailsMacro.php`
- `src/Converter/Postprocessor/RestoreCode.php`
- `src/Command/Compose.php`
- `WIKI_JS_MIGRATION.md` (this file)

### Removed/Obsolete
- `src/Composer/_defaultpages/` (27 template files + 4 SVG icons)
- MediaWiki XML output logic in ConfluenceComposer (file still exists but unused)

## Next Steps

1. **Complete remaining tasks** (see "Remaining Work" above)
2. **Test with real data** - run full pipeline on a Confluence export
3. **Verify Wiki.js import** - test importing the output into Wiki.js
4. **Update documentation** - README, usage guide, etc.
5. **Consider creating a release** - tag version 2.0 for Wiki.js support

---

**Total Implementation:** ~17 files changed, 3 new files, ~2000+ lines of code modified
**Completion Status:** ~70% complete (core conversion done, testing/polish remaining)
