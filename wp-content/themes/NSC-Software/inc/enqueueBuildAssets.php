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

function current_page_meta_contains_layout(string $layoutName): bool
{
    if ($layoutName === '') {
        return false;
    }

    $postId = (int) get_queried_object_id();
    if ($postId <= 0) {
        return false;
    }

    $allMeta = get_post_meta($postId);
    if (!is_array($allMeta)) {
        return false;
    }

    foreach ($allMeta as $values) {
        foreach ((array) $values as $value) {
            if (is_string($value) && strpos($value, $layoutName) !== false) {
                return true;
            }
        }
    }

    return false;
}

function should_enqueue_slick_assets(): bool
{
    return is_front_page()
        || current_page_meta_contains_layout('nscBlockTestimonials')
        || current_page_meta_contains_layout('nscBlockWhyNsc')
        || is_page('why-nsc');
}

function should_enqueue_vue_runtime(): bool
{
    if (function_exists('NscSoftware\\Components\\NSCBlockBlogsArchive\\current_page_includes_blogs_archive_block')
        && \NscSoftware\Components\NSCBlockBlogsArchive\current_page_includes_blogs_archive_block()) {
        return true;
    }

    if (is_page(['blogs', 'blog', 'case-studies', 'careers', 'jobs'])) {
        return true;
    }

    return current_page_meta_contains_layout('nscBlockCaseStudiesArchive')
        || current_page_meta_contains_layout('nscBlockJobsArchive');
}

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
        // Improve first render by skipping style/layout for below-the-fold sections.
        wp_add_inline_style(
            'nsc-software-build',
            '.mainContent > *:not(:first-child){content-visibility:auto;contain-intrinsic-size:1px 900px;}'
        );
    }

    $needsSlick = should_enqueue_slick_assets();
    if ($needsSlick) {
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
        wp_script_add_data('nsc-slick', 'defer', true);
    }

    $jsPath = $buildPath . '/js/scripts.js';
    if (file_exists($jsPath)) {
        $buildScriptDeps = ['jquery'];
        if ($needsSlick) {
            $buildScriptDeps[] = 'nsc-slick';
        }
        if (should_enqueue_vue_runtime()) {
            wp_enqueue_script(
                'nsc-vue3',
                'https://unpkg.com/vue@3/dist/vue.global.prod.js',
                [],
                '3',
                true
            );
            wp_script_add_data('nsc-vue3', 'defer', true);
            $buildScriptDeps[] = 'nsc-vue3';
        }
        wp_enqueue_script(
            'nsc-software-build',
            $buildUri . '/js/scripts.js',
            $buildScriptDeps,
            filemtime($jsPath),
            true
        );
        wp_script_add_data('nsc-software-build', 'defer', true);
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
                    $slider.slick({ slidesToShow: slidesShow, slidesToScroll: 1, autoplay: true, autoplaySpeed: 5000, speed: 800, arrows: false, dots: true, infinite: true, pauseOnHover: true, swipe: true, draggable: true, cssEase: "ease-in-out", prevArrow: "<button type=\"button\" class=\"slick-prev\" aria-label=\"Previous\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M15.707 4.29289C16.0975 4.68342 16.0975 5.31643 15.707 5.70696L10.4141 10.9999H22C22.5522 10.9999 23 11.4476 23 11.9999C23 12.5522 22.5522 12.9999 22 12.9999H10.4141L15.707 18.2929C16.0975 18.6834 16.0975 19.3164 15.707 19.707C15.3165 20.0975 14.6834 20.0975 14.2929 19.707L7.29289 12.707C6.90237 12.3164 6.90237 11.6834 7.29289 11.293L14.2929 4.29289C14.6834 3.90237 15.3165 3.90237 15.707 4.29289Z\" fill=\"currentColor\"/></svg></button>", nextArrow: "<button type=\"button\" class=\"slick-next\" aria-label=\"Next\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M8.293 4.29289C7.90237 4.68342 7.90237 5.31643 8.293 5.70696L13.5859 10.9999H2C1.44772 10.9999 1 11.4476 1 11.9999C1 12.5522 1.44772 12.9999 2 12.9999H13.5859L8.293 18.2929C7.90237 18.6834 7.90237 19.3164 8.293 19.707C8.68342 20.0975 9.31643 20.0975 9.70696 19.707L16.707 12.707C17.0976 12.3164 17.0976 11.6834 16.707 11.293L9.70696 4.29289C9.31643 3.90237 8.68342 3.90237 8.293 4.29289Z\" fill=\"currentColor\"/></svg></button>" });
                }
            });',
            ['position' => 'after']
        );
    }

    // Lazy-load heavy globe stack after first paint while preserving script order.
    $lazyStack = [
        'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js?ver=0.160.0',
        'https://cdn.jsdelivr.net/npm/three-globe@2.45.0/dist/three-globe.min.js?ver=2.45.0',
        'https://cdn.jsdelivr.net/npm/@tweenjs/tween.js@23/dist/tween.umd.js?ver=23',
    ];
    $fatLinesPath = $buildPath . '/js/external/three-fat-lines.js';
    if (file_exists($fatLinesPath)) {
        $lazyStack[] = $buildUri . '/js/external/three-fat-lines.js?ver=' . filemtime($fatLinesPath);
    }
    $globePath = $buildPath . '/js/external/globe.js';
    if (file_exists($globePath)) {
        $lazyStack[] = $buildUri . '/js/external/globe.js?ver=' . filemtime($globePath);
    }
    $wavePath = $buildPath . '/js/external/wave.js';
    if (file_exists($wavePath)) {
        $lazyStack[] = $buildUri . '/js/external/wave.js?ver=' . filemtime($wavePath);
    }

    add_action('wp_footer', function () use ($lazyStack) {
        if (empty($lazyStack)) {
            return;
        }

        echo '<script id="nsc-lazy-globe-stack">(function(){'
            . 'var urls=' . wp_json_encode(array_values($lazyStack)) . ';'
            . 'if(!Array.isArray(urls)||!urls.length){return;}'
            . 'function loadSeq(i){if(i>=urls.length){return;}'
            . 'var s=document.createElement("script");s.src=urls[i];s.async=false;s.defer=true;'
            . 's.onload=function(){loadSeq(i+1);};'
            . 's.onerror=function(){loadSeq(i+1);};'
            . 'document.body.appendChild(s);}'
            . 'function boot(){'
            . 'if(window.requestIdleCallback){window.requestIdleCallback(function(){loadSeq(0);},{timeout:1500});}'
            . 'else{setTimeout(function(){loadSeq(0);},200);}'
            . '}'
            . 'if(document.readyState==="complete"){boot();}'
            . 'else{window.addEventListener("load",boot,{once:true});}'
            . '})();</script>' . "\n";
    }, 20);
}, 20);

