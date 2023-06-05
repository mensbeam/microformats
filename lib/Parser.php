<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

class Parser {
    public function parse(\DOMNode $node, string $baseUrl = ""): array {
        
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
            if (!$node) {
                return null;
            }
            $next = $node->nextSibling;
        }
        return $next;
    }
}