<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as MffPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Binds a set of blog data onto every widget Page DTO (and its subpages) so blog
 * widgets read it as page.post / page.blogPosts / page.breadcrumb / ... Each data
 * key maps to a MagicfrontPageTrait setter by convention (`post` -> setPost,
 * `blogCategory` -> setBlogCategory, ...); a key whose setter does not exist on the
 * page is skipped. Adding a new bound field only needs the setter on the page DTO
 * plus the key in the data array — no change here.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class BlogPageBinder {

    /** @param array $data */
    public function __construct(private readonly array $data) {
    }

    public function applyTo(?ElementCollection $pages): void {
        if ($pages === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof MffPage) {
                continue;
            }
            foreach ($this->data as $key => $value) {
                $setter = 'set' . ucfirst($key);
                if (method_exists($page, $setter)) {
                    $page->$setter($value);
                }
            }
            $sub = $page->getSubpages();
            if (!empty($sub)) {
                $this->applyTo(new ElementCollection(['items' => $sub]));
            }
        }
    }
}
