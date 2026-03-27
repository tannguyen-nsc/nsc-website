<?php

declare(strict_types=1);

/**
 * Tools → NSC WPScan: run site-root run-nsc-wpscan.php via HTTP, cache HTML, rescan with progress UI.
 *
 * Token must match run-nsc-wpscan.php ($requiredToken).
 */

namespace NscSoftware\WpscanAdmin;

const CAPABILITY = 'manage_options';
const OPTION_KEY = 'nsc_wpscan_last_result';
const AJAX_ACTION = 'nsc_wpscan_run';
const NONCE_ACTION = 'nsc_wpscan_run';

/**
 * Must stay in sync with run-nsc-wpscan.php ($requiredToken).
 */
function wpscan_token(): string
{
    return 'nsc-wpscan-2026';
}

function build_wpscan_request_url(): string
{
    $base = \home_url('/run-nsc-wpscan.php');

    return $base . '?' . \http_build_query([
        'token' => wpscan_token(),
        'format' => 'json',
    ]);
}

/**
 * @return array{html: string, http_code: int, completed_at: int, target_url: string}
 */
function get_default_last_result(): array
{
    return [
        'html' => '',
        'http_code' => 0,
        'completed_at' => 0,
        'target_url' => '',
    ];
}

/**
 * @return array{html: string, http_code: int, completed_at: int, target_url: string}
 */
function get_last_result(): array
{
    $raw = \get_option(OPTION_KEY, null);
    if (!\is_array($raw)) {
        return get_default_last_result();
    }

    return [
        'html' => isset($raw['html']) && \is_string($raw['html']) ? $raw['html'] : '',
        'http_code' => isset($raw['http_code']) && \is_numeric($raw['http_code']) ? (int) $raw['http_code'] : 0,
        'completed_at' => isset($raw['completed_at']) && \is_numeric($raw['completed_at']) ? (int) $raw['completed_at'] : 0,
        'target_url' => isset($raw['target_url']) && \is_string($raw['target_url']) ? $raw['target_url'] : '',
    ];
}

add_action('admin_menu', static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        return;
    }

    \add_management_page(
        \__('NSC WPScan Tool', 'NscSoftware'),
        \__('NSC WPScan', 'NscSoftware'),
        CAPABILITY,
        'nsc-wpscan-tool',
        __NAMESPACE__ . '\\render_page',
        101
    );
});

