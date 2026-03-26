<?php

declare(strict_types=1);

/**
 * wp-admin Tools → NSC seeders: run HTTP seed scripts via AJAX and show output inline.
 *
 * Uses wp_remote_get() to the public seeder URLs (follows redirects, e.g. global options → front).
 *
 * Self-signed HTTPS: set in wp-config.php — define('NSC_SEEDER_HTTP_SSL_VERIFY', false);
 * Or use environment local/development, or host .test / .localhost (see ssl_verify_for_seeder_request()).
 * Override: add_filter('nsc_seeder_wp_remote_sslverify', fn ($v, $url) => false, 10, 2);
 *
 * HTTP Basic Auth in front of the site (e.g. .htaccess): wp_remote_get() does not use the browser session.
 * Set in wp-config.php so server-side seeder requests authenticate:
 *   define( 'NSC_SEEDER_HTTP_BASIC_USER', 'nscdev' );
 *   define( 'NSC_SEEDER_HTTP_BASIC_PASSWORD', 'same-as-htpasswd' );
 * Override: add_filter('nsc_seeder_wp_remote_headers', fn ($h, $url) => $h, 10, 2);
 *
 * Token values must stay in sync with create-nsc-*.php in the site root.
 */

namespace NscSoftware\SeedersAdmin;

const CAPABILITY = 'manage_options';
const AJAX_NONCE_ACTION = 'nsc_run_seeder';
const AJAX_NONCE_PREFIX_MIGRATION = 'nsc_migrate_table_prefix';

/** @return array<string, string> slug => absolute URL without query */
function seeder_script_urls(): array
{
    $root = \trailingslashit(\home_url());

    return [
        'options' => $root . 'create-nsc-global-options.php',
        'pages' => $root . 'create-nsc-pages.php',
        'menus' => $root . 'create-nsc-menus.php',
        'blogs' => $root . 'create-nsc-blog-posts.php',
        'career' => $root . 'create-nsc-job-posts.php',
        'case_study' => $root . 'create-nsc-case-study-posts.php',
    ];
}

/** @return array<string, string> slug => token */
function seeder_tokens(): array
{
    return [
        'options' => 'nsc-global-options-2026',
        'pages' => 'nsc-create-pages-2026',
        'menus' => 'nsc-create-menus-2026',
        'blogs' => 'nsc-create-blog-posts-2026',
        'career' => 'nsc-create-job-posts-2026',
        'case_study' => 'nsc-create-case-studies-2026',
    ];
}

/**
 * Slugs allowed for create-nsc-pages.php page_scope (must match create-nsc-pages $pages plus "policies").
 *
 * @return list<string>
 */
function pages_seeder_allowed_scopes(): array
{
    return [
        'home',
        'about',
        'ai',
        'blogs',
        'career',
        'case-studies',
        'contact',
        'technology-apabilities',
        'our-services',
        'privacy-policy',
        'cookies-policy',
        'terms-of-use',
        'policies',
        'master',
        'test',
    ];
}

/**
 * @return array<string, string> scope value => admin label
 */
function pages_seeder_scope_choices(): array
{
    return [
        '' => \__('All pages', 'NscSoftware'),
        'home' => \__('Home', 'NscSoftware'),
        'about' => \__('About', 'NscSoftware'),
        'ai' => \__('AI', 'NscSoftware'),
        'blogs' => \__('Blogs', 'NscSoftware'),
        'career' => \__('Career', 'NscSoftware'),
        'case-studies' => \__('Case studies', 'NscSoftware'),
        'contact' => \__('Contact', 'NscSoftware'),
        'technology-apabilities' => \__('Technology Capabilities', 'NscSoftware'),
        'our-services' => \__('Our Services', 'NscSoftware'),
        'policies' => \__('All policy pages (privacy, cookies, terms)', 'NscSoftware'),
        'privacy-policy' => \__('Privacy Policy', 'NscSoftware'),
        'cookies-policy' => \__('Cookies Policy', 'NscSoftware'),
        'terms-of-use' => \__('Terms of Use', 'NscSoftware'),
        'master' => \__('Master (dev template)', 'NscSoftware'),
        'test' => \__('Test (dev template)', 'NscSoftware'),
    ];
}

/**
 * Polylang passes for “Run all”: default (omit seed_lang) then each non-default locale.
 *
 * @return list<array{slug: string, label: string}>
 */
function get_run_all_language_passes(): array
{
    $passes = [
        [
            'slug' => '',
            'label' => \__('Default language', 'NscSoftware'),
        ],
    ];

    if (\function_exists('pll_the_languages') && \function_exists('pll_default_language')) {
        $def = (string) \pll_default_language('slug');
        $raw = \pll_the_languages([
            'raw' => 1,
            'echo' => 0,
            'hide_if_empty' => 0,
            'hide_if_no_translation' => 0,
        ]);
        if (\is_array($raw)) {
            foreach ($raw as $row) {
                if (!\is_array($row) || empty($row['slug'])) {
                    continue;
                }
                $slug = (string) $row['slug'];
                if ($slug === $def) {
                    continue;
                }
                $name = isset($row['name']) ? (string) $row['name'] : $slug;
                $passes[] = [
                    'slug' => $slug,
                    'label' => $name . ' (' . $slug . ')',
                ];
            }
        }
    }

    return $passes;
}

