<?php

$transformLegacyMarkup = static function (string $html, callable $mutator): string {
    if ($html === '' || !class_exists(\DOMDocument::class)) {
        return $html;
    }

    $protectedBlocks = [];
    $htmlForDom = preg_replace_callback(
        '/<script\b[^>]*>[\s\S]*?<\/script>/i',
        static function (array $match) use (&$protectedBlocks): string {
            $placeholder = '%%TC6_SCRIPT_BLOCK_' . count($protectedBlocks) . '%%';
            $protectedBlocks[$placeholder] = $match[0];

            return $placeholder;
        },
        $html
    );
    if (!is_string($htmlForDom) || $htmlForDom === '') {
        $htmlForDom = $html;
        $protectedBlocks = [];
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new \DOMDocument('1.0', 'UTF-8');
    $wrappedHtml = '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="tc6-legacy-root">' . $htmlForDom . '</div></body></html>';

    if (!$dom->loadHTML($wrappedHtml)) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $html;
    }

    $xpath = new \DOMXPath($dom);
    $root = $xpath->query("//*[@id='tc6-legacy-root']")->item(0);

    if (!$root instanceof \DOMElement) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $html;
    }

    $changed = $mutator($dom, $xpath, $root);

    if (!$changed) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $html;
    }

    $rebuilt = '';
    foreach ($root->childNodes as $childNode) {
        $rebuilt .= $dom->saveHTML($childNode);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($protectedBlocks !== []) {
        $rebuilt = strtr($rebuilt, $protectedBlocks);
    }

    return $rebuilt;
};

return [
    'stripEstimate' => static function (string $html, string $rootId, string $styleNeedle) use ($transformLegacyMarkup): string {
        if ($html === '' || stripos($html, $rootId) === false) {
            return $html;
        }

        return $transformLegacyMarkup($html, static function (\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $root) use ($rootId, $styleNeedle): bool {
            $target = $xpath->query(".//*[@id='" . $rootId . "']", $root)->item(0);
            if (!$target instanceof \DOMElement || $target->parentNode === null) {
                return false;
            }

            $previousSibling = $target->previousSibling;
            while ($previousSibling !== null && $previousSibling->nodeType === XML_TEXT_NODE && trim((string)$previousSibling->textContent) === '') {
                $toRemove = $previousSibling;
                $previousSibling = $previousSibling->previousSibling;
                $toRemove->parentNode?->removeChild($toRemove);
            }

            if ($previousSibling instanceof \DOMElement
                && strtolower($previousSibling->tagName) === 'style'
                && stripos((string)$previousSibling->textContent, $styleNeedle) !== false
            ) {
                $previousSibling->parentNode?->removeChild($previousSibling);
            }

            $target->parentNode->removeChild($target);
            return true;
        });
    },
    'stripById' => static function (string $html, string $targetId) use ($transformLegacyMarkup): string {
        if ($html === '' || $targetId === '' || stripos($html, $targetId) === false) {
            return $html;
        }

        return $transformLegacyMarkup($html, static function (\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $root) use ($targetId): bool {
            $target = $xpath->query(".//*[@id='" . $targetId . "']", $root)->item(0);
            if (!$target instanceof \DOMElement || $target->parentNode === null) {
                return false;
            }

            $target->parentNode->removeChild($target);
            return true;
        });
    },
    'stripStepIntro' => static function (string $html) use ($transformLegacyMarkup): string {
        return $transformLegacyMarkup($html, static function (\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $root): bool {
            $changed = false;
            $queries = [
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' fps-step-kicker ')]",
                './/h1',
                ".//*[self::p and contains(concat(' ', normalize-space(@class), ' '), ' fps-step-sub ')]",
            ];

            foreach ($queries as $query) {
                $node = $xpath->query($query, $root)->item(0);
                if ($node !== null && $node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                    $changed = true;
                }
            }

            return $changed;
        });
    },
];
