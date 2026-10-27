<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * The first day of the week of the commerce's calendar, counted as the widgets' date pickers count it (moment: 0 =
 * Sunday … 6 = Saturday). LogiCommerce keeps it in its calendar (`DateTimeFormatter::getCalendar()`, an IntlCalendar,
 * which counts 1 = Sunday … 7 = Saturday). One count for every calendar the plugin hands over: the product page's
 * options, the account's shopping list rows and `shared.calendar.firstDayOfWeek` (#5064).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class WeekStart {

    /** The day for the date pickers, or null when the calendar does not say (no calendar, or a day out of range). */
    public static function fromCalendar(?\IntlCalendar $calendar): ?int {
        return $calendar === null ? null : self::fromIcuDay($calendar->getFirstDayOfWeek());
    }

    /** IntlCalendar's day (1 = Sunday … 7 = Saturday) as the date pickers count it (0 = Sunday … 6 = Saturday), or null. */
    public static function fromIcuDay(mixed $day): ?int {
        return is_int($day) && $day >= 1 && $day <= 7 ? $day - 1 : null;
    }
}
