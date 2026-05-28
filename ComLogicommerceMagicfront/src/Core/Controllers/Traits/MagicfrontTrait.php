<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\FilterInput\FilterInput;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Services\PluginService;
use FWK\Twig\TwigLoader;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Twig\PluginTwigBootstrap;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Resources\Environment;
use SDK\Dtos\Common\Route;

/**
 * Mixes Magicfront-aware batch/data hooks into HTML controllers (Home,
 * Page\Module). Controllers extending the FWK base classes call
 * magicfrontInit() in their constructor, then delegate their setBatchData
 * and setData to setMagicfrontBatchData / setMagicfrontData.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait MagicfrontTrait {

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $token = null;

    protected ?string $page = null;

    protected bool $pluginMagicfrontEnabled = false;

    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            MagicfrontToken::MF_TOKEN => new FilterInput($noMod),
            Parameters::PAGE         => new FilterInput($noMod),
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;
        $this->widgetsService = WidgetsService::getInstance();

        /** @var PluginService $pluginService */
        $pluginService = Loader::service(Services::PLUGIN);
        $this->pluginMagicfrontEnabled = $pluginService->isPluginMagicFrontEnabled($route->getType());

        $this->token = MagicfrontToken::setToken($this->getRequestParam(MagicfrontToken::MF_TOKEN, false, null));
        $this->page  = $this->getRequestParam(Parameters::PAGE, false, null);

        $this->page = $this->page
            ?? (!empty($this->token) ? $this->widgetsService->getPageId((string)$route->getId()) : null);
    }

    protected function isCacheable(): bool {
        if (MagicfrontUtils::isCanvasMode()) {
            return false;
        }
        return parent::isCacheable();
    }

    /**
     * Register the plugin's Twig customisation on the storefront's Twig
     * environments so widget templates — including those rendered via
     * `template_from_string` inside the widgets.html.twig macro — can resolve
     * `mff_price`.
     *
     * fwk's TwigLoader keeps TWO envs: `$twig` (main) and a private `$coreTwig`
     * that hosts core macros (including the widget macro). By the time this
     * hook runs, `loadCore()` has already loaded macros into coreTwig, so
     * coreTwig's extension set is initialised — `addFunction()` / `addGlobal()`
     * throw on it. We use `registerUndefinedFunctionCallback()` for coreTwig
     * (the only Twig API immune to the init lock) and the normal `addFunction`
     * path for the still-unlocked main env. Reflection is required because
     * TwigLoader exposes no getter for coreTwig.
     *
     * The single-widget AJAX render path (GetWidgetHandler) wires the same
     * bootstrap into its own private Twig env — see
     * GetWidgetHandler::buildTwigEnvironment.
     */
    protected function addTwigBaseFunctions(TwigLoader $twig): void {
        parent::addTwigBaseFunctions($twig);
        $ctx = ContextBuilder::fromSession();
        PluginTwigBootstrap::apply($twig->getTwigEnvironment(), $ctx);
        PluginTwigBootstrap::applyLazyFunctions(self::fwkCoreTwig($twig), $ctx);
    }

    /**
     * Reach fwk's private `$coreTwig` env. No public getter exposes it. If the
     * property ever disappears or stops being a Twig\Environment, throw —
     * silently skipping would let featuredProduct render with empty prices
     * and hide the contract drift.
     */
    private static function fwkCoreTwig(TwigLoader $twig): \Twig\Environment {
        $ref = new \ReflectionProperty(TwigLoader::class, 'coreTwig');
        $ref->setAccessible(true);
        $coreTwig = $ref->getValue($twig);
        if (!$coreTwig instanceof \Twig\Environment) {
            throw new \RuntimeException(
                'MagicfrontTrait: fwk TwigLoader::$coreTwig is not a Twig\\Environment instance.'
            );
        }
        return $coreTwig;
    }


    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if (!$this->pluginMagicfrontEnabled || !$this->token || !$this->page) {
            return;
        }
        $this->pages = $this->widgetsService->getPageWidgets($this->page, $this->route->getLanguage());
    }

    protected function setMagicfrontData(): void {
        $this->pages = PageRelationResolver::setData($this->pages);
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTemplateList = [];
        $widgetTypes        = [];

        if ($this->pluginMagicfrontEnabled && $this->token && $this->pages !== null) {
            $widgetTypes = WidgetTypeCollector::fromPages($this->pages->getItems() ?? []);
            if ($widgetTypes !== []) {
                foreach ($this->widgetsService->getWidgetTemplatesForTypes($widgetTypes) as $type => $template) {
                    $widgetTemplateList[$type] = $template->getTemplateHtml();
                }
            }
        }

        $canvasMode = MagicfrontUtils::isCanvasMode();
        $showAssets = !$canvasMode && !empty($this->page) && !empty($widgetTypes);
        $language   = $this->route->getLanguage();

        $this->setDataValue('widgetTemplateList', $widgetTemplateList);
        $this->setDataValue('widgetTypes', $widgetTypes);
        $this->setDataValue('page', $this->page);
        $this->setDataValue('mfAssetsUrl', Environment::get('MF_ASSETS_URL'));
        $this->setDataValue('mfCanvasMode', $canvasMode);
        $this->setDataValue('mfShowAssets', $showAssets);
        $this->setDataValue('mfCustomCssUrl', $showAssets ? MagicfrontUtils::storefrontUrl(FunctionType::CUSTOMIZE_CSS, $this->page, $language) : null);
        $this->setDataValue('mfCustomJsUrl',  $showAssets ? MagicfrontUtils::storefrontUrl(FunctionType::CUSTOMIZE_JS,  $this->page, $language) : null);
    }
}
