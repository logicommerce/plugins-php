<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

/**
 * «This block» per device (display@_self) without ever writing a display value to show it.
 *
 * Absence means visible: showing a widget on a device REMOVES its value instead of writing `display: block`, which
 * broke wrappers that are `display: contents` (card pieces on a subgrid, cells) after hiding and showing them again.
 * One case still needs care: hidden on desktop and visible on a smaller device. Desktop rules carry no media query,
 * so the smaller device would inherit the `none`. Instead of cancelling it with a value, the desktop `none` is
 * emitted only on the range where it applies:
 *
 *   - tablet visible → desktop `none` in `@media (min-width: 992px)`; the tablet «visible» emits nothing; a mobile
 *     without a value of its own keeps inheriting the desktop `none`, so it is emitted explicitly on mobile.
 *   - only mobile visible → desktop `none` in `@media (min-width: 768px)` (covers tablet too); mobile emits nothing.
 *
 * Only display@_self: a PART's display may be what the template's CSS needs to show it, so its values stay literal.
 * Kept byte-identical in the storefront plugin and the docker renderer's plugin-src, used by CssGeneratorTrait and
 * InstanceCssBuilder alike (both renderers emit the same CSS).
 */
final class VisibilityRanges {

    /** Wide desktop: above the tablet range. */
    public const WIDE_MEDIA = '@media (min-width: 992px)';

    /** Desktop and tablet: above the mobile range. */
    public const FROM_TABLET_MEDIA = '@media (min-width: 768px)';

    private const STYLE_ID = 'display';
    private const SELF = '_self';

    /**
     * @param array<int, mixed> $styleValues one widget's style values (arrays or objects)
     * @return array{desktop: array<int, mixed>, devices: array<int, mixed>, wide: array<int, mixed>, fromTablet: array<int, mixed>}
     *         desktop — what the desktop pass renders; devices — what the tablet/mobile passes project from;
     *         wide / fromTablet — desktop-shaped entries for the two range media queries.
     */
    public static function split(array $styleValues): array {
        $desktop = [];
        $devices = [];
        $wide = [];
        $fromTablet = [];
        foreach ($styleValues as $style) {
            $arr = is_array($style) ? $style : (is_object($style) ? get_object_vars($style) : null);
            if ($arr === null || !self::isHiddenSelf($arr)) {
                $desktop[] = $style;
                $devices[] = $style;
                continue;
            }
            $tablet = self::deviceValue($arr, 'tablet');
            $mobile = self::deviceValue($arr, 'mobile');
            $base = $arr;
            unset($base['tablet'], $base['mobile']);
            if (self::isVisible($tablet)) {
                $wide[] = $base;
                $projected = $arr;
                unset($projected['tablet']);
                if (self::isVisible($mobile)) {
                    unset($projected['mobile']);
                } elseif ($mobile === null) {
                    $projected['mobile'] = ['value' => 'none'];
                }
                $devices[] = $projected;
            } elseif (self::isVisible($mobile)) {
                $fromTablet[] = $base;
                $projected = $arr;
                unset($projected['mobile']);
                $devices[] = $projected;
            } else {
                $desktop[] = $style;
                $devices[] = $style;
            }
        }
        return ['desktop' => $desktop, 'devices' => $devices, 'wide' => $wide, 'fromTablet' => $fromTablet];
    }

    private static function isHiddenSelf(array $style): bool {
        $elementId = $style['elementId'] ?? $style['htmlKey'] ?? $style['elementKey'] ?? $style['element'] ?? '';
        return ($style['styleId'] ?? null) === self::STYLE_ID && $elementId === self::SELF
            && ($style['value'] ?? null) === 'none';
    }

    private static function deviceValue(array $style, string $device): mixed {
        $override = $style[$device] ?? null;
        if (is_object($override)) {
            $override = get_object_vars($override);
        }
        return is_array($override) && array_key_exists('value', $override) ? $override['value'] : null;
    }

    private static function isVisible(mixed $value): bool {
        return $value !== null && $value !== '' && $value !== 'none';
    }
}
