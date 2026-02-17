<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use SDK\Core\Dtos\Element;

abstract class AbstractPluginRouteHandler implements PluginRouteHandlerInterface {

    public function handle(ComLogicommerceMagicfrontController $controller): ?Element {
        return null;
    }

    public function isRawResponse(): bool {
        return false;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        return null;
    }

    public function getRawResponseContentType(): ?string {
        return null;
    }
}
