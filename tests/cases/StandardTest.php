<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Microformats\TestCase;

use MensBeam\Microformats;

/**
 * @covers MensBeam\Microformats
 * @covers MensBeam\Microformats\Parser
 */
class StandardTest extends \PHPUnit\Framework\TestCase {
    protected const SUPPRESSED = [
        'microformats-v1/hcard/multiple'                          => "whether vcard keys are p- or u- is unclear",
        'microformats-v1/includes/hcarditemref'                   => "include pattern not implemented",
        'microformats-v1/includes/heventitemref'                  => "include pattern not implemented",
        'microformats-v1/includes/hyperlink'                      => "include pattern not implemented",
        'microformats-v1/includes/object'                         => "include pattern not implemented",
        'microformats-v1/includes/table'                          => "include pattern not implemented",
        'microformats-v2/rel/duplicate-rels'                      => "this test has a spurious newline at the beginning of a value",
        'microformats-v2-unit/names/names-microformats'           => "This is probably a bug in the HTML parser",
        'microformats-v2-unit/nested/nested-microformat-mistyped' => "The spec may change here soon",
    ];

    /** @dataProvider provideStandardTests */
    public function testStandardTests(string $name, string $path, array $options): void {
        if (isset(self::SUPPRESSED[$name])) {
            $this->markTestIncomplete(self::SUPPRESSED[$name]);
        }
        // parse input
        $base = strpos($name, "microformats-v2-unit/") === 0 ? "http://example.test/" : "http://example.com/";
        $act = Microformats::fromFile($path.".html", "text/html; charset=UTF-8", $base, $options);
        // read expectation data
        $exp = json_decode(file_get_contents($path.".json"), true);
        if ($exp) {
            // fix up expectation where necessary
            array_walk_recursive($exp, function(&$v) {
                // URLs differ trivially from output of our normalization library
                $v = preg_replace('#^https?://[^/]+$#', "$0/", $v);
            });
            // URLs also need fixing as keys in rel-urls
            foreach ($exp['rel-urls'] as $k => $v) {
                $fixed = preg_replace('#^https?://[^/]+$#', "$0/", $k);
                $exp['rel-urls'][$fixed] = $v;
                if ($fixed !== $k) {
                    unset($exp['rel-urls'][$k]);
                }
            }
            // perform some further monkey-patching on specific tests
            $exp = $this->fixTests($exp, $name);
        } else {
            // if there are no expectations we're probably developing a new test; print the output as JSON
            echo Microformats::toJson($act, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            exit;
        }
        // sort both arrays
        $this->ksort($exp);
        $this->ksort($act);
        // run comparison
        foreach ($exp['items'] as $k => $mf) {
            $x = json_encode($mf, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            $a = json_encode($act['items'][$k] ?? new \stdClass, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
            $types = implode(", ", $mf['type']);
            $this->assertSame($x, $a, "Microformat $types does not match");
        }
        $this->assertEquals($exp, $act);
    }

    public function provideStandardTests(): \Generator {
        // the standard tests
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."vendor-bin/phpunit/vendor/mf2/tests/tests/", ['thoroughTrim' => false, 'dateNormalization' => false]);
        // tests from php-mf2
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."tests/cases/third-party/", ['dateNormalization' => false, 'lang' => false]);
        // tests from our own corpus
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."tests/cases/mensbeam/default-settings/", []);
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."tests/cases/mensbeam/lang-true/", ['lang' => true]);
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."tests/cases/mensbeam/thoroughtrim-false/", ['thoroughTrim' => false]);
        // new unit tests, still being written
        yield from $this->provideTestList(\MensBeam\Microformats\BASE."tests/cases/microformats-v2-unit/");
    }

    protected function provideTestList(string $set, array $options = []): \Generator {
        if (!is_dir($set)) {
            return;
        }
        $base = strtr(\MensBeam\Microformats\BASE."tests/cases/", "\\", "/");
        if (strpos(strtr($set, "\\", "/"), $base,) !== 0) {
            $base = strtr($set, "\\", "/");
        }
        foreach (new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($set)), '/\.json$/') as $file) {
            $path = $file->getPathname();
            $path =  preg_replace('/\.json$/', '', $path);
            $name = strtr($path, "\\", "/");
            $name = str_replace($base, "", $name);
            // perform some special handling for the standard unit test suite
            if (!$options && preg_match('/^microformats-v2-unit\/(?!text\/)/', $name)) {
                // run the test with both text trimming algorithms so that we ensure the tests pass with both
                $opt = [
                    'thoroughTrim' => true,
                    'dateNormalization' => true,
                ];
                yield "$name options:default" => [$name, $path, $opt];
                $opt = [
                    'thoroughTrim' => false,
                    'dateNormalization' => false,
                ];
                yield "$name options:standard" => [$name, $path, $opt];
            } else {
                yield $name => [$name, $path, $options];
            }
        }
    }

    protected function ksort(&$arr) {
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $this->ksort($v);
            }
         }
         ksort($arr);
    }

    protected function fixTests(array $exp, string $test) {
        switch ($test) {
            case "microformats-v2/h-event/time":
            case "microformats-v1/hcalendar/time":
                $this->fixDates($exp['items'][0]['properties']['start']);
                break;
            case "microformats-v2/h-event/concatenate":
            case "third-party/phpmf2/classic/vevent-summary":
                $this->fixDates($exp['items'][0]['properties']['start']);
                $this->fixDates($exp['items'][0]['properties']['end']);
                break;
            case "third-party/phpmf2/vcp":
                $this->fixDates($exp['items'][5]['properties']['published']);
                $exp['items'][7]['properties']['published'][0] = "2013-02-01 06:01";
                break;
            case "third-party/phpmf2/classic/fberriman":
                $exp['items'][0]['properties']['published'][0] = "2013-05-14T11:54:06+00:00";
                break;
        }
        return $exp;
    }

    protected function fixDates(&$dateArray): void {
        foreach ($dateArray as &$d) {
            $d = strtr($d, "Tt", "  ");
            $d = preg_replace('/([+-]\d\d)(\d\d)$/', "$1:$2", $d);
            $d = preg_replace('/:\d\d[+-]\d\d$/', "$0:00", $d);
        }
    }
}