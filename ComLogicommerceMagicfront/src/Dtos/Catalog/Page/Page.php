<?php

namespace Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page;

use FWK\Core\Dtos\Traits\RelatedItemsTrait;
use Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits\MagicfrontPageTrait;
use SDK\Dtos\Catalog\Page\Page as SDKPage;

/**
 * This is the Page container class.
 *
 * @see RelatedItemsTrait
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page
 */
class Page extends SDKPage {
    use RelatedItemsTrait, MagicfrontPageTrait;
}
