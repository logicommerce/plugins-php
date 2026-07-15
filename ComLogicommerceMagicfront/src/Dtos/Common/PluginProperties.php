<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Enums\AvailablePagesValue;
use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;
use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\Traits\ElementTrait;

class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    protected array $properties = [];

    public function getProperties(): array {
        return $this->properties;
    }

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PluginPropertiesProperty::class);
    }

    /** Layout/chrome routes — broad: any chrome toggle on → all routes. */
    public function getAvailablePages(): array {
        if ($this->isPreviewRequest() || $this->isHeaderOverlayEnabled() || $this->isFooterOverlayEnabled()) {
            return $this->allRouteTypes();
        }
        return $this->routesFromAvailablepages();
    }

    /** Controller takeover routes — strict: only BO `availablepages` (chrome toggles don't broaden). */
    public function getControllerOverridePages(): array {
        if ($this->isPreviewRequest()) {
            return $this->allRouteTypes();
        }
        return $this->routesFromAvailablepages();
    }

    /** @return string[] */
    private function routesFromAvailablepages(): array {
        return array_values(array_filter(array_map(
            static fn($v) => AvailablePagesValue::TO_ROUTE_TYPE[$v] ?? null,
            $this->getAvailablepagesValues()
        )));
    }

    /** @return string[] */
    private function allRouteTypes(): array {
        return array_values(array_filter(
            (new \ReflectionClass(\FWK\Enums\RouteType::class))->getConstants(),
            'is_string'
        ));
    }

    public function isHeaderOverlayEnabled(): bool {
        return $this->getBooleanProperty(PluginPropertiesPropertyNames::USEHEADER);
    }

    public function isFooterOverlayEnabled(): bool {
        return $this->getBooleanProperty(PluginPropertiesPropertyNames::USEFOOTER);
    }

    /** @return string[] */
    private function getAvailablepagesValues(): array {
        foreach ($this->properties as $property) {
            if ($property->getName() === PluginPropertiesPropertyNames::AVAILABLEPAGES) {
                return $property->getValue() ?? [];
            }
        }
        return [];
    }

    private function getBooleanProperty(string $name): bool {
        foreach ($this->properties as $property) {
            if ($property->getName() === $name) {
                return filter_var($property->getValue(), FILTER_VALIDATE_BOOLEAN);
            }
        }
        return false;
    }

    /** Editor preview unlock: canvas iframe or mfToken URL param. */
    private function isPreviewRequest(): bool {
        return MagicfrontUtils::isCanvasMode() || !empty($_GET[MagicfrontToken::MF_TOKEN]);
    }
}