function should_defer_main_stylesheet_for_mobile_home(): bool
{
    return wp_is_mobile() && is_front_page();
}

// On mobile home, load the main stylesheet non-blocking to improve paint metrics.
add_filter('style_loader_tag', function (string $html, string $handle, string $href, string $media): string {
    if ($handle !== 'nsc-software-build' || !should_defer_main_stylesheet_for_mobile_home()) {
        return $html;
    }
    $safeHref = esc_url($href);
    $safeMedia = $media !== '' ? esc_attr($media) : 'all';

    return '<link rel="preload" as="style" href="' . $safeHref . '" media="' . $safeMedia . '" onload="this.onload=null;this.rel=\'stylesheet\'">' .
        '<noscript><link rel="stylesheet" href="' . $safeHref . '" media="' . $safeMedia . '"></noscript>';
}, 10, 4);

// Montserrat (matches frontend/src/css/_global.scss). Disable for Lighthouse experiments:
// add_filter('nsc_load_external_google_fonts', '__return_false');
if ((bool) apply_filters('nsc_load_external_google_fonts', true)) {
    add_action('wp_head', function () {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">' . "\n";
    }, 1);
}

// Minimal above-the-fold CSS for mobile home while deferred full stylesheet is loading.
add_action('wp_head', function () {
    if (!should_defer_main_stylesheet_for_mobile_home()) {
        return;
    }
    echo '<style id="nsc-critical-mobile-home">'
        . 'html,body{margin:0;padding:0}'
        . 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.4}'
        . '.mobile-header{position:fixed;top:0;left:0;right:0;z-index:50;background:#fff}'
        . '.mobile-header-wrapper{display:flex;align-items:center;justify-content:space-between;min-height:56px;padding:0 12px}'
        . '.hero.home{position:relative;padding-top:56px}'
        . '.hero.home img{display:block;width:100%;height:auto}'
        . '.hero.home .headlines{padding:16px 12px}'
        . '.hero.home h1{margin:0 0 12px;font-size:clamp(28px,8vw,44px);line-height:1.1}'
        . '</style>' . "\n";
}, 0);

// Help LCP on home by preloading hero images.
add_action('wp_head', function () use ($buildUri) {
    if (!is_front_page()) {
        return;
    }
    echo '<link rel="preload" as="image" href="' . esc_url($buildUri . '/img/hero-light-mob.webp') . '" media="(max-width: 767px)">' . "\n";
    echo '<link rel="preload" as="image" href="' . esc_url($buildUri . '/img/hero-light.webp') . '" media="(min-width: 768px)">' . "\n";
}, 2);

// Expose build URI for globe.js (e.g. ./img/s.png) and wave.js
add_action('wp_head', function () use ($buildUri) {
    echo '<script id="nsc-globe-build-uri">window.NSC_GLOBE_BUILD_URI='
        . json_encode($buildUri, JSON_UNESCAPED_SLASHES)
        . ";</script>\n";
}, 5);

// Optional: heavy bloom addons for globe (disabled by default for Lighthouse).
// Re-enable via: add_filter('nsc_enable_globe_bloom_addons', '__return_true');
if ((bool) apply_filters('nsc_enable_globe_bloom_addons', false)) {
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
}
