<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

use MensBeam\HTML\Parser\Serializer;

class Parser {
    /** @var array The list of class names which are backward-compatibility microformat markers */
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
    /** @var array The list of class names which are backward-compatibility property markers. Each value is in turn an array listing the root (in v2 format) for which the property applies, the value of which is an indexed array containing the v2 prefix, v2 equivalent name, and possibly three other members: an array with additional classes to add to the element's effective class list, the name of acontainer property, and whether processing of the property should be deferred till the microformat has been otherwise processed */
    protected const BACKCOMPAT_CLASSES = [
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
        'entry-date'        => ['h-entry' => ["dt", "published", [], null, true]], // also requires special processing
        'entry-summary'     => ['h-entry' => ["p", "summary"]],
        'entry-title'       => ['h-entry' => ["p", "name"]],
        'experience'        => ['h-resume' => ["p", "experience", ["vevent"]]],
        'extended-address'  => ['h-adr' => ["p", "extended-address"], 'h-card' => ["p", "extended-address"]],
        'family-name'       => ['h-card' => ["p", "family-name"]],
        'fn'                => ['h-card' => ["p", "name"], 'h-product' => ["p", "name"], 'h-recipe' => ["p", "name"], 'h-review' => ["p", "name", [], "item"], , 'h-review-aggregate' => ["p", "name", [], "item"]],
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
        'photo'             => ['h-card' => ["u", "photo"], 'h-product' => ["u", "photo"], 'h-recipe' => ["u", "photo"], 'h-review' => ["u", "photo", [], "item"], , 'h-review-aggregate' => ["u", "photo", [], "item"]],
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
        'site-description'  => ['h-feed' => ["p", "summary"]],
        'site-title'        => ['h-feed' => ["p", "name"]],
        'street-address'    => ['h-adr' => ["p", "street-address"], 'h-card' => ["p", "street-address"]],
        'summary'           => ['h-event' => ["p", "name"], 'h-recipe' => ["p", "summary"], 'h-resume' => ["p", "summary"], 'h-review' => ["p", "name"], 'h-review-aggregate' => ["p", "name"]],
        'tel'               => ['h-card' => ["p", "tel"]],
        'title'             => ['h-card' => ["p", "job-title"]],
        'tz'                => ['h-card' => ["p", "tz"]],
        'uid'               => ['h-card' => ["u", "uid"]],
        'updated'           => ['h-entry' => ["dt", "updated"]],
        'url'               => ['h-card' => ["u", "url"], 'h-event' => ["u", "url"], 'h-product' => ["u", "url"], 'h-review' => ["u", "url", [], "item"], , 'h-review-aggregate' => ["u", "url", [], "item"]],
        'votes'             => ['h-review-aggregate' => ["p", "votes"]],
        'worst'             => ['h-review' => ["p", "worst"], 'h-review-aggregate' => ["p", "worst"]],
        'yield'             => ['h-recipe' => ["p", "yield"]],
    ];
    /** @var array The list of link relations which are backward-compatibility property markers. The format is the same as for backcompat classes */
    protected const BACKCOMPAT_RELATIONS = [
        // h-review and h-review-agregate also include "self bookmark", but this requires special processing
        'bookmark' => ['h-entry' => ["u", "url"]],
        'tag'      => ['h-entry' => ["p", "category", [], null, true], 'h-feed' => ["p", "category"], 'h-review' => ["p", "category"], , 'h-review-aggregate' => ["p", "category"]],
        'author'   => ['h-entry' => ["u", "author", [], null, true]],
    ];
    /** @var array The list of attributes which contain URLs, and their host elements */
    protected const URL_ATTRS = [
        ''           => ["itemid", "itemprop", "itemtype"],
        'a'          => ["href", "ping"],
        'area'       => ["href", "ping"],
        'audio'      => ["src"],
        'base'       => ["href"],
        'blockquote' => ["cite"],
        'button'     => ["formaction"],
        'del'        => ["cite"],
        'embed'      => ["src"],
        'form'       => ["action"],
        'iframe'     => ["src"],
        'img'        => ["src"],
        'input'      => ["formaction", "src"],
        'ins'        => ["cite"],
        'link'       => ["href"],
        'object'     => ["data"],
        'q'          => ["cite"],
        'script'     => ["src"],
        'source'     => ["src"],
        'track'      => ["src"],
        'video'      => ["poster", "src"],
    ];
    protected const DATE_TYPE_DATE = 1 << 0;
    protected const DATE_TYPE_HOUR = 1 << 1;
    protected const DATE_TYPE_MIN = 1 << 2;
    protected const DATE_TYPE_SEC = 1 << 3;
    protected const DATE_TYPE_ZONE = 1 << 4;
    protected const DATE_TYPE_TIME = self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN | self::DATE_TYPE_SEC;
    protected const DATE_INPUT_FORMATS = [
        # YYYY-MM-DD
        'Y-m-d' => self::DATE_TYPE_DATE,
        # YYYY-DDD
        'Y-z'   => self::DATE_TYPE_DATE,
    ];
    protected const TIME_INPUT_FORMATS = [
        # HH:MM:SS
        'H:i:s'   => self::DATE_TYPE_TIME,
        # HH:MM
        'H:i'     => self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN,
        # HH:MM:SSam HH:MM:SSpm
        'h:i:sa'  => self::DATE_TYPE_TIME,
        # HH:MMam HH:MMpm
        'h:ia'    => self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN,
        # HHam HHpm
        'ha'      => self::DATE_TYPE_HOUR,
        // 12-hour clock without leading zeroes; this is not part of the spec, but probably occurs
        'g:i:sa'  => self::DATE_TYPE_TIME,
        'g:ia'    => self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN,
        'ga'      => self::DATE_TYPE_HOUR,
    ];
    protected const ZONE_INPUT_FORMATS = [
        # -XX:YY +XX:YY
        'P'  => self::DATE_TYPE_ZONE,
        # -XXYY +XXYY
        'O'  => self::DATE_TYPE_ZONE,
        # -XX +XX
        // Hour-only time zones require special processing
        # Z
        '\Z' => self::DATE_TYPE_ZONE,
    ];
    protected const DATE_OUTPUT_FORMATS = [
        self::DATE_TYPE_DATE                                                                     => 'Y-m-d',
        self::DATE_TYPE_DATE | self::DATE_TYPE_TIME | self::DATE_TYPE_ZONE                       => 'Y-m-d H:i:sO',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE => 'Y-m-d H:iO',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE                       => 'Y-m-d H:00O',
        self::DATE_TYPE_DATE | self::DATE_TYPE_TIME                                              => 'Y-m-d H:i:s',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN                        => 'Y-m-d H:i',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR                                              => 'Y-m-d H:00',
        self::DATE_TYPE_TIME | self::DATE_TYPE_ZONE                                              => 'H:i:sO',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE                        => 'H:iO',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE                                              => 'H:00O',
        self::DATE_TYPE_TIME                                                                     => 'H:i:s',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_MIN                                               => 'H:i',
        self::DATE_TYPE_HOUR                                                                     => 'H:00',
    ];

