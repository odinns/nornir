<?php

declare(strict_types=1);

namespace App\Services\Wayback;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final readonly class WaybackTextExtractor
{
    /**
     * @return array{title:?string, meta_description:?string, authored_text:string}
     */
    public function extract(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $noiseNodes = $xpath->query('//script|//style|//noscript|//*[@id="wm-ipp"]|//*[contains(concat(" ", normalize-space(@class), " "), " wb-autocomplete-suggestions ")]');

        if ($noiseNodes !== false) {
            foreach ($noiseNodes as $node) {
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $title = $this->firstText($xpath, '//title');
        $metaDescription = null;
        $metaNode = $this->firstNode($xpath, '//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "description"]');

        if ($metaNode instanceof DOMElement) {
            $metaDescription = $this->clean($metaNode->getAttribute('content'));
        }

        $body = $this->firstNode($xpath, '//body');
        $authoredText = $this->clean($body instanceof DOMNode ? $body->textContent : $document->textContent);

        return [
            'title' => $title,
            'meta_description' => $metaDescription === '' ? null : $metaDescription,
            'authored_text' => $authoredText,
        ];
    }

    private function firstText(DOMXPath $xpath, string $query): ?string
    {
        $node = $this->firstNode($xpath, $query);

        if (! $node instanceof DOMNode) {
            return null;
        }

        $text = $this->clean($node->textContent);

        return $text === '' ? null : $text;
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\r\n]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function firstNode(DOMXPath $xpath, string $query): ?DOMNode
    {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof DOMNode ? $node : null;
    }
}
