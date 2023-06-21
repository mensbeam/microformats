<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

use MensBeam\HTML\Parser\Serializer;

class Parser {
    /** @var array A ranking of prefixes (with 0 being least preferred) to break ties when multiple properties of the same name exist on one element */
    protected const PREFIX_RANK = [
        "p"  => 1,
        "dt" => 2,
        "u"  => 3,
        "e"  => 4,
    ];
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
        'hnews'             => "h-news", // this is not a defined v2 dialect, but tests do require it
    ];
    /** @var array The list of class names which are backward-compatibility property markers. Each value is in turn an array listing the root (in v2 format) for which the property applies, the value of which is an indexed array containing the v2 prefix, v2 equivalent name, and possibly two other members: an array with additional classes to add to the element's effective class list, and whether processing of the property should be deferred till the microformat has been otherwise processed */
    protected const BACKCOMPAT_CLASSES = [
        'additional-name'   => ['h-card' => ["p", "additional-name"]],
        'adr'               => ['h-card' => ["p", "adr"]],
        'affiliation'       => ['h-resume' => ["p", "affiliation", ["vcard"]]],
        'agent'             => ['h-card' => ["p", "agent"]],
        'attendee'          => ['h-event' => ["p", "attendee"]],
        'author'            => ['h-entry' => ["p", "author", ["vcard"]], 'h-recipe' => ["p", "author", ["vcard"]], 'h-feed' => ["p", "author", ["vcard"]]],
        'average'           => ['h-review' => ["p", "average"], 'h-review-aggregate' => ["p", "average"]],
        'bday'              => ['h-card' => ["dt", "bday"]],
        'best'              => ['h-review' => ["p", "best"], 'h-review-aggregate' => ["p", "best"]],
        'brand'             => ['h-product' => ["p", "brand"]],
        'category'          => ['h-card' => ["p", "category"], 'h-entry' => ["p", "category"], 'h-event' => ["p", "category"],  'h-product' => ["p", "category"]],
        'class'             => ['h-card' => ["p", "class"]],
        'contact'           => ['h-resume' => ["p", "contact", ["vcard"]]],
        'count'             => ['h-review-aggregate' => ["p", "count"]],
        'country-name'      => ['h-adr' => ["p", "country-name"], 'h-card' => ["p", "country-name"]],
        'dateline'          => ['h-news' => ["p", "dateline"]],
        'description'       => ['h-event' => ["p", "description"], 'h-product' => ["p", "description"], 'h-review' => ["e", "content"]],
        'dtend'             => ['h-event' => ["dt", "end"]],
        'dtreviewed'        => ['h-review' => ["dt", "reviewed"]],
        'dtstart'           => ['h-event' => ["dt", "start"]],
        'duration'          => ['h-event' => ["dt", "duration"], 'h-recipe' => ["dt", "duration"]],
        'education'         => ['h-resume' => ["p", "education", ["vevent"]]],
        'email'             => ['h-card' => ["u", "email"]],
        'entry'             => ['h-news' => ["p", "entry"]],
        'entry-content'     => ['h-entry' => ["e", "content"]],
        'entry-date'        => ['h-entry' => ["dt", "published", [], true]], // also requires special processing
        'entry-summary'     => ['h-entry' => ["p", "summary"]],
        'entry-title'       => ['h-entry' => ["p", "name"]],
        'experience'        => ['h-resume' => ["p", "experience", ["vevent"]]],
        'extended-address'  => ['h-adr' => ["p", "extended-address"], 'h-card' => ["p", "extended-address"]],
        'family-name'       => ['h-card' => ["p", "family-name"]],
        'fn'                => ['h-card' => ["p", "name"], 'h-product' => ["p", "name"], 'h-recipe' => ["p", "name"], 'h-review' => ["p", "name"], 'h-review-aggregate' => ["p", "name"], 'h-item' => ["p", "name"]],
        'geo'               => ['h-card' => ["p", "geo"], 'h-event' => ["p", "geo"], 'h-news' => ["p", "geo"]],
        'given-name'        => ['h-card' => ["p", "given-name"]],
        'honorific-prefix'  => ['h-card' => ["p", "honorific-prefix"]],
        'honorific-suffix'  => ['h-card' => ["p", "honorific-suffix"]],
        'identifier'        => ['h-product' => ["u", "identifier"]],
        'ingredient'        => ['h-recipe' => ["p", "ingredient"]],
        'instructions'      => ['h-recipe' => ["e", "instructions"]],
        'item'              => ['h-review' => ["p", "item"], 'h-review-aggregate' => ["p", "item"]],
        'key'               => ['h-card' => ["u", "key"]],
        'label'             => ['h-card' => ["p", "label"]],
        'latitude'          => ['h-card' => ["p", "latitude"], 'h-event' => ["p", "latitude"], 'h-geo' => ["p", "latitude"]],
        'locality'          => ['h-adr' => ["p", "locality"], 'h-card' => ["p", "locality"]],
        'location'          => ['h-event' => ["p", "location"]],
        'logo'              => ['h-card' => ["u", "logo"]],
        'longitude'         => ['h-card' => ["p", "longitude"], 'h-event' => ["p", "longitude"], 'h-geo' => ["p", "longitude"]],
        'mailer'            => ['h-card' => ["p", "mailer"]],
        'nickname'          => ['h-card' => ["p", "nickname"]],
        'note'              => ['h-card' => ["p", "note"]],
        'nutrition'         => ['h-recipe' => ["p", "nutrition"]],
        'organization-name' => ['h-card' => ["p", "organization-name"]],
        'organization-unit' => ['h-card' => ["p", "organization-unit"]],
        'org'               => ['h-card' => ["p", "org"]],
        'photo'             => ['h-card' => ["u", "photo"], 'h-product' => ["u", "photo"], 'h-recipe' => ["u", "photo"], 'h-review' => ["u", "photo"], 'h-review-aggregate' => ["u", "photo"], 'h-feed' => ["u", "photo"], 'h-item' => ["u", "photo"]],
        'postal-code'       => ['h-adr' => ["p", "postal-code"], 'h-card' => ["p", "postal-code"]],
        'post-office-box'   => ['h-adr' => ["p", "post-office-box"], 'h-card' => ["p", "post-office-box"]],
        'price'             => ['h-product' => ["p", "price"]],
        'published'         => ['h-entry' => ["dt", "published"], 'h-recipe' => ["dt", "published"]],
        'rating'            => ['h-review' => ["p", "rating"], 'h-review-aggregate' => ["p", "rating"]],
        'region'            => ['h-adr' => ["p", "region"], 'h-card' => ["p", "region"]],
        'rev'               => ['h-card' => ["dt", "rev"]],
        'reviewer'          => ['h-review' => ["p", "author", ["vcard"]]],
        'review'            => ['h-product' => ["p", "review", ["hreview"]]], // also requires special processing
        'role'              => ['h-card' => ["p", "role"]],
        'skill'             => ['h-resume' => ["p", "skill"]],
        'site-description'  => ['h-feed' => ["p", "summary"]],
        'site-title'        => ['h-feed' => ["p", "name"]],
        'sound'             => ['h-card' => ["u", "sound"]],
        'source-org'        => ['h-news' => ["p", "source-org"]],
        'sort-string'       => ['h-card' => ["p", "sort-string"]],
        'street-address'    => ['h-adr' => ["p", "street-address"], 'h-card' => ["p", "street-address"]],
        'summary'           => ['h-event' => ["p", "name"], 'h-recipe' => ["p", "summary"], 'h-resume' => ["p", "summary"], 'h-review' => ["p", "name"], 'h-review-aggregate' => ["p", "name"]],
        'tel'               => ['h-card' => ["p", "tel"]],
        'title'             => ['h-card' => ["p", "job-title"]],
        'tz'                => ['h-card' => ["p", "tz"]],
        'uid'               => ['h-card' => ["u", "uid"]],
        'updated'           => ['h-entry' => ["dt", "updated"]],
        'url'               => ['h-card' => ["u", "url"], 'h-event' => ["u", "url"], 'h-product' => ["u", "url"], 'h-review' => ["u", "url"], 'h-review-aggregate' => ["u", "url"], 'h-feed' => ["u", "url"], 'h-item' => ["u", "url"]],
        'votes'             => ['h-review-aggregate' => ["p", "votes"]],
        'worst'             => ['h-review' => ["p", "worst"], 'h-review-aggregate' => ["p", "worst"]],
        'yield'             => ['h-recipe' => ["p", "yield"]],
    ];
    /** @var array The list of link relations which are backward-compatibility property markers. The format is the same as for backcompat classes */
    protected const BACKCOMPAT_RELATIONS = [
        // h-review and h-review-agregate also include "self bookmark", but this requires special processing
        // the tag relation also requires special processing
        'bookmark'     => ['h-entry' => ["u", "url"]],
        'tag'          => ['h-entry' => ["p", "category", [], true], 'h-feed' => ["p", "category"], 'h-review' => ["p", "category"], 'h-review-aggregate' => ["p", "category"]],
        'author'       => ['h-entry' => ["u", "author", [], true]],
        'item-license' => ['h-news' => ["u", "license"]],
        'principles'   => ['h-news' => ["u", "principles"]],
    ];
    /** @var array The list of (global) attributes which contain URLs and apply to any element */
    protected const URL_ATTRS_GLOBAL = ["itemid", "itemprop", "itemtype"];
    /** @var array The list of (non-global) attributes which contain URLs and their host elements */
    protected const URL_ATTRS = [
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
    protected const DATE_TYPE_ZULU = 1 << 5;
    protected const DATE_INPUT_FORMATS = [
        # YYYY-MM-DD
        'Y-m-d' => self::DATE_TYPE_DATE,
        # YYYY-DDD
        'Y-z'   => self::DATE_TYPE_DATE,
    ];
    protected const TIME_INPUT_FORMATS = [
        # HH:MM:SS
        'H:i:s'   => self::DATE_TYPE_SEC,
        # HH:MM
        'H:i'     => self::DATE_TYPE_MIN,
        # HH:MM:SSam HH:MM:SSpm
        'h:i:sa'  => self::DATE_TYPE_SEC,
        # HH:MMam HH:MMpm
        'h:ia'    => self::DATE_TYPE_MIN,
        # HHam HHpm
        'ha'      => self::DATE_TYPE_HOUR,
        // 12-hour clock without hour's leading zero; these are not part of the spec, but definitely occur
        'g:i:sa'  => self::DATE_TYPE_SEC,
        'g:ia'    => self::DATE_TYPE_MIN,
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
        '\Z' => self::DATE_TYPE_ZULU,
        // Lowercase z is used in tests
        '\z' => self::DATE_TYPE_ZULU,
    ];
    protected const DATE_OUTPUT_FORMATS = [
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC | self::DATE_TYPE_ZONE  => 'Y-m-d H:i:sO',
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC | self::DATE_TYPE_ZULU  => 'Y-m-d H:i:s\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE  => 'Y-m-d H:iO',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN | self::DATE_TYPE_ZULU  => 'Y-m-d H:i\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE => 'Y-m-d H:00O',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_ZULU => 'Y-m-d H:00\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC                         => 'Y-m-d H:i:s',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN                         => 'Y-m-d H:i',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR                        => 'Y-m-d H:00',
        self::DATE_TYPE_DATE                                               => 'Y-m-d',
        self::DATE_TYPE_SEC | self::DATE_TYPE_ZONE                         => 'H:i:sO',
        self::DATE_TYPE_SEC | self::DATE_TYPE_ZULU                         => 'H:i:s\Z',
        self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE                         => 'H:iO',
        self::DATE_TYPE_MIN | self::DATE_TYPE_ZULU                         => 'H:i\Z',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE                        => 'H:00O',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_ZULU                        => 'H:00\Z',
        self::DATE_TYPE_SEC                                                => 'H:i:s',
        self::DATE_TYPE_MIN                                                => 'H:i',
        self::DATE_TYPE_HOUR                                               => 'H:00',
        self::DATE_TYPE_ZONE                                               => 'O',
        self::DATE_TYPE_ZULU                                               => '\Z',
    ];

    protected $options;
    protected $baseUrl;
    protected $docUrl;
    protected $xpath;

    /** Parses a DOMElement for microformats
     *
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     */
    public function parseElement(\DOMElement $node, string $baseUrl = "", ?array $options = null): array {
        $root = $node;
        // normalize options
        $this->options = $this->normalizeOptions($options ?? []);
        // Perform HTML base-URL resolution
        $this->docUrl = $baseUrl;
        $this->baseUrl = $this->getBaseUrl($root, $baseUrl);
        // Initialize an XPath processor
        $this->xpath = new \DOMXPath($node->ownerDocument);
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
                $out['items'][] = $this->parseMicroformat($node, $types, false);
            } elseif ($types = $this->matchRootsBackcompat($classes)) {
                $out['items'][] = $this->parseMicroformat($node, $types, true);
            } else {
                # if none found, parse child elements for microformats (depth first, doc order)
                $node = $this->nextElement($node, $root, true);
                continue;
            }
            // continue to the next element, passing over children (they have already been examined)
            $node = $this->nextElement($node, $root, false);
        }
        # parse all hyperlink (<a> <area> <link>) elements for rel microformats, adding to the JSON rels & rel-urls hashes accordingly
        foreach ($this->xpath->query(".//a[@rel][@href]|.//area[@rel][@href]|.//link[@rel][@href]", $root) as $link) {
            # To parse a hyperlink element (e.g. a or link) for rel
            #   microformats: use the following algorithm or an algorithm that
            #   produces equivalent results:
            # set url to the value of the "href" attribute of the element,
            #   normalized to be an absolute URL following the containing
            #   document's language's rules for resolving relative URLs (e.g.
            #   in HTML, use the current URL context as determined by the
            #   page, and first <base> element if any).
            $url = $this->normalizeUrl($link->getAttribute("href"));
            # treat the "rel" attribute of the element as a space separate set of rel values
            $rels = $this->parseTokens($link, "rel");
            # # for each rel value (rel-value)
            foreach ($rels as $relValue) {
                # if there is no key rel-value in the rels hash then create it with an empty array as its value
                if (!isset($out['rels'][$relValue])) {
                    $out['rels'][$relValue] = [];
                }
                # if url is not in the array of the key rel-value in the rels hash then add url to the array
                // NOTE: We add unconditionally and will filter for uniqueness later
                $out['rels'][$relValue][] = $url;
            }
            # if there is no key with name url in the top-level "rel-urls"
            #   hash then add a key with name url there, with an empty hash
            #   value
            if (!isset($out['rel-urls'][$url])) {
                $out['rel-urls'][$url] = [];
            }
            # add keys to the hash of the key with name url for each of these
            #   attributes (if present) and key not already set:
            #       "hreflang": the value of the "hreflang" attribute
            #       "media": the value of the "media" attribute
            #       "title": the value of the "title" attribute
            #       "type": the value of the "type" attribute
            #       "text": the text content of the element if any
            foreach (["hreflang", "media", "title", "type"] as $attr) {
                if (!isset($out['rel-urls'][$url][$attr]) && $link->hasAttribute($attr)) {
                    $out['rel-urls'][$url][$attr] = trim($link->getAttribute($attr));
                }
            }
            if (!isset($out['rel-urls'][$url]['text']) && strlen($text = $this->getCleanText($link, "p"))) {
                $out['rel-urls'][$url]['text'] = $text;
            }
            # if there is no "rels" key in that hash, add it with an empty array value
            if (!isset($out['rel-urls'][$url]['rels'])) {
                $out['rel-urls'][$url]['rels'] = [];
            }
            # set the value of that "rels" key to an array of all unique items
            #   in the set of rel values unioned with the current array value
            #   of the "rels" key, sorted alphabetically.
            // NOTE: sorting  and uniqueness filtering will be done later
            array_push($out['rel-urls'][$url]['rels'], ...$rels);
        }
        // sort and clean rel microformats
        foreach ($out['rels'] as $k => $v) {
            $out['rels'][$k] = array_values(array_unique($v));
        }
        foreach ($out['rel-urls'] as $k => $v) {
            $out['rel-urls'][$k]['rels'] = array_unique($v['rels']);
            sort($out['rel-urls'][$k]['rels']);
        }
        // clean up temporary instance properties
        foreach (["options", "xpath", "docUrl", "baseUrl"] as $prop) {
            $this->$prop = null;
        }
        # return the resulting JSON
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
            // PROPOSAL: https://github.com/microformats/microformats2-parsing/issues/59
            // exclude Tailwind classes https://tailwindcss.com/docs/height
            return preg_match('/^h(?:-[a-z0-9]+)?(?:-[a-z]+)+$/S', $c) && !preg_match('/^h-(?:px|auto|full|screen|min|max|fit)$/S', $c);
        });
    }

    protected function matchRootsBackcompat(array $classes, bool $backcompat = false): array {
        $out = [];
        $item = false;
        foreach ($classes as $c) {
            if ($backcompat && $c === "item") {
                // we only consider "item" a root if there are no other roots
                $item = true;
            }
            if ($compat = self::BACKCOMPAT_ROOTS[$c] ?? null) {
                $out[] = $compat;
            }
        }
        if ($item && !$out) {
            $out[] = "h-item";
        }
        return $out;
    }

    protected function hasRoots(\DOMElement $node): bool {
        $classes = $this->parseTokens($node, "class");
        return (bool) ($this->matchRootsMf2($classes) ?: $this->matchRootsBackcompat($classes));
    }

    protected function matchPropertiesMf2(array $classes): array {
        $out = [];
        foreach ($classes as $c) {
            # The "*" for root (and property) class names consists of an
            #   optional vendor prefix (series of 1+ number or lowercase
            #   a-z characters i.e. [0-9a-z]+, followed by '-'), then one
            #   or more '-' separated lowercase a-z words.
            if (preg_match('/^(p|u|dt|e)((?:-[a-z0-9]+)?(?:-[a-z]+)+)$/S', $c, $match)) {
                $prefix = $match[1];
                $name = substr($match[2], 1);
                if (!isset($out[$name])) {
                    // property with this name has not been seen yet; add it
                    $out[$name] = [$prefix, $name];
                } elseif (static::PREFIX_RANK[$prefix] > static::PREFIX_RANK[$out[$name][0]]) {
                    // property prefix is of a higher rank than one already seen; use the new prefix
                    $out[$name][0] = $prefix;
                }
            }
        }
        return array_values($out);
    }

    protected function matchPropertiesBackcompat(array &$classes, array $types, \DOMElement $node): array {
        $props = [];
        $out = [];
        foreach ($types as $t) {
            // check for backcompat classes
            foreach ($classes as $c) {
                if ($c === "entry-date" && ($node->localName !== "time" || !$node->hasAttribute("datetime"))) {
                    // entry-date is only valid on time elements with a machine-readable datetime
                    continue;
                } elseif ($map = static::BACKCOMPAT_CLASSES[$c][$t] ?? null) {
                    $props[] = $map;
                }
            }
            // check for backcompat relations, if the node is of the appropriate type
            if (in_array($node->localName, ["a", "area", "link"])) {
                $relations = $this->parseTokens($node, "rel");
                foreach ($relations as $r) {
                    if ($map = static::BACKCOMPAT_RELATIONS[$r][$t] ?? null) {
                        $props[] = $map;
                    }
                }
                // check for "self bookmark" relations, if applicable
                if (in_array($t, ["h-review", "h-review-aggregate"]) && sizeof(array_intersect(["self", "bookmark"], $relations)) === 2) {
                    $props[] = ["u", "url"];
                }
            }
        }
        // filter the list of properties for uniqueness by name
        // while we're at it we'll also add extra roots where needed
        foreach ($props as $map) {
            $prefix = $map[0];
            $name = $map[1];
            $extraRoots = $map[2] ?? [];
            if (
                // property with this name has not been seen yet
                !isset($out[$name])
                // property prefix is of a higher rank than one already seen and isn't deferrable
                || (static::PREFIX_RANK[$prefix] > static::PREFIX_RANK[$out[$name][0]] && !($map[3] ?? false))
            ) {
                $out[$name] = $map;
                // add any extra roots, where needed
                // The "hreview" class is a special case as "hreview-aggregate" is equivalent
                foreach ($extraRoots as $r) {
                    if (!in_array($r, $classes) && !($r === "hreview" && in_array("hreview-aggregate", $classes))) {
                        $classes[] = $r;
                    }
                }
            }
        }
        return array_values($out);
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
        // keep track of the implied date
        $impliedDate = null;
        // keep track of whether there is a p- or e- property or child on the microformat; this is required for implied property processing
        $hasP = false;
        $hasE = false;
        $hasU = false;
        $hasChild = false;
        # parse child elements (document order) by:
        while ($node = $this->nextElement($node ?? $root, $root, !($child = $child ?? false))) {
            $child = null;
            $classes = $this->parseTokens($node, "class");
            if ($backcompat) {
                # if parsing a backcompat root, parse child element class name(s) for backcompat properties
                $properties = $this->matchPropertiesBackcompat($classes, $types, $node);
            } else {
                # else parse a child element class for property class name(s) "p-*,u-*,dt-*,e-*"
                $properties = $this->matchPropertiesMf2($classes);
            }
            # parse a child element for microformats (recurse)
            // NOTE: We do this in a different order from the spec because this seems to be what is actually required
            if ($childTypes = $this->matchRootsMf2($classes)) {
                $child = $this->parseMicroformat($node, $childTypes, false);
                $hasChild = true;
            } elseif ($childTypes = $this->matchRootsBackcompat($classes, $backcompat)) {
                $child = $this->parseMicroformat($node, $childTypes, true);
                $hasChild = true;
            }
            # [if the element is a microformat and it has no properties] add
            #   found elements that are microformats to the "children" array
            if ($child && !$properties) {
                if (!isset($out['children'])) {
                    $out['children'] = [];
                }
                $out['children'][] = $child;
            }
            # if such class(es) are found, it is a property element
            # add properties found to current microformat's properties: { } structure
            foreach ($properties as $p) {
                [$prefix, $key, $extraRoots, $defer] = array_pad($p, 5, null);
                $hasP = $hasP ?: $prefix === "p";
                $hasE = $hasE ?: $prefix === "e";
                $hasU = $hasU ?: $prefix === "u";
                // parse the node for the property value
                $value = $this->parseProperty($node, $prefix, $backcompat ? $types : [], $impliedDate, (bool) $child);
                if ($prefix === "dt") {
                    // keep track of the last seen date value to serve as an implied date
                    $impliedDate = $value;
                }
                # if that child element itself has a microformat ("h-*" or
                #   backcompat roots) and is a property element, add it into
                #   the array of values for that property as a { } structure,
                #   add to that { } structure:
                #     value:
                if ($child) {
                    if ($prefix === "p" && isset($child['properties']['name'])) {
                        # if it's a p-* property element, use the first p-name of the h-* child
                        $childValue = ['value' => $child['properties']['name'][0]];
                    } elseif ($prefix === "e") {
                        # else if it's an e-* property element, re-use its { } structure with existing value: inside.
                        // NOTE: This is misleading; the 'value' and 'html' keys should both be copied over
                        $childValue = ['value' => $value['value'], 'html' => $value['html']];
                    } elseif ($prefix === "u" && isset($child['properties']['url'])) {
                        # else if it's a u-* property element and the h-* child has a u-url, use the first such u-url
                        $childValue = ['value' => $child['properties']['url'][0]];
                    } else {
                        # else use the parsed property value per p-*,u-*,dt-* parsing respectively
                        $childValue = ['value' => $value];
                    }
                    $value = array_merge($childValue, $child);
                    $childValue = null;
                }
                if ($defer) {
                    // defer addition of the property if it's supposed to be a fallback for another instance of the property
                    $deferred[] = [$key, $value];
                } else {
                    if (!isset($out['properties'][$key])) {
                        $out['properties'][$key] = [];
                    }
                    $out['properties'][$key][] = $value;
                }
            }
        }
        // add any deferred properties
        $known = array_keys($out['properties']);
        foreach ($deferred as [$key, $value]) {
            if (!in_array($key, $known)) {
                if (!isset($out['properties'][$key])) {
                    $out['properties'][$key] = [];
                }
                $out['properties'][$key][] = $value;
            }
        }
        # imply properties for the found microformat
        if (!$backcompat) {
            # if no explicit "name" property, and no other p-* or e-* properties, and no nested microformats,
            if (!isset($out['properties']['name']) && !$hasChild && !$hasP && !$hasE) {
                # then imply by:
                if ($root->hasAttribute("alt") && in_array($root->localName, ["img", "area"])) {
                    # if img.h-x or area.h-x, then use its alt attribute for name
                    $name = $root->getAttribute("alt");
                } elseif ($root->hasAttribute("title") && $root->localName === "abbr") {
                    # else if abbr.h-x[title] then use its title attribute for name
                    $name = $root->getAttribute("title");
                } elseif (($set = $this->xpath->query("./img[@alt and @alt != '' and count(../*) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>img:only-child[alt]:not([alt=""]):not[.h-*] then use that img’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (($set = $this->xpath->query("./area[@alt and @alt != '' and count(../*) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>area:only-child[alt]:not([alt=""]):not[.h-*] then use that area’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (($set = $this->xpath->query("./abbr[@title and @title != '' and count(../*) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>abbr:only-child[title]:not([title=""]):not[.h-*] then use that abbr title for name
                    $name = $set->item(0)->getAttribute("title");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./img[@alt and @alt != '' and count(../*) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>img:only-child[alt]:not([alt=""]):not[.h-*] then use that img’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./area[@alt and @alt != '' and count(../*) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>area:only-child[alt]:not([alt=""]):not[.h-*] then use that area’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./abbr[@title and @title != '' and count(../*) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>abbr:only-child[title]:not([title=""]):not[.h-*] use that abbr’s title for name
                    $name = $set->item(0)->getAttribute("title");
                } else {
                    # else use the textContent of the .h-x for name after [cleaning]
                    $name = $this->getCleanText($root, "p");
                }
                # remove all leading/trailing spaces
                $out['properties']['name'] = [trim($name)];
            }
            # if no explicit "photo" property, and no other explicit u-* (Proposed: change to: u-* or e-*) properties, and no nested microformats,
            if (!isset($out['properties']['photo']) && !$hasChild && !$hasU && !$hasE) {
                $photo = null;
                # then imply by:
                if ($root->localName === "img" && $root->hasAttribute("src")) {
                    # if img.h-x[src], then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($root)];
                } elseif ($root->localName === "object" && $root->hasAttribute("data")) {
                    # else if object.h-x[data] then use data for photo
                    $photo = $root->getAttribute("data");
                } elseif (($set = $this->xpath->query("./img[@src and count(../img) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>img[src]:only-of-type:not[.h-*] then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($set->item(0))];
                } elseif (($set = $this->xpath->query("./object[@data and count(../object) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>object[data]:only-of-type:not[.h-*] then use that object’s data for photo
                    $photo = $set->item(0)->getAttribute("data");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./img[@src and count(../img) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>img[src]:only-of-type:not[.h-*], then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($set->item(0))];
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./object[@data and count(../object) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>object[data]:only-of-type:not[.h-*], then use that object’s data for photo
                    $photo = $set->item(0)->getAttribute("data");
                }
                if (is_string($photo)) {
                    # if there is a gotten photo value, return the normalized
                    #   absolute URL of it, following the containing document's
                    #   language's rules for resolving relative URLs (e.g. in
                    #   HTML, use the current URL context as determined by the
                    #   page, and first <base> element, if any).
                    $out['properties']['photo'] = [$this->normalizeUrl($photo)];
                }
            }
            # if no explicit "url" property, and no other explicit u-* (Proposed: change to: u-* or e-*) properties, and no nested microformats,
            if (!isset($out['properties']['url']) && !$hasChild && !$hasU && !$hasE) {
                $url = null;
                # then imply by:
                if ($root->hasAttribute("href") && in_array($root->localName, ["a", "area"])) {
                    # if a.h-x[href] or area.h-x[href] then use that [href] for url
                    $url = $root->getAttribute("href");
                } elseif (($set = $this->xpath->query("./a[@href and count(../a) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>a[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (($set = $this->xpath->query("./area[@href and count(../area) = 1]", $root))->length && !$this->hasRoots($set->item(0))) {
                    # else if .h-x>area[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./a[@href and count(../a) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>a[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (
                    ($set = $this->xpath->query("./*[not(template) and count(../*) = 1]", $root))->length
                    && !$this->hasRoots($set->item(0))
                    && ($set = $this->xpath->query("./area[@href and count(../area) = 1]", $set->item(0)))->length
                    && !$this->hasRoots($set->item(0))
                ) {
                    # else if .h-x>:only-child:not[.h-*]>area[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                }
                if (is_string($url)) {
                    # if there is a gotten url value, return the normalized
                    #   absolute URL of it, following the containing document's
                    #   language's rules for resolving relative URLs (e.g. in
                    #   HTML, use the current URL context as determined by the
                    #   page, and first <base> element, if any).
                    $out['properties']['url'] = [$this->normalizeUrl($url)];
                }
            }
        }
        // return the final structure
        return $out;
    }

    protected function parseProperty(\DOMElement $node, string $prefix, array $backcompatTypes, ?string $impliedDate, bool $isChild) {
        switch ($prefix) {
            case "p":
                # To parse an element for a p-x property value (whether explicit p-* or backcompat equivalent):
                if (!$isChild && $text = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) {
                    # Parse the element for the Value Class Pattern. If a value is found, return it.
                    return $text;
                } elseif (in_array($node->localName, ["abbr", "link"]) && $node->hasAttribute("title")) {
                    # If abbr.p-x[title] or link.p-x[title], return the title attribute.
                    return $node->getAttribute("title");
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.p-x[value] or input.p-x[value], then return the value attribute
                    return $node->getAttribute("value");
                } elseif (in_array($node->localName, ["img", "area"]) && $node->hasAttribute("alt")) {
                    # else if img.p-x[alt] or area.p-x[alt], then return the alt attribute
                    return $node->getAttribute("alt");
                } elseif (in_array($node->localName, ["a", "area", "link"]) && array_intersect($backcompatTypes, array_keys(static::BACKCOMPAT_RELATIONS['tag'])) && preg_match('/\btag\b/', $node->getAttribute("rel"))) {
                    // we have encountered a tag relation during backcompat processing
                    // https://microformats.org/wiki/rel-tag#Abstract
                    // we are required to retrieve the last component of the URL path and use that
                    if (preg_match('#([^/]*)/?$#', URL::fromString($this->normalizeUrl($node->getAttribute("href")))->getPath(), $match)) {
                        return urldecode($match[1]);
                    }
                    return "";
                }
                # else return the textContent of the element after [cleaning]
                return $this->getCleanText($node, $prefix);
            case "u":
                # To parse an element for a u-x property value (whether explicit u-* or backcompat equivalent):
                if (in_array($node->localName, ["a", "area", "link"]) && $node->hasAttribute("href")) {
                    # if a.u-x[href] or area.u-x[href] or link.u-x[href], then get the href attribute
                    $url = $node->getAttribute("href");
                } elseif ($node->localName === "img" && $node->hasAttribute("src")) {
                    # else if img.u-x[src] return the result of "parse an img element for src and alt" (see Sec.1.5)
                    // NOTE: this seems not to apply to backcompat processing
                    if (!$backcompatTypes) {
                        return $this->parseImg($node);
                    } else {
                        $url= $node->getAttribute("src");
                    }
                } elseif (in_array($node->localName, ["audio", "video", "source", "iframe"]) && $node->hasAttribute("src")) {
                    # else if audio.u-x[src] or video.u-x[src] or source.u-x[src] or iframe.u-x[src], then get the src attribute
                    $url = $node->getAttribute("src");
                } elseif ($node->localName === "video" && $node->hasAttribute("poster")) {
                    # else if video.u-x[poster], then get the poster attribute
                    $url = $node->getAttribute("poster");
                } elseif ($node->localName === "object" && $node->hasAttribute("data")) {
                    # else if object.u-x[data], then get the data attribute
                    $url = $node->getAttribute("data");
                } elseif (!$isChild && $url = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) {
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
                // NOTE: Because we perform implied date resolution we don't blindly return data from nodes; returning is done below after checks
                # To parse an element for a dt-x property value (whether explicit dt-* or backcompat equivalent):
                if (!$isChild && $date = $this->getValueClassPattern($node, $prefix, $backcompatTypes, $impliedDate)) {
                    # parse the element for the Value Class Pattern, including the date and time parsing rules. If a value is found, then return it.
                    return $date;
                } elseif (in_array($node->localName, ["time", "ins", "del"]) && $node->hasAttribute("datetime")) {
                    # if time.dt-x[datetime] or ins.dt-x[datetime] or del.dt-x[datetime], then return the datetime attribute
                    $date = $node->getAttribute("datetime");
                } elseif ($node->localName === "abbr" && $node->hasAttribute("title")) {
                    # else if abbr.dt-x[title], then return the title attribute
                    $date = $node->getAttribute("title");
                } elseif (in_array($node->localName, ["data", "input"]) && $node->hasAttribute("value")) {
                    # else if data.dt-x[value] or input.dt-x[value], then return the value attribute
                    $date = $node->getAttribute("value");
                } else {
                    # else return the textContent of the element after removing all leading/trailing spaces and nested <script> & <style> elements.
                    $date = $this->getCleanText($node, $prefix);
                }
                return $this->stitchDate($this->parseDatePart($date), $impliedDate) ?? $date;
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
                // normalize URLs in the copy
                $copyNode = $copy;
                while ($copyNode) {
                    foreach (array_merge(self::URL_ATTRS_GLOBAL, self::URL_ATTRS[$copyNode->localName] ?? []) as $attr) {
                        if ($copyNode->hasAttribute($attr)) {
                            $copyNode->setAttribute($attr, $this->normalizeUrl($copyNode->getAttribute($attr), ($copyNode->localName === "base" ? $this->docUrl : $this->baseUrl)));
                        }
                    }
                    $copyNode = $this->nextElement($copyNode, $copy, true);
                }
                // return the result
                return [
                    'html'  => trim(Serializer::serializeInner($copy)),
                    'value' => $this->getCleanText($node, $prefix),
                ];
            default:
                throw new \Exception("Unimplemented prefix $prefix");
        }
    }

    protected function getValueClassPattern(\DOMElement $root, string $prefix, array $backcompatTypes, ?string $impliedDate = null) {
        $out = [];
        $skipChildren = false;
        while ($node = $this->nextElement($node ?? $root, $root, !$skipChildren)) {
            $skipChildren = false;
            $classes = $this->parseTokens($node, "class");
            $candidate = null;
            if (!array_intersect(["value", "value-title"], $classes) && (
                ($backcompatTypes && ($this->matchPropertiesBackcompat($classes, $backcompatTypes, $node) || $this->matchRootsBackcompat($classes)))
                || ($this->matchRootsMf2($classes) || $this->matchPropertiesMf2($classes))
            )) {
                // only consider elements which are not themselves properties or roots, unless they have a value
                // NOTE: The specification doesn't mention roots, but these should surely be skipped as well
                $skipChildren = true;
                continue;
            } elseif ($node->hasAttribute("title") && in_array("value-title", $classes)) {
                $candidate = trim($node->getAttribute("title"));
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
            } else {
                continue;
            }
            if ($prefix !== "dt") {
                $skipChildren = true;
                $out[] = $candidate;
            } else {
                // parse and normalize date parts
                $candidate = $this->parseDatePart($candidate);
                if ($candidate && !(
                    # ignore any further "value" elements that specify the date.
                    (isset($out['date']) && isset($candidate['date']))
                    # ignore any further "value" elements that specify the time.
                    || (isset($out['time']) && isset($candidate['time']))
                    # ignore any further "value" elements that specify the timezone.
                    || (isset($out['zone']) && isset($candidate['zone']))
                )) {
                    $skipChildren = true;
                    $out += $candidate;
                }
            }
        }
        if ($out) {
            if ($prefix !== "dt") {
                # if the microformats property expects a simple string, enumerated
                #   value, or telephone number, then the values extracted from the
                #   value elements should be concatenated without inserting
                #   additional characters or white-space.
                return implode("", $out);
            } else {
                # if the microformats property expects a datetime value, see the Date Time Parsing section.
                // The rules for datetimes are dispersed elsewhere. All that's required here is to stitch parts together
                return $this->stitchDate($out, $impliedDate);
            }
        } else {
            return null;
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

    protected function parseDatePart(string $input): array {
        // do a first-pass normalization on the input; this normalizes am/pm and normalizes and trims whitespace
        $input = trim(preg_replace(['/([ap])\.m\.$/', '/\s+/'], ["$1m", " "], strtr($input, "APM", "apm")));
        // match against all valid date/time format patterns and returns the matched parts
        // we try with space and with T between date and time, as well as with and without space before time zone
        foreach (self::DATE_INPUT_FORMATS as $df => $dp) {
            if ($out = $this->testDate($input, "$df")) {
                return [
                    'date' => $out->format(self::DATE_OUTPUT_FORMATS[$dp]),
                ];
            }
            foreach (self::TIME_INPUT_FORMATS as $tf => $tp) {
                if ($out = $this->testDate($input, "$df $tf", "$df\T$tf", "$df\\t$tf")) {
                    return [
                        'date' => $out->format(self::DATE_OUTPUT_FORMATS[$dp]),
                        'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                    ];
                }
                foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
                    if ($out = $this->testDate($input, "$df $tf$zf", "$df\T$tf$zf", "$df\\t$tf$zf", "$df $tf $zf", "$df\T$tf $zf", "$df\\t$tf $zf")) {
                        return [
                            'date' => $out->format(self::DATE_OUTPUT_FORMATS[$dp]),
                            'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                            'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                        ];
                    }
                    // if no match was found and we're testing a pattern ending in "O" (zone offset without colon), add double-zero to input and try again
                    if ($zf[strlen($zf) - 1] === "O") {
                        $padded = $input."00";
                        if ($out = $this->testDate($padded, "$df $tf$zf", "$df\T$tf$zf", "$df\\t$tf$zf", "$df $tf $zf", "$df\T$tf $zf", "$df\\t$tf $zf")) {
                            return [
                                'date' => $out->format(self::DATE_OUTPUT_FORMATS[$dp]),
                                'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                                'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                            ];
                        }
                    }
                }
            }
        }
        foreach (self::TIME_INPUT_FORMATS as $tf => $tp) {
            if ($out = $this->testDate($input, "$tf")) {
                return [
                    'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                ];
            }
            foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
                if ($out = $this->testDate($input, "$tf$zf", "$tf $zf")) {
                    return [
                        'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                        'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                    ];
                }
                if ($zf[strlen($zf) - 1] === "O") {
                    $padded = $input."00";
                    if ($out = $this->testDate($padded, "$tf$zf", "$tf $zf")) {
                        return [
                            'time' => $out->format(self::DATE_OUTPUT_FORMATS[$tp]),
                            'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                        ];
                    }
                }
            }
        }
        foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
            if ($out = $this->testDate($input, "$zf")) {
                return [
                    'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                ];
            }
            if ($zf[strlen($zf) - 1] === "O") {
                $padded = $input."00";
                if ($out = $this->testDate($padded, "$zf")) {
                    return [
                        'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                    ];
                }
            }
        }
        return [];
    }

    protected function testDate(string $input, string ...$format): ?\DateTimeImmutable {
        foreach ($format as $f) {
            $out = \DateTimeImmutable::createFromFormat("!$f", $input, new \DateTimeZone("UTC"));
            if ($out && $out->format($f) === $input) {
                return $out;
            }
        }
        return null;
    }

    protected function stitchDate(array $parts, ?string $implied): ?string {
        if (sizeof($parts) === 3) {
            return $parts['date']." ".$parts['time'].$parts['zone'];
        } elseif (sizeof($parts) === 1 && isset($parts['date'])) {
            return $parts['date'];
        } else {
            $implied = $implied ? $this->parseDatePart($implied) : [];
            // PROPOSAL: https://github.com/microformats/microformats2-parsing/issues/4
            // only imply time zone if so configured
            if (!$this->options['impliedTz']) {
                $implied['zone'] = null;
            }
            if (isset($parts['date']) && isset($parts['time'])) {
                return $parts['date']." ".$parts['time'].($implied['zone'] ?? "");
            } elseif (isset($parts['time']) && isset($implied['date'])) {
                return $implied['date']." ".$parts['time'].($parts['zone'] ?? $implied['zone'] ?? "");
            }
        }
        return null;
    }

    protected function normalizeUrl(string $url, string $baseUrl = null): string {
        // TODO: Implement better URL parser
        try {
            return (string) Url::fromString($url, $baseUrl ?? $this->baseUrl);
        } catch (\Exception $e) {
            return $url;
        }
    }

    protected function getCleanText(\DOMElement $node, string $prefix): string {
        if ($this->options['basicTrim']) {
            return $this->getCleanTextBasic($node, $prefix);
        } else {
            // https://microformats.org/wiki/textcontent-parsing
            # Plain text of element
            # To get the plain text for an Element input:
            # Let output be the result of running [Element to string] on input
            $output = $this->getCleanTextThorough($node, $prefix);
            # Remove any sequence of one or more consecutive U+0020 SPACE code points directly before and after an U+000A LF code point from output
            $output = preg_replace('/^\s+|\s+$/m', "", $output);
            # Strip leading and trailing ASCII whitespace from output
            $output = trim($output);
            # Replace any sequence of one or more consecutive U+0020 SPACE code points in output with a single U+0020 SPACE code point
            $output = preg_replace('/ {2,}/', " ", $output);
            # Return output
            return $output;
        }
    }

    protected function getCleanTextThorough(\DOMElement $node, string $prefix): string {
        # Element to string
        # To get the string value for an Element input:
        # Let output be an empty list
        $output = [];
        # Let children be the children of input in tree order
        # For each child in children:
        foreach ($node->childNodes as $n) {
            if ($n instanceof \DOMText) {
                # If child is a Text node:
                # Let value be the textContent of child
                $value = $n->textContent;
                # Replace any U+0009 TAB, U+000A LF, and U+000D CR code points in value with a single U+0020 SPACE code point
                // NOTE: This ought to include FORM FEED characters
                $value = strtr($value, "\t\n\r\f", "    ");
                # Append value to output
                $output[] = $value;
            } elseif ($n instanceof \DOMElement) {
                # If child is an Element, switch on its tagName:
                // NOTE: we switch on localName instead to avoid silly case folding
                switch ($n->localName) {
                    case "script":
                    case "style":
                    case "template":
                        # SCRIPT
                        # STYLE
                        // TEMPLATE as well
                        # Continue
                        continue 2;
                    case "img":
                        # IMG
                        if ($n->hasAttribute("alt")) {
                            # If child has an alt attribute, then:
                            # Let value be the contents of the alt attribute
                            # Strip leading and trailing ASCII whitespace from value
                            $value = trim($n->getAttribute("alt"));
                        } elseif ($n->hasAttribute("src")) {
                            # Else if child has a src attribute, then:
                            # Let value be the contents of the src attribute
                            # Strip leading and trailing ASCII whitespace from value
                            $value = trim($n->getAttribute("src"));
                            # Set value to the absolute URL created by resolving value following the containing document’s language’s rules
                            $value = $this->normalizeUrl($value);
                        } else {
                            # Else continue
                            continue 2;
                        }
                        # Append and prepend a single U+0020 SPACE code point to value
                        # Append value to output
                        $output[] = " ".$value." ";
                        break;
                    case "br":
                        # BR
                        # Append a string containing a single U+000A LF code point to output
                        $output[] = "\n";
                        break;
                    case "p":
                        # P
                        # Let value be the result of running this algorithm on child
                        # Prepend a single U+000A LF code point to value
                        # Append value to output
                        $output[] = "\n".$this->getCleanTextThorough($n, $prefix);
                        break;
                    default:
                        # Any other value
                        # Let value be the result of running this algorithm on child
                        # Append value to output
                        $output[] = $this->getCleanTextThorough($n, $prefix);
                        break;
                }
            } else {
                # Else continue
                continue;
            }
        }
        # Return the concatenation of output
        return implode("", $output);
    }

    protected function getCleanTextBasic(\DOMElement $node, string $prefix): string {
        #  the textContent of the element after:
        $copy = $node->cloneNode(true);
        # dropping any nested <script> & <style> elements;
        foreach ($copy->getElementsByTagName("script") as $e) {
            $e->parentNode->removeChild($e);
        }
        foreach ($copy->getElementsByTagName("style") as $e) {
            $e->parentNode->removeChild($e);
        }
        // also drop templates; their contents would not normally be included in textContent
        foreach ($copy->getElementsByTagName("template") as $e) {
            $e->parentNode->removeChild($e);
        }
        # replacing any nested <img> elements with their alt attribute, if
        #   present; otherwise their src attribute, if present, adding a
        #   space at the beginning and end, resolving the URL if it’s
        #   relative;
        foreach ($copy->getElementsByTagName("img") as $e) {
            $alt = $e->getAttribute("alt");
            $src = " ".($e->hasAttribute("src") ? $this->normalizeUrl($e->getAttribute("src")) : "")." ";
            if ($prefix === "u") {
                // alt sttribute does not apply to u-properties
                $attr = strlen($src) ? $src : "";
            } elseif ($prefix === "e") {
                $attr = strlen($alt) ? $alt : $src;
            } else {
                // src attribute does not apply to p-properties
                $attr = strlen($alt) ? $alt : "";
            }
            $e->parentNode->replaceChild($e->ownerDocument->createTextNode($attr), $e);
        }
        # removing all leading/trailing spaces
        return trim($copy->textContent);
    }

    protected function getBaseUrl(\DOMElement $root, string $base): string {
        $set = $root->ownerDocument->getElementsByTagName("base");
        if ($set->length) {
            return $this->normalizeUrl($set[0]->getAttribute("href"), $base);
        }
        return $base;
    }

    /** Finds the next element in tree order after $node, if any
     *
     * @param \DOMNode $node The context node
     * @param \DOMElement $root The element to consider the contextual root of the tree
     * @param bool $considerChildren Whether or not child nodes are valid next nodes
     */
    protected function nextElement(\DOMElement $node, \DOMElement $root, bool $considerChildren): ?\DOMElement {
        if ($considerChildren && $node->hasChildNodes()) {
            $node = $node->firstChild;
            $next = $node;
        } elseif ($node->isSameNode($root)) {
            return null;
         } else {
            $next = $node->nextSibling;
        }
        while ($next && (!$next instanceof \DOMElement ||  $next->localName === "template")) {
            $next = $next->nextSibling;
        }
        while (!$next) {
            $node = $node->parentNode;
            if (!$node || $node->isSameNode($root)) {
                return null;
            }
            $next = $node->nextSibling;
            while ($next && (!$next instanceof \DOMElement ||  $next->localName === "template")) {
                $next = $next->nextSibling;
            }
        }
        return $next;
    }

    protected function normalizeOptions(array $options) {
        return [
            'impliedTz' => (bool) ($options['impliedTz'] ?? false),
            'basicTrim' => (bool) ($options['basicTrim'] ?? false),
        ];
    }
}
