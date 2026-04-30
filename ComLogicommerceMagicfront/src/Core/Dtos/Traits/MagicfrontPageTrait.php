<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits;

use FWK\Dtos\DragAndDrop\Widget;
use SDK\Core\Dtos\ElementCollection;

/**
 * Mixes Magicfront-specific fields (moduleSettings, draftId, slotId, slot
 * permissions, related collections) into the plugin Page DTO. See
 * Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits
 */
trait MagicfrontPageTrait {

    protected array $moduleSettings = [];

    protected ?ElementCollection $products = null;

    protected ?ElementCollection $categories = null;

    protected string $draftId = "";

    protected ?string $slotId = null;

    protected ?array $slotPermissions = null;

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

    /**
     * Override SDK Page::setSubpages so that constructor roundtrips (via toArray →
     * new Page($array), triggered by FillFromParentTrait::fillFromParentCollection
     * in PageRelationResolver) preserve the plugin Page class. The SDK default
     * rehydrates children through PageFactory, which returns SDK Page instances
     * lacking MagicfrontPageTrait — i.e. slotId, slotPermissions, moduleSettings
     * and draftId would silently drop on every roundtrip.
     *
     * Input may be a mix of plugin Pages (already hydrated), arrays (serialized
     * form emerging from toArray), or SDK Pages (upgraded in place). All three
     * are normalized to plugin Page so getSlotId/getSlotPermissions keep working
     * through the whole resolution chain.
     */
    protected function setSubpages(array $subpages): void {
        $items = [];
        $pluginPageClass = \Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page::class;
        foreach ($subpages as $sp) {
            if ($sp instanceof $pluginPageClass) {
                $items[] = $sp;
            } elseif (is_array($sp)) {
                $items[] = new $pluginPageClass($sp);
            } elseif (is_object($sp) && method_exists($sp, 'toArray')) {
                $items[] = new $pluginPageClass($sp->toArray());
            }
        }
        $this->subpages = $items;
    }

    public function setDraftId(string $draftId): void {
        $this->draftId = $draftId;
    }

    public function getDraftId(): string {
        return $this->draftId;
    }

    public function setSlotId(?string $slotId): void {
        $this->slotId = $slotId;
    }

    public function getSlotId(): ?string {
        return $this->slotId;
    }

    public function setSlotPermissions(?array $permissions): void {
        $this->slotPermissions = $permissions;
    }

    public function getSlotPermissions(): ?array {
        return $this->slotPermissions;
    }
}
