<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Widgets;

use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;
use Plugins\ComLogicommerceMagicfront\Core\Twig\WidgetTemplateTransformer;

/**
 * This is the WidgetTemplate class.
 * The information of API widget templates will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see WidgetTemplate::getId()
 * @see WidgetTemplate::getTemplateHtml()
 * @see WidgetTemplate::getTemplateCss()
 * @see WidgetTemplate::getTemplateJs()
 * @see WidgetTemplate::getProperties()
 * @see WidgetTemplate::getStyles()
 * @see WidgetTemplate::getChildStructure()
 * @see WidgetTemplate::getSlots()
 *
 * @see Element
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Dtos
 */
class WidgetTemplate extends Element {
    use ElementTrait;

    protected string $id = '';

    protected string $templateHtml = '';

    protected string $templateCss = '';

    protected string $templateJs = '';

    protected array $properties = [];

    /** @var WidgetTemplateStyle[] */
    protected array $styles = [];

    protected ?WidgetTemplateChildStructure $childStructure = null;

    /**
     * Typed slots declared by the widget template (the slot composition
     * mechanism). Each entry is an associative array matching the backend
     * `WidgetTemplateSlot` shape: id, defaultType, minItems, maxItems,
     * allowMove, allowDelete, allowDuplicate, allowTypeChange.
     *
     * @var array<int, array<string,mixed>>
     */
    protected array $slots = [];

    /**
     * Returns the widget template id.
     *
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Returns the widget template HTML (with `mff_widget_slot` and `data-mff-child-index` transforms applied).
     *
     * @return string
     */
    public function getTemplateHtml(): string {
        return $this->templateHtml;
    }

    /**
     * Returns the widget template CSS.
     *
     * @return string
     */
    public function getTemplateCss(): string {
        return $this->templateCss;
    }

    /**
     * Returns the widget template JavaScript.
     *
     * @return string
     */
    public function getTemplateJs(): string {
        return $this->templateJs;
    }

    /**
     * Returns the widget template property definitions.
     *
     * @return array
     */
    public function getProperties(): array {
        return $this->properties;
    }

    /**
     * Returns the widget template style definitions.
     *
     * @see WidgetTemplateStyle
     * @return WidgetTemplateStyle[]
     */
    public function getStyles(): array {
        return $this->styles;
    }

    /**
     * Returns the child structure descriptor, or NULL if the template has none.
     *
     * @see WidgetTemplateChildStructure
     * @return WidgetTemplateChildStructure|NULL
     */
    public function getChildStructure(): ?WidgetTemplateChildStructure {
        return $this->childStructure;
    }

    /**
     * Returns the typed-slot declarations, or an empty array if the template
     * has none. A non-empty array means the widget is a slot container and its
     * instance CSS must be `:not()`-scoped at the slot boundary to prevent
     * descendant leakage into slot-child widget roots.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getSlots(): array {
        return $this->slots;
    }

    protected function setTemplateHtml(string $html): void {
        $this->templateHtml = WidgetTemplateTransformer::transform($html);
    }

    protected function setStyles(array $styles): void {
        $this->styles = $this->setArrayField($styles, WidgetTemplateStyle::class);
    }

    protected function setChildStructure(array $childStructure): void {
        $this->childStructure = new WidgetTemplateChildStructure($childStructure);
    }
}
