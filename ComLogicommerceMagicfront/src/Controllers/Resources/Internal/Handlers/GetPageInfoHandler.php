<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use FWK\Core\Resources\Language;
use FWK\Core\Resources\Session;
use SDK\Core\Dtos\Element;

class GetPageInfoHandler extends AbstractPluginRouteHandler {

    public function supports(string $type): bool {
        return $type === FunctionType::GET_PAGE_INFO;
    }

    public function handle(ComLogicommerceMagicfrontController $controller): ?Element {
        return new class() extends Element {
            public function jsonSerialize(): mixed {
                return [
                    'routeId' => Session::getInstance()->getRouteId(),
                    'language' => Language::getInstance()->getLanguage(),
                ];
            }
        };
    }
}
