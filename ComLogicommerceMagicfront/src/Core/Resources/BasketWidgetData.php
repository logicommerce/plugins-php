<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Utils;
use FWK\ViewHelpers\Basket\BasketViewHelper;
use SDK\Dtos\Basket\Basket;
use SDK\Dtos\Basket\BasketRow;
use SDK\Dtos\Basket\BasketWarnings\BasketWarning;
use SDK\Enums\BasketRowType;
use SDK\Enums\BasketWarningSeverity;

/**
 * The shopper's basket for basketContent (`page.basket`): the SDK basket exactly as LogiCommerce gives it, plus what
 * the block needs prepared the way LogiCommerce's own basket page prepares it (FWK BasketContent macro), in fields of
 * its own (#7981, F-831; review of request 465). No SDK field is overwritten:
 *  - `items[].mffOptions`: each option as `{name, text}`, the text LogiCommerce writes for it
 *    (Utils::parseBasketRowOptions). The SDK `value` of a selection is the chosen value object, not its text
 *    («Talla: Array»), and a multiple selection carries `valueList`;
 *  - `items[].mffWarnings` and `mffWarnings` (the band): each warning as `{code, severity, hashes, message}`, with the
 *    text LogiCommerce writes from its language sheet (BasketViewHelper::setBasketWarningMessage), HTML included (the
 *    «resend» link of USER_NOT_VERIFIED works as in the native page). Like LogiCommerce, the ones whose text ends up
 *    empty are left out (a row's stock warning, for one) and a row's warning is not repeated in the band. The SDK
 *    `basketWarnings` only carry a code;
 *  - `items[].mffWarningSeverity`: the highest severity that concerns the row, as LogiCommerce colours it.
 * The unit price the block paints is the SDK's own `prices.unitPrice` (`prices.price` is the amount of the row).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class BasketWidgetData {

    private const SEVERITY_RANK = [
        BasketWarningSeverity::INFO => 1,
        BasketWarningSeverity::WARNING => 2,
        BasketWarningSeverity::ERROR => 3,
    ];

    /** @var callable(BasketWarning, Basket, bool): array{message: string, hash: string} */
    private $warningText;

    /** @var callable(object): string */
    private $optionText;

    /**
     * The defaults are LogiCommerce's own texts; a test hands its own, since they read the FWK language sheet.
     */
    public function __construct(?callable $warningText = null, ?callable $optionText = null) {
        $this->warningText = $warningText ?? static function (BasketWarning $warning, Basket $basket, bool $ofRow): array {
            $written = BasketViewHelper::setBasketWarningMessage($warning, $basket, 'WARNING_', $ofRow);
            return ['message' => trim($written->getMessage()), 'hash' => $written->getMessageHash()];
        };
        $this->optionText = $optionText ?? static function (object $option): string {
            // Utils writes «<name> <values>» for LogiCommerce's own messages; the block writes the name itself.
            return trim(substr(Utils::parseBasketRowOptions([$option]), strlen($option->getName())));
        };
    }

    /**
     * @return array<string, mixed> the basket for `page.basket`
     */
    public function of(Basket $basket): array {
        $data = json_decode(json_encode($basket), true) ?? [];
        $rows = [];
        $rowHashes = [];
        foreach ($basket->getItems() as $row) {
            $warnings = $this->writtenWarnings($this->ownWarnings($row), $basket, true);
            foreach ($warnings as $warning) {
                $rowHashes[$warning['hash']] = true;
            }
            $options = method_exists($row, 'getOptions') ? array_values($row->getOptions()) : [];
            $rows[$row->getHash()] = [
                'mffOptions' => array_map(fn (object $option): array => [
                    'name' => $option->getName(),
                    'text' => $this->textOf($option),
                ], $options),
                'mffWarnings' => array_map(self::forTemplate(...), $warnings),
                'mffWarningSeverity' => $this->severity($row, $basket),
            ];
        }

        foreach ($data['items'] ?? [] as $key => $item) {
            $row = $rows[$item['hash'] ?? ''] ?? null;
            if ($row !== null) {
                $data['items'][$key] += $row;
            }
        }

        // A warning already shown under its row is not repeated in the band (LogiCommerce compares the written texts).
        $band = [];
        foreach ($this->writtenWarnings($basket->getBasketWarnings(), $basket, false) as $warning) {
            if (!isset($rowHashes[$warning['hash']]) && !isset($band[$warning['hash']])) {
                $band[$warning['hash']] = self::forTemplate($warning);
            }
        }
        $data['mffWarnings'] = array_values($band);

        return $data;
    }

    /**
     * The warnings of a row itself: its own, or those of its items for a bundle.
     *
     * @return BasketWarning[]
     */
    private function ownWarnings(BasketRow $row): array {
        if ($row->getType() === BasketRowType::BUNDLE && method_exists($row, 'getItems')) {
            $warnings = [];
            foreach ($row->getItems() as $item) {
                $warnings = [...$warnings, ...$item->getBasketWarnings()];
            }
            return $warnings;
        }
        return method_exists($row, 'getBasketWarnings') ? $row->getBasketWarnings() : [];
    }

    /**
     * Each warning with LogiCommerce's text, leaving out the ones whose text is empty (LogiCommerce does not show them:
     * a row's stock warning, for one).
     *
     * @param BasketWarning[] $warnings
     * @return array<int, array{warning: BasketWarning, message: string, hash: string}>
     */
    private function writtenWarnings(array $warnings, Basket $basket, bool $ofRow): array {
        $written = [];
        foreach ($warnings as $warning) {
            $text = ($this->warningText)($warning, $basket, $ofRow);
            if ($text['message'] !== '') {
                $written[] = ['warning' => $warning, 'message' => $text['message'], 'hash' => $text['hash']];
            }
        }
        return $written;
    }

    /** @param array{warning: BasketWarning, message: string, hash: string} $written */
    private static function forTemplate(array $written): array {
        return [
            'code' => $written['warning']->getCode(),
            'severity' => $written['warning']->getSeverity(),
            'hashes' => $written['warning']->getHashes(),
            'message' => $written['message'],
        ];
    }

    /**
     * The highest severity that concerns the row, as LogiCommerce colours it (BasketContent::getRowWarningSeverity):
     * its own warnings and the basket's that name it, whether or not their text is shown.
     */
    private function severity(BasketRow $row, Basket $basket): string {
        $warnings = $this->ownWarnings($row);
        foreach ($basket->getBasketWarnings() as $warning) {
            if (in_array($row->getHash(), $warning->getHashes(), true)) {
                $warnings[] = $warning;
            }
        }
        $highest = '';
        foreach ($warnings as $warning) {
            if ((self::SEVERITY_RANK[$warning->getSeverity()] ?? 0) > (self::SEVERITY_RANK[$highest] ?? 0)) {
                $highest = $warning->getSeverity();
            }
        }
        return $highest;
    }

    private function textOf(object $option): string {
        try {
            return ($this->optionText)($option);
        } catch (\Throwable) {
            // A selection with no value chosen: the block writes the name alone.
            return '';
        }
    }
}
