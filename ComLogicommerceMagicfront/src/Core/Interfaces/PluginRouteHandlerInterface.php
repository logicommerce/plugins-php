<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Interfaces;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use SDK\Core\Dtos\Element;

interface PluginRouteHandlerInterface {

    public function supports(string $type): bool;

    public function handle(ComLogicommerceMagicfrontController $controller): ?Element;

    public function isRawResponse(): bool;

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string;

    public function getRawResponseContentType(): ?string;
}
