<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam;

use MensBeam\HTML\Parser as HTMLParser;
use MensBeam\Microformats\Parser as MfParser;

/** A generic parser for microformats
 *
 * It implements Microformats v2 as well as backwards-compatible processing of
 * so-called "classic" or "backcompat" Microformats. Some of its functionality
 * is optional. Where an $options array is a possible parameter, the following
 * keys are understood:
 * 
 * - `impliedTz` (bool) Whether to allow an implied datetime value to supply an
 * implied timezone to datetimes without a timezone
 * - `lang` (bool) Whether to include language information in microformat and
 * rich-text structures
 * - `simpleTrim` (bool) Whether to use the traditional "simple" whitespace
 * trimming algorithm rather than the default, more aggressive trimming algorithm
 * 
 * Currently all input is assumed to be HTML, but processing of generic XML
 * data may be supported in future.
 */
class Microformats {
    /** Parses a file for microformats
     * 
     * If reading the file fails `null` is returned.
     * 
     * While fopen wrappers can be used to open remote resources over HTTP, no
     * effort is made to support this specially by reading the `Content-Type`
     * header or deducing the URL. Using a proper HTTP client such as Guzzle
     * is highly recommended instead.
     * 
     * @param string $file The file to read and parse
     * @param string $contentType The HTTP Content-Type of the file if known, optionally with parameters
     * @param string $url The effective URL (after redirections) of the file if known
     * @param array $options Options for the parser; please see the class documentetation for details
     */
    public static function fromFile(string $file, string $contentType, string $url, array $options = []): ?array {
        $string = file_get_contents($file);
        if ($string === false) {
            return null;
        }
        return static::fromString($string, $contentType, $url, $options);
    }

    /** Parses a string for microformats
     * 
     * @param string $input The string to parse
     * @param string $contentType The HTTP Content-Type of the string if known, optionally with parameters
     * @param string $url The effective URL (after redirections) of the string if known
     * @param array $options Options for the parser; please see the class documentetation for details
     */
    public static function fromString(string $input, string $contentType, string $url, array $options = []): array {
        $parsed = HTMLParser::parse($input, $contentType);
        return static::fromHTMLElement($parsed->document->documentElement, $url, $options);
    }

    /** Parses an HTML element for microformats
     * 
     * @param \DOMElement $input The element to examine. Siblings and ancestors of this element will be ignored
     * @param string $url The effective URL (after redirections) of the document if known
     * @param array $options Options for the parser; please see the class documentetation for details
     */
    public static function fromHTMLElement(\DOMElement $input, string $url, array $options = []): array {
        return (new MfParser)->parseHTMLElement($input, $url, $options);
    }

    /** Serializes a Microformats structure to JSON.
     * 
     * This is necessary to serialize empty hash tables (JSON objects)
     * correctly. It cannot cover all possible cases of manipulation, but
     * does cover cases which normally occur with data in the wild.
     * 
     * @param array $data The Microformats structure to serialize
     * @param int $flags [optional] Bitmask consisting of JSON_HEX_QUOT, JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, JSON_NUMERIC_CHECK, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT, JSON_UNESCAPED_UNICODE. JSON_THROW_ON_ERROR The behaviour of these constants is described on the JSON constants page
     * @param int $depth [optional] Set the maximum depth. Must be greater than zero.
     */
    public static function toJson(array $data, int $flags = 0, int $depth = 512): string {
        $walk = function(&$arr) use(&$walk) {
            foreach ($arr as $k => &$v) {
                if (is_array($v)) {
                    if ($k === "properties" && !$v) {
                        $v = new \stdClass;
                    } else {
                        $walk($v);
                    }
                }
            }
        };
        if (!$data['rels']) {
            $data['rels'] = new \stdClass;
        }
        if (!$data['rel-urls']) {
            $data['rel-urls'] = new \stdClass;
        }
        $walk($data['items']);
        return json_encode($data, $flags, $depth);
    }
}