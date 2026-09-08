<?php

namespace MiMFa\Library;

final class TinyMCEContentSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th',
        'td', 'a', 'img', 'figure', 'figcaption', 'div', 'span'
    ];

    private const GLOBAL_ATTRIBUTES = ['class', 'dir', 'style', 'title'];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan']
    ];

    public static function Sanitize(mixed $content): mixed
    {
        if (!is_string($content) || !str_contains($content, '<'))
            return $content;

        if (!class_exists(\DOMDocument::class))
            return strip_tags($content, '<p><br><strong><b><em><i><u><s><strike><sub><sup><blockquote><pre><code><h1><h2><h3><h4><h5><h6><ul><ol><li><hr><table><thead><tbody><tfoot><tr><th><td><a><img><figure><figcaption><div><span>');

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="tinymce-content-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('tinymce-content-root');
        if (!$root)
            return '';

        self::CleanNode($root);

        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child)
            $html .= $document->saveHTML($child);

        return $html;
    }

    public static function SanitizeValues(array $values): array
    {
        if (array_key_exists('Content', $values))
            $values['Content'] = self::Sanitize($values['Content']);

        return $values;
    }

    private static function CleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes ?? []) as $child)
            self::CleanNode($child);

        if ($node->nodeType !== XML_ELEMENT_NODE || !$node instanceof \DOMElement)
            return;

        $tag = strtolower($node->nodeName);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg'], true)) {
                $node->parentNode?->removeChild($node);
                return;
            }

            while ($node->firstChild)
                $node->parentNode?->insertBefore($node->firstChild, $node);
            $node->parentNode?->removeChild($node);
            return;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attribute)
            self::CleanAttribute($node, $tag, $attribute);

        if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank')
            $node->setAttribute('rel', 'noopener noreferrer');
    }

    private static function CleanAttribute(\DOMElement $node, string $tag, \DOMAttr $attribute): void
    {
        $name = strtolower($attribute->nodeName);
        $value = trim($attribute->nodeValue);
        $allowed = in_array($name, self::GLOBAL_ATTRIBUTES, true)
            || in_array($name, self::TAG_ATTRIBUTES[$tag] ?? [], true);

        if (!$allowed || str_starts_with($name, 'on')) {
            $node->removeAttributeNode($attribute);
            return;
        }

        if (in_array($name, ['href', 'src'], true)
            && !preg_match('~^(https?://|/|#|mailto:|tel:)~i', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
            $node->removeAttributeNode($attribute);
            return;
        }

        if (in_array($name, ['width', 'height', 'colspan', 'rowspan'], true) && !preg_match('/^\d{1,4}$/', $value)) {
            $node->removeAttributeNode($attribute);
            return;
        }

        if ($name === 'class')
            $node->setAttribute($name, preg_replace('/[^a-z0-9_\-\s]/i', '', $value) ?? '');
        elseif ($name === 'dir' && !in_array(strtolower($value), ['rtl', 'ltr', 'auto'], true))
            $node->removeAttributeNode($attribute);
        elseif ($name === 'target' && !in_array(strtolower($value), ['_blank', '_self'], true))
            $node->removeAttributeNode($attribute);
        elseif ($name === 'style')
            self::CleanStyle($node, $value);
    }

    private static function CleanStyle(\DOMElement $node, string $value): void
    {
        $styles = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $styleValue] = array_pad(explode(':', $declaration, 2), 2, null);
            $property = strtolower(trim((string)$property));
            $styleValue = strtolower(trim((string)$styleValue));

            $valid = ($property === 'text-align' && in_array($styleValue, ['left', 'right', 'center', 'justify', 'start', 'end'], true))
                || ($property === 'direction' && in_array($styleValue, ['rtl', 'ltr'], true))
                || ($property === 'text-decoration' && in_array($styleValue, ['underline', 'line-through', 'none'], true))
                || ($property === 'font-weight' && in_array($styleValue, ['normal', 'bold', 'bolder', 'lighter'], true))
                || ($property === 'font-style' && in_array($styleValue, ['normal', 'italic'], true))
                || ($property === 'font-family' && preg_match('/^[a-z0-9\s,\'"-]+$/i', $styleValue) && !preg_match('/url|expression|var|calc/i', $styleValue))
                || ($property === 'font-size' && preg_match('/^(?:1[0-9]|[2-8][0-9]|9[0-6])px$/', $styleValue))
                || ($property === 'list-style-type' && in_array($styleValue, ['none', 'disc', 'circle', 'square', 'decimal', 'lower-alpha', 'upper-alpha'], true))
                || ($property === 'background-color' && preg_match('/^#[0-9a-f]{6}$/', $styleValue))
                || ($property === 'border-right' && preg_match('/^[1-9][0-9]?px solid #[0-9a-f]{6}$/', $styleValue))
                || ($property === 'padding' && in_array($styleValue, ['0.5rem', '0.75rem', '1rem', '1.25rem'], true))
                || ($property === 'border-radius' && preg_match('/^(?:[0-9]|1[0-6])px$/', $styleValue));

            if ($valid)
                $styles[] = $property . ':' . $styleValue;
        }

        if ($styles)
            $node->setAttribute('style', implode(';', $styles));
        else
            $node->removeAttribute('style');
    }
}