add_action('admin_enqueue_scripts', static function (string $hookSuffix): void {
    if ($hookSuffix !== 'tools_page_nsc-wpscan-tool' || !\current_user_can(CAPABILITY)) {
        return;
    }

    \wp_register_script('nsc-wpscan-admin', false, ['jquery'], false, true);
    \wp_enqueue_script('nsc-wpscan-admin');
    \wp_localize_script('nsc-wpscan-admin', 'nscWpscanAdmin', [
        'ajaxUrl' => \admin_url('admin-ajax.php'),
        'nonce' => \wp_create_nonce(NONCE_ACTION),
        'i18n' => [
            'scanning' => \__('WPScan is running…', 'NscSoftware'),
            'scanningHint' => \__('This can take several minutes. Keep this tab open.', 'NscSoftware'),
            'done' => \__('Scan finished.', 'NscSoftware'),
            'error' => \__('Scan failed.', 'NscSoftware'),
            'badResponse' => \__('Unexpected response from the server.', 'NscSoftware'),
            'noOutput' => \__('No output yet. Click Rescan to run WPScan.', 'NscSoftware'),
        ],
    ]);

    $js = <<<'JS'
(function ($) {
  function notice(type, message) {
    var $c = $("#nsc-wpscan-notices").empty();
    var cls = type === "success" ? "notice-success" : "notice-error";
    $("<div />", { class: "notice " + cls + " is-dismissible" })
      .append($("<p />").text(message))
      .appendTo($c);
  }

  function setBusy(on) {
    $("#nsc-wpscan-rescan").prop("disabled", !!on);
    $("#nsc-wpscan-spinner").toggleClass("is-active", !!on);
  }

  function showProgress(on) {
    var $w = $("#nsc-wpscan-progress");
    $w.find(".nsc-wpscan-progress__track").attr("aria-busy", on ? "true" : "false");
    if (on) {
      $w.removeAttr("hidden").attr("aria-hidden", "false").show();
    } else {
      $w.attr("hidden", "hidden").attr("aria-hidden", "true").hide();
    }
  }

  function setFrameHtml(html) {
    var $f = $("#nsc-wpscan-frame");
    if (html == null || html === "") {
      $f.attr("srcdoc", "");
      return;
    }
    $f.attr("srcdoc", html);
  }

  $(document).on("click", "#nsc-wpscan-rescan", function (e) {
    e.preventDefault();
    setBusy(true);
    notice("success", nscWpscanAdmin.i18n.scanning);
    setFrameHtml("");
    $("#nsc-wpscan-empty-hint").remove();
    $("#nsc-wpscan-status-last").text("—");
    showProgress(true);
    $("#nsc-wpscan-progress-meta").text(
      nscWpscanAdmin.i18n.scanning + " " + nscWpscanAdmin.i18n.scanningHint
    );

    $.ajax({
      url: nscWpscanAdmin.ajaxUrl,
      type: "POST",
      dataType: "json",
      timeout: 0,
      data: {
        action: "nsc_wpscan_run",
        nonce: nscWpscanAdmin.nonce
      }
    })
      .done(function (res) {
        if (!res || res.success !== true) {
          var msg =
            res && res.data && res.data.message
              ? res.data.message
              : nscWpscanAdmin.i18n.badResponse;
          notice("error", msg);
          if (res && res.data && res.data.html) {
            setFrameHtml(res.data.html);
          }
          if (res && res.data && res.data.statusLine) {
            $("#nsc-wpscan-status-last").text(res.data.statusLine);
          }
          return;
        }
        notice("success", nscWpscanAdmin.i18n.done);
        if (res.data && res.data.html) {
          setFrameHtml(res.data.html);
        }
        if (res.data && res.data.statusLine) {
          $("#nsc-wpscan-status-last").text(res.data.statusLine);
        }
      })
      .fail(function (xhr) {
        var msg = nscWpscanAdmin.i18n.error;
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        notice("error", msg);
      })
      .always(function () {
        showProgress(false);
        setBusy(false);
      });
  });
})(jQuery);
JS;

    \wp_add_inline_script('nsc-wpscan-admin', $js);
});

add_action('wp_ajax_' . AJAX_ACTION, static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to run WPScan.', 'NscSoftware')], 403);
    }

    \check_ajax_referer(NONCE_ACTION, 'nonce');

    $url = build_wpscan_request_url();
    $headers = \NscSoftware\SeedersAdmin\seeder_http_request_headers($url);
    $sslVerify = \NscSoftware\SeedersAdmin\ssl_verify_for_seeder_request($url);

    $response = \wp_remote_get(
        $url,
        [
            'timeout' => 600,
            'redirection' => 12,
            'headers' => $headers,
            'sslverify' => $sslVerify,
        ]
    );

    if (\is_wp_error($response)) {
        \wp_send_json_error([
            'message' => $response->get_error_message(),
        ]);
    }

    $code = (int) \wp_remote_retrieve_response_code($response);
    $body = (string) \wp_remote_retrieve_body($response);

    $target = \home_url('/');
    if ($code < 200 || $code >= 400) {
        \update_option(
            OPTION_KEY,
            [
                'html' => $body,
                'http_code' => $code,
                'completed_at' => \time(),
                'target_url' => $target,
            ],
            false
        );

        $statusLine = format_status_line($code, \time(), $target);

        \wp_send_json_error([
            'message' => \sprintf(
                /* translators: %d HTTP status */
                \__('WPScan request returned HTTP %d.', 'NscSoftware'),
                $code
            ),
            'html' => $body,
            'statusLine' => $statusLine,
        ]);
    }

    \update_option(
        OPTION_KEY,
        [
            'html' => $body,
            'http_code' => $code,
            'completed_at' => \time(),
            'target_url' => $target,
        ],
        false
    );

    $statusLine = format_status_line($code, \time(), $target);

    \wp_send_json_success([
        'html' => $body,
        'httpStatus' => $code,
        'statusLine' => $statusLine,
    ]);
});

function format_status_line(int $httpCode, int $completedAt, string $targetUrl): string
{
    $date = $completedAt > 0
        ? \wp_date(\get_option('date_format') . ' ' . \get_option('time_format'), $completedAt)
        : '—';

    return \sprintf(
        /* translators: 1: date/time, 2: HTTP code, 3: scanned URL */
        \__('%1$s · HTTP %2$d · Target %3$s', 'NscSoftware'),
        $date,
        $httpCode,
        $targetUrl
    );
}

