<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

/**
 * The two chrome regions a page renders around its content. The backing value is the blob
 * content key (`content.header` / `content.footer`) and the dcsapi kind token, so callers use
 * $kind->value everywhere a raw string is needed. Each case also maps to its Twig global keys,
 * keeping that wiring in one place instead of threading four string arguments through the render.
 */
enum ChromeKind: string {

    case Header = 'header';
    case Footer = 'footer';

    public function pagesGlobalKey(): string {
        return match ($this) {
            self::Header => MagicfrontControllerData::HEADER_PAGES,
            self::Footer => MagicfrontControllerData::FOOTER_PAGES,
        };
    }

    public function templatesGlobalKey(): string {
        return match ($this) {
            self::Header => MagicfrontControllerData::HEADER_TEMPLATE_LIST,
            self::Footer => MagicfrontControllerData::FOOTER_TEMPLATE_LIST,
        };
    }

    public function cssGlobalKey(): string {
        return match ($this) {
            self::Header => MagicfrontControllerData::HEADER_CSS,
            self::Footer => MagicfrontControllerData::FOOTER_CSS,
        };
    }

    public function jsGlobalKey(): string {
        return match ($this) {
            self::Header => MagicfrontControllerData::HEADER_JS,
            self::Footer => MagicfrontControllerData::FOOTER_JS,
        };
    }
}