/**
 * @return list<array{key: string, title: string}>
 */
function get_run_all_seeder_groups(): array
{
    return [
        ['key' => 'options', 'title' => \__('Global options', 'NscSoftware')],
        ['key' => 'pages', 'title' => \__('Pages', 'NscSoftware')],
        ['key' => 'menus', 'title' => \__('Menus', 'NscSoftware')],
        ['key' => 'blogs', 'title' => \__('Blog posts', 'NscSoftware')],
        ['key' => 'career', 'title' => \__('Careers (jobs)', 'NscSoftware')],
        ['key' => 'case_study', 'title' => \__('Case studies', 'NscSoftware')],
    ];
}

/**
 * For each language (default first, then each locale): options → pages → menus → posts → careers → case studies.
 *
 * @param list<array{slug: string, label: string}> $passes
 * @param list<array{key: string, title: string}> $groups
 *
 * @return list<array{seeder: string, seed_lang: string, label: string}>
 */
function build_run_all_sequencer_steps_matrix(array $passes, array $groups): array
{
    $steps = [];
    foreach ($passes as $pass) {
        foreach ($groups as $group) {
            $steps[] = [
                'seeder' => $group['key'],
                'seed_lang' => $pass['slug'],
                'label' => $group['title'] . ' — ' . $pass['label'],
            ];
        }
    }

    return $steps;
}

/**
 * @return list<array{seeder: string, seed_lang: string, label: string}>
 */
function build_run_all_sequencer_steps(): array
{
    return build_run_all_sequencer_steps_matrix(
        get_run_all_language_passes(),
        get_run_all_seeder_groups()
    );
}

/**
 * @param array<string, string> $extraQuery Appended to URL (e.g. page_scope, rebuild).
 * @return string|null Full URL or null if seeder key invalid.
 */
function build_seeder_request_url(string $key, string $seedLangRaw, array $extraQuery = []): ?string
{
    $urls = seeder_script_urls();
    $tokens = seeder_tokens();

    if ($key === '' || !isset($urls[$key], $tokens[$key])) {
        return null;
    }

    $query = [
        'token' => $tokens[$key],
    ];

    $trimLang = \trim($seedLangRaw);
    if ($trimLang !== '') {
        $lang = \sanitize_key($trimLang);
        if ($lang !== '') {
            $query['seed_lang'] = $lang === 'all' ? 'all' : $lang;
        }
    }

    $query = \array_merge($query, $extraQuery);

    return $urls[$key] . '?' . \http_build_query($query);
}

/**
 * Whether to verify SSL for wp_remote_* to seeder URLs (cURL error 60 with self-signed local certs).
 */
function ssl_verify_for_seeder_request(string $url): bool
{
    if (\defined('NSC_SEEDER_HTTP_SSL_VERIFY')) {
        return (bool) \constant('NSC_SEEDER_HTTP_SSL_VERIFY');
    }

    $verify = true;

    if (\function_exists('wp_get_environment_type')) {
        $env = \wp_get_environment_type();
        if (\in_array($env, ['local', 'development'], true)) {
            $verify = false;
        }
    }

    $host = \wp_parse_url($url, \PHP_URL_HOST);
    if (\is_string($host) && $host !== '') {
        $h = \strtolower($host);
        if ($h === 'localhost' || $h === '127.0.0.1' || \str_ends_with($h, '.localhost')) {
            $verify = false;
        }
        foreach (['.test', '.local', '.invalid'] as $suffix) {
            if (\str_ends_with($h, $suffix)) {
                $verify = false;
                break;
            }
        }
    }

    $filtered = \apply_filters('https_local_ssl_verify', $verify, $url);

    return (bool) \apply_filters('nsc_seeder_wp_remote_sslverify', (bool) $filtered, $url);
}

/**
 * Headers for wp_remote_get() to seeder URLs (optional Basic Auth for locked-down hosts).
 *
 * @return array<string, string>
 */
function seeder_http_request_headers(string $url): array
{
    $headers = [
        'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
    ];

    if (\defined('NSC_SEEDER_HTTP_BASIC_USER') && \defined('NSC_SEEDER_HTTP_BASIC_PASSWORD')) {
        $user = (string) \constant('NSC_SEEDER_HTTP_BASIC_USER');
        $pass = (string) \constant('NSC_SEEDER_HTTP_BASIC_PASSWORD');
        if ($user !== '') {
            $headers['Authorization'] = 'Basic ' . \base64_encode($user . ':' . $pass);
        }
    }

    return \apply_filters('nsc_seeder_wp_remote_headers', $headers, $url);
}

