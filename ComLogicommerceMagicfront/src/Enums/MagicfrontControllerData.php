<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

/**
 * Twig data keys MagicFront exposes to templates.
 *
 * Centralising the strings here means a typo on either side — PHP setter
 * (Controller::setDataValue / Environment::addGlobal) or Twig consumer —
 * fails loud instead of silently making the variable null.
 *
 * Naming follows fwk's `FWK\Enums\ControllerData` pattern: PascalCase constant,
 * camelCase value.
 */
abstract class MagicfrontControllerData {

    public const PAGE                 = 'page';

    /**
     * The page's chrome doc-id refs `{header:<id>, footer:<id>}` — the chrome docs THIS
     * page points at (its own forks, or the shared defaults). Read by TwigInitializer to
     * fetch the chrome tree by id; empty/missing kinds fall back to the commerce defaults.
     */
    public const PAGE_CHROME          = 'pageChrome';

    public const WIDGET_TEMPLATE_LIST = 'widgetTemplateList';
    public const WIDGET_TYPES         = 'widgetTypes';

    public const ASSETS_URL      = 'mffAssetsUrl';
    public const CANVAS_MODE     = 'mffCanvasMode';
    public const PREVIEW_MODE    = 'mffPreviewMode';
    public const SHOW_ASSETS     = 'mffShowAssets';
    public const CUSTOM_CSS      = 'mffCustomCss';
    public const CUSTOM_JS       = 'mffCustomJs';
    public const OVERRIDE_HEADER = 'mffOverrideHeader';
    public const OVERRIDE_FOOTER = 'mffOverrideFooter';

    public const HEADER_PAGES         = 'mffHeaderPages';
    public const HEADER_TEMPLATE_LIST = 'mffHeaderTemplateList';
    public const HEADER_CSS           = 'mffHeaderCss';
    public const HEADER_JS            = 'mffHeaderJs';
    public const FOOTER_PAGES         = 'mffFooterPages';
    public const FOOTER_TEMPLATE_LIST = 'mffFooterTemplateList';
    public const FOOTER_CSS           = 'mffFooterCss';
    public const FOOTER_JS            = 'mffFooterJs';

    public const ACCOUNT_PANEL_PAGES         = 'mffAccountPanelPages';
    public const ACCOUNT_PANEL_TEMPLATE_LIST = 'mffAccountPanelTemplateList';
    public const ACCOUNT_PANEL_CSS           = 'mffAccountPanelCss';
    public const ACCOUNT_PANEL_JS            = 'mffAccountPanelJs';
    public const BASKET_PANEL_PAGES          = 'mffBasketPanelPages';
    public const BASKET_PANEL_TEMPLATE_LIST  = 'mffBasketPanelTemplateList';
    public const BASKET_PANEL_CSS            = 'mffBasketPanelCss';
    public const BASKET_PANEL_JS             = 'mffBasketPanelJs';

    /** Globals emitted by {@see \Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder::toGlobals()}. */
    public const CONTEXT_PREVIEW_MODE = 'previewMode';
    public const CONTEXT_CORE_MODE    = 'coreMode';
}
