<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

class Parser {
    protected const BACKCOMPAT_ROOTS = [
        'adr'               => "h-adr",
        'vcard'             => "h-card",
        'hfeed'             => "h-feed",
        'hentry'            => "h-entry",
        'vevent'            => "h-event",
        'geo'               => "h-geo",
        'hproduct'          => "h-product",
        'hrecipe'           => "h-recipe",
        'hresume'           => "h-resume",
        'hreview'           => "h-review",
        'hreview-aggregate' => "h-review-aggregate",
    ];
    protected const BACKCOMPAT_PROPERTIES = [
        'h-adr'              => [
            'post-office-box'  => ["p", "post-office-box"],
            'extended-address' => ["p", "extended-address"],
            'street-address'   => ["p", "street-address"],
            'locality'         => ["p", "locality"],
            'region'           => ["p", "region"],
            'postal-code'      => ["p", "postal-code"],
            'country-name'     => ["p", "country-name"],
        ],
        'h-card'             => [
            'fn'                => ["p", "name"],
            'honorific-prefix'  => ["p", "honorific-prefix"],
            'given-name'        => ["p", "given-name"],
            'additional-name'   => ["p", "additional-name"],
            'family-name'       => ["p", "family-name"],
            'honorific-suffix'  => ["p", "honorific-suffix"],
            'nickname'          => ["p", "nickname"],
            'email'             => ["u", "email"],
            'logo'              => ["u", "logo"],
            'photo'             => ["u", "photo"],
            'url'               => ["u", "url"],
            'uid'               => ["u", "uid"],
            'category'          => ["p", "category"],
            'adr'               => ["p", "adr"],
            'extended-address'  => ["p", "extended-address"],
            'street-address'    => ["p", "street-address"],
            'locality'          => ["p", "locality"],
            'region'            => ["p", "region"],
            'postal-code'       => ["p", "postal-code"],
            'country-name'      => ["p", "country-name"],
            'label'             => ["p", "label"],
            'geo'               => ["p", "geo"],
            'latitude'          => ["p", "latitude"],
            'longitude'         => ["p", "longitude"],
            'tel'               => ["p", "tel"],
            'note'              => ["p", "note"],
            'bday'              => ["dt", "bday"],
            'key'               => ["u", "key"],
            'org'               => ["p", "org"],
            'organization-name' => ["p", "organization-name"],
            'organization-unit' => ["p", "organization-unit"],
            'title'             => ["p", "job-title"],
            'role'              => ["p", "role"],
            'tz'                => ["p", "tz"],
            'rev'               => ["dt", "rev"],
        ],
        'h-feed'             => [
            // NOTE: h-feed mapping requires special handling beyond what is listed here
        ],
        'h-entry'            => [
            // NOTE: h-entry mapping requires special handling beyond what is listed here
            'entry-title'   => ["p", "name"],
            'entry-summary' => ["p", "summary"],
            'entry-content' => ["e", "content"],
            'published'     => ["dt", "published"],
            'updated'       => ["dt", "updated"],
            'author'        => ["p", "author"],
            'category'      => ["p", "category"],
        ],
        'h-event'            => [
            'summary'     => ["p", "name"],
            'dtstart'     => ["dt", "start"],
            'dtend'       => ["dt", "end"],
            'duration'    => ["dt", "duration"],
            'description' => ["p", "description"],
            'url'         => ["u", "url"],
            'category'    => ["p", "category"],
            'location'    => ["p", "location"],
            'geo'         => ["p", "geo"],
            'latitude'    => ["p", "latitude"],
            'longitude'   => ["p", "longitude"],
        ],
        'h-geo'              => [
            'latitude'  => ["p", "latitude"],
            'longitude' => ["p", "longitude"],
        ],
        'h-product'          => [
            'fn'          => ["p", "name"],
            'photo'       => ["u", "photo"],
            'brand'       => ["p", "brand"],
            'category'    => ["p", "category"],
            'description' => ["p", "description"],
            'identifier'  => ["u", "identifier"],
            'url'         => ["u", "url"],
            'review'      => ["p", "review"],
            'price'       => ["p", "price"],
        ],
        'h-recipe'           => [
            'fn'           => ["p", "name"],
            'ingredient'   => ["p", "ingredient"],
            'yield'        => ["p", "yield"],
            'instructions' => ["e", "instructions"],
            'duration'     => ["dt", "duration"],
            'photo'        => ["u", "photo"],
            'summary'      => ["p", "summary"],
            'author'       => ["p", "author"],
            'nutrition'    => ["p", "nutrition"],
            'published'    => ["dt", "published"],
        ],
        'h-resume'           => [
            'summary'     => ["p", "summary"],
            'contact'     => ["p", "contact"],
            'education'   => ["p", "education"],
            'experience'  => ["p", "experience"],
            'skill'       => ["p", "skill"],
            'affiliation' => ["p", "affiliation"],
        ],
        'h-review'           => [
            // NOTE: h-review mapping requires special handling beyond what is listed here
            'summary'     => ["p", "name"],
            'reviewer'    => ["p", "reviewer"],
            'dtreviewed'  => ["dt", "reviewed"],
            'rating'      => ["p", "rating"],
            'best'        => ["p", "best"],
            'worst'       => ["p", "worst"],
            'description' => ["e", "description"],
        ],
        'h-review-aggregate' => [
            // NOTE: h-review-aggregate mapping requires special handling beyond what is listed here
            'summary' => ["p", "name"],
            'rating'  => ["p", "rating"],
            'best'    => ["p", "best"],
            'worst'   => ["p", "worst"],
            'count'   => ["p", "count"],
            'votes'   => ["p", "votes"],
        ],
    ];