add_action('admin_menu', static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        return;
    }

    \add_management_page(
        \__('NSC HTTP seeders', 'NscSoftware'),
        \__('NSC seeders', 'NscSoftware'),
        CAPABILITY,
        'nsc-http-seeders',
        __NAMESPACE__ . '\\render_page'
    );
});

add_action('admin_enqueue_scripts', static function (string $hookSuffix): void {
    if ($hookSuffix !== 'tools_page_nsc-http-seeders' || !\current_user_can(CAPABILITY)) {
        return;
    }

    \wp_register_script('nsc-seeders-admin', false, ['jquery'], false, true);
    \wp_enqueue_script('nsc-seeders-admin');
    \wp_localize_script('nsc-seeders-admin', 'nscSeedersAdmin', [
        'ajaxUrl' => \admin_url('admin-ajax.php'),
        'nonce' => \wp_create_nonce(AJAX_NONCE_ACTION),
        'prefixNonce' => \wp_create_nonce(AJAX_NONCE_PREFIX_MIGRATION),
        'runAllLanguagePassesFull' => get_run_all_language_passes(),
        'runAllSeederGroups' => get_run_all_seeder_groups(),
        'runAllDefaultLangSlug' => \function_exists('pll_default_language') ? (string) \pll_default_language('slug') : '',
        'i18n' => [
            'running' => \__('Running seeder…', 'NscSoftware'),
            'runningAll' => \__('Running all seeders in sequence…', 'NscSoftware'),
            'done' => \__('Seeder finished.', 'NscSoftware'),
            'doneAll' => \__('All seeders finished.', 'NscSoftware'),
            'error' => \__('Seeder failed.', 'NscSoftware'),
            'errorAllStopped' => \__('Run all stopped after an error.', 'NscSoftware'),
            'badResponse' => \__('Unexpected response from the server.', 'NscSoftware'),
            'step' => \__('Step', 'NscSoftware'),
            'of' => \__('of', 'NscSoftware'),
            'runAllTitle' => \__('NSC — Run all seeders (summary)', 'NscSoftware'),
            'stepsCompleted' => \__('steps completed', 'NscSoftware'),
            'runAllEmptyQueue' => \__('Nothing to run for this selection (e.g. no secondary languages when “All others” is chosen).', 'NscSoftware'),
            'prefixWorking' => \__('Migrating table prefix…', 'NscSoftware'),
            'prefixDone' => \__('Prefix migration finished.', 'NscSoftware'),
            'prefixError' => \__('Prefix migration failed.', 'NscSoftware'),
            'prefixInvalid' => \__('Enter a valid prefix (letters, numbers, underscores).', 'NscSoftware'),
            'prefixPreview' => \__('Will use:', 'NscSoftware'),
        ],
    ]);

    $js = <<<'JS'
(function ($) {
  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function notice(type, message) {
    var $c = $("#nsc-seeder-notices").empty();
    var cls = type === "success" ? "notice-success" : "notice-error";
    $("<div />", { class: "notice " + cls })
      .append($("<p />").text(message))
      .appendTo($c);
  }

  function setBusy(on) {
    $(".nsc-run-seeder, #nsc-seed-lang, #nsc-page-scope, #nsc-menu-rebuild, #nsc-run-all-seeders, #nsc-prefix-migrate").prop(
      "disabled",
      !!on
    );
    $("#nsc-seeder-spinner").toggleClass("is-active", !!on);
  }

  function resetRunAllProgress() {
    var $w = $("#nsc-run-all-progress");
    $w.removeClass("nsc-run-all-progress--error").hide().attr("hidden", "hidden").attr("aria-hidden", "true");
    $w.find(".nsc-run-all-progress__meta").text("");
    var $fill = $w.find(".nsc-run-all-progress__fill");
    $fill.css("width", "0%");
    var $track = $w.find(".nsc-run-all-progress__track");
    $track.attr("aria-valuenow", "0");
  }

  function showRunAllProgress() {
    var $w = $("#nsc-run-all-progress");
    $w.removeClass("nsc-run-all-progress--error").removeAttr("hidden").attr("aria-hidden", "false").show();
  }

  /**
   * @param {number} fraction 0..1
   * @param {string} metaText
   */
  function setRunAllProgress(fraction, metaText) {
    var $w = $("#nsc-run-all-progress");
    var pct = Math.max(0, Math.min(100, Math.round(fraction * 100)));
    $w.find(".nsc-run-all-progress__fill").css("width", pct + "%");
    $w.find(".nsc-run-all-progress__track").attr("aria-valuenow", String(pct));
    if (metaText != null && metaText !== "") {
      $w.find(".nsc-run-all-progress__meta").text(metaText);
    }
  }

  function runAllProgressMeta(stepIndexZero, total, label) {
    return (
      nscSeedersAdmin.i18n.step +
      " " +
      (stepIndexZero + 1) +
      " " +
      nscSeedersAdmin.i18n.of +
      " " +
      total +
      " — " +
      label
    );
  }

  /**
   * Run all: "" = default only; "all-others" = each non-default locale only; else one specific slug.
   */
  function resolveRunAllLanguagePasses(langVal) {
    var full = nscSeedersAdmin.runAllLanguagePassesFull || [];
    var defSlug = nscSeedersAdmin.runAllDefaultLangSlug || "";
    if (langVal === "") {
      var defOnly = full.filter(function (p) {
        return p.slug === "";
      });
      return defOnly.length ? defOnly : [{ slug: "", label: "Default language" }];
    }
    if (langVal === "all-others") {
      return full.filter(function (p) {
        return p.slug !== "";
      });
    }
    var wantSlug = langVal === defSlug ? "" : langVal;
    var hit = full.filter(function (p) {
      return p.slug === wantSlug;
    });
    if (hit.length) {
      return hit;
    }
    return [{ slug: wantSlug, label: langVal }];
  }

  function buildRunAllSteps() {
    var langVal = $("#nsc-seed-lang").val() || "";
    var passes = resolveRunAllLanguagePasses(langVal);
    var groups = nscSeedersAdmin.runAllSeederGroups || [];
    var steps = [];
    for (var pi = 0; pi < passes.length; pi++) {
      for (var gi = 0; gi < groups.length; gi++) {
        steps.push({
          seeder: groups[gi].key,
          seed_lang: passes[pi].slug,
          label: groups[gi].title + " — " + passes[pi].label
        });
      }
    }
    return steps;
  }

  function runSeederAjax(payload) {
    return $.ajax({
      url: nscSeedersAdmin.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: $.extend(
        {
          action: "nsc_run_seeder",
          nonce: nscSeedersAdmin.nonce,
          seeder: "",
          seed_lang: "",
          page_scope: "",
          menu_rebuild: "",
          nsc_run_all: ""
        },
        payload
      )
    });
  }

  $(document).on("click", ".nsc-run-seeder", function (e) {
    e.preventDefault();
    var seeder = $(this).data("seeder");
    if (!seeder) return;

    setBusy(true);
    notice("success", nscSeedersAdmin.i18n.running);
    $("#nsc-seeder-frame").attr("srcdoc", "");

    runSeederAjax({
      seeder: seeder,
      seed_lang: (function () {
        var v = $("#nsc-seed-lang").val() || "";
        return v === "all-others" ? "all" : v;
      })(),
      page_scope: $("#nsc-page-scope").val() || "",
      menu_rebuild: $("#nsc-menu-rebuild").is(":checked") ? "1" : ""
    } )
      .done(function (res) {
        if (!res || res.success !== true) {
          var msg =
            res && res.data && res.data.message
              ? res.data.message
              : nscSeedersAdmin.i18n.badResponse;
          notice("error", msg);
          if (res && res.data && res.data.html) {
            $("#nsc-seeder-frame").attr("srcdoc", res.data.html);
          }
          return;
        }
        notice("success", nscSeedersAdmin.i18n.done);
        if (res.data && res.data.html) {
          $("#nsc-seeder-frame").attr("srcdoc", res.data.html);
        }
      })
      .fail(function (xhr) {
        var msg = nscSeedersAdmin.i18n.error;
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        notice("error", msg);
      })
      .always(function () {
        setBusy(false);
      });
  });

  $(document).on("click", "#nsc-run-all-seeders", function (e) {
    e.preventDefault();
    var steps = buildRunAllSteps();
    if (!steps.length) {
      notice("error", nscSeedersAdmin.i18n.runAllEmptyQueue || nscSeedersAdmin.i18n.badResponse);
      return;
    }

    setBusy(true);
    resetRunAllProgress();
    showRunAllProgress();
    setRunAllProgress(0, nscSeedersAdmin.i18n.runningAll);
    notice("success", nscSeedersAdmin.i18n.runningAll);
    var docParts = [
      "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>",
      escapeHtml(nscSeedersAdmin.i18n.runAllTitle),
      "</title><style>body{font-family:Arial,sans-serif;padding:16px 20px;max-width:1200px;margin:0 auto;}section.nsc-seed-step{margin:0 0 2em;padding-bottom:1.5em;border-bottom:1px solid #c3c4c7;}section.nsc-seed-step h2{font-size:1.1em;margin:0 0 .5em;color:#1d2327;}p.nsc-seed-summary{font-size:1.05em;margin-top:1.5em}</style></head><body>",
      "<h1>",
      escapeHtml(nscSeedersAdmin.i18n.runAllTitle),
      "</h1><p>",
      escapeHtml(nscSeedersAdmin.i18n.step),
      " 1 ",
      escapeHtml(nscSeedersAdmin.i18n.of),
      " ",
      steps.length,
      "</p>"
    ];

    var idx = 0;

    function finishSuccess() {
      setRunAllProgress(
        1,
        nscSeedersAdmin.i18n.doneAll +
          " — " +
          steps.length +
          " " +
          nscSeedersAdmin.i18n.stepsCompleted +
          "."
      );
      docParts.push(
        "<p class=\"nsc-seed-summary\"><strong>",
        escapeHtml(nscSeedersAdmin.i18n.doneAll),
        "</strong> — ",
        steps.length,
        " ",
        escapeHtml(nscSeedersAdmin.i18n.stepsCompleted),
        ".</p></body></html>"
      );
      $("#nsc-seeder-frame").attr("srcdoc", docParts.join(""));
      notice("success", nscSeedersAdmin.i18n.doneAll);
      setBusy(false);
    }

    function fail(msg, htmlSnippet) {
      $("#nsc-run-all-progress").addClass("nsc-run-all-progress--error");
      setRunAllProgress(
        steps.length ? idx / steps.length : 0,
        nscSeedersAdmin.i18n.errorAllStopped + " " + msg
      );
      docParts.push("</section>");
      docParts.push(
        "<section class=\"nsc-seed-step\"><h2 style=\"color:#b32d2e\">",
        escapeHtml(nscSeedersAdmin.i18n.error),
        "</h2><p>",
        escapeHtml(msg),
        "</p>",
        htmlSnippet || "",
        "</section>"
      );
      docParts.push("</body></html>");
      $("#nsc-seeder-frame").attr("srcdoc", docParts.join(""));
      notice("error", nscSeedersAdmin.i18n.errorAllStopped + " " + msg);
      setBusy(false);
    }

    function next() {
      if (idx >= steps.length) {
        finishSuccess();
        return;
      }
      var step = steps[idx];
      setRunAllProgress(idx / steps.length, runAllProgressMeta(idx, steps.length, step.label));
      docParts.push(
        "<section class=\"nsc-seed-step\"><h2>",
        escapeHtml(step.label),
        "</h2>"
      );

      runSeederAjax({
        seeder: step.seeder,
        seed_lang: step.seed_lang || "",
        page_scope: "",
        menu_rebuild: step.seeder === "menus" ? "1" : "",
        nsc_run_all: "1"
      })
        .done(function (res) {
          if (!res || res.success !== true) {
            var msg =
              res && res.data && res.data.message
                ? res.data.message
                : nscSeedersAdmin.i18n.badResponse;
            fail(
              msg,
              res && res.data && res.data.html ? "<div>" + res.data.html + "</div>" : ""
            );
            return;
          }
          docParts.push(res.data && res.data.html ? res.data.html : "<p>—</p>");
          docParts.push("</section>");
          idx += 1;
          next();
        })
        .fail(function (xhr) {
          var msg = nscSeedersAdmin.i18n.error;
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            msg = xhr.responseJSON.data.message;
          }
          fail(msg, "");
        });
    }

    next();
  });

  function nscNormalizePrefixInput(raw) {
    var s = String(raw == null ? "" : raw).trim();
    s = s.replace(/[^a-zA-Z0-9_]/g, "");
    if (!s) return "";
    s = s.replace(/_+$/, "") + "_";
    return s.length > 64 ? "" : s;
  }

  function nscUpdatePrefixPreview() {
    var v = nscNormalizePrefixInput($("#nsc-new-prefix").val());
    $("#nsc-prefix-preview").text(v || "—");
  }

  $(document).on("input", "#nsc-new-prefix", nscUpdatePrefixPreview);

  $(document).on("click", "#nsc-prefix-migrate", function (e) {
    e.preventDefault();
    var raw = $("#nsc-new-prefix").val() || "";
    var normalized = nscNormalizePrefixInput(raw);
    if (!normalized) {
      notice("error", nscSeedersAdmin.i18n.prefixInvalid);
      return;
    }
    setBusy(true);
    notice("success", nscSeedersAdmin.i18n.prefixWorking);
    $.ajax({
      url: nscSeedersAdmin.ajaxUrl,
      type: "POST",
      dataType: "json",
      data: {
        action: "nsc_migrate_table_prefix",
        nonce: nscSeedersAdmin.prefixNonce,
        new_prefix: raw
      }
    })
      .done(function (res) {
        if (!res || res.success !== true) {
          var msg =
            res && res.data && res.data.message
              ? res.data.message
              : nscSeedersAdmin.i18n.prefixError;
          notice("error", msg);
          return;
        }
        notice("success", res.data && res.data.message ? res.data.message : nscSeedersAdmin.i18n.prefixDone);
        if (res.data && res.data.redirect) {
          window.setTimeout(function () {
            window.location.href = res.data.redirect;
          }, 800);
        }
      })
      .fail(function (xhr) {
        var msg = nscSeedersAdmin.i18n.prefixError;
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        notice("error", msg);
      })
      .always(function () {
        setBusy(false);
      });
  });

  $(function () {
    if ($("#nsc-new-prefix").length) {
      nscUpdatePrefixPreview();
    }
  });
})(jQuery);
JS;

    \wp_add_inline_script('nsc-seeders-admin', $js);
});

