<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /**
     * Permitted HTML tags for article rich text content.
     */
    protected const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
        'span', 'small', 'mark', 'sub', 'sup',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'a', 'img', 'figure', 'figcaption',
        'div', 'font',
    ];

    /**
     * Allowed CSS style declarations on elements.
     */
    protected const ALLOWED_STYLES = [
        'color',
        'background-color',
        'text-align',
        'font-weight',
        'font-style',
        'text-decoration',
        'font-size',
    ];

    /**
     * Clean and sanitize HTML for safe display without stripping valid modern formatting.
     */
    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $trimmed = trim($html);

        // Normalize basic empty paragraph markup from rich text editors
        if (in_array($trimmed, ['<p></p>', '<p><br></p>', '<p><br/></p>', '<br>', '<div><br></div>'], true)) {
            return '';
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        // Wrap with XML encoding declaration and container div to support full UTF-8
        $wrapped = '<?xml encoding="utf-8" ?><div>' . $trimmed . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if (! $root) {
            return '';
        }

        self::sanitizeNode($root);

        // Extract inner HTML of wrapper div
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    protected static function sanitizeNode(DOMNode $node): void
    {
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Remove dangerous container elements and their children completely
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'canvas', 'base', 'meta', 'link'], true)) {
                        $node->removeChild($child);
                        continue;
                    }

                    // Otherwise unwrap unknown tags (keep text children)
                    while ($child->hasChildNodes()) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                self::sanitizeAttributes($child);
                self::sanitizeNode($child);
            }
        }
    }

    protected static function sanitizeAttributes(DOMElement $element): void
    {
        $attributes = iterator_to_array($element->attributes);
        $tag = strtolower($element->tagName);

        foreach ($attributes as $attr) {
            $name = strtolower($attr->name);
            $val = trim($attr->value);

            // Strip any JavaScript event attributes (onclick, onload, onerror, etc.)
            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($name);
                continue;
            }

            if ($name === 'style') {
                $cleanStyle = self::cleanStyle($val);
                if ($cleanStyle !== '') {
                    $element->setAttribute('style', $cleanStyle);
                } else {
                    $element->removeAttribute('style');
                }
                continue;
            }

            if ($name === 'href' && $tag === 'a') {
                if (self::isSafeUrl($val)) {
                    $element->setAttribute('href', $val);
                    $element->setAttribute('target', '_blank');
                    $element->setAttribute('rel', 'noopener noreferrer');
                } else {
                    $element->removeAttribute('href');
                }
                continue;
            }

            if ($name === 'src' && $tag === 'img') {
                if (self::isSafeImageUrl($val)) {
                    $element->setAttribute('src', $val);
                } else {
                    $element->removeAttribute('src');
                }
                continue;
            }

            if ($name === 'color' && preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9,\s\.\%]+\)|[a-z]{3,20})$/i', $val)) {
                $existingStyle = $element->getAttribute('style');
                $newStyle = rtrim($existingStyle, '; ') . ($existingStyle ? '; ' : '') . "color: {$val};";
                $element->setAttribute('style', $newStyle);
                $element->setAttribute('color', $val);
                continue;
            }

            if ($name === 'align' && in_array(strtolower($val), ['left', 'center', 'right', 'justify'], true)) {
                $align = strtolower($val);
                $existingStyle = $element->getAttribute('style');
                $newStyle = rtrim($existingStyle, '; ') . ($existingStyle ? '; ' : '') . "text-align: {$align};";
                $element->setAttribute('style', $newStyle);
                $element->setAttribute('align', $align);
                continue;
            }

            if (in_array($name, ['alt', 'title', 'class', 'width', 'height', 'loading'], true)) {
                continue;
            }

            $element->removeAttribute($name);
        }
    }

    protected static function cleanStyle(string $style): string
    {
        // Forbid CSS expressions and url handlers
        if (preg_match('/(expression|javascript|behavior|vbscript)/i', $style)) {
            return '';
        }

        $parts = explode(';', $style);
        $clean = [];

        foreach ($parts as $part) {
            $sub = explode(':', $part, 2);
            if (count($sub) !== 2) {
                continue;
            }

            $prop = strtolower(trim($sub[0]));
            $val = trim($sub[1]);

            if (in_array($prop, self::ALLOWED_STYLES, true)) {
                // Ensure value does not smuggle dangerous directives
                if (! preg_match('/(url|expression|javascript|binding)/i', $val)) {
                    $clean[] = "{$prop}: {$val}";
                }
            }
        }

        return count($clean) > 0 ? implode('; ', $clean) . ';' : '';
    }

    protected static function isSafeUrl(string $url): bool
    {
        return preg_match('/^(https?:\/\/|mailto:|\/|#)/i', $url) === 1;
    }

    protected static function isSafeImageUrl(string $url): bool
    {
        return preg_match('/^(https?:\/\/|\/|data:image\/(png|jpeg|jpg|gif|webp);base64,)/i', $url) === 1;
    }
}
