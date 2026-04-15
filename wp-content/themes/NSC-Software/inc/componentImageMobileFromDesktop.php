<?php

declare(strict_types=1);

namespace NscSoftware;

use NscSoftware\Utils\ComponentImageMobileFromDesktop;

/**
 * Duplicate desktop ACF image into the mobile field when mobile is empty (per component).
 *
 * @param mixed $data
 *
 * @return mixed
 */
$applyMerge = static function ($data, string $desktopKey, string $mobileKey) {
    if (!is_array($data)) {
        return $data;
    }

    return ComponentImageMobileFromDesktop::merge($data, $desktopKey, $mobileKey);
};

$register = static function (string $componentName, string $desktopKey, string $mobileKey) use ($applyMerge) {
    add_filter(
        "NscSoftware/addComponentData?name={$componentName}",
        static function ($data) use ($applyMerge, $desktopKey, $mobileKey) {
            return $applyMerge($data, $desktopKey, $mobileKey);
        },
        15,
        1
    );
};

$register('NSCBlockHero', 'imageDesktop', 'imageMobile');
$register('NSCBlockStats', 'imageDesktop', 'imageMobile');
$register('NSCBlockGlobalPresence', 'backgroundDesktop', 'backgroundMobile');
$register('NSCBlockOurLeaders', 'backgroundDesktop', 'backgroundMobile');
$register('NSCBlockWhyUs', 'backgroundImage', 'backgroundImageMobile');
$register('NSCBlockHowWeWork', 'image', 'imageMobile');
$register('NSCBlockHowWeWorkPageHero', 'imageDesktop', 'imageMobile');
$register('NSCBlockHowWeWorkPageEngagement', 'backgroundDesktop', 'backgroundMobile');
$register('NSCFooter', 'logo', 'logoMobile');
