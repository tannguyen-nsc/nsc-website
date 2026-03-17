<?php
declare(strict_types=1);

use Timber\Timber;

if (!isset($buildTemplate) || !is_string($buildTemplate) || $buildTemplate === '') {
    status_header(500);
    wp_die('Missing build template mapping.');
}

$context = Timber::context();
$buildPath = trailingslashit(get_template_directory()) . 'frontend/build/' . ltrim($buildTemplate, '/');

if (!is_readable($buildPath)) {
    status_header(404);
    wp_die('Build template not found: ' . esc_html($buildTemplate));
}

$html = (string) file_get_contents($buildPath);
$buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
$cf7HeadAssets = '';
$pageMap = [
    'index.html' => '',
    'about.html' => 'about',
    'ai.html' => 'ai',
    'blogs.html' => 'blogs',
    'career.html' => 'career',
    'case-studies.html' => 'case-studies',
    'contact.html' => 'contact',
    'our-capabilites.html' => 'our-capabilites',
    'our-services.html' => 'our-services',
    'master.html' => 'master',
    'test.html' => 'test',
];

// Rewrite static asset paths from the exported HTML to theme build assets.
$html = strtr($html, [
    '"./css/' => '"' . $buildUri . '/css/',
    "'./css/" => "'" . $buildUri . '/css/',
    '"./js/' => '"' . $buildUri . '/js/',
    "'./js/" => "'" . $buildUri . '/js/',
    '"./img/' => '"' . $buildUri . '/img/',
    "'./img/" => "'" . $buildUri . '/img/',
    '"./fonts/' => '"' . $buildUri . '/fonts/',
    "'./fonts/" => "'" . $buildUri . '/fonts/',
    '"./video/' => '"' . $buildUri . '/video/',
    "'./video/" => "'" . $buildUri . '/video/',
]);

// Rewrite internal page anchors from static .html to WordPress page URLs.
$html = preg_replace_callback(
    '/\bhref=(["\'])([^"\']+)\1/i',
    static function (array $matches) use ($pageMap): string {
        $quote = $matches[1];
        $href = $matches[2];

        // Skip absolute, protocol-relative, in-page, and special links.
        if (
            preg_match('/^(?:[a-z][a-z0-9+\-.]*:)?\/\//i', $href) ||
            str_starts_with($href, '#') ||
            str_starts_with($href, 'mailto:') ||
            str_starts_with($href, 'tel:')
        ) {
            return $matches[0];
        }

        // Normalize and keep query/hash suffix.
        $parts = parse_url($href);
        if ($parts === false) {
            return $matches[0];
        }
        $path = isset($parts['path']) ? ltrim($parts['path'], './') : '';
        $path = $path === '' ? 'index.html' : $path;

        if (!isset($pageMap[$path])) {
            return $matches[0];
        }

        $slug = $pageMap[$path];
        $url = $slug === '' ? home_url('/') : home_url('/' . $slug . '/');

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#' . $parts['fragment'];
        }

        return 'href=' . $quote . esc_url($url) . $quote;
    },
    $html
);

// Replace static HTML forms with Contact Form 7 embed (if configured).
$cf7FormId = (int) get_option('nsc_cf7_primary_form_id', 0);
if ($cf7FormId > 0) {
    if (!did_action('wp_enqueue_scripts')) {
        do_action('wp_enqueue_scripts');
    }

    if (function_exists('wpcf7_enqueue_styles')) {
        wpcf7_enqueue_styles();
    }
    if (function_exists('wpcf7_enqueue_scripts')) {
        wpcf7_enqueue_scripts();
    }

    ob_start();
    if (function_exists('wp_print_styles')) {
        wp_print_styles(['contact-form-7']);
    }
    if (function_exists('wp_print_scripts')) {
        wp_print_scripts(['contact-form-7']);
    }
    $cf7HeadAssets = (string) ob_get_clean();

    $cf7Shortcode = sprintf('[contact-form-7 id="%d"]', $cf7FormId);
    $cf7Rendered = do_shortcode($cf7Shortcode);
    if (is_string($cf7Rendered) && $cf7Rendered !== '') {
        $cf7Rendered = preg_replace('/<p>\s*(<input[^>]*wpcf7-submit[^>]*>)\s*<\/p>/i', '$1', $cf7Rendered);
        $html = preg_replace('/<form\b[\s\S]*?<\/form>/i', $cf7Rendered, $html);
    }
}

