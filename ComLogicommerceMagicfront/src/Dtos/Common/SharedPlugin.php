<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * One plugin type of `shared.plugins` (`shared.plugins.captcha`…). Its properties are the keys a widget reads
 * (toArray()). A type that needs more data gets it here, never the plugin's properties: they may hold its keys.
 *
 * @see BaseShared
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class SharedPlugin extends Element {
    use ElementTrait;

    protected bool $enabled = false;

    /**
     * Sets whether a plugin of the store provides this type.
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    /**
     * Returns whether a plugin of the store provides this type.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }
}
