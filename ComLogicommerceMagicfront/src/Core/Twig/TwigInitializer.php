<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use SDK\Core\Resources\Timer;
use FWK\Core\Resources\Utils;
use FWK\Twig\PluginTwigInitializer;
use Plugins\ComLogicommerceMagicfront\Core\Resources\CaptchaSiteKey;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use Twig\Environment;

/**
 * This is the FWK Twig hook of MagicFront: it returns the layout of the page and puts our header, footer and panels
 * into Twig globals. It only calls:
 *   - EditorChrome: what the editor paints (drafts, preview tab, Personalización).
 *   - PublishedChrome: what the storefront paints (what is published).
 *   - ChromeGlobals: the Twig globals, the same for both.
 *
 * @see EditorChrome
 * @see PublishedChrome
 * @see ChromeGlobals
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class TwigInitializer implements PluginTwigInitializer {

    private const LAYOUT = 'layouts/magicfront.html.twig';

    // Disabled: no code has ever sent X-MFF-Content-Only (userPanel uses the widgetContent endpoint). Delete in the cleanup.
    // private const CONTENT_ONLY_LAYOUT = 'layouts/mff-content-only.html.twig';

    /**
     * This method returns the layout of the page and adds our header, footer and panels to the Twig globals.
     *
     * @param Environment $main
     * @param Environment $core
     * @param string $routeType
     * @param array $controllerData
     *
     * @return string|NULL 
     */
    public function apply(Environment $main, Environment $core, string $routeType, array $controllerData): ?string {
        // Disabled: no code has ever sent X-MFF-Content-Only. Delete in the cleanup.
        // $contentOnly = MagicfrontUtils::isContentOnlyRequest();
        $pagePId = SpecialPagePId::forRouteType($routeType) ?? (string) ($controllerData[MagicfrontControllerData::PAGE] ?? '');
        Utils::addTimerDebugFlag('ti-context', Timer::START_SUFFIX);
        $ctx = ContextBuilder::fromSession(true, $pagePId);
        Utils::addTimerDebugFlag('ti-context', Timer::END_SUFFIX);
        PluginTwigBootstrap::apply($main, $ctx);
        // Widgets render in $core (the FWK widget macro), which is locked by now: functions only, through late binding.
        PluginTwigBootstrap::applyLazyFunctions($core, $ctx);
        // The browser half of the captcha (setCaptchaToken), for every storefront page with our layout: the canvas never
        // sends a form, so it asks LogiCommerce nothing there.
        $main->addGlobal(MagicfrontControllerData::CAPTCHA_SITE_KEY, RenderMode::isCanvasMode() ? '' : CaptchaSiteKey::get());

        // Disabled: no code has ever sent X-MFF-Content-Only. Delete in the cleanup.
        // if ($contentOnly) {
        //     return self::CONTENT_ONLY_LAYOUT;
        // }
        // Studio (the editor's widget designer): the widget alone, without header, footer or panels.
        if (EditorChrome::isStudioDocument($controllerData)) {
            return EditorChrome::STUDIO_LAYOUT;
        }

        $published = PublishedChrome::create();
        $chromeOnly = EditorChrome::getChromeOnly();
        $panelOnly = EditorChrome::getPanelOnly($chromeOnly);
        $headerOn = EditorChrome::isRegionShown(ChromeKind::Header, $chromeOnly, $published);
        $footerOn = EditorChrome::isRegionShown(ChromeKind::Footer, $chromeOnly, $published);
        $globals = new ChromeGlobals($main);
        $globals->addRegionFlags($headerOn, $footerOn, $chromeOnly, $panelOnly);
        // Nothing of ours to paint: the theme keeps its own header and footer.
        if (!$headerOn && !$footerOn && $panelOnly === null) {
            return EditorChrome::getLayout(self::LAYOUT, $chromeOnly);
        }

        $regions = [];
        if ($headerOn) {
            $regions[] = ChromeKind::Header;
        }
        if ($footerOn) {
            $regions[] = ChromeKind::Footer;
        }

        if (RenderMode::isPreviewMode()) {
            $editor = EditorChrome::create($ctx->language, $controllerData, $chromeOnly);
            Utils::addTimerDebugFlag('ti-chrome', Timer::START_SUFFIX);
            foreach ($regions as $kind) {
                $globals->addRegion($kind, $editor->getRegion($kind));
            }
            Utils::addTimerDebugFlag('ti-chrome', Timer::END_SUFFIX);
            foreach ($editor->getPanels($headerOn, $panelOnly) as $kind) {
                $globals->addPanel($kind, $editor->getPanel($kind, $published));
            }
        } else {
            $pageChrome = PublishedChrome::getPageChrome($controllerData);
            Utils::addTimerDebugFlag('ti-chrome', Timer::START_SUFFIX);
            foreach ($regions as $kind) {
                $globals->addRegion($kind, $published->getRegion($kind, $pageChrome));
            }
            Utils::addTimerDebugFlag('ti-chrome', Timer::END_SUFFIX);
            foreach ($published->getPanels($headerOn, $panelOnly) as $kind) {
                $globals->addPanel($kind, $published->getPanel($kind));
            }
        }

        Utils::addTimerDebugFlag('ti-shared', Timer::START_SUFFIX);
        $globals->addShared($controllerData);
        Utils::addTimerDebugFlag('ti-shared', Timer::END_SUFFIX);
        return EditorChrome::getLayout(self::LAYOUT, $chromeOnly);
    }
}