add_action('wp_ajax_nsc_run_seeder', static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to run seeders.', 'NscSoftware')], 403);
    }

    \check_ajax_referer(AJAX_NONCE_ACTION, 'nonce');

    $key = isset($_POST['seeder']) ? \sanitize_key((string) $_POST['seeder']) : '';
    $seedLang = isset($_POST['seed_lang']) ? (string) $_POST['seed_lang'] : '';
    if ($seedLang === 'all-others') {
        $seedLang = 'all';
    }
    $isRunAll = isset($_POST['nsc_run_all']) && (string) $_POST['nsc_run_all'] === '1';

    $extraQuery = [];
    if ($key === 'pages' && !$isRunAll) {
        $scope = isset($_POST['page_scope']) ? \sanitize_key((string) $_POST['page_scope']) : '';
        if ($scope !== '' && $scope !== 'all' && \in_array($scope, pages_seeder_allowed_scopes(), true)) {
            $extraQuery['page_scope'] = $scope;
        }
    }
    if ($key === 'menus' && ($isRunAll || (isset($_POST['menu_rebuild']) && (string) $_POST['menu_rebuild'] === '1'))) {
        $extraQuery['rebuild'] = '1';
    }

    $url = build_seeder_request_url($key, $seedLang, $extraQuery);
    if ($url === null) {
        \wp_send_json_error(['message' => \__('Invalid seeder.', 'NscSoftware')], 400);
    }

    $response = \wp_remote_get(
        $url,
        [
            'timeout' => 300,
            'redirection' => 12,
            'headers' => seeder_http_request_headers($url),
            'sslverify' => ssl_verify_for_seeder_request($url),
        ]
    );

    if (\is_wp_error($response)) {
        \wp_send_json_error([
            'message' => $response->get_error_message(),
        ]);
    }

    $code = (int) \wp_remote_retrieve_response_code($response);
    $body = (string) \wp_remote_retrieve_body($response);

    if ($code < 200 || $code >= 400) {
        \wp_send_json_error([
            'message' => \sprintf(
                /* translators: %d HTTP status code */
                \__('Seeder returned HTTP %d.', 'NscSoftware'),
                $code
            ),
            'html' => $body,
        ]);
    }

    \wp_send_json_success([
        'html' => $body,
        'httpStatus' => $code,
    ]);
});

