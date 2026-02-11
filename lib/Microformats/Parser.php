<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats;

use MensBeam\HTML\Parser\Serializer;

/** A generic parser for microformats
 *
 * It implements Microformats v2 as well as backwards-compatible processing of
 * so-called "classic" or "backcompat" Microformats. Some of its functionality
 * is optional. Where an $options array is a possible parameter, the following
 * keys are understood:
 * 
 * - `dateNormalization` (bool) Whether to perform date and time normalization
 * throughout parsing rather than only in value-class parsing where it is
 * required by the specification. True by default
 * - `impliedTz` (bool) Whether to allow an implied datetime value to supply an
 * implied timezone to datetimes without a timezone
 * - `lang` (bool) Whether to include language information in microformat and
 * rich-text structures. True by default
 * - `thoroughTrim` (bool) Whether to use the more thorough whitespace-trimming
 * algorithm proposed for future standardization rather than the "classic",
 * simpler whitespace-trimming algorithm mandated by the parsing specification.
 * True by default.
 */
class Parser {
    /** @var array A ranking of prefixes (with 1 being least preferred) to break ties when multiple properties of the same name exist on one element */
    protected const PREFIX_RANK = [
        "p"  => 1,
        "dt" => 2,
        "u"  => 3,
        "e"  => 4,
    ];
    /** @var array The list of class names which are backward-compatibility microformat markers */
    protected const BACKCOMPAT_ROOTS = [
        // NOTE: "item" also functions as a root, but it is never a first-level root, so it is not listed here and is instead accommodated via special processing
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
        'key'               => ['h-card' => ["p", "key"]], // h-card docs say u-, but test suite assumes p-; see https://microformats.org/wiki/h-card#Backward_Compatibility
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
        // h-review and h-review-agregate also include "self bookmark", but this requires special processing to identify
        // the tag relation also requires special processing to retrieve the correct value
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
        // TODO: Fill this out with SVG and MathML attributes as well
        // See https://github.com/microformats/mf2py/issues/181#issuecomment-1625461973
        'a'          => ["href", "ping"],
        'area'       => ["href", "ping"],
        'audio'      => ["src"],
        'base'       => ["href"], // this requires special processing to not resolve against itself
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
        // Ordinal dates require special processing because PHP's ordinal-date format pattern is utterly bizarre
    ];
    protected const TIME_INPUT_FORMATS = [
        # HH:MM:SS
        'H:i:s'   => self::DATE_TYPE_SEC,
        'G:i:s'   => self::DATE_TYPE_SEC,
        # HH:MM
        'H:i'     => self::DATE_TYPE_MIN,
        'G:i'     => self::DATE_TYPE_MIN,
        # HH:MM:SSam HH:MM:SSpm
        'h:i:sa'  => self::DATE_TYPE_SEC,
        'g:i:sa'  => self::DATE_TYPE_SEC,
        # HH:MMam HH:MMpm
        'h:ia'    => self::DATE_TYPE_MIN,
        'g:ia'    => self::DATE_TYPE_MIN,
        # HHam HHpm
        'ha'      => self::DATE_TYPE_HOUR,
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
        '\z' => self::DATE_TYPE_ZULU, // Lowercase z is used in tests
    ];
    protected const DATE_OUTPUT_FORMATS = [
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC | self::DATE_TYPE_ZONE  => 'Y-m-d H:i:sP',
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC | self::DATE_TYPE_ZULU  => 'Y-m-d H:i:s\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE  => 'Y-m-d H:iP',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN | self::DATE_TYPE_ZULU  => 'Y-m-d H:i\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE => 'Y-m-d H:00P',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR | self::DATE_TYPE_ZULU => 'Y-m-d H:00\Z',
        self::DATE_TYPE_DATE | self::DATE_TYPE_SEC                         => 'Y-m-d H:i:s',
        self::DATE_TYPE_DATE | self::DATE_TYPE_MIN                         => 'Y-m-d H:i',
        self::DATE_TYPE_DATE | self::DATE_TYPE_HOUR                        => 'Y-m-d H:00',
        self::DATE_TYPE_DATE                                               => 'Y-m-d',
        self::DATE_TYPE_SEC | self::DATE_TYPE_ZONE                         => 'H:i:sP',
        self::DATE_TYPE_SEC | self::DATE_TYPE_ZULU                         => 'H:i:s\Z',
        self::DATE_TYPE_MIN | self::DATE_TYPE_ZONE                         => 'H:iP',
        self::DATE_TYPE_MIN | self::DATE_TYPE_ZULU                         => 'H:i\Z',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_ZONE                        => 'H:00P',
        self::DATE_TYPE_HOUR | self::DATE_TYPE_ZULU                        => 'H:00\Z',
        self::DATE_TYPE_SEC                                                => 'H:i:s',
        self::DATE_TYPE_MIN                                                => 'H:i',
        self::DATE_TYPE_HOUR                                               => 'H:00',
        self::DATE_TYPE_ZONE                                               => 'P',
        self::DATE_TYPE_ZULU                                               => '\Z',
    ];

