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
        'additional-name'   => ['h-card' => ["p", "additional-name"]],
        'adr'               => ['h-card' => ["p", "adr"]],
        'affiliation'       => ['h-resume' => ["p", "affiliation", ["vcard"]]],
        'author'            => ['h-entry' => ["p", "author" ["vcard"]], 'h-recipe' => ["p", "author", ["vcard"]]],
        'bday'              => ['h-card' => ["dt", "bday"]],
        'best'              => ['h-review' => ["p", "best"], 'h-review-aggregate' => ["p", "best"]],
        'brand'             => ['h-product' => ["p", "brand"]],
        'category'          => ['h-card' => ["p", "category"], 'h-entry' => ["p", "category"], 'h-event' => ["p", "category"],  'h-product' => ["p", "category"]],
        'contact'           => ['h-resume' => ["p", "contact", ["vcard"]]],
        'count'             => ['h-review-aggregate' => ["p", "count"]],
        'country-name'      => ['h-adr' => ["p", "country-name"], 'h-card' => ["p", "country-name"]],
        'description'       => ['h-event' => ["p", "description"], 'h-product' => ["p", "description"], 'h-review' => ["e", "description"]],
        'dtend'             => ['h-event' => ["dt", "end"]],
        'dtreviewed'        => ['h-review' => ["dt", "reviewed"]],
        'dtstart'           => ['h-event' => ["dt", "start"]],
        'duration'          => ['h-event' => ["dt", "duration"], 'h-recipe' => ["dt", "duration"]],
        'education'         => ['h-resume' => ["p", "education", ["vevent"]]],
        'email'             => ['h-card' => ["u", "email"]],
        'entry-content'     => ['h-entry' => ["e", "content"]],
        'entry-summary'     => ['h-entry' => ["p", "summary"]],
        'entry-title'       => ['h-entry' => ["p", "name"]],
        'experience'        => ['h-resume' => ["p", "experience", ["vevent"]]],
        'extended-address'  => ['h-adr' => ["p", "extended-address"], 'h-card' => ["p", "extended-address"]],
        'family-name'       => ['h-card' => ["p", "family-name"]],
        'fn'                => ['h-card' => ["p", "name"], 'h-product' => ["p", "name"], 'h-recipe' => ["p", "name"]],
        'geo'               => ['h-card' => ["p", "geo"], 'h-event' => ["p", "geo"]],
        'given-name'        => ['h-card' => ["p", "given-name"]],
        'honorific-prefix'  => ['h-card' => ["p", "honorific-prefix"]],
        'honorific-suffix'  => ['h-card' => ["p", "honorific-suffix"]],
        'identifier'        => ['h-product' => ["u", "identifier"]],
        'ingredient'        => ['h-recipe' => ["p", "ingredient"]],
        'instructions'      => ['h-recipe' => ["e", "instructions"]],
        'key'               => ['h-card' => ["u", "key"]],
        'label'             => ['h-card' => ["p", "label"]],
        'latitude'          => ['h-card' => ["p", "latitude"], 'h-event' => ["p", "latitude"], 'h-geo' => ["p", "latitude"]],
        'locality'          => ['h-adr' => ["p", "locality"], 'h-card' => ["p", "locality"]],
        'location'          => ['h-event' => ["p", "location", ["adr", "vcard"]]],
        'logo'              => ['h-card' => ["u", "logo"]],
        'longitude'         => ['h-card' => ["p", "longitude"], 'h-event' => ["p", "longitude"], 'h-geo' => ["p", "longitude"]],
        'nickname'          => ['h-card' => ["p", "nickname"]],
        'note'              => ['h-card' => ["p", "note"]],
        'nutrition'         => ['h-recipe' => ["p", "nutrition"]],
        'organization-name' => ['h-card' => ["p", "organization-name"]],
        'organization-unit' => ['h-card' => ["p", "organization-unit"]],
        'org'               => ['h-card' => ["p", "org"]],
        'photo'             => ['h-card' => ["u", "photo"], 'h-product' => ["u", "photo"], 'h-recipe' => ["u", "photo"]],
        'postal-code'       => ['h-adr' => ["p", "postal-code"], 'h-card' => ["p", "postal-code"]],
        'post-office-box'   => ['h-adr' => ["p", "post-office-box"], 'h-card' => ["p", "post-office-box"]],
        'price'             => ['h-product' => ["p", "price"]],
        'published'         => ['h-entry' => ["dt", "published"], 'h-recipe' => ["dt", "published"]],
        'rating'            => ['h-review' => ["p", "rating"], 'h-review-aggregate' => ["p", "rating"]],
        'region'            => ['h-adr' => ["p", "region"], 'h-card' => ["p", "region"]],
        'rev'               => ['h-card' => ["dt", "rev"]],
        'reviewer'          => ['h-review' => ["p", "reviewer"]],
        'review'            => ['h-product' => ["p", "review", ["hreview"]]],
        'role'              => ['h-card' => ["p", "role"]],
        'skill'             => ['h-resume' => ["p", "skill"]],
        'street-address'    => ['h-adr' => ["p", "street-address"], 'h-card' => ["p", "street-address"]],
        'summary'           => ['h-event' => ["p", "name"], 'h-recipe' => ["p", "summary"], 'h-resume' => ["p", "summary"], 'h-review' => ["p", "name"], 'h-review-aggregate' => ["p", "name"]],
        'tel'               => ['h-card' => ["p", "tel"]],
        'title'             => ['h-card' => ["p", "job-title"]],
        'tz'                => ['h-card' => ["p", "tz"]],
        'uid'               => ['h-card' => ["u", "uid"]],
        'updated'           => ['h-entry' => ["dt", "updated"]],
        'url'               => ['h-card' => ["u", "url"], 'h-product' => ["u", "url"]],
        'url'               => ['h-event' => ["u", "url"]],
        'votes'             => ['h-review-aggregate' => ["p", "votes"]],
        'worst'             => ['h-review' => ["p", "worst"], 'h-review-aggregate' => ["p", "worst"]],
        'yield'             => ['h-recipe' => ["p", "yield"]],
    ];

    protected $baseUrl;

    /** Parses a DOMElement for microformats
     * 
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     */
    public function parseNode(\DOMElement $node, string $baseUrl = ""): array {
        $root = $node;
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
                $node = $this->nextElement($node, $root, true);
                continue;
            }
            // continue to the next element, passing over children (they have already been examined)
            $node = $this->nextElement($node, $root, false);
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

    protected function parsePropertiesBackcompat(\DOMElement $node, array &$classes, array $types): array {
        $out = [];
        foreach ($classes as $c) {
            foreach ($types as $t) {
                $map = static::BACKCOMPAT_PROPERTIES[$c][$t] ?? null;
                if (!$map) {
                    // TODO : handle special mapped properties
                    continue;
                }
                $prefix = $map[0];
                $key = $map[1];
                $roots = $map[2] ?? [];
                if (!isset($out[$key])) {
                    $out[$key] = [];
                }
                $out[$key][] = $this->parseProperty($node, $prefix);
                foreach ($roots as $r) {
                    if (!in_array($r, $classes)) {
                        $classes[] = $r;
                    }
                }
            }
        }
        // TODO: handle link relations
        return $out;
    }

    protected function parseProperty(\DOMElement $node, string $prefix) {
        switch ($prefix) {
            case "p":
                # To parse an element for a p-x property value (whether explicit p-* or backcompat equivalent):
                if ($text = $this->getValueClassPattern($node)) {
                    # Parse the element for the Value Class Pattern. If a value is found, return it.
                    return $text;
                } elseif (in_array($node->localName, ["abbr", "link"]) && $node->hasAttribute("title")) {
                    # If abbr.p-x[title] or link.p-x[title], return the title attribute.
                    return $node->getAttribute("href");
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.p-x[value] or input.p-x[value], then return the value attribute
                    return $node->getAttribute("value");
                } elseif (in_array($node->localName, ["img", "area"]) && $node->hasAttribute("alt")) {
                    # else if img.p-x[alt] or area.p-x[alt], then return the alt attribute
                    return $node->getAttribute("alt");
                } else {
                    # else return the textContent of the element after
                    return $this->getCleanText($node, $prefix);
                }
            case "u":
                # To parse an element for a u-x property value (whether explicit u-* or backcompat equivalent):
                if (in_array($node->localName, ["a", "area", "link"]) && $node->hasAttribute("href")) {
                    # if a.u-x[href] or area.u-x[href] or link.u-x[href], then get the href attribute
                    return $this->normalizeUrl($node->getAttribute("href"));
                } elseif ($node->localName === "img" && $node->hasAttribute("src")) {
                    # else if img.u-x[src] return the result of "parse an img element for src and alt" (see Sec.1.5)
                    return $this->parseImg($node);
                } elseif (in_array($node->localName, ["audio", "video", "source", "iframe"]) && $node->hasAttribute("src")) {
                    # else if audio.u-x[src] or video.u-x[src] or source.u-x[src] or iframe.u-x[src], then get the src attribute
                    return $this->normalizeUrl($node->getAttribute("src"));
                } elseif ($node->localName === "video" && $node->hasAttribute("poster")) {
                    # else if video.u-x[poster], then get the poster attribute
                    return $this->normalizeUrl($node->getAttribute("href"));
                } elseif ($node->localName === "object" && $node->hasAttribute("data")) {
                    # else if object.u-x[data], then get the data attribute
                    return $this->normalizeUrl($node->getAttribute("data"));
                } elseif ($url = $this->getValueClassPattern($node)) {
                    # else parse the element for the Value Class Pattern. If a value is found, get it
                    return $this->normalizeUrl($url);
                } elseif ($node->localName === "abbr" && $node->hasAttribute("title")) {
                    # else if abbr.u-x[title], then get the title attribute
                    return $this->normalizeUrl($node->getAttribute("title"));
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.u-x[value] or input.u-x[value], then get the value attribute
                    return $this->normalizeUrl($node->getAttribute("value"));
                } else {
                    return $this->getCleanText($node, $prefix);
                }
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

    protected function getCleanText(\DOMElement $node, string $prefix): string {
        $copy = $node->cloneNode(true);
        foreach ($copy->getElementsByTagName("script") as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($copy->getElementsByTagName("style") as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($copy->getElementsByTagName("img") as $e) {
            $alt = $e->getAttribute("alt");
            $src = $e->hasAttribute("src") ? $this->normalizeUrl($e->getAttribute("src")) : "";
            if ($prefix === "u") {
                $attr = strlen($src) ? $src : "";
            } else {
                $attr = strlen($alt) ? $alt : $src;
            }
            $e->parentNode->replaceChild($e->ownerDocument->createTextNode(" ".$attr." "), $e);
        }
        return trim($copy->textContent);
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