<?php
/** @license MIT
 * Copyright 2023 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace MensBeam\Microformats\Test;


class Microformats extends \MensBeam\Microformats {
    protected static function useHtmlDocument(): bool {
        return false;
    }
}