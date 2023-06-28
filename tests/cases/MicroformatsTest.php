<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Microformats\TestCase;

use MensBeam\Microformats;

/**
 * @covers MensBeam\Microformats
 */
class MicroformatsTest extends \PHPUnit\Framework\TestCase {
    public function testSerializeToJson(): void {
        $exp = '{"items":[{"type":["h-card"],"properties":{}}],"rels":{},"rel-urls":{}}';
        $act = Microformats::toJson(Microformats::fromString('<div class="vcard"></div>', "", ""));
        $this->assertSame($exp, $act);
    }

    public function testParseMissingFile(): void {
        $this->assertNull(@Microformats::fromFile("THIS FILE DOES NOT EXIST", "", ""));
    }

    public function testParseRedirectedUrl(): void {
        $exp = [
            'items'    => [
                [
                    'type' => ["h-test"],
                    'properties' => [
                        'name' => ["Ça et là"],
                        'url'  => ["http://localhost:8000/root.html"],
                    ],
                ],
            ],
            'rels'     => [],
            'rel-urls' => [],
        ];
        $this->assertSame($exp, Microformats::fromUrl("http://localhost:8000/redir"));
    }

    public function testParseInvalidUrl(): void {
        $this->assertNull(@Microformats::fromUrl("http://localhost:8000/404"));
    }
}