<?php

/**
 * Enqueue frontend build assets (CSS/JS from frontend/build) so that
 * NSC header, footer and section components that mirror the static build
 * use the same styles and scripts.
 *
 * Also injects NSC_GLOBE_BUILD_URI for globe.js/wave.js and enqueues
 * Three.js, globe, and wave scripts so hero home (wave) and Why Us (globe)
 * work on dynamic component pages.
 */

namespace NscSoftware\EnqueueBuildAssets;

$buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
$buildPath = get_template_directory() . '/frontend/build';

add_action('wp_enqueue_scripts', function () use ($buildUri, $buildPath) {
    $cssPath = $buildPath . '/css/style.css';
    if (file_exists($cssPath)) {
        wp_enqueue_style(
            'nsc-software-build',
            $buildUri . '/css/style.css',
            [],
            filemtime($cssPath)
        );
        // Hide the admin bar on the front so it does not overlap the main menu.
        wp_add_inline_style('nsc-software-build', '#wpadminbar { display: none !important; }');
    }

    // Slick carousel (testimonials logos + testimonials-slider) – same as build index.html
    wp_enqueue_style(
        'nsc-slick',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',
        [],
        '1.8.1'
    );
    wp_enqueue_style(
        'nsc-slick-theme',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css',
        ['nsc-slick'],
        '1.8.1'
    );
    wp_enqueue_script(
        'nsc-slick',
        'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
        ['jquery'],
        '1.8.1',
        true
    );

    $jsPath = $buildPath . '/js/scripts.js';
    if (file_exists($jsPath)) {
        $buildScriptDeps = ['jquery', 'nsc-slick'];
        // scripts.js bundles blog-list.js (Blogs archive). Vue 3 must load first whenever build JS runs;
        // conditional enqueue was fragile (ACF/get_field vs. saved meta), leaving Vue undefined while
        // #blog-list-app exists — then the list never mounts.
        wp_enqueue_script(
            'nsc-vue3',
            'https://unpkg.com/vue@3/dist/vue.global.prod.js',
            [],
            '3',
            true
        );
        wp_script_add_data('nsc-vue3', 'defer', true);
        $buildScriptDeps[] = 'nsc-vue3';
        wp_enqueue_script(
            'nsc-software-build',
            $buildUri . '/js/scripts.js',
            $buildScriptDeps,
            filemtime($jsPath),
            true
        );
        // Fallback: init testimonial sliders on load (WP home can run build script before testimonials DOM is ready)
        wp_add_inline_script(
            'nsc-software-build',
            'window.addEventListener("load", function nscTestimonialSlidersFallback() {
                if (typeof jQuery === "undefined" || typeof jQuery.fn.slick === "undefined") return;
                var $ = jQuery;
                var MOBILE = 425, TABLET = 768, DESKTOP = 1024, LARGE = 1280;
                var $logos = $(".testimonials .logos");
                if ($logos.length && !$logos.hasClass("slick-initialized")) {
                    var logosShow = 3;
                    if (screen.width > MOBILE) logosShow = 4;
                    if (screen.width > TABLET) logosShow = 5;
                    if (screen.width > DESKTOP) logosShow = 6;
                    var total = $logos.find(".logo").length;
                    if (total > 1 && logosShow >= total) logosShow = total - 1;
                    $logos.slick({ slidesToShow: logosShow, slidesToScroll: 1, autoplay: true, autoplaySpeed: 5000, speed: 800, arrows: false, dots: false, infinite: true, pauseOnHover: true, swipe: true, draggable: true });
                }
                var $slider = $(".testimonials-slider");
                if ($slider.length && !$slider.hasClass("slick-initialized")) {
                    var slidesShow = 1;
                    if (screen.width >= DESKTOP) slidesShow = 2;
                    if (screen.width >= LARGE) slidesShow = 3;
                    $slider.slick({ slidesToShow: slidesShow, slidesToScroll: 1, autoplay: true, autoplaySpeed: 5000, speed: 800, arrows: false, dots: false, infinite: true, pauseOnHover: true, swipe: true, draggable: true, cssEase: "ease-in-out", prevArrow: "<button type=\"button\" class=\"slick-prev\" aria-label=\"Previous\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M15.707 4.29289C16.0975 4.68342 16.0975 5.31643 15.707 5.70696L10.4141 10.9999H22C22.5522 10.9999 23 11.4476 23 11.9999C23 12.5522 22.5522 12.9999 22 12.9999H10.4141L15.707 18.2929C16.0975 18.6834 16.0975 19.3164 15.707 19.707C15.3165 20.0975 14.6834 20.0975 14.2929 19.707L7.29289 12.707C6.90237 12.3164 6.90237 11.6834 7.29289 11.293L14.2929 4.29289C14.6834 3.90237 15.3165 3.90237 15.707 4.29289Z\" fill=\"currentColor\"/></svg></button>", nextArrow: "<button type=\"button\" class=\"slick-next\" aria-label=\"Next\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M8.293 4.29289C7.90237 4.68342 7.90237 5.31643 8.293 5.70696L13.5859 10.9999H2C1.44772 10.9999 1 11.4476 1 11.9999C1 12.5522 1.44772 12.9999 2 12.9999H13.5859L8.293 18.2929C7.90237 18.6834 7.90237 19.3164 8.293 19.707C8.68342 20.0975 9.31643 20.0975 9.70696 19.707L16.707 12.707C17.0976 12.3164 17.0976 11.6834 16.707 11.293L9.70696 4.29289C9.31643 3.90237 8.68342 3.90237 8.293 4.29289Z\" fill=\"currentColor\"/></svg></button>" });
                }
            });',
            ['position' => 'after']
        );
    }

    // Three.js, globe, and wave (for hero home wave + Why Us globe on component pages)
    // Defer so they run after wp_footer Three.js + addons (defer) – avoids THREE undefined
    wp_enqueue_script(
        'nsc-three-fat-lines',
        $buildUri . '/js/external/three-fat-lines.js',
        [],
        file_exists($buildPath . '/js/external/three-fat-lines.js') ? filemtime($buildPath . '/js/external/three-fat-lines.js') : null,
        true
    );
    wp_script_add_data('nsc-three-fat-lines', 'defer', true);
    wp_enqueue_script(
        'nsc-three-globe',
        'https://cdn.jsdelivr.net/npm/three-globe@2.45.0/dist/three-globe.min.js',
        [],
        '2.45.0',
        true
    );
    wp_script_add_data('nsc-three-globe', 'defer', true);
    wp_enqueue_script(
        'nsc-tween',
        'https://cdn.jsdelivr.net/npm/@tweenjs/tween.js@23/dist/tween.umd.js',
        [],
        '23',
        true
    );
    wp_script_add_data('nsc-tween', 'defer', true);
    $globePath = $buildPath . '/js/external/globe.js';
    if (file_exists($globePath)) {
        wp_enqueue_script(
            'nsc-globe',
            $buildUri . '/js/external/globe.js',
            ['nsc-three-fat-lines', 'nsc-three-globe', 'nsc-tween'],
            filemtime($globePath),
            true
        );
        wp_script_add_data('nsc-globe', 'defer', true);
    }
    $wavePath = $buildPath . '/js/external/wave.js';
    if (file_exists($wavePath)) {
        wp_enqueue_script(
            'nsc-wave',
            $buildUri . '/js/external/wave.js',
            [],
            filemtime($wavePath),
            true
        );
        wp_script_add_data('nsc-wave', 'defer', true);
    }
}, 20);

