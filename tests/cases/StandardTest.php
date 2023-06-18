<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Microformats\TestCase;

use MensBeam\Microformats\Parser;
use MensBeam\HTML\DOMParser;

/** @covers MensBeam\Microformats\Parser */
class StandardTest extends \PHPUnit\Framework\TestCase {
    protected const BASE = \MensBeam\Microformats\BASE."vendor-bin/phpunit/vendor/mf2/tests/tests/";
    protected const SUPPRESSED = [
        "microformats-mixed/h-resume/mixedroots",
        "microformats-v1/hcalendar/ampm",
        "microformats-v1/hcalendar/attendees",
        "microformats-v1/hcalendar/concatenate",
        "microformats-v1/hcalendar/time",
        "microformats-v1/hcard/multiple",
        "microformats-v1/hcard/name",
        "microformats-v1/hcard/single",
        "microformats-v1/hfeed/simple",
        "microformats-v1/hnews/all",
        "microformats-v1/hnews/minimum",
        "microformats-v1/hproduct/aggregate",
        "microformats-v1/hresume/affiliation",
        "microformats-v1/hresume/education",
        "microformats-v1/hresume/work",
        "microformats-v1/hreview-aggregate/hcard",
        "microformats-v1/hreview-aggregate/justahyperlink",
        "microformats-v1/hreview-aggregate/vevent",
        "microformats-v1/hreview/item",
        "microformats-v1/hreview/vcard",
        "microformats-v1/includes/hcarditemref",
        "microformats-v1/includes/heventitemref",
        "microformats-v1/includes/hyperlink",
        "microformats-v1/includes/object",
        "microformats-v1/includes/table",
        "microformats-v2/h-event/ampm",
        "microformats-v2/h-event/concatenate",
        "microformats-v2/h-event/dt-property",
        "microformats-v2/h-event/time",
        "microformats-v2/h-review-aggregate/hevent",
        "microformats-v2/h-review-aggregate/simpleproperties",
    ];

    /** @dataProvider provideStandardTests */
    public function testStandardTests(string $path): void {
        if (in_array($path, self::SUPPRESSED)) {
            $this->markTestIncomplete();
        }
        // read data
        $exp = json_decode(file_get_contents(self::BASE.$path.".json"), true);
        $html = file_get_contents(self::BASE.$path.".html");
        // fix up expectation where necessary
        array_walk_recursive($exp, function(&$v) {
            // URLs differ trivially from output of our normalization library
            if (preg_match('#^https?://[^/]+$#', $v)) {
                $v .= "/";
            }
            // at least one test has spurious whitespace
            $v = trim($v);
        });
        // parse input
        $dom = new DOMParser;
        $parser = new Parser;
        $doc = $dom->parseFromString($html, "text/html; charset=UTF-8");
        $act = $parser->parseElement($doc->documentElement, "http://example.com");
        // sort both arrays
        $this->ksort($exp);
        $this->ksort($act);
        // run comparison
        $this->assertSame($exp, $act);
    }

    public function provideStandardTests(): \Generator {
        foreach (new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::BASE)), '/\.json$/') as $path) {
            $path = str_replace(self::BASE, "", $path->getPathname());
            $path =  preg_replace('/\.json$/', '', $path);
            yield $path => [$path];
        }
    }

    protected function ksort(&$arr) {
        foreach ($arr as &$v) {
            if (is_array($v))
                $this->ksort($v);
         }
         ksort($arr);
    }

}