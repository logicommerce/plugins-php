<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Core\Interfaces\PluginRouteHandlerInterface;
use Plugins\ComLogicommerceMagicfront\Core\Resources\DesignConfig;
use SDK\Core\Dtos\Element;

/**
 * Serves the CSS for the active (page type, design) pair as one concatenated
 * stylesheet. Driven by the `page` and `design` query params; only the files
 * listed in DesignConfig for that pair are emitted.
 *
 * URL: .../plugin_route/com.logicommerce.magicfront?type=customizeDesignCSS&page=category&design=design189
 */
class CustomizeDesignStyleHandler implements PluginRouteHandlerInterface {

    public function supports(string $type): bool {
        return $type === 'customizeDesignCSS';
    }

    public function handle(object $controller): ?Element {
        return null;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContent(object $controller): ?string {
        $page   = (string) ($controller->getRequestParamValue('page', false) ?? '');
        $design = (string) ($controller->getRequestParamValue('template', false) ?? '');
        if (!DesignConfig::isValidPage($page)) {
            return '';
        }
        $override = DesignConfig::overrideFromPayload(filter_input(INPUT_GET, \FWK\Enums\Parameters::ADDITIONAL_DATA));
        $baseDir = dirname(__DIR__, 4) . '/assets/css/' . $page . '/';
        $output = '';
        foreach (DesignConfig::cssFiles($page, $design, $override) as $file) {
            $path = $baseDir . $file;
            if (file_exists($path)) {
                $output .= "/* === {$page}/{$file} === */\n" . file_get_contents($path) . "\n";
            }
        }
        return $output;
    }

    public function getRawResponseContentType(): ?string {
        return 'text/css; charset=utf-8';
    }
}
