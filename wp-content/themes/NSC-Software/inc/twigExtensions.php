<?php

/**
 * Add Twig extensions.
 */

namespace NscSoftware\TwigExtensions;

use NscSoftware\Utils\TwigExtensionRenderComponent;
use NscSoftware\Utils\TwigExtensionReadingTime;
use NscSoftware\Utils\TwigExtensionPlaceholderImage;

add_filter('timber/twig', function ($twig) {
    $twig->addExtension(new TwigExtensionRenderComponent());
    $twig->addExtension(new TwigExtensionReadingTime());
    $twig->addExtension(new TwigExtensionPlaceholderImage());
    return $twig;
});
