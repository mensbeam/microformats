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
        "microformats-mixed/h-card/mixedproperties",
        "microformats-mixed/h-card/tworoots",
        "microformats-mixed/h-entry/mixedroots",
        "microformats-mixed/h-resume/mixedroots",
        "microformats-v1/adr/simpleproperties",
        "microformats-v1/geo/abbrpattern",
        "microformats-v1/geo/hidden",
        "microformats-v1/geo/simpleproperties",
        "microformats-v1/geo/valuetitleclass",
        "microformats-v1/hcalendar/ampm",
        "microformats-v1/hcalendar/attendees",
        "microformats-v1/hcalendar/combining",
        "microformats-v1/hcalendar/concatenate",
        "microformats-v1/hcalendar/time",
        "microformats-v1/hcard/email",
        "microformats-v1/hcard/format",
        "microformats-v1/hcard/hyperlinkedphoto",
        "microformats-v1/hcard/justahyperlink",
        "microformats-v1/hcard/justaname",
        "microformats-v1/hcard/multiple",
        "microformats-v1/hcard/name",
        "microformats-v1/hcard/single",
        "microformats-v1/hentry/summarycontent",
        "microformats-v1/hfeed/simple",
        "microformats-v1/hnews/all",
        "microformats-v1/hnews/minimum",
        "microformats-v1/hproduct/aggregate",
        "microformats-v1/hproduct/simpleproperties",
        "microformats-v1/hresume/affiliation",
        "microformats-v1/hresume/contact",
        "microformats-v1/hresume/education",
        "microformats-v1/hresume/skill",
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
        "microformats-v2/h-adr/geourl",
        "microformats-v2/h-adr/justaname",
        "microformats-v2/h-adr/lettercase",
        "microformats-v2/h-adr/simpleproperties",
        "microformats-v2/h-card/baseurl",
        "microformats-v2/h-card/childimplied",
        "microformats-v2/h-card/extendeddescription",
        "microformats-v2/h-card/hcard",
        "microformats-v2/h-card/hyperlinkedphoto",
        "microformats-v2/h-card/impliedname",
        "microformats-v2/h-card/impliedphoto",
        "microformats-v2/h-card/impliedurl",
        "microformats-v2/h-card/impliedurlempty",
        "microformats-v2/h-card/justahyperlink",
        "microformats-v2/h-card/justaname",
        "microformats-v2/h-card/nested",
        "microformats-v2/h-card/p-property",
        "microformats-v2/h-card/relativeurls",
        "microformats-v2/h-card/relativeurlsempty",
        "microformats-v2/h-entry/encoding",
        "microformats-v2/h-entry/impliedname",
        "microformats-v2/h-entry/impliedvalue-nested",
        "microformats-v2/h-entry/justahyperlink",
        "microformats-v2/h-entry/justaname",
        "microformats-v2/h-entry/scriptstyletags",
        "microformats-v2/h-entry/summarycontent",
        "microformats-v2/h-entry/u-property",
        "microformats-v2/h-entry/urlincontent",
        "microformats-v2/h-event/ampm",
        "microformats-v2/h-event/attendees",
        "microformats-v2/h-event/combining",
        "microformats-v2/h-event/concatenate",
        "microformats-v2/h-event/dates",
        "microformats-v2/h-event/dt-property",
        "microformats-v2/h-event/justahyperlink",
        "microformats-v2/h-event/justaname",
        "microformats-v2/h-event/time",
        "microformats-v2/h-feed/implied-title",
        "microformats-v2/h-feed/simple",
        "microformats-v2/h-geo/abbrpattern",
        "microformats-v2/h-geo/altitude",
        "microformats-v2/h-geo/hidden",
        "microformats-v2/h-geo/justaname",
        "microformats-v2/h-geo/simpleproperties",
        "microformats-v2/h-geo/valuetitleclass",
        "microformats-v2/h-product/aggregate",
        "microformats-v2/h-product/justahyperlink",
        "microformats-v2/h-product/justaname",
        "microformats-v2/h-product/simpleproperties",
        "microformats-v2/h-recipe/all",
        "microformats-v2/h-recipe/minimum",
        "microformats-v2/h-resume/affiliation",
        "microformats-v2/h-resume/contact",
        "microformats-v2/h-resume/education",
        "microformats-v2/h-resume/justaname",
        "microformats-v2/h-resume/skill",
        "microformats-v2/h-resume/work",
        "microformats-v2/h-review-aggregate/hevent",
        "microformats-v2/h-review-aggregate/justahyperlink",
        "microformats-v2/h-review-aggregate/simpleproperties",
        "microformats-v2/h-review/hyperlink",
        "microformats-v2/h-review/implieditem",
        "microformats-v2/h-review/item",
        "microformats-v2/h-review/justaname",
        "microformats-v2/h-review/photo",
        "microformats-v2/h-review/vcard",
        "microformats-v2/mixed/id",
        "microformats-v2/mixed/ignoretemplate",
        "microformats-v2/mixed/vendorprefix",
        "microformats-v2/mixed/vendorprefixproperty",
        "microformats-v2/rel/duplicate-rels",
        "microformats-v2/rel/license",
        "microformats-v2/rel/nofollow",
        "microformats-v2/rel/rel-urls",
        "microformats-v2/rel/varying-text-duplicate-rels",
        "microformats-v2/rel/xfn-all",
        "microformats-v2/rel/xfn-elsewhere",
    ];

    /** @dataProvider provideStandardTests */
    public function testStandardTests(string $path): void {
        if (in_array($path, self::SUPPRESSED)) {
            $this->markTestIncomplete();
        }
        $dom = new DOMParser;
        $parser = new Parser;
        $exp = json_decode(file_get_contents(self::BASE.$path.".json"), true);
        $html = file_get_contents(self::BASE.$path.".html");
        $doc = $dom->parseFromString($html, "text/html; charset=UTF-8");
        $act = $parser->parseElement($doc->documentElement);
        $this->assertSame($exp, $act);
    }

    public function provideStandardTests(): \Generator {
        foreach (new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::BASE)), '/\.json$/') as $path) {
            $path = str_replace(self::BASE, "", $path->getPathname());
            $path =  preg_replace('/\.json$/', '', $path);
            yield [$path];
        }
    }

}