// Normalize Contact Form 7 wrapper spacing to match static HTML styling.
$cf7NormalizeCss = <<<'CSS'
<style id="nsc-cf7-normalize">
  .wpcf7 {
    width: 100%;
  }

  .wpcf7 form {
    margin: 0;
  }

  .wpcf7 form p {
    margin: 0;
  }

  .wpcf7 .wpcf7-form-control-wrap {
    display: block;
  }

  .wpcf7 .wpcf7-not-valid-tip {
    margin-top: 6px;
    font-size: 12px;
  }

  .wpcf7 .wpcf7-response-output {
    margin: 12px 0 0 !important;
  }
</style>
CSS;

if (stripos($html, '</head>') !== false) {
    $html = preg_replace('/<\/head>/i', $cf7NormalizeCss . "\n</head>", $html, 1);
} else {
    $html = $cf7NormalizeCss . $html;
}

if ($cf7HeadAssets !== '') {
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $cf7HeadAssets . "\n</head>", $html, 1);
    } else {
        $html = $cf7HeadAssets . $html;
    }
}

// Hide selected menu items temporarily without removing markup.
$menuHideCss = <<<'CSS'
<style id="nsc-menu-temp-hide">
  /* Hide menu anchors directly (desktop + mobile). */
  .desktop-header .nav a[href*="blogs.html"],
  .desktop-header .nav a[href*="career.html"],
  .desktop-header .nav a[href*="case-studies.html"],
  .desktop-header .nav a[href*="/blogs/"],
  .desktop-header .nav a[href*="/career/"],
  .desktop-header .nav a[href*="/case-studies/"],
  .desktop-header .nav a[href="#language"],
  .desktop-header .nav a[href="#en"],
  .desktop-header .nav a[href="#de"],
  .desktop-header .nav a[href="#ja"],
  .desktop-header .nav a[href="#vi"],
  .mobile-nav-list a[href*="blogs.html"],
  .mobile-nav-list a[href*="career.html"],
  .mobile-nav-list a[href*="case-studies.html"],
  .mobile-nav-list a[href*="/blogs/"],
  .mobile-nav-list a[href*="/career/"],
  .mobile-nav-list a[href*="/case-studies/"],
  .mobile-nav-list a[href="#language"],
  .mobile-nav-list a[href="#en"],
  .mobile-nav-list a[href="#de"],
  .mobile-nav-list a[href="#ja"],
  .mobile-nav-list a[href="#vi"] {
    display: none !important;
  }

  /* Prefer hiding parent menu item where :has is supported */
  li:has(> a[href="blogs.html"]),
  li:has(> a[href="career.html"]),
  li:has(> a[href="case-studies.html"]),
  li:has(> a[href*="/blogs/"]),
  li:has(> a[href*="/career/"]),
  li:has(> a[href*="/case-studies/"]),
  li:has(> a[href="#language"]),
  li:has(> a[href="#en"]),
  li:has(> a[href="#de"]),
  li:has(> a[href="#ja"]),
  li:has(> a[href="#vi"]) {
    display: none !important;
  }

  /* Temporary spacing tweak for reduced top menu items */
  .desktop-header .wrapper nav .nav {
    display: flex !important;
    justify-content: flex-start !important;
    align-items: center !important;
    gap: 28px !important;
    width: auto !important;
  }

  .desktop-header .wrapper nav .nav > li {
    margin: 0 !important;
  }

  .desktop-header .wrapper nav .nav > li > a {
    padding-left: 14px !important;
    padding-right: 14px !important;
  }

  .desktop-header .wrapper nav .nav > li > a.contact-btn {
    margin-left: 10px !important;
  }

  .mobile-nav .mobile-nav-link {
    padding-top: 10px !important;
    padding-bottom: 10px !important;
  }
</style>
CSS;

$menuHideScript = <<<'JS'
<script id="nsc-menu-temp-hide-script">
  document.addEventListener('DOMContentLoaded', function () {
    var shouldHide = function (href) {
      if (!href) return false;
      return /(?:blogs\.html|career\.html|case-studies\.html|\/blogs\/|\/career\/|\/case-studies\/|#language|#en|#de|#ja|#vi)$/i.test(href);
    };

    document.querySelectorAll('.desktop-header .nav a, .mobile-nav-list a').forEach(function (a) {
      var href = a.getAttribute('href') || '';
      if (!shouldHide(href)) return;
      var li = a.closest('li');
      if (li) {
        li.style.display = 'none';
      } else {
        a.style.display = 'none';
      }
    });
  });
</script>
JS;

if (stripos($html, '</head>') !== false) {
    $html = preg_replace('/<\/head>/i', $menuHideCss . "\n" . $menuHideScript . "\n</head>", $html, 1);
} else {
    $html = $menuHideCss . "\n" . $menuHideScript . $html;
}

$context['static_html'] = $html;
Timber::render('templates/page-static-build.twig', $context);
