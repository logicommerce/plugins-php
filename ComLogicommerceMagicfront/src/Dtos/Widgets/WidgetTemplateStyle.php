<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Widgets;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the WidgetTemplateStyle class.
 * The information of API widget template style definitions will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see WidgetTemplateStyle::getId()
 * @see WidgetTemplateStyle::getCssProperty()
 * @see WidgetTemplateStyle::getElementId()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Dtos
 */
class WidgetTemplateStyle {
    use ElementTrait;

    protected string $id = '';

    protected string $cssProperty = '';

    protected string $elementId = '';

    /**
     * Optional descendant CSS selector appended after {@code [data-mff-el="Y"]}
     * by the renderer. Enables styling of merchant-authored descendants (e.g.
     * links inside a rich-text block) without requiring a {@code data-mff-el}
     * hook on every inner element. Values are server-side-validated; the
     * renderer trusts that content.
     */
    protected string $descendantSelector = '';

    /**
     * Returns the style id (unique within the template's style scope).
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Returns the CSS property name this style maps to (e.g. `color`, `font-size`).
     *
     * @return string
     */
    public function getCssProperty(): string {
        return $this->cssProperty;
    }

    /**
     * Returns the element id within the template that this style targets.
     *
     * @return string
     */
    public function getElementId(): string {
        return $this->elementId;
    }

    public function getDescendantSelector(): string {
        return $this->descendantSelector;
    }
}
