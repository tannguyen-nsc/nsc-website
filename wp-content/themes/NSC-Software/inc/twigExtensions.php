<?php

/**
 * Add Twig extensions.
 */

namespace NscSoftware\TwigExtensions;

use NscSoftware\Utils\TwigExtensionRenderComponent;
use NscSoftware\Utils\TwigExtensionReadingTime;
use NscSoftware\Utils\TwigExtensionPlaceholderImage;
use Twig\TwigFunction;

add_filter('timber/twig', function ($twig) {
    $twig->addExtension(new TwigExtensionRenderComponent());
    $twig->addExtension(new TwigExtensionReadingTime());
    $twig->addExtension(new TwigExtensionPlaceholderImage());
    $twig->addFunction(new TwigFunction('nsc_hero_explore_scroll_stats', static function ($url) {
        return \NscSoftware\Components\NSCBlockHero\hero_button_is_explore_scroll_to_stats((string) $url);
    }));
    return $twig;
});