// Google Fonts (Montserrat) – match frontend/build/index.html
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">' . "\n";
}, 1);

// Expose build URI for globe.js (e.g. ./img/s.png) and wave.js
add_action('wp_head', function () use ($buildUri) {
    echo '<script id="nsc-globe-build-uri">window.NSC_GLOBE_BUILD_URI='
        . json_encode($buildUri, JSON_UNESCAPED_SLASHES)
        . ";</script>\n";
}, 5);

// Three.js in head (no defer) so THREE is defined before any footer script (globe, wave, three-fat-lines)
add_action('wp_head', function () {
    echo '<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>' . "\n";
}, 6);

// Addons (EffectComposer etc.) in footer – attach to existing THREE
add_action('wp_footer', function () {
    echo '<script type="importmap">' . "\n";
    echo json_encode([
        'imports' => [
            'three' => 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js',
            'three/addons/' => 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    echo '</script>' . "\n";
    echo '<script type="module">' . "\n";
    echo "import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';\n";
    echo "import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';\n";
    echo "import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';\n";
    echo "import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';\n";
    echo "import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';\n";
    echo "if (typeof THREE !== 'undefined') {\n";
    echo "  THREE.EffectComposer = EffectComposer;\n";
    echo "  THREE.RenderPass = RenderPass;\n";
    echo "  THREE.ShaderPass = ShaderPass;\n";
    echo "  THREE.UnrealBloomPass = UnrealBloomPass;\n";
    echo "  THREE.OutputPass = OutputPass;\n";
    echo "  window.dispatchEvent(new Event('three-addons-ready'));\n";
    echo "}\n";
    echo '</script>' . "\n";
}, 5);