    /** @var array The list of options supplied by the user, after normallization */
    protected $options;
    /** @var string The base URL supplied by the user, resolved against <base href="..."> when appropriate */
    protected $baseUrl;
    /** @var string The base URL supplied by the user, with no further normalization or transformation */
    protected $docUrl;
    /** @var \DOMXPath The XPtah processor used for certain aspects of parsing */
    protected $xpath;
    /** @var array The list of microformat root candidates found by XPath at the start of processing; the array is manipulated during processing to remove child roots so that they are only processed once */
    protected $roots;

    /** Parses an HTML DOMElement for microformats
     * 
     * @see https://microformats.org/wiki/microformats2-parsing
     *
     * @param \DOMElement $node The DOMElement to parse
     * @param string $baseURL The base URL against which to resolve relative URLs in the output
     * @param array $options An associative array of options. Please see the class documentation for more details
     */
    public function parseHtmlElement(\DOMElement $node, string $baseUrl = "", ?array $options = null): array {
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
        // We'll save ourselves a lot of tree walking by using XPath to find first-level root candidates
        $this->getRootCandidates($node);
        for ($a = 0; $a < sizeof($this->roots); $a++) {
            $node = $this->roots[$a];
            # parse element class for root class name(s) "h-*" and if none, backcompat root classes
            # if found, start parsing a new microformat
            $classes = $this->parseTokens($node, "class");
            if ($types = $this->matchRootsMf2($classes)) {
                $out['items'][] = $this->parseMicroformat($node, $types, false);
            } elseif ($types = $this->matchRootsBackcompat($classes)) {
                $out['items'][] = $this->parseMicroformat($node, $types, true);
            }
        }
        # parse all hyperlink (<a> <area> <link>) elements for rel microformats, adding to the JSON rels & rel-urls hashes accordingly
        foreach ($this->xpath->query(".//a[@rel and @href and not(ancestor::template)]|.//area[@rel and @href and not(ancestor::template)]|.//link[@rel and @href and not(ancestor::template)]", $root) as $link) {
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
            // NOTE: The specification is subtly wrong in the steps above.
            // See https://github.com/microformats/microformats2-parsing/issues/85
            if (!$rels) {
                continue;
            }
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
                    $out['rel-urls'][$url][$attr] = $link->getAttribute($attr);
                }
            }
            $text = $this->options['thoroughTrim'] ? $this->getCleanText($link, "p") : $link->textContent;
            if (!isset($out['rel-urls'][$url]['text']) && strlen($text)) {
                $out['rel-urls'][$url]['text'] = $text;
            }
            # if there is no "rels" key in that hash, add it with an empty array value
            if (!isset($out['rel-urls'][$url]['rels'])) {
                $out['rel-urls'][$url]['rels'] = [];
            }
            # set the value of that "rels" key to an array of all unique items
            #   in the set of rel values unioned with the current array value
            #   of the "rels" key, sorted alphabetically.
            // NOTE: sorting and uniqueness filtering will be done later
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
        foreach (["roots", "options", "xpath", "docUrl", "baseUrl"] as $prop) {
            $this->$prop = null;
        }
        # return the resulting JSON
        return $out;
    }

    /** Searches an element tree for elements which appear to have microformat
     * root classes
     *
     * This is an inexact search, which may include false positives, and for
     * backcompat roots may exclude some descendent roots.
     * 
     * The results of this search should only be used as an optimization to
     * pare down the number of elements which must be examined for first-level
     * roots.
     * 
     * @param \DOMElement $node The element to start searching from, including itself
     */
    protected function getRootCandidates(\DOMElement $node): void {
        // NOTE: Templates being completely ignored is an unwritten rule followed by all implementations
        $query = [ 
            "descendant-or-self::*[contains(concat(' ', normalize-space(@class)), ' h-') and not(ancestor-or-self::template)]",
        ];
        foreach (array_keys(static::BACKCOMPAT_ROOTS) as $root) {
            $query[] = "descendant-or-self::*[contains(concat(' ', normalize-space(@class), ' '), ' $root ') and not(ancestor-or-self::template)]";
        }
        $query = implode("|", $query);
        $this->roots = iterator_to_array($this->xpath->query($query, $node));
    }

    /** Splits the attribute of an element into an array of unique whitespace-separated tokens
     * 
     * @param \DOMElement $node The subject element
     * @param string $attr The ttribute to split
     */
    protected function parseTokens(\DOMElement $node, string $attr): array {
        $attr = $this->trim($node->getAttribute($attr));
        if ($attr !== "") {
            return preg_split("/[ \r\n\t\f]+/sS", $attr);
        } else {
            return [];
        }
    }

    /** Filters an array of class names for those which are syntactically
     * microformat v2 roots
     * 
     * The list of possible roots is undefined. This merely validates that a
     * class name fits the syntactic rules.
     * 
     * @param array $classes The array of class names to filter
     */
    protected function matchRootsMf2(array $classes): array {
        return array_filter(array_unique($classes), function($c) {
            # The "*" for root (and property) class names consists of an
            #   optional vendor prefix (series of 1+ number or lowercase
            #   a-z characters i.e. [0-9a-z]+, followed by '-'), then one
            #   or more '-' separated lowercase a-z words.
            // PROPOSAL: https://github.com/microformats/microformats2-parsing/issues/59
            // exclude Tailwind classes https://tailwindcss.com/docs/height
            return preg_match('/^h(?:-[a-z0-9]+)?(?:-[a-z]+)+$/S', $c) && !preg_match('/^h-(?:px|auto|full|screen|min|max|fit)$/S', $c);
        });
    }

    /** Filters an array of class names for those which are microformat v1
     * roots
     * 
     * The list of backcompat roots is finite, and likely fixed by 2023.
     * 
     * The returned array contains the equivalent v2 microformat root names
     * rather than the original names.
     * 
     * @param array $classes The array of class names to filter
     * @param bool $backcompatParent Whether the classes are being checked in the context of a backcompat parent microformat structure
     */
    protected function matchRootsBackcompat(array $classes, bool $backcompatParent = false): array {
        $out = [];
        $item = false;
        foreach ($classes as $c) {
            if ($backcompatParent && $c === "item") {
                // "item" is only a root if it appears as a child of another
                //   backcompat root, but see below
                $item = true;
            } elseif ($compat = self::BACKCOMPAT_ROOTS[$c] ?? null) {
                $out[] = $compat;
            }
        }
        if ($item && !$out) {
            // we only consider "item" a root if there are no other roots
            $out[] = "h-item";
        }
        return $out;
    }

    /** Reads an array of classes and returns information on those which are
     * Microformats v2 properties
     * 
     * As with Microformats v2 roots, the list of possible properties is
     * undefined, and syntax defines validity.
     * 
     * The returned information is an indexed array of indexed arrays each
     * containing the following data:
     * 
     * - The prefix (`p`, `dt`, `u`, or `e`)
     * - The property name with prefix and dash removed (e.g. `author`)
     * 
     * @param array $classes The array of class names to examine
     */
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
                /* Other implementations don't perform de-duplication
                   See https://github.com/microformats/microformats2-parsing/issues/61
                if (!isset($out[$name])) {
                    // property with this name has not been seen yet; add it
                    $out[$name] = [$prefix, $name];
                } elseif (static::PREFIX_RANK[$prefix] > static::PREFIX_RANK[$out[$name][0]]) {
                    // property prefix is of a higher rank than one already seen; use the new prefix
                    $out[$name][0] = $prefix;
                }
                */
                $out[] = [$prefix, $name];
            }
        }
        return array_values($out);
    }

    /** Returns the list of Microformats v1 properties on an element, after
     * mapping to v2 properties
     * 
     * Properties are valid only for certain Microformat dialects (hence the
     * $types parameter), and some properties are derived from link relations
     * rather than class names (hence the $node parameter). Additionally some
     * properties can imply other root classes. These are appended to the
     * &$classes reference when not already in the class list.
     * 
     * The returned information is an indexed array of indexed arrays each
     * containing the following data:
     * 
     * - The prefix (`p`, `dt`, `u`, or `e`) mapped to the property
     * - The mapped property name
     * - Whether the property should be deferred and only added if no
     * other property of the same name appears in the microformat
     * 
     * The returned array will contain the same property name only once. If
     * for example the class list contains both a `vcard fn` and a 
     * `vevent summary`, the output will contain only one of the two, based
     * on prefix ranking.
     * 
     * @param &array $classes A reference to an array of the element's classes
     * @param array $types Anarray of the current microformat's types
     * @param \DOMElement $node The element to examine
     */
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
            $defer = $map[3] ?? false;
            if (
                // property with this name has not been seen yet
                !isset($out[$name])
                // property prefix is of a higher rank than one already seen and isn't deferrable
                || (static::PREFIX_RANK[$prefix] > static::PREFIX_RANK[$out[$name][0]] && !($map[3] ?? false))
            ) {
                $out[$name] = [$prefix, $name, $defer];
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

    /** Parses an element for microformat information
     * 
     * Returns a microformat "item" structure, possibly with child and
     * descendent amicroformat structures.
     * 
     * @see https://microformats.org/wiki/microformats2-parsing#parse_an_element_for_class_microformats
     * 
     * @param \DOMElement $root The root element of the microformat
     * @param array $types The previously determined v2 microformat types of the element
     * @param bool $backcompat Whether this element is a v1 microformat and backward-compatible processing should be used
     */
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
        // if so configured, add language information
        if ($this->options['lang'] && ($lang = $this->getLang($root))) {
            $out['lang'] = $lang;
        }
        // keep track of deferred properties ("use Y if X is not defined")
        $deferred = [];
        // keep track of the implied date and partial datetime values which need to be
        //   filled in with a post-implied date. The members of the partials array will
        //   be a set of date-parts and a reference pointing into the microformat
        //   structure at the element to be replace.
        $impliedDate = null;
        $partials = [];
        // keep track of whether there is a p-, u-, or e- property or child on the microformat; this is required for implied property processing
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
            if ($child) {
                if (!$properties) {
                    if (!isset($out['children'])) {
                        $out['children'] = [];
                    }
                    $out['children'][] = $child;
                }
                // if our root element is in the list of root candiates found by XPath, remove
                //   it from that list while we're here
                foreach ($this->roots as $k => $r) {
                    if ($node->isSameNode($r)) {
                        unset($this->roots[$k]);
                        $this->roots = array_values($this->roots);
                        break;
                    }
                }
            }
            # if such class(es) are found, it is a property element
            # add properties found to current microformat's properties: { } structure
            foreach ($properties as $p) {
                [$prefix, $key, $defer] = array_pad($p, 3, null);
                $hasP = $hasP ?: $prefix === "p";
                $hasE = $hasE ?: $prefix === "e";
                $hasU = $hasU ?: $prefix === "u";
                // parse the node for the property value
                $partialDate = null;
                $value = $this->parseProperty($node, $prefix, $backcompat ? $types : [], $impliedDate, (bool) $child);
                if ($prefix === "dt") {
                    // dates may have a partial value due to post-implied dates in VCP processing
                    [$value, $partialDate] = $value;
                    if (!$partialDate && ($parsedDate = $this->parseDatePart($value))) {
                        // if this is not a partial date, save it as our implied date for future values
                        //   in this microformat structure, assuming it is valid (it may be an unparsed
                        //   value which is not actually suitable once parsed)
                        $impliedDate = $parsedDate;
                        // fill in any partial dates we may have; this involves overwriting the values of references
                        foreach ($partials as [$partialDate, &$ref]) {
                            $ref = $this->stitchDate($partialDate, $impliedDate, true);
                        }
                        $partials = [];
                    }
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
                        $childValue = $value;
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
                    // defer addition of the property if it's supposed to be a fallback for another
                    //   instance of the property (this only occurs in backcompat processing)
                    $deferred[] = [$key, $value];
                    // save a reference for any partial date
                    if ($partialDate) {
                        if (!$child) {
                            $partials[] = [$partialDate, &$deferred[sizeof($deferred) -1][1]];
                        } else {
                            $partials[] = [$partialDate, &$deferred[sizeof($deferred) -1][1]['value']];
                        }
                    }
                } else {
                    if (!isset($out['properties'][$key])) {
                        $out['properties'][$key] = [];
                    }
                    $out['properties'][$key][] = $value;
                    // save a reference for any partial date
                    if ($partialDate) {
                        if (!$child) {
                            $partials[] = [$partialDate, &$out['properties'][$key][sizeof($out['properties'][$key]) -1]];
                        } else {
                            $partials[] = [$partialDate, &$out['properties'][$key][sizeof($out['properties'][$key]) -1]['value']];
                        }
                    }
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
        // NOTE: The ":not[.h-*]" condition referenced frequently in specification text is meaningless
        // See https://github.com/microformats/microformats2-parsing/issues/62
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
                } elseif (($set = $this->xpath->query("./img[@alt and @alt != '' and count(../*) = 1]", $root))->length) {
                    # else if .h-x>img:only-child[alt]:not([alt=""]):not[.h-*] then use that img’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (($set = $this->xpath->query("./area[@alt and @alt != '' and count(../*) = 1]", $root))->length) {
                    # else if .h-x>area:only-child[alt]:not([alt=""]):not[.h-*] then use that area’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (($set = $this->xpath->query("./abbr[@title and @title != '' and count(../*) = 1]", $root))->length) {
                    # else if .h-x>abbr:only-child[title]:not([title=""]):not[.h-*] then use that abbr title for name
                    $name = $set->item(0)->getAttribute("title");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./img[@alt and @alt != '' and count(../*) = 1]", $set->item(0)))->length
                ) {
                    # else if .h-x>:only-child:not[.h-*]>img:only-child[alt]:not([alt=""]):not[.h-*] then use that img’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./area[@alt and @alt != '' and count(../*) = 1]", $set->item(0)))->length
                ) {
                    # else if .h-x>:only-child:not[.h-*]>area:only-child[alt]:not([alt=""]):not[.h-*] then use that area’s alt for name
                    $name = $set->item(0)->getAttribute("alt");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./abbr[@title and @title != '' and count(../*) = 1]", $set->item(0)))->length
                ) {
                    # else if .h-x>:only-child:not[.h-*]>abbr:only-child[title]:not([title=""]):not[.h-*] use that abbr’s title for name
                    $name = $set->item(0)->getAttribute("title");
                } else {
                    # else use the textContent of the .h-x for name after [cleaning]
                    $name = $this->getCleanText($root, "p");
                }
                # remove all leading/trailing spaces
                $out['properties']['name'] = [$this->trim($name)];
            }
            # if no explicit "photo" property, and no other explicit u-* (Proposed: change to: u-* or e-*) properties, and no nested microformats,
            // NOTE: No implementations follow the e- proposal as of 2023-07-10
            if (!isset($out['properties']['photo']) && !$hasChild && !$hasU) {
                $photo = null;
                # then imply by:
                if ($root->localName === "img" && $root->hasAttribute("src")) {
                    # if img.h-x[src], then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($root)];
                } elseif ($root->localName === "object" && $root->hasAttribute("data")) {
                    # else if object.h-x[data] then use data for photo
                    $photo = $root->getAttribute("data");
                } elseif (($set = $this->xpath->query("./img[@src and count(../img) = 1]", $root))->length) {
                    # else if .h-x>img[src]:only-of-type:not[.h-*] then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($set->item(0))];
                } elseif (($set = $this->xpath->query("./object[@data and count(../object) = 1]", $root))->length) {
                    # else if .h-x>object[data]:only-of-type:not[.h-*] then use that object’s data for photo
                    $photo = $set->item(0)->getAttribute("data");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./img[@src and count(../img) = 1]", $set->item(0)))->length
                ) {
                    # else if .h-x>:only-child:not[.h-*]>img[src]:only-of-type:not[.h-*], then use the result of "parse an img element for src and alt" (see Sec.1.5) for photo
                    $out['properties']['photo'] = [$this->parseImg($set->item(0))];
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./object[@data and count(../object) = 1]", $set->item(0)))->length
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
            // NOTE: No implementations follow the e- proposal as of 2023-07-10
            if (!isset($out['properties']['url']) && !$hasChild && !$hasU) {
                $url = null;
                # then imply by:
                if ($root->hasAttribute("href") && in_array($root->localName, ["a", "area"])) {
                    # if a.h-x[href] or area.h-x[href] then use that [href] for url
                    $url = $root->getAttribute("href");
                } elseif (($set = $this->xpath->query("./a[@href and count(../a) = 1]", $root))->length) {
                    # else if .h-x>a[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (($set = $this->xpath->query("./area[@href and count(../area) = 1]", $root))->length) {
                    # else if .h-x>area[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./a[@href and count(../a) = 1]", $set->item(0)))->length
                ) {
                    # else if .h-x>:only-child:not[.h-*]>a[href]:only-of-type:not[.h-*], then use that [href] for url
                    $url = $set->item(0)->getAttribute("href");
                } elseif (
                    ($set = $this->xpath->query("./*[not(self::template) and count(../*) = 1]", $root))->length
                    && ($set = $this->xpath->query("./area[@href and count(../area) = 1]", $set->item(0)))->length
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

    /** Retrieves the value of a single microformat property from an element
     * 
     * The value returned is determined from the prefix and the structure of
     * the element rather than the property name.
     * 
     * @see https://microformats.org/wiki/microformats2-parsing#parse_an_element_for_properties
     * 
     * @param \DOMElement $node The element to retrieve a value from
     * @param string $prefix The property prefix (`p`, `dt`, `u`, or `e`)
     * @param array $backcompatTypes The set of microformat types currently in scope, if performing backcompat processing (and empty array otherwise)
     * @param array|null $impliedDate A previously-seen date value from which we can imply a date if only a time is present on the element
     * @param bool $isChild Whether the subject element is itself a child microformat. This affect whether the "Value Class Pattern" applies
     * @return string|array
     */
    protected function parseProperty(\DOMElement $node, string $prefix, array $backcompatTypes, ?array $impliedDate, bool $isChild) {
        switch ($prefix) {
            case "p":
                # To parse an element for a p-x property value (whether explicit p-* or backcompat equivalent):
                if (!$isChild && ($text = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) !== null) {
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
                    preg_match('#([^/]*)/?$#', Url::fromString($this->normalizeUrl($node->getAttribute("href")))->getPath(), $match);
                    return urldecode($match[1]);
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
                } elseif (!$isChild && ($url = $this->getValueClassPattern($node, $prefix, $backcompatTypes)) !== null) {
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
                if (!$isChild && ($date = $this->getValueClassPattern($node, $prefix, $backcompatTypes, $impliedDate)) !== null) {
                    # parse the element for the Value Class Pattern, including the date and time
                    #   parsing rules. If a value is found, then return it.
                    // To handle post-implied dates we have to jump through some hoops. If the
                    //   returned value is an array, then it is a set of time-parts needing an
                    //   implied date. Instead of returning this partial date we continue with
                    //   processing to get the value which should be inserted till a post-implied
                    //   date is found (if ever). Yes, this is totally crazy.
                    if (is_array($date)) {
                        $partialValue = $date;
                    } else {
                        return [$date, null];
                    }
                }
                if (in_array($node->localName, ["time", "ins", "del"]) && $node->hasAttribute("datetime")) {
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
                if ($this->options['dateNormalization']) {
                    $date = $this->stitchDate($this->parseDatePart($date), $impliedDate, false) ?? $date;
                }
                return [$date, $partialValue ?? null];
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
                $out = [
                    'html'  => $this->trim(Serializer::serializeInner($copy)),
                    'value' => $this->getCleanText($node, $prefix),
                ];
                // if so configured, add language information
                if ($this->options['lang'] && ($lang = $this->getLang($node))) {
                    $out['lang'] = $lang;
                }
                return $out;
            default:
                throw new \Exception("Unimplemented prefix $prefix"); // @codeCoverageIgnore
        }
    }

    /** Retrieves a "Value Class Pattern" value from an element
     * 
     * The Value Class Pattern is a means of marking only certain spans of text
     * as relevant information, allowing for naturally-flowing text and
     * machine-readable information to co-exist.
     * 
     * @see https://microformats.org/wiki/value-class-pattern#Basic_Parsing
     * 
     * @param \DOMElement $root The subject element
     * @param string $prefix The property prefix (`p`, `dt`, `u`, or `e`)
     * @param array $backcompatTypes The set of microformat types currently in scope, if performing backcompat processing (an empty array otherwise)
     * @param array|null $impliedDate A previously-seen date value from which we can imply a date if only a time is present on the element
     * @return string|array|null
     */
    protected function getValueClassPattern(\DOMElement $root, string $prefix, array $backcompatTypes, ?array $impliedDate = null) {
        $out = [];
        $skipChildren = false;
        while ($node = $this->nextElement($node ?? $root, $root, !$skipChildren)) {
            $skipChildren = false;
            $classes = $this->parseTokens($node, "class");
            $candidate = null;
            if (
                ($backcompatTypes && ($this->matchPropertiesBackcompat($classes, $backcompatTypes, $node) || $this->matchRootsBackcompat($classes)))
                || ($this->matchRootsMf2($classes) || $this->matchPropertiesMf2($classes))
            ) {
                // only consider elements which are not inside properties or roots
                // NOTE: The specification doesn't mention roots, but these should surely be skipped as well
                $skipChildren = true;
            }
            if (in_array("value-title", $classes)) {
                # Where a microformats property has a child element with class
                #   name of value-title, the content of the title attribute of
                #   that element must be parsed, rather than the portion of the
                #   element that would be parsed for a class name of value.
                // NOTE: This applies even if there is no title attribute
                $candidate = $node->getAttribute("title");
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
                    #   otherwise its inner-text. [dt- property only]
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
                    $out += $candidate; // this is an array union; keys which don't exist in $out are added from $candidate
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
                # if the microformats property expects a datetime value, see
                #   the Date Time Parsing section.
                // The rules for datetimes are dispersed elsewhere. All that's
                //   required here is to stitch parts together
                // NOTE: We use RFC 3339 time zone format when generalized
                //   normalization is requested instead of the VCP format
                //   without colon
                return $this->stitchDate($out, $impliedDate, !$this->options['dateNormalization']);
            }
        } else {
            return null;
        }
    }

    /** Retrieves structured data from an HTML `img` element
     * 
     * If the element has an `alt` attribute an array containing both `alt` and
     * `src` keys is returned. Otherwise a string with the value of `src` is
     * returned instead.
     * 
     * @see https://microformats.org/wiki/microformats2-parsing#parse_an_img_element_for_src_and_alt
     * 
     * @param \DOMElement $node The `img` element to examine
     * @return array|string
     */
    protected function parseImg(\DOMElement $node) {
        # To parse an img element for src and alt attributes:
        if ($node->localName === "img" && $node->hasAttribute("alt")) {
            # if img[alt]
            # return a new {} structure with
            return [
                # value: the element's src attribute as a normalized absolute URL
                'value' => $this->normalizeUrl($node->getAttribute("src")),
                # alt: the element's alt attribute
                'alt'   => $node->getAttribute("alt"),
            ];
        } else {
            # else return the element's src attribute as a normalized absolute URL
            return $this->normalizeUrl($node->getAttribute("src"));
        }
    }

    /** Validates whether a string is a date, and returns its parts
     * 
     * The return value is an array which can contain zero or more of the
     * follwing keys:
     * 
     * - `date`
     * - `time`
     * - `zone`
     * 
     * Absence of all keys indicates an invalid string. The three parts may
     * appear in any combination except `date` and `zone` without `time`.
     * 
     * @see https://microformats.org/wiki/value-class-pattern#Date_and_time_parsing
     * 
     * @param string $input The string to test for validity
     */
    protected function parseDatePart(string $input): array {
        // do a first-pass normalization on the input; there are transformations
        //   which are compatible with the allowed output formats, so we don't
        //   need to keep the original value
        $input = preg_replace([
            '/[ \r\n\t\f]+/s',                 // collapses whitespace
            '/([ap])\.m\./',                   // together with strtr() below, converts various permutations to just "am" or "pm"
            '/(^|:\d\d)(\+|-)(\d(:?\d\d)?)$/', // adds leading zeroes to time zone offsets lacking them
            '/(^|:\d\d)[+-]\d\d$/',            // adds minutes to time zones lacking them
            '/(^|:\d\d)-(00:?00)$/'            // converts negative-zero time zone offset to positive (PHP does not support negative-zero)
        ], [' ', '$1m', '$1${2}0$3', '${0}00', '$1+$2'], strtr($this->trim($input), "APM", "apm"));
        // check for an ordinal date and convert it to a calendar date; PHP does not have a usable ordinal date format
        if (preg_match('/^(\d{4}-\d{3}(?!\d))/', $input)) {
            $input = $this->normalizeOrdinalDate($input);
        }
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
            }
        }
        foreach (self::ZONE_INPUT_FORMATS as $zf => $zp) {
            if ($out = $this->testDate($input, "$zf")) {
                return [
                    'zone' => $out->format(self::DATE_OUTPUT_FORMATS[$zp]),
                ];
            }
        }
        return [];
    }

    /** Tests a string for validity against one or more date-format strings
     * 
     * The returned value will be the first valid DateTimeImmutable value, if any.
     * 
     * @param string $input The string to validate
     * @param string $format One or more date formats to validate against
     */
    protected function testDate(string $input, string ...$format): ?\DateTimeImmutable {
        foreach ($format as $f) {
            $out = \DateTimeImmutable::createFromFormat("!$f", $input, new \DateTimeZone("UTC"));
            if ($out && $out->format($f) === $input) {
                return $out;
            }
        }
        return null;
    }

    /** Concatenates date parts together, optionally with an implied date
     * 
     * @param array $parts The date parts, `date`, `time`, and `zone`
     * @param array|null $implied An optional implied date to use if the date is absent from the input
     * @param bool $vcp Whether the date is stitched as part of the Value Class Pattern algorithm. VCP requires a non-RFC 3339 time zone specifier in its normalized form. VCP also allows provisional dateless times, and these are handled specially
     * @return string|array|null
     */
    protected function stitchDate(array $parts, ?array $implied, bool $vcp) {
        if ($vcp && isset($parts['zone'])) {
            $parts['zone'] = str_replace(":", "", $parts['zone']);
        }
        if (sizeof($parts) === 3) {
            return $parts['date']." ".$parts['time'].$parts['zone'];
        } elseif (isset($parts['date']) && !isset($parts['time'])) {
            return $parts['date'];
        } else {
            $implied = $implied ?? [];
            // PROPOSAL: https://github.com/microformats/microformats2-parsing/issues/4
            // only imply time zone if so configured
            if (!$this->options['impliedTz']) {
                $implied['zone'] = null;
            } elseif ($vcp && isset($implied['zone'])) {
                $implied['zone'] = str_replace(":", "", $implied['zone']);
            }
            if (isset($parts['date']) && isset($parts['time'])) {
                return $parts['date']." ".$parts['time'].($implied['zone'] ?? "");
            } elseif (isset($parts['time']) && isset($implied['date'])) {
                return $implied['date']." ".$parts['time'].($parts['zone'] ?? $implied['zone'] ?? "");
            } elseif (isset($parts['time']) && $vcp) {
                return $parts;
            }
        }
        return null;
    }

    /** Converts an ordinal date (possibly embedded in a longer string) into a calendar date
     * 
     * This is necessary because PHP does not have support for ordinal dates in
     * any way which suits our purpose. If the date is out of range (less than
     * one or greater than the number of days in the year) the input string
     * will be returned unmodified and the string will ultimately be rejected
     * as invalid.
     * 
     * @param string $input A candidate date string which begins with an ordinal date
     */
    protected function normalizeOrdinalDate(string $input): string {
        assert(preg_match('/^(\d{4}-\d{3}(?!\d))/', $input), new \Exception("Input is not an ordinal date"));
        $year = substr($input, 0, 4);
        $day = (int) substr($input, 5, 3) -1; // we subtract one because PHP's "z" date format for ordinal days counts from zero
        if ($day < 0 || $day > 365) {
            // day is out of range even for a leap year
            return $input;
        }
        $test = \DateTimeImmutable::createFromFormat("!Y-z", "$year-$day", new \DateTimeZone("UTC"));
        // ensure the year hasn't rolled over in a common year
        if ($test->format("Y") === $year) {
            return substr_replace($input, $test->format("Y-m-d"), 0, 8);
        }
        return $input;
    }

    /** Resolves a URL against a base URL and normalizes the result
     * 
     * @param string $url The URL to resolve and normalize
     * @param string|null $baseUrl The base URL to resolve against. If this argument is absent the document base will be used
     */
    protected function normalizeUrl(string $url, ?string $baseUrl = null): string {
        // TODO: Implement better URL parser
        try {
            return (string) Url::fromString($url, $baseUrl ?? $this->baseUrl);
        } catch (\Exception $e) { // @codeCoverageIgnore
            return $url; // @codeCoverageIgnore
        }
    }

    /** Retrieves the trimmed plain-text content of an HTML element
     * 
     * Depending on user options the traditional "simple" algorithm may be used
     * in place of the more recent "thorough" algorithm.
     * 
     * @see https://microformats.org/wiki/textcontent-parsing
     * 
     * @param \DOMElement $node The element whose text is to be retrieved
     * @param string $prefix The prefix of the microformat property the text is to be used for. This is only relevant for the "simple" algorithm
     */
    protected function getCleanText(\DOMElement $node, string $prefix): string {
        if (!$this->options['thoroughTrim']) {
            return $this->getCleanTextSimple($node, $prefix);
        } else {
            // https://microformats.org/wiki/textcontent-parsing
            # Plain text of element
            # To get the plain text for an Element input:
            # Let output be the result of running [Element to string] on input
            $output = $this->getCleanTextThorough($node, $prefix);
            # Remove any sequence of one or more consecutive U+0020 SPACE code points directly before and after an U+000A LF code point from output
            $output = preg_replace('/^[ \r\t\f]+|[ \r\t\f]+$/m', "", $output);
            # Strip leading and trailing ASCII whitespace from output
            $output = $this->trim($output);
            # Replace any sequence of one or more consecutive U+0020 SPACE code points in output with a single U+0020 SPACE code point
            $output = preg_replace('/[ \r\n\t\f]{2,}/m', " ", $output);
            # Return output
            return $output;
        }
    }

    /** Part of the algorithm to retrieve the trimmed plain-text content of an HTML element
     *
     * @see https://microformats.org/wiki/textcontent-parsing#Element_to_string
     * 
     * @param \DOMElement $node The element whose text is to be retrieved
     */
    protected function getCleanTextThorough(\DOMElement $node): string {
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
                            $value = $this->trim($n->getAttribute("alt"));
                        } elseif ($n->hasAttribute("src")) {
                            # Else if child has a src attribute, then:
                            # Let value be the contents of the src attribute
                            # Strip leading and trailing ASCII whitespace from value
                            $value = $this->trim($n->getAttribute("src"));
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
                        $output[] = "\n".$this->getCleanTextThorough($n);
                        break;
                    default:
                        # Any other value
                        # Let value be the result of running this algorithm on child
                        # Append value to output
                        $output[] = $this->getCleanTextThorough($n);
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

    /** Part of the algorithm to retrieve the trimmed plain-text content of an HTML element
     *
     * This is the traditional "simple" algorithm.
     * 
     * @param \DOMElement $node The element whose text is to be retrieved
     * @param string $prefix The prefix of the microformat property the text is to be used for
     */
    protected function getCleanTextSimple(\DOMElement $node, string $prefix): string {
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
        #   relative; [p- and e- only]
        if (in_array($prefix, ["p", "e"])) {
            foreach ($copy->getElementsByTagName("img") as $e) {
                $attr = null;
                if ($e->hasAttribute("alt")) {
                    $attr = $alt = $e->getAttribute("alt");
                } elseif ($e->hasAttribute("src")) {
                    $attr = " ".($e->hasAttribute("src") ? $this->normalizeUrl($e->getAttribute("src")) : "")." ";
                }
                if ($attr !== null) {
                    $e->parentNode->replaceChild($e->ownerDocument->createTextNode($attr), $e);
                }
            }
        }
        # removing all leading/trailing spaces
        return $this->trim($copy->textContent);
    }

    /** Retrieves and resolves the base URL of an HTML document's `<base>`
     * element, if any
     * 
     * @param \DOMElement $root Any element within the document to check
     * @param string $base The HTTP-level base URL, if available
     */
    protected function getBaseUrl(\DOMElement $root, string $base): string {
        $set = $root->ownerDocument->getElementsByTagName("base");
        if ($set->length) {
            return $this->normalizeUrl($set[0]->getAttribute("href"), $base);
        }
        return $base;
    }

    /** Finds the nearest HTML language information for an element
     * 
     * No validation or normalization is performed on the returned information.
     * 
     * @param \DOMElement $node The subject element
     */
    protected function getLang(\DOMElement $node): ?string {
        while ($node && !($node instanceof \DOMElement && $node->hasAttribute("lang"))) {
            $node = $node->parentNode;
        }
        if ($node && strlen($lang = $this->trim($node->getAttribute("lang")))) {
            return $lang;
        }
        return null;
    }

    /** Finds the next element in tree order after $node, if any
     *
     * @param \DOMNode $node The context node
     * @param \DOMElement $root The element to consider the contextual root of the tree; nodes outside this element will not be examined
     * @param bool $considerChildren Whether or not child nodes are valid next nodes
     */
    protected function nextElement(\DOMElement $node, \DOMElement $root, bool $considerChildren): ?\DOMElement {
        if ($considerChildren && $node->hasChildNodes() && $node->localName !== "template") {
            $node = $node->firstChild;
            $next = $node;
        } elseif ($node->isSameNode($root)) {
            return null;
         } else {
            $next = $node->nextSibling;
        }
        while ($next && (!$next instanceof \DOMElement ||  $next->localName === "template")) {
            // NOTE: Templates being completely ignored is an unwritten rule followed by all implementations
            $next = $next->nextSibling;
        }
        while (!$next) {
            $node = $node->parentNode;
            if (!$node || $node->isSameNode($root)) {
                return null;
            }
            $next = $node->nextSibling;
            while ($next && (!$next instanceof \DOMElement ||  $next->localName === "template")) {
                // NOTE: Templates being completely ignored is an unwritten rule followed by all implementations
                $next = $next->nextSibling;
            }
        }
        return $next;
    }

    protected function trim(string $str): string {
        return trim($str, " \r\n\t\f");
    }

    /** Normalizes an array of options
     * 
     * Default values are filled in and unknown options removed
     * 
     * @param array $options The options array to normalize
     */
    protected function normalizeOptions(array $options): array {
        return [
            'dateNormalization' => (bool) ($options['dateNormalization'] ?? true),
            'impliedTz'         => (bool) ($options['impliedTz'] ?? false),
            'lang'              => (bool) ($options['lang'] ?? true),
            'thoroughTrim'      => (bool) ($options['thoroughTrim'] ?? true),
        ];
    }
}
