<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * MagicFront page types (`pageType` of the page record and the published blob) that paint a sample in the editor,
 * and the `GET /samples/{kind}` kind of each.
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class MagicfrontPageType extends Enum {

    public const PRODUCT = 'PRODUCT';

    public const CATEGORY = 'CATEGORY';

    public const SAMPLE_KIND = [self::PRODUCT => 'product', self::CATEGORY => 'category'];
}
