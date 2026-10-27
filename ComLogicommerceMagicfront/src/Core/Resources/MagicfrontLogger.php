<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use SDK\Core\Resources\Logger\LoggerAdapter;

/**
 * The plugin's own log, on LogiCommerce's logger (the SDK's LoggerAdapter, like ExceptionLogger): every line carries
 * the commerce, the request and the IP, goes wherever the store sends its logs (LOG_HANDLER: syslog, or a file under
 * LOG_PATH/magicfront/) and is switched off with LOG_ENABLED or MAGICFRONTLOGGER_ENABLED. Never error_log()
 * (AGENTS.md §4).
 *
 * `code` says what happened, one constant per event. The adapter keeps only the keys it declares and drops the rest
 * of the context without a word, so a new field goes into PARAMS first.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class MagicfrontLogger extends LoggerAdapter {

    /** The editor canvas language could not be applied to a render: prices and dates keep the session's. */
    public const CONTENT_LOCALE_NOT_APPLIED = 'content_locale_not_applied';

    /** The header's category tree could not be read: the menu shows its empty state. */
    public const HEADER_CATEGORY_MENU_UNAVAILABLE = 'header_category_menu_unavailable';

    protected const REQUIRED_PARAMS = ['code'];

    protected const PARAMS = ['widgetType', 'widgetId', 'reason', 'line', 'language', 'errorClass', 'errorMessage'];

    /**
     * Logs one event and never throws: a log that cannot be written must not break the render it reports on.
     *
     * @param string $level a PSR-3 level (warning, error…)
     * @param string $code one of this class's event constants
     * @param array $context only the keys in PARAMS reach the line
     */
    public static function record(string $level, string $code, string $message, array $context = []): void {
        try {
            self::getInstance()->log($level, $message, ['code' => $code] + $context);
        } catch (\Throwable) {
            // Building the logger failed (its own failures inside log() go to the SDK's fallback): nowhere to write.
        }
    }

    protected function getLoggerName(): string {
        return 'magicfront_log';
    }

    protected function getLogName(): string {
        return 'magicfront';
    }
}
