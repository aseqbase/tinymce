# Changed Files

This delivery is based on the `aseqbase/tinymce` package scaffold. No file under the AseqBase `base` or `aseq` framework directories is changed by this module.

## Modified scaffold files

- `composer.json` — package description and PHP/DOM requirements
- `README.md` — features, installation, security, and update instructions
- `-bootstrap/manifest.json` — valid package metadata and TinyMCE version
- `initialize/tinymce.php` — module initialization, route registration, and admin menu
- `model/plugin/TinyMCE.php` — editor configuration and modular route interception
- `model/module/TinyMCE.php` — TinyMCE assets, toolbar, fonts, buttons, callouts, checklists, images, and translations
- `route/administrator/tinymce/editor.php` — English settings page title
- `asset/struct/tinymce/**` — official TinyMCE distribution updated from 8.0.1 to 8.9.0

The unused bundled `plugins/help/js/i18n/keynav/ar.js` and `fa.js` files are excluded. Persian UI strings are supplied only through the AseqBase lexicon generated from the translation CSV/XLSX files.

## New integration files

- `model/library/TinyMCEContentSanitizer.php`
- `model/module/TinyMCEContentTable.php`
- `route/administrator/tinymce/contents.php`
- `asset/struct/tinymce/pas/content.css`
- `asset/struct/tinymce/pas/editor.css`
- `asset/struct/tinymce/pas/fonts/B Nazanin.ttf`
- `asset/struct/tinymce/pas/fonts/Vazirmatn.ttf`
- `asset/struct/tinymce/pas/fonts/B Yekan.ttf`
- `asset/struct/tinymce/pas/fonts/B Titr.ttf`
- `asset/struct/tinymce/pas/fonts/B Koodak.ttf`
- `asset/struct/tinymce/pas/fonts/Calibri.ttf`
- `translations/tinymce-en-fa.csv`
- `translations/PAS_TinyMCE_Translations_EN_FA.xlsx`
- `INSTALL-FA.md`
- `CHANGED_FILES.md`

`B Tir.ttf` is intentionally excluded and replaced by the supplied `B Titr.ttf` font.

## Targeted validation completed

- PHP syntax check for every module PHP file
- Composer and package manifest JSON validation
- Server-side sanitizer security test
- Persian/Arabic search normalization test
- Generated editor configuration test
- Translation row, duplicate, and blank-value checks
- Excel workbook inspection and visual rendering
- Font internal-family verification
- Local HTTP checks for TinyMCE, CSS, and font assets

The final authenticated browser test must be repeated after installation on the target website because this delivery intentionally contains no login bypass or credentials.
