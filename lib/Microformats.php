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
class Microformats implements \ArrayAccess, \JsonSerializable {
    protected $data;

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
    public static function fromFile(string $file, string $contentType, string $url, array $options = []): ?self {
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
    public static function fromString(string $input, string $contentType, string $url, array $options = []): self {
        $parsed = HTMLParser::parse($input, $contentType);
        return static::fromHTMLElement($parsed->document->documentElement, $url, $options);
    }

    /** Parses an HTML element for microformats
     * 
     * @param \DOMElement $input The element to examine. Siblings and ancestors of this element will be ignored
     * @param string $url The effective URL (after redirections) of the document if known
     * @param array $options Options for the parser; please see the class documentetation for details
     */
    public static function fromHTMLElement(\DOMElement $input, string $url, array $options = []): self {
        return new static((new MfParser)->parseHTMLElement($input, $url, $options));
    }

    /** Imports a plain array into this wrapper class
     * 
     * This is mainly useful for proper JSON serialization.
     * 
     * @param array $data The complete Microformats associative array
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    public function offsetExists(mixed $offset): bool {
        return isset($this->data[$offset]);
    }

    public function &offsetGet(mixed $offset): mixed {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->data[$offset]);
    }

    public function jsonSerialize(): mixed {
        // In order for a Microformats structure to serialize to JSON correctly
        //   we must ensure empty hash tables serialize to objects rather than
        //   arrays as they otherwise would. This cannot cover all possible
        //   cases of manipulation, but does cover cases which normally occur
        //   with data in the wild.
        $data = $this->data;
        $walk = function(&$arr) {
            foreach ($arr as $k => &$v) {
                if (is_array($v)) {
                    if ($k === "properties" && !$v) {
                        $v = new \stdClass;
                    } else {
                        __FUNCTION__($v);
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
        return $data;
    }
}