add_action('wp_ajax_nsc_migrate_table_prefix', static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to run this action.', 'NscSoftware')], 403);
    }

    \check_ajax_referer(AJAX_NONCE_PREFIX_MIGRATION, 'nonce');

    $raw = isset($_POST['new_prefix']) ? \wp_unslash((string) $_POST['new_prefix']) : '';
    $newPrefix = \NscSoftware\WpTablePrefix\normalize_prefix_input($raw);
    if ($newPrefix === '') {
        \wp_send_json_error(['message' => \__('Invalid prefix. Use letters, numbers, and underscores.', 'NscSoftware')], 400);
    }

    $result = \NscSoftware\WpTablePrefix\migrate($newPrefix, \ABSPATH, false);
    if ($result instanceof \WP_Error) {
        \wp_send_json_error(['message' => $result->get_error_message()], 500);
    }

    \wp_clear_auth_cookie();
    if (\function_exists('wp_destroy_current_session')) {
        \wp_destroy_current_session();
    }

    $redirect = \wp_login_url(
        \admin_url('tools.php?page=nsc-http-seeders&tab=prefix')
    );

    \wp_send_json_success([
        'message' => \__(
            'Prefix migration completed. You will be redirected to log in again. After signing in, open Settings → Permalinks and click Save once.',
            'NscSoftware'
        ),
        'redirect' => $redirect,
    ]);
});