    protected $baseUrl;

    /** Parses a DOMElement for microformats
     * 
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     */
    public function parseNode(\DOMElement $node, string $baseUrl = ""): array {
        $root = $node;
        // Perform HTML base-URL resolution
        $this->baseUrl = $baseUrl;
        $this->baseUrl = $this->getBaseUrl($root, $baseUrl);
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
            $classes = $this->parseTokens($node, "class");
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

    protected function parseTokens(\DOMElement $node, string $attr): array {
        $attr = trim($node->getAttribute($attr), " \r\n\t\f");
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
        // keep track of deferred properties ("use Y if X is not defined")
        $deferred = [];
        # parse child elements (document order) by:
        while ($node = $this->nextElement($node ?? $root, $root, !($isRoot = $isRoot ?? false))) {
            $isRoot = false;
            $classes = $this->parseTokens($node, "class");
            if ($backcompat) {
                # if parsing a backcompat root, parse child element class name(s) for backcompat properties
                $properties = $this->matchPropertiesBackcompat($classes, $types, $node);
            } else {
                # else parse a child element class for property class name(s) "p-*,u-*,dt-*,e-*"
                $properties = $this->matchPropertiesMf2($classes);
            }
            # if such class(es) are found, it is a property element
            # add properties found to current microformat's properties: { } structure
            foreach ($properties as $p) {
                [$prefix, $key, $extraRoots, $container, $defer] = array_pad($p, 5, null);
                if ($defer) {
                    // defer evaluation of the property if it's supposed to be a fallback for another instance of the property
                    $deferred[] = [$node, $p];
                } elseif ($container) {
                    // if a container property is defined as part of backcompat processing, we insert into that; there can only ever be one instance of it
                    if (!isset($out['properties'][$container])) {
                        $out['properties'][$container] = [[$key => []]];
                    } elseif (!isset($out['properties'][$container][0][$key])) {
                        $out['properties'][$container][0][$key] = [];
                    }
                    $out['properties'][$container][0][$key][] = $this->parseProperty($node, $prefix, $backcompat ? $types : []);
                } else {
                    if (!isset($out['properties'][$key])) {
                        $out['properties'][$key] = [];
                    }
                    $out['properties'][$key][] = $this->parseProperty($node, $prefix, $backcompat ? $types : []);
                }
                // now add any extra roots to the element's class list; this only ever occurs during backcompat processing
                foreach ($extraRoots ?? [] as $r) {
                    if (!in_array($r, $classes)) {
                        $classes[] = $r;
                    }
                }
            }
            # parse a child element for microformats (recurse)
            if ($types = $this->matchRootsMf2($classes)) {
                $child = $this->parseMicroformat($node, $types, false);
            } elseif ($types = $this->matchRootsBackcompat($classes)) {
                $child = $this->parseMicroformat($node, $types, true);
            } else {
                $child = null;
            }
            if ($child) {
                $isRoot = true;
            }
        }
        // TODO: Process deferred properties
        return $out;
    }

    protected function matchPropertiesMf2(array $classes): array {
        $out = [];
        foreach ($classes as $c) {
            # The "*" for root (and property) class names consists of an
            #   optional vendor prefix (series of 1+ number or lowercase
            #   a-z characters i.e. [0-9a-z]+, followed by '-'), then one
            #   or more '-' separated lowercase a-z words.
            if (!preg_match('/^(p|u|dt|e)((?:-[a-z0-9]+)?(?:-[a-z]+)+)$/S', $c, $match)) {
                $out[] = [
                    $match[1], // the prefix
                    substr($match[2], 1), // the property name
                ];
            }
        }
        return $out;
    }

    protected function matchPropertiesBackcompat(array $classes, array $types, \DOMElement $node): array {
        $out = [];
        foreach ($types as $t) {
            // check for backcompat classes
            foreach ($classes as $c) {
                if ($map = static::BACKCOMPAT_CLASSES[$c][$t] ?? null) {
                    if ($c === "entry-date" && ($node->localName !== "time" || !$node->hasAttribute("datetime"))) {
                        // entry-date is only valid on time elements with a machine-readable datetime
                        continue;
                    }
                    $out[] = $map;
                }
            }
            // check for backcompat relations
            $relations = $this->parseTokens($node, "rel");
            foreach ($relations as $r) {
                if ($map = static::BACKCOMPAT_CLASSES[$r][$t] ?? null) {
                    $out[] = $map;
                }
            }
            // check for "self bookmark" relations, if applicable
            if (in_array($t, ["h-review", "h-review-aggregate"]) && sizeof(array_intersect(["self", "bookmark"], $relations)) === 2) {
                $out[] = ["u", "url"];
            }
        }
        return $out;
    }

    protected function parseProperty(\DOMElement $node, string $prefix, array $backcompatTypes) {
        switch ($prefix) {
            case "p":
                # To parse an element for a p-x property value (whether explicit p-* or backcompat equivalent):
                if ($text = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) {
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
                    # else return the textContent of the element after [cleaning]
                    return $this->getCleanText($node, $prefix);
                }
            case "u":
                # To parse an element for a u-x property value (whether explicit u-* or backcompat equivalent):
                if (in_array($node->localName, ["a", "area", "link"]) && $node->hasAttribute("href")) {
                    # if a.u-x[href] or area.u-x[href] or link.u-x[href], then get the href attribute
                    $url = $node->getAttribute("href");
                } elseif ($node->localName === "img" && $node->hasAttribute("src")) {
                    # else if img.u-x[src] return the result of "parse an img element for src and alt" (see Sec.1.5)
                    return $this->parseImg($node);
                } elseif (in_array($node->localName, ["audio", "video", "source", "iframe"]) && $node->hasAttribute("src")) {
                    # else if audio.u-x[src] or video.u-x[src] or source.u-x[src] or iframe.u-x[src], then get the src attribute
                    $url = $node->getAttribute("src");
                } elseif ($node->localName === "video" && $node->hasAttribute("poster")) {
                    # else if video.u-x[poster], then get the poster attribute
                    $url = $node->getAttribute("href");
                } elseif ($node->localName === "object" && $node->hasAttribute("data")) {
                    # else if object.u-x[data], then get the data attribute
                    $url = $node->getAttribute("data");
                } elseif ($url = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) {
                    # else parse the element for the Value Class Pattern. If a value is found, get it
                    // Nothing to do in this branch
                } elseif ($node->localName === "abbr" && $node->hasAttribute("title")) {
                    # else if abbr.u-x[title], then get the title attribute
                    $url = $node->getAttribute("title");
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.u-x[value] or input.u-x[value], then get the value attribute
                    $url = $node->getAttribute("value");
                } else {
                    # else get the textContent of the element after removing all leading/trailing spaces and nested <script> & <style> elements
                    $url = $this->getCleanText($node, $prefix);
                }
                # return the normalized absolute URL of the gotten value,
                #   following the containing document's language's rules for
                #   resolving relative URLs (e.g. in HTML, use the current URL
                #   context as determined by the page, and first <base>
                #   element, if any).
                return $this->normalizeUrl($url);
            case "dt":
                # To parse an element for a dt-x property value (whether explicit dt-* or backcompat equivalent):
                if ($date = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) {
                    # parse the element for the Value Class Pattern, including the date and time parsing rules. If a value is found, then return it.
                    return $date;
                } elseif (in_array($node->localName, ["time", "ins", "del"]) && $node->hasAttribute("datetime")) {
                    # if time.dt-x[datetime] or ins.dt-x[datetime] or del.dt-x[datetime], then return the datetime attribute
                    return $node->getAttribute("datetime");
                } elseif ($node->localName === "abbr" && $node->hasAttribute("title")) {
                    # else if abbr.dt-x[title], then return the title attribute
                    return $node->getAttribute("title");
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.dt-x[value] or input.dt-x[value], then return the value attribute
                    return $node->getAttribute("value");
                } else {
                    # else return the textContent of the element after removing all leading/trailing spaces and nested <script> & <style> elements.
                    return $this->getCleanText($node, $prefix);
                }
            case "e":
                # To parse an element for a e-x property value (whether explicit "e-*" or backcompat equivalent):
                # return a dictionary with two keys:
                # html: the innerHTML of the element by using the HTML spec:
                #   Serializing HTML Fragments algorithm, with
                #   leading/trailing spaces removed. Proposed: and normalized
                #   absolute URLs in all URL attributes except those that are
                #   fragment-only, e.g. start with '#'.(issue 38)
                # value: the textContent of the element after [cleaning]
                $copy = $node->cloneNode(true);
                // TODO: normalize URLs
                return [
                    'html' => trim(Serializer::serializeInner($copy)),
                    'value' => $this->getCleanText($node, $prefix),
                ];
            default:
                throw new \Exception("Unimplemented prefix $prefix");
        }
    }

    protected function getValueClassPattern(\DOMElement $node, string $prefix, array $backcompatTypes) {
        $out = [];
        $root = $node;
        $dateParts = 0;
        $skipChildren = false;
        while ($node = $this->nextElement($node, $root, !$skipChildren)) {
            $classes = $this->parseTokens($node, "class");
            if (
                ($backcompatTypes && ($this->matchRootsBackcompat($classes) || $this->matchPropertiesBackcompat($classes, $backcompatTypes, $node)))
                || ($this->matchRootsMf2($classes) || $this->matchPropertiesMf2($classes))
            ) {
                // only consider elements which are not themselves properties or roots
                // NOTE: The specification doesn't mention roots, but these should surely be skipped as well
                $skipChildren = true;
            } elseif (in_array("value", $classes)) {
                # Where an element with such a microformat property class name
                #   has a descendant with class name value (a "value element")
                #   not inside some other property element, parsers should use
                #   the following portion of that value element:
                if (in_array($node->localName, ["img", "area"])) {
                    # if the value element is an img or area element, then use the element's alt attribute value.
                    $candidate = $node->getAttribute("alt");
                } elseif ($node->localName === "data") {
                    # if the value element is a data element, then use the element's value attribute value if present, otherwise its inner-text.
                    if ($node->hasAttribute("value")) {
                        $candidate = $node->getAttribute("value");
                    } else {
                        $candidate = $this->getCleanText($node, $prefix);
                    }
                } elseif ($node->localName === "abbr") {
                    # if the value element is an abbr element, then use the element's title attribute value if present, otherwise its inner-text.
                    if ($node->hasAttribute("title")) {
                        $candidate = $node->getAttribute("title");
                    } else {
                        $candidate = $this->getCleanText($node, $prefix);
                    }
                } elseif ($prefix === "dt" && in_array($node->localName, ["del", "ins", "time"])) {
                    # if the element is a del, ins, or time element, then use
                    #   the element's datetime attribute value if present,
                    #   otherwise its inner-text. [datetime only]
                    if ($node->hasAttribute("datetime")) {
                        $candidate = $node->getAttribute("datetime");
                    } else {
                        $candidate = $this->getCleanText($node, $prefix);
                    }
                } else {
                    # for any other element, use its inner-text.
                    $candidate = $this->getCleanText($node, $prefix);
                }
                if ($prefix !== "dt") {
                    $skipChildren = true;
                    $out[] = $candidate;
                } else {
                    // TODO: date processing
                    $skipChildren = true;
                }
            } else {
                $skipChildren = false;
            }
        }
        if ($prefix !== "dt") {
            # if the microformats property expects a simple string, enumerated
            #   value, or telephone number, then the values extracted from the
            #   value elements should be concatenated without inserting
            #   additional characters or white-space.
            return implode("", $out);
        } else {
            # if the microformats property expects a datetime value, see the Date Time Parsing section.
            // TODO
            return $out;
        }
    }

    protected function parseImg(\DOMElement $node) {
        # To parse an img element for src and alt attributes:
        if ($node->localName === "img" && $node->hasAttribute("alt")) {
            # if img[alt]
            # return a new {} structure with
            return [
                # value: the element's src attribute as a normalized absolute URL
                'value' => $this->normalizeUrl($node->getAttribute("src")),
                # alt: the element's alt attribute
                'alt'   => trim($node->getAttribute("alt")),
            ];
        } else {
            # else return the element's src attribute as a normalized absolute URL
            return $this->normalizeUrl($node->getAttribute("src"));
        }
    }

    protected function parseDate(string $input): ?array {
        // do a first-pass normalization on the input; this normalizes am/pm and trims whitespace
        $input = trim(preg_replace(['/([ap])\.m\.$/', '/\s+/'], ["$1m", " "], strtr($input, "APM", "apm")));
        // match against all valid date/time format patterns and return the normalized representations and the matched parts
        // we try with space and with T between date and time, as well as with and without space before time zone
        foreach (self::DATE_INPUT_FORMATS as $df => $dp) {
            if ($out = $this->testDate($input, "!$df")) {
                return [$out->format(self::DATE_OUTPUT_FORMATS[$dp]), $dp];
            }
            foreach (self::TIME_INPUT_FORMATS as $tf => $tp) {
                if ($out = $this->testDate($input, "!$df $tf", "!$df\T$tf")) {
                    return [$out->format(self::DATE_OUTPUT_FORMATS[$dp | $tp]), $dp | $tp];
                }
                foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
                    if ($out = $this->testDate($input, "!$df $tf$zf", "!$df\T$tf$zf","!$df $tf $zf", "!$df\T$tf $zf")) {
                        return [$out->format(self::DATE_OUTPUT_FORMATS[$dp | $tp | $zp]), $dp | $tp | $zp];
                    }
                    // if no match was found and we're testing a pattern ending in "O" (zone offset without colon), add double-zero to input and try again
                    if ($zf[strlen($zf) - 1] === "O") {
                        $padded = $input."00";
                        if ($out = $this->testDate($padded, "!$df $tf$zf", "!$df\T$tf$zf", "!$df $tf $zf", "!$df\T$tf $zf")) {
                            return [$out->format(self::DATE_OUTPUT_FORMATS[$dp | $tp | $zp]), $dp | $tp | $zp];
                        }
                    }
                }
            }
        }
        foreach (self::TIME_INPUT_FORMATS as $tf => $tp) {
            if ($out = $this->testDate($input, "!$tf")) {
                return [$out->format(self::DATE_OUTPUT_FORMATS[$tp]), $tp];
            }
            foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
                if ($out = $this->testDate($input, "!$tf$zf", "!$tf $zf")) {
                    return [$out->format(self::DATE_OUTPUT_FORMATS[$tp | $zp]), $tp | $zp];
                }
                if ($zf[strlen($zf) - 1] === "O") {
                    $padded = $input."00";
                    if ($out = $this->testDate($padded, "!$tf$zf", "!$tf $zf")) {
                        return [$out->format(self::DATE_OUTPUT_FORMATS[$tp | $zp]), $tp | $zp];
                    }
                }
            }
        }
        foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
            if ($out = $this->testDate($input, "!$zf")) {
                return [$out->format(self::DATE_OUTPUT_FORMATS[$zp]), $zp];
            }
            if ($zf[strlen($zf) - 1] === "O") {
                $padded = $input."00";
                if ($out = $this->testDate($padded, "!$zf")) {
                    return [$out->format(self::DATE_OUTPUT_FORMATS[$zp]), $zp];
                }
            }
        }
        return null;
    }

    protected function testDate(string $input, string ...$format): ?\DateTimeImmutable {
        foreach ($format as $f) {
            $out = \DateTimeImmutable::createFromFormat($f, $input, new \DateTimeZone("UTC"));
            if ($out && $out->format($f) === $input) {
                return $out;
            }
        }
        return null;
    }

    protected function normalizeUrl(string $url): string {
        // TODO: Stub
        return $url;
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

    protected function getBaseUrl(\DOMElement $root, string $base): string {
        $set = $root->ownerDocument->getElementsByTagName("base");
        if ($set->length) {
            return $this->normalizeUrl($set[0]->getAttribute("href"));
        }
        return $base;
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