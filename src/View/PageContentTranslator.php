<?php
declare(strict_types=1);

namespace App\View;

final class PageContentTranslator
{
    /**
     * Apply legacy page translations to visible text without mutating markup or code.
     *
     * @param array<string, string> $translations
     */
    public static function translate(string $html, array $translations): string
    {
        if ($html === '' || $translations === []) {
            return $html;
        }

        $parts = preg_split(
            '~('
            . '<script\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>[\s\S]*?</script\s*>'
            . '|<style\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>[\s\S]*?</style\s*>'
            . '|<!--[\s\S]*?-->'
            . '|<(?:(?:"[^"]*")|(?:\'[^\']*\')|[^\'">])*>'
            . ')~i',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if (!is_array($parts)) {
            return $html;
        }

        foreach ($parts as &$part) {
            if (!str_starts_with($part, '<')) {
                $part = strtr($part, $translations);
            }
        }
        unset($part);

        return implode('', $parts);
    }
}
