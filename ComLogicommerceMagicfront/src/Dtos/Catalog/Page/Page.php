<?php

namespace Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page;

use FWK\Core\Dtos\Traits\RelatedItemsTrait;
use Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits\DcsPageTrait;
use SDK\Dtos\Catalog\Page\Page as SDKPage;

/**
 * This is the Page container class.
 *
 * @see RelatedItemsTrait
 *
 * @package FWK\Dtos\Catalog
 */
class Page extends SDKPage {
    use RelatedItemsTrait, DcsPageTrait;
}