    protected $baseUrl;

    /** Parses a DOMElement for microformats
     * 
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     */
    public function parseNode(\DOMElement $node, string $baseUrl = ""): array {
        $this->rootNode = $node;
        $this->baseUrl = $baseUrl;
        # start with an empty JSON "items" array and "rels" & "rel-urls" hashes:
        $out = [
            'items'    => [],
            'rels'     => [],
            'rel-urls' => [],
        ];
        # parse the root element for class microformats, adding to the JSON items array accordingly
        while ($node) {
            # parse element class for root class name(s) "h-*" and if none, backcompat root classes
            # if found, start parsing a new microformat
            $classes = $this->parseClasses($node);
            if ($types = $this->matchRootsMf2($classes)) {
                $out[] = $this->parseMicroformat($node, $types, false);
            } elseif ($types = $this->matchRootsBackcompat($classes)) {
                $out[] = $this->parseMicroformat($node, $types, true);
            } else {
                # if none found, parse child elements for microformats (depth first, doc order)
                $node = $this->nextElement($node, $this->rootNode, true);
                continue;
            }
            // continue to the next element, passing over children (they have already been examined)
            $node = $this->nextElement($node, $this->rootNode, false);
        }

        // TODO: clean up instance properties
        return $out;
    }

    protected function parseClasses(\DOMElement $node): array {
        $attr = trim($node->getAttribute("class"), " \r\n\t\f");
        if ($attr !== "") {
            return array_unique(preg_split("/[ \r\n\t\f]+/sS", $attr));
        } else {
            return [];
        }
    }

    protected function matchRootsMf2(array $classes): array {
        return array_filter($classes, function($c) {
            # The "*" for root (and property) class names consists of an
            #   optional vendor prefix (series of 1+ number or lowercase
            #   a-z characters i.e. [0-9a-z]+, followed by '-'), then one
            #   or more '-' separated lowercase a-z words.
            // exclude Tailwind classes https://tailwindcss.com/docs/height
            return preg_match('/^h(?:-[a-z0-9]+)?(?:-[a-z]+)+$/S', $c) && !preg_match('/^h-(?:px|auto|full|screen|min|max|fit)$/S', $c);
        });
    }

    protected function matchRootsBackcompat(array $classes): array {
        $out = [];
        foreach ($classes as $c) {
            if ($compat = self::BACKCOMPAT_ROOTS[$c] ?? null) {
                $out[] = $compat;
            }
        }
        return $out;
    }

