<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class HtmlContract
{
    /** HTML syntax spelling may differ; text, tags, attributes and order may not. */
    public static function equivalent(string $left, string $right): bool
    {
        return $left === $right || self::tree($left) === self::tree($right);
    }

    private static function tree(string $html): array
    {
        // Preserve actual text line endings, including in <pre>; do not trim,
        // collapse whitespace, lowercase text, strip tags, or sanitize either side.
        $html = str_replace("\r", '&#13;', $html);
        $document = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR, 'UTF-8');
        return self::node($document->documentElement);
    }

    private static function node(\Dom\Node $node): array
    {
        if (!$node instanceof \Dom\Element) {
            return [$node->nodeType, $node->nodeValue];
        }
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[($attribute->namespaceURI ?? '') . ':' . $attribute->name] = $attribute->value;
        }
        ksort($attributes);
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = self::node($child);
        }
        return [$node->namespaceURI, $node->localName, $attributes, $children];
    }
}
