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
 * accepts ({@see self::PER_PAGE}) in the controller's batch, and when the answer says there are more pages the rest,
 * up to the last page the API announced, are asked together in ONE batch call, like FWK's
 * ServiceTrait::getAllElementCollectionItems; they used to go one by one, a call per page (#4360).
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
     * @param callable(list<int>): array<int, mixed> $pages asks the API, in ONE batch, for the pages given (2..last) of
     *        the same catalogue; answers keyed by page number
     *
     * @return array<string, array{name: string, controlType: string}>
     */
    public static function fromPages(?ElementCollection $first, callable $pages): array {
        if (!$first instanceof ElementCollection) {
            return [];
        }
        $names = self::names($first->getItems());
        $last = $first->getPagination()?->getTotalPages() ?? 1;
        if ($last < 2) {
            return $names;
        }
        $answers = $pages(range(2, $last));
        for ($n = 2; $n <= $last; $n++) {
            $next = $answers[$n] ?? null;
            // A page that does not come (an API error) is skipped: what the others name is kept.
            if ($next instanceof ElementCollection) {
                $names += self::names($next->getItems());
            }
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