function render_page(): void
{
    if (!\current_user_can(CAPABILITY)) {
        return;
    }

    $defaultLangSlug = '';
    $defaultLangSlugNorm = '';
    if (\function_exists('pll_default_language')) {
        $def = \pll_default_language('slug');
        if (\is_string($def) && $def !== '') {
            $defaultLangSlug = $def;
            $defaultLangSlugNorm = \strtolower($def);
        }
    }

    /** slug => display name for admin labels (Polylang order). */
    $langLabelsBySlugNorm = [];
    if (\function_exists('pll_the_languages')) {
        $raw = \pll_the_languages([
            'raw' => 1,
            'echo' => 0,
            'hide_if_empty' => 0,
            'hide_if_no_translation' => 0,
        ]);
        if (\is_array($raw)) {
            foreach ($raw as $row) {
                if (!\is_array($row) || empty($row['slug'])) {
                    continue;
                }
                $slug = (string) $row['slug'];
                $langLabelsBySlugNorm[\strtolower($slug)] = isset($row['name']) ? (string) $row['name'] : $slug;
            }
        }
    }

    /** Non-default languages only (default is covered by “Default only (slug)”). */
    $langsSecondary = [];
    if (\function_exists('pll_languages_list')) {
        $ordered = \pll_languages_list(['fields' => 'slug']);
        if (\is_array($ordered)) {
            foreach ($ordered as $slug) {
                if (!\is_string($slug) || $slug === '') {
                    continue;
                }
                if ($defaultLangSlugNorm !== '' && \strtolower($slug) === $defaultLangSlugNorm) {
                    continue;
                }
                $label = $langLabelsBySlugNorm[\strtolower($slug)] ?? $slug;
                $langsSecondary[$slug] = $label;
            }
        }
    }

    $tabBase = \admin_url('tools.php?page=nsc-http-seeders');
    $activeTab = isset($_GET['tab']) && (string) $_GET['tab'] === 'prefix' ? 'prefix' : 'seeders';
    global $wpdb;
    ?>
    <div class="wrap">
        <h1>
            <?php echo \esc_html(\__('NSC seeders', 'NscSoftware')); ?>
            <span id="nsc-seeder-spinner" class="spinner" style="float:none;margin-top:4px"></span>
        </h1>

        <h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
            <a href="<?php echo \esc_url($tabBase . '&tab=seeders'); ?>" class="nav-tab <?php echo $activeTab === 'seeders' ? 'nav-tab-active' : ''; ?>">
                <?php echo \esc_html(\__('HTTP seeders', 'NscSoftware')); ?>
            </a>
            <a href="<?php echo \esc_url($tabBase . '&tab=prefix'); ?>" class="nav-tab <?php echo $activeTab === 'prefix' ? 'nav-tab-active' : ''; ?>">
                <?php echo \esc_html(\__('WP prefix migration', 'NscSoftware')); ?>
            </a>
        </h2>

        <div id="nsc-seeder-notices" class="nsc-seeder-notices" style="max-width:960px;margin:12px 0;"></div>

        <?php if ($activeTab === 'prefix') { ?>
            <p class="description" style="max-width:720px;">
                <?php echo \esc_html(\__('Back up the database and wp-config.php first. Single-site only; multisite is not supported. The new prefix must end with an underscore (e.g. wp_); if you omit it, one is added automatically.', 'NscSoftware')); ?>
            </p>
            <table class="form-table" role="presentation" style="max-width:720px;">
                <tr>
                    <th scope="row"><?php echo \esc_html(\__('Current table prefix', 'NscSoftware')); ?></th>
                    <td>
                        <input type="text" class="regular-text" readonly value="<?php echo \esc_attr($wpdb->prefix); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nsc-new-prefix"><?php echo \esc_html(\__('New table prefix', 'NscSoftware')); ?></label>
                    </th>
                    <td>
                        <input type="text" class="regular-text" id="nsc-new-prefix" autocomplete="off" />
                        <p class="description">
                            <?php echo \esc_html(\__('Preview:', 'NscSoftware')); ?>
                            <code id="nsc-prefix-preview">—</code>
                        </p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary button-large" id="nsc-prefix-migrate">
                    <?php echo \esc_html(\__('Save changes and migrate', 'NscSoftware')); ?>
                </button>
            </p>
        <?php } else { ?>

        <p class="description">
            <?php echo \esc_html(\__('Individual buttons: this list controls seed_lang. “Run all seeders” uses the same list to choose which language passes run — see Run all below.', 'NscSoftware')); ?>
        </p>

        <table class="form-table" role="presentation" style="max-width:720px;">
            <tr>
                <th scope="row">
                    <label for="nsc-seed-lang"><?php echo \esc_html(\__('Translation language', 'NscSoftware')); ?></label>
                </th>
                <td>
                    <select id="nsc-seed-lang">
                        <?php if ($langsSecondary !== []) { ?>
                            <option value="all-others"><?php echo \esc_html(\__('All others (non-default languages)', 'NscSoftware')); ?></option>
                        <?php } ?>
                        <option value="">
                            <?php
                            if ($defaultLangSlug !== '') {
                                echo \esc_html(\sprintf(
                                    /* translators: %s: Polylang default language slug, e.g. en */
                                    \__('Default only (%s)', 'NscSoftware'),
                                    $defaultLangSlug
                                ));
                            } else {
                                echo \esc_html(\__('Default only (no translation sync)', 'NscSoftware'));
                            }
                            ?>
                        </option>
                        <?php foreach ($langsSecondary as $slug => $name) { ?>
                            <option value="<?php echo \esc_attr($slug); ?>"><?php echo \esc_html($name . ' (' . $slug . ')'); ?></option>
                        <?php } ?>
                    </select>
                    <?php if ($defaultLangSlug === '' && $langsSecondary === []) { ?>
                        <p class="description"><?php echo \esc_html(\__('Polylang not active or no languages — only “Default only” applies.', 'NscSoftware')); ?></p>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nsc-page-scope"><?php echo \esc_html(\__('Pages seeder: update only', 'NscSoftware')); ?></label>
                </th>
                <td>
                    <select id="nsc-page-scope">
                        <?php foreach (pages_seeder_scope_choices() as $val => $label) { ?>
                            <option value="<?php echo \esc_attr($val); ?>"><?php echo \esc_html($label); ?></option>
                        <?php } ?>
                    </select>
                    <p class="description">
                        <?php echo \esc_html(\__('Applies only when you click “Pages”. Choose “All pages” to run the full page list. Matches create-nsc-pages.php page_scope. To refresh only legal pages (and their Polylang copies when Translation language is not default), choose All policy pages or a single policy slug — body HTML comes from policy-content/*.html in the site root.', 'NscSoftware')); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="nsc-menu-rebuild"><?php echo \esc_html(\__('Menus seeder', 'NscSoftware')); ?></label>
                </th>
                <td>
                    <label><input type="checkbox" id="nsc-menu-rebuild" value="1" /> <?php echo \esc_html(\__('Rebuild menus (delete all items in main + sitemap menus before seeding)', 'NscSoftware')); ?></label>
                    <p class="description">
                        <?php echo \esc_html(\__('If unchecked, only items from a previous NSC seed are replaced. Applies when you click “Menus” only. “Run all seeders” always passes rebuild in the request (this checkbox is ignored).', 'NscSoftware')); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2 class="title" style="margin-top:1.5em;"><?php echo \esc_html(\__('Run all (queue)', 'NscSoftware')); ?></h2>
        <p class="description" style="max-width:960px;">
            <?php echo \esc_html(\__('Ignores the pages/menus form fields. Per pass, order is: global options → pages (full list) → menus (rebuild on) → blog posts → careers → case studies. Translation language: “Default only (slug)” — one pass, default locale only (6 steps). “All others” — one pass per secondary Polylang language (6 steps × count). A single language — that locale only (6 steps). No Google API key: placeholders are (lang) in lowercase; legacy [LANG] is stripped before re-prefixing.', 'NscSoftware')); ?>
        </p>
        <p>
            <button type="button" class="button button-primary button-large" id="nsc-run-all-seeders" style="margin:4px 8px 12px 0;">
                <?php echo \esc_html(\__('Run all seeders', 'NscSoftware')); ?>
            </button>
        </p>
        <div id="nsc-run-all-progress" class="nsc-run-all-progress" style="display:none;max-width:720px;margin:0 0 20px;" hidden aria-hidden="true">
            <div class="nsc-run-all-progress__meta" style="margin:0 0 8px;font-size:13px;line-height:1.4;color:#50575e;"></div>
            <div
                class="nsc-run-all-progress__track"
                style="height:14px;background:#dcdcde;border-radius:6px;overflow:hidden;box-shadow:inset 0 1px 2px rgba(0,0,0,.07);"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
            >
                <div
                    class="nsc-run-all-progress__fill"
                    style="height:100%;width:0%;background:#2271b1;border-radius:6px;transition:width .28s ease;"
                ></div>
            </div>
        </div>
        <style>
            .nsc-run-all-progress.nsc-run-all-progress--error .nsc-run-all-progress__fill {
                background: #b32d2e;
            }
        </style>

        <h2 class="title" style="margin-top:1.5em;"><?php echo \esc_html(\__('Run script', 'NscSoftware')); ?></h2>
        <p>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="options" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Global options', 'NscSoftware')); ?>
            </button>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="pages" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Pages', 'NscSoftware')); ?>
            </button>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="menus" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Menus', 'NscSoftware')); ?>
            </button>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="blogs" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Blog posts', 'NscSoftware')); ?>
            </button>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="career" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Careers (jobs)', 'NscSoftware')); ?>
            </button>
            <button type="button" class="button button-primary nsc-run-seeder" data-seeder="case_study" style="margin:4px 8px 4px 0;">
                <?php echo \esc_html(\__('Case studies', 'NscSoftware')); ?>
            </button>
        </p>

        <h2 class="title" style="margin-top:1.5em;"><?php echo \esc_html(\__('Output', 'NscSoftware')); ?></h2>
        <iframe
            id="nsc-seeder-frame"
            title="<?php echo \esc_attr(\__('Seeder output', 'NscSoftware')); ?>"
            style="width:100%;max-width:1200px;min-height:420px;border:1px solid #c3c4c7;background:#fff;"
        ></iframe>
        <?php } ?>
    </div>
    <?php
}
