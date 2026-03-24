<?php

declare(strict_types=1);

namespace NscSoftware\Utils;

/**
 * When an ACF image (desktop) is set but the paired mobile field is empty,
 * copy desktop into mobile so Twig can render one asset on both breakpoints.
 */
final class ComponentImageMobileFromDesktop
{
    /**
     * @param mixed $value ACF image (array, ID, object) or empty.
     */
    public static function acfImageFieldEmpty($value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return true;
        }
        if (is_numeric($value)) {
            return (int) $value <= 0;
        }
        if (is_array($value)) {
            if (!empty($value['url']) || !empty($value['src'])) {
                return false;
            }
            $id = (int) ($value['ID'] ?? $value['id'] ?? 0);

            return $id <= 0;
        }
        if (is_object($value)) {
            $id = (int) ($value->ID ?? $value->id ?? 0);

            return $id <= 0;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function merge(array $data, string $desktopKey, string $mobileKey): array
    {
        if (!self::acfImageFieldEmpty($data[$mobileKey] ?? null)) {
            return $data;
        }
        if (self::acfImageFieldEmpty($data[$desktopKey] ?? null)) {
            return $data;
        }
        $data[$mobileKey] = $data[$desktopKey];

        return $data;
    }
}
