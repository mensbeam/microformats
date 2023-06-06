<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

class Parser {
    protected $rootNode;
    protected $baseUrl;
    protected $out;

    /** Parses a DOMElement for microformats
     * 
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     */
    public function parseNode(\DOMElement $node, string $baseUrl = ""): array {
        $this->rootNode = $node;
        $this->baseUrl = $baseUrl;
        # start with an empty JSON "items" array and "rels" & "rel-urls" hashes:
        $this->out = [
            'items'    => [],
            'rels'     => [],
            'rel-urls' => [],
        ];
        # parse the root element for class microformats, adding to the JSON items array accordingly
        while ($node) {
            [$node, $item] = $this->parseRoot($node);
            if ($item) {
                $this->out[] = $item;
            }
        }

        return $this->out;
    }

    protected function parseRoot(\DOMElement $root): array {
        $node = $root;
        # parse element class for root class name(s) "h-*" and if none, backcompat root classes
        $classes = preg_split("[ \r\n\t\f]+", $node->getAttribute("class"));
        $types = array_filter($classes, function($c) {
            // exclude Tailwind classes https://tailwindcss.com/docs/height
            return substr($c, 0, 2) === "h-" && !preg_match('/^h-(?:\d+(?:\.\d+|\/\d+)?|px|auto|full|screen|min|max|fit|\[[^\]]*\])$/', $c);
        });
        if ($types) {
            return $this->parseMf2($node, $types); 
        }
        // find backcompat classes
    }

    /** Finds the next node in tree order after $node, if any
     * 
     * @param \DOMNode $node The context node
     * @param bool $considerChildren Whether or not child nodes are valid next nodes
     */
    protected function nextNode(\DOMNode $node, bool $considerChildren): ?\DOMNode {
        if ($node->hasChildNodes() && $considerChildren) {
            return $node->firstChild;
        }
        $next = $node->nextSibling;
        while (!$next) {
            $node = $node->parentNode;
            if ($node->isSameNode($this->rootNode)) {
                return null;
            }
            $next = $node->nextSibling;
        }
        return $next;
    }
}