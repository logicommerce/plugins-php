<?php

namespace Plugins\ComLogicommerceMagicfront\Services;

/**
 * WidgetTemplateDefaults - Complete widget templates for DCS
 * 
 * This class provides complete, ready-to-use Twig templates for all DCS widget types.
 * These templates replace the incomplete templates returned by the DCS API.
 * 
 * @package Plugins\ComLogicommerceMagicfront\Services
 */
class WidgetTemplateDefaults {

    /**
     * Get all widget templates
     * 
     * Returns an associative array where:
     * - key: widget type (e.g., 'heading', 'text', 'columns')
     * - value: complete Twig template string
     * 
     * @return array<string, string>
     */
    public static function getAllTemplates(): array {
        return [
            'heading' => self::getHeadingTemplate(),
            'image' => self::getImageTemplate(),
            'columns' => self::getColumnsTemplate(),
            'columnWrapper' => self::getColumnWrapperTemplate(),
            'text' => self::getTextTemplate(),
            'mainSlider' => self::getMainSliderTemplate(),
            'productsGrid' => self::getProductsGridTemplate(),
        ];
    }

    /**
     * Heading widget template
     * Displays a heading with configurable level (h1-h6)
     */
    private static function getHeadingTemplate(): string {
        return <<<'TWIG'
{# Generic DCS Widget: heading #}
{% set title = moduleSettings["title"]|default(page.language.name|trim) %}
{% set level = moduleSettings["level"]|default("h2")|lower %}

{# Ensure valid heading level #}
{% if level not in ["h1","h2","h3","h4","h5","h6"] %}
    {% set level = "h2" %}
{% endif %}

<section id="{{ widgetId }}" class="module-page-title">
    <div class="inset">
        {% if title|length > 0 %}
            <{{ level }} class="module-title {{ level }}">
                {{ title }}
            </{{ level }}>
        {% endif %}
    </div>
</section>
TWIG;
    }

    /**
     * Image widget template
     * Displays a single image with optional link
     */
    private static function getImageTemplate(): string {
        return <<<'TWIG'
<section id="{{ moduleType }}-{{ widgetId }}" class="module module-image{{ moduleSettings["lc-container"] ? " container-md" : "" }}">
    {%- if page.language.destinationUrl|length -%}
        <a class="inset" href="{{- page.language.destinationUrl -}}" target="{{- page.language.target -}}" {% if not page.language.linkFollowing -%} rel="nofollow" {%- endif %} {% if page.language.alt|length -%} title="{{- page.language.alt -}}" {%- endif %}>
    {%- else -%}
        <div class="inset">
    {%- endif -%}

        {%- if page.language.largeImage|length -%}
            <img class="img-fluid" src="{{- page.language.largeImage -}}" loading="lazy" {% if page.language.alt|length -%} alt="{{- page.language.alt -}}" {%- endif %}>
        {%- endif -%}

    {%- if page.language.destinationUrl|length -%}
        </a>
    {%- else -%}
        </div>
    {%- endif -%}
</section>
TWIG;
    }

    /**
     * Columns widget template
     * Container for multiple column layouts (2, 3, 4, or 6 columns)
     * IMPORTANT: Uses widgetMacros.widgets() for recursive rendering
     */
    private static function getColumnsTemplate(): string {
        return <<<'TWIG'
{# Generic DCS Widget: columns #}
{% import "macros/widget.twig" as widgetMacros %}

{% set columns = moduleSettings["columns.count"]|default(page.subpages|default([])|length) %}
{% set columnClass = "col-lg-12" %}
{% if columns == 2 %}
    {% set columnClass = "col-lg-6" %}
{% elseif columns == 3 %}
    {% set columnClass = "col-lg-4" %}
{% elseif columns == 4 %}
    {% set columnClass = "col-lg-3" %}
{% elseif columns == 6 %}
    {% set columnClass = "col-lg-2" %}
{% endif %}

<section id="{{ widgetId }}" class="module-columns">
    <div class="row">
        {% for i in 0..(columns - 1) %}
            {% if page.subpages[i] is defined %}
                {% set subPage = page.subpages[i] %}
                {% set widgetDraftId = subPage.draftId|default(subPage.pId)|default("") %}
                {% set widgetId = (page.id == 0 ? widgetDraftId : page.id) %}
                
                {% set payload = {
                    type: subPage.customType,
                    id: widgetDraftId|default(subPage.id)|default(""),
                    draftId: widgetDraftId,
                    parentId: (subPage.parentId is defined ? subPage.parentId : null),
                    label: (subPage.label is defined ? subPage.label : null)
                } %}

                <div class="col-12 {{ columnClass }}">
                    <!-- DCS_WIDGET_START {{ payload|json_encode|raw }} -->
                    <div id="{{ widgetId }}">
                        {% if subPage.subpages is defined %}
                            {{ widgetMacros.widgets({
                                pages: subPage.subpages,
                                version: version,
                                widgetTemplateList: widgetTemplateList
                            }) }}
                        {% endif %}
                    </div>
                    <!-- DCS_WIDGET_END -->
                </div>
            {% endif %}
        {% endfor %}
    </div>
</section>
TWIG;
    }

    /**
     * Column wrapper widget template
     * Container for nested widgets inside a column
     */
    private static function getColumnWrapperTemplate(): string {
        return <<<'TWIG'
{# Generic DCS Widget: columnWrapper #}
{# This is a container for nested widgets inside a column #}
<div id="{{ widgetId }}" class="column-wrapper">
    {# Content is rendered by parent columns.html.twig via widgetMacros #}
</div>
TWIG;
    }

    /**
     * Text widget template
     * Displays rich text content
     */
    private static function getTextTemplate(): string {
        return <<<'TWIG'
{# Generic DCS Widget: text #}
{% set content = moduleSettings["content"]|default(page.language.pageContent) %}

<section id="{{ widgetId }}" class="module-page-seo-home">
    <div class="inset">
        {% if content|length > 0 %}
            <div class="content">
                <div class="html-output">{{ content|raw }}</div>
            </div>
        {% endif %}
    </div>
</section>
TWIG;
    }

    /**
     * Main slider widget template
     * Full-width image slider with captions and links
     */
    private static function getMainSliderTemplate(): string {
        return <<<'TWIG'
{% set moduleSettings = page.moduleSettings|default([]) %}
{% set printCaption = moduleSettings["lc-caption"] == "true" %}
{% set moduleType = page.customType|default("") %}

{% if page.subpages|length %}
    <section id="{{moduleType}}-{{widgetId}}" class="module module-page-slider module-page-slider-full-width block-full-width{{ moduleSettings["lc-container"] ? " container-md" : "" }}" data-module="pageSliderFullWidth">
        <div class="{{- containerClass|default("container-fluid") }} p-0">
            <div class="swiper">
                <div class="swiper-wrapper">
                    {% for subpage in page.subpages|filter(subpage => subpage.language.largeImage|length) %}
                        {% set desktopImage = subpage.language.largeImage %}
                        {% set tabletImage = subpage.language.smallTitle|default(desktopImage) %}
                        {% set mobileImage = subpage.language.smallImage|default(desktopImage) %}

                        <div class="swiper-slide">
                            {% set additionalLinkText = subpage.customTagValues|filter(customTag => customTag.position == 1) %}
                            {% if printCaption %}
                                <div class="container-caption d-none d-md-flex">
                                    <div class="caption {{ captionContainerClass|default("container-md") }}">
                                        <div class="content">
                                            {% if subpage.language.name|trim|length %}
                                                <{{ titleNode|default("div") }} class="title">
                                                    {{- subpage.language.name|trim -}}
                                                </{{ titleNode|default("div") }}>
                                            {% endif %}

                                            {% if subpage.language.pageContent|length %}
                                                <div class="text">
                                                    <div class="html-output">{{ subpage.language.pageContent }}</div>
                                                </div>
                                            {% endif %}

                                            {# Link #}
                                            {% if subpage.language.destinationUrl|length and additionalLinkText|length %}
                                                {% set additionalLinkText = additionalLinkText|first %}
                                                {% if additionalLinkText.value|length %}
                                                    <a class="btn btn-primary" href="{{ subpage.language.destinationUrl }}" target="{{ subpage.language.target }}" {% if not page.language.linkFollowing %} rel="nofollow" {% endif %}>
                                                        <span class="text">{{- additionalLinkText.value -}}</span>
                                                        <svg class="icon"><use xlink:href="#icon-angle-right"></use></svg>
                                                    </a>
                                                {% endif %}
                                            {% endif %}
                                        </div>
                                    </div>
                                    {% if subpage.language.destinationUrl|length %}
                                        <a class="background-link" href="{{- subpage.language.destinationUrl -}}" target="{{- subpage.language.target -}}" {% if not subpage.language.linkFollowing %} rel="nofollow" {% endif %} {% if subpage.language.name|length %} title="{{- subpage.language.name|striptags -}}" {% endif %}></a>
                                    {% endif %}
                                </div>
                            {% endif %}

                            {% if subpage.language.destinationUrl|length %}
                                <a class="inset" href="{{- subpage.language.destinationUrl -}}" target="{{- subpage.language.target -}}" {% if not subpage.language.linkFollowing %} rel="nofollow" {% endif %} {% if subpage.language.name|length %} title="{{- subpage.language.name|striptags -}}" {% endif %}>
                            {% else %}
                                <div class="inset">
                            {% endif %}

                                <picture>
                                    <source media="(min-width: 992px)" srcset="{{ desktopImage }}">
                                    <source media="(min-width: 576px)"  srcset="{{ tabletImage }}">
                                    <source srcset="{{ mobileImage }}">
                                    <img class="slide-image img-fluid" src="{{- mobileImage -}}" loading="lazy" {% if subpage.language.name|length %}alt="{{- subpage.language.name|striptags -}}"{% endif %}>
                                </picture>

                            {% if subpage.language.destinationUrl|length %}
                                </a>
                            {% else %}
                                </div>
                            {% endif %}
                        </div>
                    {% endfor %}
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev swiper-control">
                    <svg class="icon"><use xlink:href="#icon-angle-left"></use></svg>
                </div>
                <div class="swiper-button-next swiper-control">
                    <svg class="icon"><use xlink:href="#icon-angle-right"></use></svg>
                </div>
            </div>
        </div>
    </section>
{% endif %}
TWIG;
    }

    /**
     * Products grid widget template
     * Displays a grid of products with buy forms
     * NOTE: This is a very long template - contains complete product card markup
     */
    private static function getProductsGridTemplate(): string {
        // NOTE: I'm keeping the full template you provided
        // It's very long but complete
        return <<<'TWIG'
{% set products = page.products %}
{%- if products|length -%}
    {%- if not mobileColumns is defined or not mobileColumns in [1, 2] -%}
        {%- set mobileColumns = 1 -%}
    {%- endif -%}
    {%- if not titleNode is defined or not titleNode|length -%}
        {%- set titleNode = "div" -%}
    {%- endif -%}
    {%- if not titleClass is defined or not titleClass|length -%}
        {%- set titleClass = "heading" -%}
    {%- endif -%}
    {%- if not containerClass is defined -%}
        {%- set containerClass = "" -%}
    {%- endif -%}

    {% set path =  version ~ "/widgets/productList.html.twig" %}
    {% if version == "" or source(path, ignore_missing=true) is empty %}
        {% set path = "/widgets/productList.html.twig"  %}
    {% endif %}

    <section id="{{moduleType}}-{{widgetId}}" class="module module-products module-products-grid {{ moduleSettings["lc-container"] ? " container-md" : "" }} {{ mobileColumns == 2 ? "row-items-xs-2" : "" }}">
        <div class="{{- containerClass -}}">
            {%- if titleText is defined and titleText|length -%}
                <div class="module-title-wrap">
                    <{{ titleNode }} class="module-title {{ titleClass }}">
                        {{- titleText -}}
                    </{{ titleNode }}>
                </div>
            {%- endif -%}

            <div class="grid-items">
                {%- for product in products -%}
                    <div class="grid-item">
                        {% import "macros/product.twig" as productMacros %}

<div id="{{moduleType}}-{{widgetId}}" class="product-list{{product.definition.offer ? " offer-true" : ""}} {{product.definition.featured ? " featured-true" : ""}}">
    {%- set contentBuyForm -%}
        <div class="inset">
            <div class="product-list-img-cont">
                <a class="product-list-img-link" href="{{- product.language.urlSeo -}}" {% if not product.language.linkFollowing %}rel="nofollow"{% endif %} title="{{- product.language.name|escape -}}">
                    <div class="product-list-img-size ratio ratio-1x1">
                        {% set notFoundImage = "/assets/themes/default/img/not-found.png"%}
                        <img src="{{- product.mainImages.smallImage|default(notFoundImage) -}}" alt="{{ product.language.altImageKeywords|default(product.language.name) }}" class="product-list-img img-fluid" onError="this.src='{{- notFoundImage -}}'; this.onerror=null;" loading="lazy">
                    </div>
                    {%- set offer = product.definition.offer and offerImage|length -%}
                    {%- set featured = product.definition.featured and featuredImage|length -%}
                    {%- set discounts = themeConfiguration.commerce.showDiscounts and product.id in productsIdsWithDiscounts and discountsImage|length -%}
                    {%- if offer or featured or discounts -%}
                        <div class="product-list-ribbons">
                            {%- if offer -%}
                                <img src="{{ offerImage }}" class="product-list-ribbon sale-ribbon img-fluid" alt="Product on sale">
                            {%- endif -%}
                            {%- if featured -%}
                                <img src="{{ featuredImage }}" class="product-list-ribbon featured-ribbon img-fluid" alt="Featured product">
                            {%- endif -%}
                            {%- if discounts -%}
                                <img src="{{ discountsImage }}" class="product-list-ribbon discounts-ribbon img-fluid" alt="Product with discounts">
                            {%- endif -%}
                        </div>
                    {%- endif -%}
                </a>
                {{- productMacros.buttonShoppingList({
                    item: product,
                    showLabel: false,
                    class: "btn btn-secondary",
                    showDefaultShoppingListButton: true
                }) -}}
                {%- if settings.catalogSettings.productComparisonActive and themeConfiguration.commerce.showProductComparison -%}
                    {{- productMacros.buttonProductComparison({
                        product: product,
                        showLabel: false,
                        class: "btn btn-secondary",
                    }) -}}
                {%- endif -%}
            </div>
            <div class="product-list-content">
                <a class="product-list-title-link" href="{{- product.language.urlSeo -}}" {% if not product.language.linkFollowing -%} rel="nofollow" {%- endif %} title="{{- product.language.name|escape -}}">
                    {%- if route.type in ["HOME", "CATEGORY"] -%}
                        <h3 class="product-list-title">{{ product.language.name }}</h3>
                    {%- else -%}
                        <div class="product-list-title">{{ product.language.name }}</div>
                    {%- endif -%}
                </a>
                <div class="product-list-prices">
                    {{- productMacros.property({
                        product: product,
                        property: "price",
                    }) -}}
                    {{- productMacros.property({
                        product: product,
                        property: "basePrice",
                    }) -}}
                </div>
                {%- if themeConfiguration.commerce.showStockAlert and formProductStockAlert is defined -%}
                    <div class="product-list-stock-alert">
                        {{- productMacros.property({
                            product: product,
                            property: "stock",
                            stockAlertButton: true,
                            stockAlertButtonClass: "btn btn-link",
                        }) -}}
                    </div>
                {%- endif -%}
                <div class="product-list-order-box product-list-order-box-select">
                    <div class="row">
                        <div class="col col-quick-buy">
                            {%- if purchasable -%}
                                {{- productMacros.buyFormOptions({ 
                                    product: product,
                                    showShortDescription: true,
                                    showUnavailableLabel: true,
                                    selectDefaults: true,
                                    showImageOptions: true,
                                    showGridDisabled: showGridDisabled
                                }) -}}
                            {%- endif -%}
                            {%- if product.options|length and not purchasable -%}
                                <a class="buyFormSubmit btn btn-primary linkToProduct" href="{{- product.language.urlSeo -}}" {% if not product.language.linkFollowing %}rel="nofollow"{% endif %} title="{{- product.language.name|escape -}}">
                                    BUY
                                </a>
                            {%- else -%}
                                {{- productMacros.buyFormSubmit({
                                    id: product.id,
                                    showOrderBox: product.definition.showOrderBox,
                                    class: "btn btn-primary"
                                }) -}}
                            {%- endif -%}
                        </div>
                    </div>
                </div>

            </div>
        </div>
    {%- endset -%}

    {%- if purchasable -%}
        {{- productMacros.buyProductForm({
            content: contentBuyForm,
            class: "product-list-form product-list-form-options",
            product: product,
            sectionId: linkedsSectionId,
            discountSelectableGiftId: discountSelectableGiftId
        }) -}}
    {%- else -%}
        {{- productMacros.buyProductForm({
            content: contentBuyForm,
            class: "product-list-form",
            product: product,
        }) -}}
    {%- endif -%}
</div>
                    </div>
                {%- endfor -%}
            </div>
        </div>
    </section>
{%- endif -%}
TWIG;
    }
}
