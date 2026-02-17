<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits;

use FWK\Dtos\DragAndDrop\Widget;
use SDK\Core\Dtos\ElementCollection;

/**
 * This is the Related items trait.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits
 */
trait DcsPageTrait {

    protected array $moduleSettings = [];

    protected ?ElementCollection $products = null;

    protected ?ElementCollection $categories = null;

    protected string $draftId = "";

    /**
     * Devuelve los settings del módulo.
     * Primero intenta usar moduleSettings directo, si está vacío extrae de customTagValues.
     */
    public function getModuleSettings(): array {
        if (count($this->moduleSettings) > 0) {
            return $this->moduleSettings;
        }

        // Fallback: extraer TODOS los customTagValues a moduleSettings
        foreach ($this->customTagValues ?? [] as $tag) {
            $pId = $tag->getCustomTagPId();
            if (!empty($pId)) {
                $this->moduleSettings[$pId] = $tag->getValue();
            }
        }
        return $this->moduleSettings;
    }

    /**
     * Devuelve los settings del módulo (lc-*).
     */
    public function getProducts(): ?ElementCollection {
        return $this->products;
    }

    public function setProducts(?ElementCollection $products): void {
        $this->products = $products;
    }

    public function getCategories(): ?ElementCollection {
        return $this->categories;
    }

    public function setCategories(?ElementCollection $categories): void {
        $this->categories = $categories;
    }

    public function setFWKSubpages(array $subpages): void {
        $this->subpages = $subpages;
    }

    public function setDraftId(string $draftId): void {
        $this->draftId = $draftId;
    }

    public function getDraftId(): string {
        return $this->draftId;
    }
}
