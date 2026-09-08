<?php

namespace MiMFa\Module;

use MiMFa\Library\Struct;

class TinyMCE extends Module
{
    private static bool $contentStylesRegistered = false;
    private static bool $editorRegistered = false;

    public static function RegisterContentStyles(): void
    {
        if (self::$contentStylesRegistered || !isset(\_::$Front))
            return;

        self::$contentStylesRegistered = true;
        $source = asset(\_::$Address->StructRootDirectory, 'pas/content.css', optimize: false);
        if ($source)
            \_::$Front->Libraries[] = Struct::Style(null, $source);
    }

    public static function RegisterEditor(): void
    {
        if (self::$editorRegistered)
            return;

        self::$editorRegistered = true;
        self::RegisterContentStyles();

        $script = asset(\_::$Address->StructRootDirectory, 'tinymce/tinymce.min.js', optimize: false);
        if (!$script)
            return;

        \_::$Front->Libraries[] = Struct::Script(null, $script);
        \_::$Front->Finals[] = Struct::Script(self::BuildInitializationScript());
    }

    private static function BuildInitializationScript(): string
    {
        $plugin = \_::$Joint->TinyMCE ?? null;
        $direction = strtolower((string)(\_::$Front->Translate->Direction ?? 'ltr')) === 'rtl' ? 'rtl' : 'ltr';
        $language = strtolower((string)(\_::$Front->Translate->Language ?? 'en'));
        $language = preg_match('/^[a-z]{2}(?:[_-][a-z]{2})?$/', $language) ? $language : 'en';
        $labels = self::TranslatedLabels();
        $fontFormats = self::FontFormats($labels, is_array($plugin?->FontFamilies ?? null) ? $plugin->FontFamilies : []);
        $contentCss = asset(\_::$Address->StructRootDirectory, 'pas/editor.css', optimize: false);

        $config = json_encode([
            'direction' => $direction,
            'language' => $language,
            'labels' => $labels,
            'fontFormats' => $fontFormats,
            'contentCss' => $contentCss,
            'height' => max(300, (int)($plugin?->EditorHeight ?? 620)),
            'minimumHeight' => max(250, (int)($plugin?->MinimumEditorHeight ?? 480))
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<JS
        (() => {
            if (window.__aseqTinyMCEModule) return;
            window.__aseqTinyMCEModule = true;
            const moduleConfig = $config;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[character]));

            const initializeEditor = (textarea) => {
                if (!window.tinymce || !textarea?.isConnected || textarea.dataset.aseqTinyMCEStarting === '1') return;
                if (!textarea.id) textarea.id = 'aseq-tinymce-' + Math.random().toString(36).slice(2);
                const previous = window.tinymce.get(textarea.id);
                if (previous) {
                    if (previous.targetElm === textarea) return;
                    previous.remove();
                }

                textarea.dataset.aseqTinyMCEStarting = '1';
                if (moduleConfig.language !== 'en')
                    window.tinymce.addI18n(moduleConfig.language, moduleConfig.labels);

                const ready = window.tinymce.init({
                    target: textarea,
                    license_key: 'gpl',
                    height: moduleConfig.height,
                    min_height: moduleConfig.minimumHeight,
                    resize: true,
                    menubar: false,
                    language: moduleConfig.language,
                    directionality: moduleConfig.direction,
                    plugins: 'advlist autolink lists link image charmap anchor searchreplace visualblocks code fullscreen table directionality wordcount',
                    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | alignright aligncenter alignleft alignjustify | rtl ltr | bullist numlist aseqchecklist outdent indent | link aseqbutton aseqcallout image table hr blockquote | searchreplace removeformat code fullscreen',
                    toolbar_mode: 'wrap',
                    font_family_formats: moduleConfig.fontFormats,
                    font_size_formats: '12px 14px 16px 18px 20px 22px 24px 28px 32px 36px 48px 60px 72px',
                    content_css: moduleConfig.contentCss || undefined,
                    convert_urls: false,
                    relative_urls: false,
                    paste_data_images: false,
                    automatic_uploads: false,
                    image_dimensions: true,
                    object_resizing: 'img',
                    resize_img_proportional: true,
                    valid_elements: 'p,br,strong/b,em/i,u,s/strike,sub,sup,blockquote,pre,code,h1,h2,h3,h4,h5,h6,ul[class|style],ol[class|style],li[class|style],hr,table[border|cellpadding|cellspacing|class|style],thead,tbody,tfoot,tr,th[colspan|rowspan|class|style],td[colspan|rowspan|class|style],a[href|target|rel|title|class],img[src|alt|title|width|height|class],figure[class],figcaption,div[class|dir|style],span[class|dir|style]',
                    valid_styles: { '*': 'text-align,direction,text-decoration,font-weight,font-style,font-family,font-size,list-style-type,background-color,border-right,padding,border-radius' },
                    setup: (editor) => {
                        const insertCallout = (background, border) => {
                            const selected = editor.selection.getContent({ format: 'html' }).trim();
                            const content = selected || '<p>' + escapeHtml(moduleConfig.labels['Write important text here.']) + '</p>';
                            editor.insertContent('<div class="pas-callout" dir="' + moduleConfig.direction + '" style="background-color:' + background + ';border-right:4px solid ' + border + ';padding:1rem;border-radius:8px">' + content + '</div><p><br></p>');
                            editor.save();
                        };

                        editor.ui.registry.addMenuButton('aseqcallout', {
                            text: moduleConfig.labels.Callout,
                            tooltip: moduleConfig.labels['Highlight important text in a colored callout'],
                            fetch: (callback) => callback([
                                { type: 'menuitem', text: moduleConfig.labels['Green callout'], onAction: () => insertCallout('#eaf7f2', '#168d73') },
                                { type: 'menuitem', text: moduleConfig.labels['Blue callout'], onAction: () => insertCallout('#eef5ff', '#236bbd') },
                                { type: 'menuitem', text: moduleConfig.labels['Yellow callout'], onAction: () => insertCallout('#fff8e6', '#e29511') },
                                { type: 'menuitem', text: moduleConfig.labels['Red callout'], onAction: () => insertCallout('#fff0f0', '#db2e2e') },
                                { type: 'menuitem', text: moduleConfig.labels['Gray callout'], onAction: () => insertCallout('#f3f4f6', '#6b7280') }
                            ])
                        });

                        editor.ui.registry.addButton('aseqchecklist', {
                            text: '\u2713',
                            tooltip: moduleConfig.labels.Checklist,
                            onAction: () => {
                                let list = editor.dom.getParent(editor.selection.getNode(), 'ul');
                                if (!list) {
                                    editor.execCommand('InsertUnorderedList');
                                    list = editor.dom.getParent(editor.selection.getNode(), 'ul');
                                }
                                if (!list) return;
                                editor.dom.addClass(list, 'pas-checklist');
                                editor.nodeChanged();
                                editor.save();
                            }
                        });

                        editor.ui.registry.addButton('aseqbutton', {
                            text: moduleConfig.labels.Button,
                            tooltip: moduleConfig.labels['Add button'],
                            onAction: () => editor.windowManager.open({
                                title: moduleConfig.labels['Add button'],
                                initialData: {
                                    text: editor.selection.getContent({ format: 'text' }).trim(),
                                    url: ''
                                },
                                body: {
                                    type: 'panel',
                                    items: [
                                        { type: 'input', name: 'text', label: moduleConfig.labels['Button text'] },
                                        { type: 'input', name: 'url', label: moduleConfig.labels['Link address'] }
                                    ]
                                },
                                buttons: [
                                    { type: 'cancel', text: moduleConfig.labels.Cancel },
                                    { type: 'submit', text: moduleConfig.labels.Add, primary: true }
                                ],
                                onSubmit: (api) => {
                                    const data = api.getData();
                                    const url = String(data.url ?? '').trim();
                                    if (!/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(url)) {
                                        window.alert(moduleConfig.labels['Link must start with https://, /, #, mailto:, or tel:.']);
                                        return;
                                    }
                                    const label = String(data.text ?? '').trim() || url;
                                    editor.insertContent('<a class="button main pas-content-button" href="' + escapeHtml(url) + '">' + escapeHtml(label) + '</a>');
                                    editor.save();
                                    api.close();
                                }
                            })
                        });

                        editor.on('change input undo redo', () => editor.save());
                    }
                });

                ready.catch(() => delete textarea.dataset.aseqTinyMCEStarting);
            };

            const activate = (root = document) => {
                if (root.matches?.('textarea[name="Content"]:not([data-aseq-tiny-mce-starting])'))
                    initializeEditor(root);
                root.querySelectorAll?.('textarea[name="Content"]:not([data-aseq-tiny-mce-starting])').forEach(initializeEditor);
            };

            const observer = new MutationObserver((records) => records.forEach((record) => {
                record.removedNodes.forEach((node) => {
                    if (node.nodeType !== Node.ELEMENT_NODE) return;
                    if (node.matches?.('textarea[data-aseq-tiny-mce-starting]')) window.tinymce?.get(node.id)?.remove();
                    node.querySelectorAll?.('textarea[data-aseq-tiny-mce-starting]').forEach((textarea) => window.tinymce?.get(textarea.id)?.remove());
                });
                record.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) activate(node);
                });
            }));

