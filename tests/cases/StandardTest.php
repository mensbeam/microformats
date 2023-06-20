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
    protected const SUPPRESSED = [
        'microformats-v1/hcard/multiple'         => "whether vcard keys are p- or u- is unclear",
        'microformats-v1/includes/hcarditemref'  => "include pattern not implemented",
        'microformats-v1/includes/heventitemref' => "include pattern not implemented",
        'microformats-v1/includes/hyperlink'     => "include pattern not implemented",
        'microformats-v1/includes/object'        => "include pattern not implemented",
        'microformats-v1/includes/table'         => "include pattern not implemented",
        'microformats-v2/rel/duplicate-rels'     => "this test has a spurious newline at the beginning of a value",
    ];

    /** @dataProvider provideStandardTests */
    public function testStandardTests(string $name, string $path): void {
        if (isset(self::SUPPRESSED[$name])) {
            $this->markTestIncomplete(self::SUPPRESSED[$name]);
        }
        // read data
        $exp = json_decode(file_get_contents($path.".json"), true);
        $html = file_get_contents($path.".html");
        // fix up expectation where necessary
        array_walk_recursive($exp, function(&$v) {
            // URLs differ trivially from output of our normalization library
            if (preg_match('#^https?://[^/]+$#', $v)) {
                $v .= "/";
            }
        });
        // perform some further monkey-patching on specific tests
        $exp = $this->fixTests($exp, $name);
        // parse input
        $dom = new DOMParser;
        $parser = new Parser;
        $doc = $dom->parseFromString($html, "text/html; charset=UTF-8");
        $act = $parser->parseElement($doc->documentElement, "http://example.com");
        // sort both arrays
        $this->ksort($exp);
        $this->ksort($act);
        // run comparison
        if (!$exp) {
            echo json_encode($act, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->assertSame($exp, $act);
    }

    public function provideStandardTests(): \Generator {
        return $this->provideTestList(\MensBeam\Microformats\BASE."vendor-bin/phpunit/vendor/mf2/tests/tests/");
    }

    protected function provideTestList(): \Generator {
        $tests = [
            \MensBeam\Microformats\BASE."vendor-bin/phpunit/vendor/mf2/tests/tests/", // standard tests
            //\MensBeam\Microformats\BASE."tests/cases/json/", // additional tests
        ];
        foreach ($tests as $base) {
            $base = strtr($base, "\\", "/");
            foreach (new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base )), '/\.json$/') as $file) {
                $path = $file->getPathname();
                $path =  preg_replace('/\.json$/', '', $path);
                $name = strtr($path, "\\", "/");
                $name = str_replace(strtr($base, "\\", "/"), "", $name);
                yield $name => [$name, $path];
            }
        }
    }

    protected function ksort(&$arr) {
        foreach ($arr as &$v) {
            if (is_array($v))
                $this->ksort($v);
         }
         ksort($arr);
    }

    protected function fixTests(array $exp, string $test) {
        switch ($test) {
            case "microformats-v1/hentry/summarycontent":
            case "microformats-v2/h-entry/summarycontent":
                $this->fixDates($exp['items'][0]['properties']['updated']);
                break;
            case "microformats-v2/h-feed/implied-title":
            case "microformats-v2/h-feed/simple":
                $this->fixDates($exp['items'][0]['children'][0]['properties']['updated']);
                break;
            case "microformats-v2/h-event/dates":
                $this->fixDates($exp['items'][0]['properties']['start']);
                break;
            case "microformats-v1/hnews/minimum":
            case "microformats-v1/hnews/all":
                $this->fixDates($exp['items'][0]['properties']['entry'][0]['properties']['updated']);
                break;
            case "microformats-v1/hfeed/simple":
                $this->fixDates($exp['items'][0]['children'][0]['properties']['updated']);
                break;
            case "microformats-v1/hcard/single":
                $this->fixDates($exp['items'][0]['properties']['bday']);
                $this->fixDates($exp['items'][0]['properties']['rev']);
                break;
            case "phpmf2/hentry/fberriman":
                $this->fixDates($exp['items'][0]['properties']['published']);
        }
        return $exp;
    }

    protected function fixDates(&$dateArray): void {
        foreach ($dateArray as &$d) {
            $d = strtr($d, "Tt", "  ");
            $d = preg_replace('/([+-]\d\d):(\d\d)$/', "$1$2", $d);
            $d = preg_replace('/:\d\d[+-]\d\d$/', "$0000", $d);
        }
    }

}