# AseqBase TinyMCE Editor

This package installs a self-contained rich content editor on an AseqBase website. It does not modify files under the framework `base` or `aseq` directories.

## Features

- Visual WYSIWYG editing without exposing Markdown markers
- Font family and font size selectors
- Persian and Latin bundled fonts
- Bold, italic, underline, strike-through, headings, alignment, justification, and RTL/LTR controls
- Bullet, numbered, and checklist styles
- Links, safe buttons, resizable images, tables, horizontal rules, block quotes, and colored callouts
- Find and replace inside the editor
- Full-content administration search with Persian/Arabic character normalization
- Server-side HTML sanitization before content is stored
- Dry-run Markdown migration with batched conversion, database backup, and guarded rollback
- English source strings with AseqBase translation support

## Requirements

- PHP 8.2 or later
- PHP DOM extension
- AseqBase package installer or write access to the website sequence directory

## Installation

Install this repository as an AseqBase package into the website sequence directory (for example, `pasenv`). The package folders are merged into that sequence by the AseqBase installer. No framework file replacement is required.

For manual installation, copy these package directories to the matching directories of the target sequence:

- `asset`
- `initialize`
- `model`
- `route`

Import `translations/tinymce-en-fa.csv` through the AseqBase translation importer. The XLSX file contains the same two-column lexicon for review and editing.

After installation, open `/administrator/content/contents`. The initializer registers the module route ahead of the generic administrator route only for this URL.

## Migrating legacy Markdown content

Open `/administrator/tinymce/migration` as an administrator. Review the dry-run totals and samples before applying any changes. The migration converts only Markdown or plain-text records; existing HTML, mixed HTML/Markdown, unsupported custom directives, and unsafe legacy button URLs are skipped.

Apply the migration in batches. Before each content update, the original content and update time are stored in the module-owned `TinyMCE_ContentBackup` table (with the website database prefix). Rollback restores only records whose migrated HTML has not been edited afterward.

Always run the migration against a local copy of the production database first. Keep the original SQL backup until the migrated content has been reviewed on the target website.

## Security and licensing

The package bundles TinyMCE Community 8.9.0 under GPL-2.0-or-later. This integration package is licensed under GPL-3.0-only. Submitted content is sanitized on the server; unsafe elements, event handlers, URL schemes, and styles are removed.

## Updating TinyMCE

Replace only `asset/struct/tinymce` with a tested official `tinymce-dist` release, preserving the custom `asset/struct/tinymce/pas` directory. Update `TinyMCEVersion` in `model/plugin/TinyMCE.php` and `-bootstrap/manifest.json`, then repeat the targeted checks documented in `CHANGED_FILES.md`.
