<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Loader;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\WidgetTwigRenderingTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\SubpageRouteResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use SDK\Dtos\Catalog\Page\Page as SdkPage;
use SDK\Dtos\Common\Route;

/**
 * `subpageContent` — the pageList widget's OWN endpoint for loading a subpage's content without a
 * full-page navigation (the account-panel pattern). Given the subpage's storefront path (the tree
 * link's href, prefixed by the widget's JS with the current locale segment), it resolves the path to
 * its Route the way FWK's Router does ({@see SubpageRouteResolver}), loads that page's published blob
 * (FOB — ZERO dcsapi), and renders ONLY the pageList's `content` slot (the page's own inner content),
 * NOT the pageList shell itself — the tree/shell already lives on the page. The widget's templateJs
 * swaps the returned HTML into `.pgl-content`.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
class SubpageContentHandler extends AbstractPluginRouteHandler {

    use WidgetTwigRenderingTrait;
    use CssGeneratorTrait;
    use JsGeneratorTrait;

    private const CONTENT_SLOT = 'content';

    public function supports(string $type): bool {
        return $type === FunctionType::SUBPAGE_CONTENT;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return 'text/html; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $path = (string) ($controller->getRequestParamValue(Parameters::PAGE, false) ?? '');
        if ($path === '') {
            return '';
        }

        try {
            $resolver = new SubpageRouteResolver();
            $route    = $resolver->routeByPath($path);
            if ($route instanceof Route && $route->getStatus() === 301) {
                $redirectPath = parse_url((string) $route->getRedirectUrl(), PHP_URL_PATH);
                if (is_string($redirectPath) && $redirectPath !== '') {
                    $route = $resolver->routeByPath($redirectPath);
                }
            }
            if (!$route instanceof Route || $route->getStatus() !== 200 || $route->getId() <= 0) {
                return '';
            }

            $page = Loader::service(Services::PAGE)->getPageById($route->getId());
            if (!$page instanceof SdkPage) {
                return '';
            }
            $document = PageDocument::fromJson($page->getLanguage()?->getPageContent());
            if ($document === null) {
                return '';
            }

            $pages = PageRelationResolver::setData($document->toPages());
            $items = $pages !== null ? $pages->getItems() : [];
            if ($items === []) {
                return '';
            }

            $renderList = $this->contentSlotWidgets($items);
            if ($renderList === []) {
                return '';
            }

            $templatesById      = $document->templatesById();
            $types              = WidgetTypeCollector::fromPages($renderList);
            $widgetTemplateList = $this->buildWidgetTemplateList($types, $templatesById);
            $twigEnv            = $this->buildTwigEnvironment($controller, $widgetTemplateList);

            $html = '';
            foreach ($renderList as $widget) {
                if (!$widget instanceof PluginPage) {
                    continue;
                }
                $type = $widget->getCustomType();
                if (empty($widgetTemplateList[$type])) {
                    continue;
                }
                $html .= $this->renderWidgetHtml($twigEnv, $type, $widgetTemplateList, [
                    'page'           => $widget,
                    'moduleType'     => $type,
                    'moduleSettings' => $widget->getModuleSettings(),
                    'widgetId'       => $widget->getDraftId() ?: $widget->getId(),
                    'version'        => Theme::getInstance()->getVersion(),
                ]);
            }

            $flatWidgets = WidgetTypeCollector::flatten($document->widgets()?->getItems() ?? []);
            return json_encode([
                'html' => $html,
                'css'  => $this->generateCss($flatWidgets, $templatesById),
                'js'   => $this->generateJs($templatesById),
            ]);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The widgets to render into `.pgl-content`: the children of the pageList's `content` slot (the
     * subpage's own inner content). Falls back to the full top-level list when no pageList / slot is
     * found, so a plain page still renders.
     *
     * @param  PluginPage[] $items
     * @return PluginPage[]
     */
    private function contentSlotWidgets(array $items): array {
        $pageList = $this->findPageList($items);
        if ($pageList === null) {
            return $items;
        }
        foreach ($pageList->getSubpages() ?? [] as $slot) {
            if ($slot instanceof PluginPage && $slot->getSlotId() === self::CONTENT_SLOT) {
                return [$slot];
            }
        }
        return $items;
    }

    /**
     * @param  PluginPage[] $items
     */
    private function findPageList(array $items): ?PluginPage {
        foreach ($items as $item) {
            if (!$item instanceof PluginPage) {
                continue;
            }
            if ($item->getCustomType() === 'pageList') {
                return $item;
            }
            $found = $this->findPageList($item->getSubpages() ?? []);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }
}