    protected function parseMicroformat(\DOMElement $root, array $types, bool $backcompat): array {
        # keep track of whether the root class name(s) was from backcompat
        // this is a parameter to this function
        # create a new { } structure
        $out = [
            # type: [array of unique microformat "h-*" type(s) on the element sorted alphabetically]
            // NOTE: sorting will be done below; uniqueness was already computed when classes were parsed
            'type' => $types,
            # properties: { } - to be filled in when that element itself is parsed for microformats properties
            'properties' => [],
            # if the element has a non-empty id attribute:
            # id: string value of element's id attribute
            // Added below
        ];
        sort($out['type']);
        if (strlen($id = $root->getAttribute("id"))) {
            $out['id'] = $id;
        }
        # parse child elements (document order) by:
        while ($node = $this->nextElement($node ?? $root, $root, !($isRoot = $isRoot ?? false))) {
            $isRoot = false;
            $classes = $this->parseClasses($node);
            if ($backcompat) {
                # if parsing a backcompat root, parse child element class name(s) for backcompat properties
                $properties = $this->parsePropertiesBackcompat($node, $classes, $types);
            } else {
                # else parse a child element class for property class name(s) "p-*,u-*,dt-*,e-*"
                $properties = $this->parsePropertiesMf2($node, $classes);
            }
            # if such class(es) are found, it is a property element
            # add properties found to current microformat's properties: { } structure
            foreach ($properties as $k => $v) {
                if (!isset($out['properties'][$k])) {
                    $out['properties'][$k] = [];
                }
                array_push($out['properties'][$k], ...$v);
            }
            # parse a child element for microformats (recurse)
            $child = null;
            if ($types = $this->matchRootsMf2($classes)) {
                $child = $this->parseMicroformat($node, $types, false);
            } elseif ($types = $this->matchRootsBackcompat($classes)) {
                $child = $this->parseMicroformat($node, $types, true);
            }
            if ($child) {
                $isRoot = true;
            }
        }
    }

    protected function parsePropertiesMf2(\DOMElement $node, array $classes): array {
        $out = [];
        foreach ($classes as $c) {
            # The "*" for root (and property) class names consists of an
            #   optional vendor prefix (series of 1+ number or lowercase
            #   a-z characters i.e. [0-9a-z]+, followed by '-'), then one
            #   or more '-' separated lowercase a-z words.
            if (!preg_match('/^(p|u|dt|e)((?:-[a-z0-9]+)?(?:-[a-z]+)+)$/S', $c, $match)) {
                continue;
            }
            $prefix = $match[1];
            $key = substr($match[2], 1);
            if (!isset($out[$key])) {
                $out[$key] = [];
            }
            $out[$key][] = $this->parseProperty($node, $prefix);
        }
        return $out;
    }

    protected function parseProperty(\DOMElement $node, string $prefix) {
        switch ($prefix) {
            case "p":
                // TODO
                break;
            case "u":
                // TODO
                break;
            case "dt":
                // TODO
                break;
            case "e":
                //TODO
                break;
            default:
                throw new \Exception("Unimplemented prefix $prefix");
        }
    }

    protected function parsePropertiesBackcompat(\DOMElement $node, array &$classes, array $types): array {
        $out = [];
        foreach ($classes as $c) {
            foreach ($types as $t) {
                if (($map = static::BACKCOMPAT_PROPERTIES[$t][$c] ?? null) !== null) {
                    array_push($out, ...$map);
                    break;
                }
            }
        }
        if ($roots = $this->matchRootsMf2($classes)) {
            array_push($out, ...$roots);
        }
        return array_unique($out);
    }

    /** Finds the next node in tree order after $node, if any
     * 
     * @param \DOMNode $node The context node
     * @param \DOMElement $root The element to consider the contextual root of the tree
     * @param bool $considerChildren Whether or not child nodes are valid next nodes
     */
    protected function nextElement(\DOMElement $node, \DOMElement $root, bool $considerChildren): ?\DOMElement {
        if ($considerChildren && $node->localName !== "template" && $node->hasChildNodes()) {
            $next = $node->firstChild;
            if ($next instanceof \DOMElement) {
                return $next;
            }
        }
        $next = $node->nextSibling;
        while (!$next) {
            $node = $node->parentNode;
            if ($node->isSameNode($root)) {
                return null;
            }
            $next = $node->nextSibling;
            while ($next and !$next instanceof \DOMElement) {
                $next = $next->nextSibling;
            }
        }
        return $next;
    }
}