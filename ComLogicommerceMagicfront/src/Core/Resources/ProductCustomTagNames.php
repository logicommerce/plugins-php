<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use SDK\Core\Dtos\CustomTag;
use SDK\Core\Dtos\ElementCollection;

/**
 * The name and control type of every PRODUCT custom tag, keyed by pId, for `page.productCustomTags`
 * (read by `mff_product_specs`, the product sheet's technical specs).
 *
 * The catalogue is paginated. It used to be asked without `perPage`, so the API answered with its first page only:
 * every tag past it had no name and the specs printed its code instead (product 4188 on nightly: tags 05-10 of group 41
 * showed «QA-PP-SPEC-05…10», a clean cut by order, #2519). Now the first page is asked with the largest page the API
 * accepts ({@see self::PER_PAGE}) in the controller's batch, and when the answer says there are more pages the rest
 * are fetched here, one by one, until the last page the API announced.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class ProductCustomTagNames {

    /** The largest page `CustomTagsParametersGroup::setPerPage` accepts: one call for any usual catalogue. */
    public const PER_PAGE = 100;

    private function __construct() {
    }

    /**
     * @param ElementCollection|null $first the first page, already answered in the controller's batch
     * @param callable(int): mixed $page asks the API for page N (2..last) of the same catalogue
     *
     * @return array<string, array{name: string, controlType: string}>
     */
    public static function fromPages(?ElementCollection $first, callable $page): array {
        if (!$first instanceof ElementCollection) {
            return [];
        }
        $names = self::names($first->getItems());
        $last = $first->getPagination()?->getTotalPages() ?? 1;
        for ($n = 2; $n <= $last; $n++) {
            $next = $page($n);
            // A page that does not come (an API error) or comes empty ends the walk: what is named is kept.
            if (!$next instanceof ElementCollection || count($next->getItems()) === 0) {
                break;
            }
            $names += self::names($next->getItems());
        }
        return $names;
    }

    /** @return array<string, array{name: string, controlType: string}> */
    private static function names(array $tags): array {
        $names = [];
        foreach ($tags as $tag) {
            if (!$tag instanceof CustomTag) {
                continue;
            }
            $name = $tag->getLanguage()?->getName() ?? '';
            if ($tag->getPId() !== '' && $name !== '') {
                $names[$tag->getPId()] = ['name' => $name, 'controlType' => (string) $tag->getControlType()];
            }
        }
        return $names;
    }
}