            observer.observe(document.documentElement, { childList: true, subtree: true });
            document.addEventListener('submit', () => window.tinymce?.triggerSave(), { capture: true });
            window.setTimeout(() => activate(), 250);
        })();
        JS;
    }

    private static function TranslatedLabels(): array
    {
        $keys = [
            'Callout', 'Highlight important text in a colored callout', 'Green callout',
            'Blue callout', 'Yellow callout', 'Red callout', 'Gray callout',
            'Write important text here.', 'Checklist', 'Button', 'Add button',
            'Button text', 'Link address', 'Cancel', 'Add',
            'Link must start with https://, /, #, mailto:, or tel:.',
            'Undo', 'Redo', 'Blocks', 'Font family', 'Font sizes', 'Bold', 'Italic',
            'Underline', 'Strikethrough', 'Align right', 'Align center', 'Align left',
            'Justify', 'Right to left', 'Left to right', 'Bullet list', 'Numbered list',
            'Decrease indent', 'Increase indent', 'Insert/edit link', 'Insert/edit image',
            'Table', 'Horizontal line', 'Blockquote', 'Find and replace',
            'Remove formatting', 'Source code', 'Fullscreen', 'Source',
            'Alternative description', 'Width', 'Height', 'Save', 'Close',
            'B Nazanin', 'Vazirmatn', 'B Yekan', 'B Titr', 'B Koodak'
        ];

        $labels = [];
        foreach ($keys as $key)
            $labels[$key] = self::Translate($key);

        return $labels;
    }

    private static function FontFormats(array $labels, array $families): string
    {
        if (!$families)
            $families = [
                'B Nazanin' => 'B Nazanin,Tahoma,sans-serif',
                'Vazirmatn' => 'Vazirmatn,Vazir,Tahoma,Arial,sans-serif',
                'B Yekan' => 'B Yekan,Tahoma,sans-serif',
                'B Titr' => 'B Titr,Tahoma,sans-serif',
                'B Koodak' => 'B Koodak,Tahoma,sans-serif',
                'Times New Roman' => 'Times New Roman,Times,serif',
                'Arial' => 'Arial,sans-serif',
                'Calibri' => 'Calibri,Arial,sans-serif'
            ];

        $formats = [];
        foreach ($families as $name => $family) {
            $label = $labels[$name] ?? $name;
            $formats[] = str_replace([';', '='], '', $label) . '=' . str_replace(';', '', $family);
        }

        return implode(';', $formats);
    }

    private static function Translate(string $key): string
    {
        $translated = function_exists('__') ? __($key) : $key;
        return html_entity_decode(strip_tags((string)$translated), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