function render_page(): void
{
    if (!\current_user_can(CAPABILITY)) {
        return;
    }

    $last = get_last_result();
    $hasHtml = $last['html'] !== '';
    $statusLine = $hasHtml
        ? format_status_line($last['http_code'], $last['completed_at'], $last['target_url'] !== '' ? $last['target_url'] : \home_url('/'))
        : \__('No scan stored yet.', 'NscSoftware');

    $iframeSrcdoc = $hasHtml ? \esc_attr($last['html']) : '';
    ?>
    <div class="wrap">
        <h1>
            <?php echo \esc_html(\__('NSC WPScan Tool', 'NscSoftware')); ?>
            <span id="nsc-wpscan-spinner" class="spinner" style="float:none;margin-top:4px"></span>
        </h1>

        <p class="description">
            <?php echo \esc_html(\__('Runs the same scan as opening run-nsc-wpscan.php with your site token (JSON report, tables, expandable raw JSON). Results are saved here until the next rescan.', 'NscSoftware')); ?>
        </p>

        <div id="nsc-wpscan-notices" class="nsc-wpscan-notices" style="max-width:960px;margin:12px 0;"></div>

        <table class="form-table" role="presentation" style="max-width:720px;">
            <tr>
                <th scope="row"><?php echo \esc_html(\__('WPScan status', 'NscSoftware')); ?></th>
                <td>
                    <p style="margin:0 0 6px;">
                        <strong><?php echo \esc_html(\__('Last result:', 'NscSoftware')); ?></strong>
                        <span id="nsc-wpscan-status-last"><?php echo \esc_html($statusLine); ?></span>
                    </p>
                    <p class="description" style="margin:0;">
                        <?php
                        echo \esc_html(\sprintf(
                            /* translators: %s: home URL scanned by default */
                            \__('Scanner target is your site front URL (%s), matching the standalone script default.', 'NscSoftware'),
                            \home_url('/')
                        ));
                        ?>
                    </p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" class="button button-primary button-large" id="nsc-wpscan-rescan">
                <?php echo \esc_html(\__('Rescan', 'NscSoftware')); ?>
            </button>
        </p>

        <div
            id="nsc-wpscan-progress"
            style="display:none;max-width:720px;margin:0 0 16px;"
            hidden
            aria-hidden="true"
        >
            <div
                id="nsc-wpscan-progress-meta"
                style="margin:0 0 8px;font-size:13px;line-height:1.4;color:#50575e;"
            ></div>
            <div
                class="nsc-wpscan-progress__track"
                style="height:14px;background:#dcdcde;border-radius:6px;overflow:hidden;box-shadow:inset 0 1px 2px rgba(0,0,0,.07);position:relative;"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
                aria-busy="false"
                aria-label="<?php echo \esc_attr(\__('Scan in progress', 'NscSoftware')); ?>"
            >
                <div
                    class="nsc-wpscan-progress__indeterminate"
                    style="position:absolute;top:0;bottom:0;width:38%;left:0;background:#2271b1;border-radius:6px;animation:nscWpscanSlide 1.2s linear infinite;"
                ></div>
            </div>
        </div>
        <style>
            @keyframes nscWpscanSlide {
                0% {
                    transform: translateX(-100%);
                }
                100% {
                    transform: translateX(320%);
                }
            }
        </style>

        <h2 class="title" style="margin-top:1.25em;"><?php echo \esc_html(\__('Output', 'NscSoftware')); ?></h2>
        <?php if (!$hasHtml) { ?>
            <p class="description" id="nsc-wpscan-empty-hint"><?php echo \esc_html(\__('No scan yet. Click Rescan to run WPScan; when it finishes, the full interactive report appears below.', 'NscSoftware')); ?></p>
        <?php } ?>
        <iframe
            id="nsc-wpscan-frame"
            title="<?php echo \esc_attr(\__('WPScan report', 'NscSoftware')); ?>"
            <?php if ($hasHtml) { ?>
                srcdoc="<?php echo $iframeSrcdoc; ?>"
            <?php } ?>
            style="width:100%;max-width:1200px;min-height:480px;border:1px solid #c3c4c7;background:#fff;"
        ></iframe>
    </div>
    <?php
}
