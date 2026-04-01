<?php

declare(strict_types=1);

/**
 * Tools → NSC WPScan: run site-root run-nsc-wpscan.php in-process (no HTTP loopback — avoids 403 from WAF/Cloudflare).
 *
 * Direct URL access still uses ?token= in run-nsc-wpscan.php.
 */

namespace NscSoftware\WpscanAdmin;

const CAPABILITY = 'manage_options';
const OPTION_KEY = 'nsc_wpscan_last_result';
const AJAX_ACTION = 'nsc_wpscan_run';
const NONCE_ACTION = 'nsc_wpscan_run';
const COMPONENTS_SCAN_AJAX_ACTION = 'nsc_components_db_optimize_scan';
const COMPONENTS_CLEANUP_AJAX_ACTION = 'nsc_components_db_optimize_cleanup';
const COMPONENTS_NONCE_ACTION = 'nsc_components_db_optimize';

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
        \__('NSC Scanner', 'NscSoftware'),
        \__('NSC Scanner', 'NscSoftware'),
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
        'componentsNonce' => \wp_create_nonce(COMPONENTS_NONCE_ACTION),
        'restUrl' => \rest_url('nsc/v1/wpscan-scan'),
        'restNonce' => \wp_create_nonce('wp_rest'),
        'i18n' => [
            'scanning' => \__('WPScan is running…', 'NscSoftware'),
            'scanningHint' => \__('This can take several minutes. Keep this tab open.', 'NscSoftware'),
            'done' => \__('Scan finished.', 'NscSoftware'),
            'error' => \__('Scan failed.', 'NscSoftware'),
            'badResponse' => \__('Unexpected response from the server.', 'NscSoftware'),
            'noOutput' => \__('No output yet. Click Rescan to run WPScan.', 'NscSoftware'),
            'compScanning' => \__('Scanning component data in database…', 'NscSoftware'),
            'compScanDone' => \__('Components DB scan finished.', 'NscSoftware'),
            'compCleanupDone' => \__('Cleanup completed.', 'NscSoftware'),
            'compNoSelection' => \__('Please select at least one orphan group.', 'NscSoftware'),
            'compConfirmCleanup' => \__('Delete selected residual component rows from database?', 'NscSoftware'),
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

    function handleScanPayload(payload, fromRest) {
      if (!payload) {
        notice("error", nscWpscanAdmin.i18n.badResponse);
        return;
      }
      if (fromRest) {
        notice("success", nscWpscanAdmin.i18n.done);
        if (payload.html) {
          setFrameHtml(payload.html);
        }
        if (payload.statusLine) {
          $("#nsc-wpscan-status-last").text(payload.statusLine);
        }
        return;
      }
      var res = payload;
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
    }

    function finish() {
      showProgress(false);
      setBusy(false);
    }

    function runAjaxFallback() {
      return $.ajax({
        url: nscWpscanAdmin.ajaxUrl,
        type: "POST",
        dataType: "json",
        timeout: 0,
        data: {
          action: "nsc_wpscan_run",
          nonce: nscWpscanAdmin.nonce
        }
      }).done(function (res) {
        handleScanPayload(res, false);
      });
    }

    function ajaxFailMessage(xhr) {
      var msg = nscWpscanAdmin.i18n.error;
      if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        return xhr.responseJSON.data.message;
      }
      if (xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message;
      }
      return msg;
    }

    function restFailMessage(xhr) {
      var msg = nscWpscanAdmin.i18n.error;
      if (xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message;
      }
      if (xhr.responseJSON && xhr.responseJSON.code) {
        return String(xhr.responseJSON.code);
      }
      return msg;
    }

    var restUrl = nscWpscanAdmin.restUrl || "";
    var restNonce = nscWpscanAdmin.restNonce || "";

    if (restUrl && restNonce) {
      $.ajax({
        url: restUrl,
        type: "POST",
        dataType: "json",
        timeout: 0,
        contentType: "application/json",
        data: JSON.stringify({}),
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", restNonce);
        }
      })
        .done(function (res) {
          handleScanPayload(res, true);
          finish();
        })
        .fail(function (xhr) {
          if (xhr.status === 403 || xhr.status === 404 || xhr.status === 0) {
            runAjaxFallback()
              .fail(function (xhr2) {
                notice("error", ajaxFailMessage(xhr2));
              })
              .always(finish);
            return;
          }
          notice("error", restFailMessage(xhr));
          finish();
        });
    } else {
      runAjaxFallback()
        .fail(function (xhr) {
          notice("error", ajaxFailMessage(xhr));
        })
        .always(finish);
    }
  });

  $(document).on("click", ".nsc-scanner-tab", function (e) {
    e.preventDefault();
    var tab = $(this).data("tab");
    $(".nsc-scanner-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");
    $(".nsc-scanner-panel").hide().attr("aria-hidden", "true");
    $("#nsc-scanner-panel-" + tab).show().attr("aria-hidden", "false");
  });

  function esc(x) {
    return String(x || "").replace(/[&<>\\"\\']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c];
    });
  }

  function compReq(action, payload) {
    var fd = new FormData();
    fd.append("action", action);
    fd.append("nonce", nscWpscanAdmin.componentsNonce);
    for (var k in payload) {
      if (!Object.prototype.hasOwnProperty.call(payload, k)) continue;
      var v = payload[k];
      if (Array.isArray(v)) {
        v.forEach(function (one) { fd.append(k + "[]", one); });
      } else {
        fd.append(k, v);
      }
    }
    return $.ajax({
      url: nscWpscanAdmin.ajaxUrl,
      type: "POST",
      data: fd,
      processData: false,
      contentType: false,
      dataType: "json",
      timeout: 0
    });
  }

  function renderCompRows(rows) {
    var $results = $("#nsc-components-opt-results");
    if (!rows || !rows.length) {
      $results.html("<p>No residual component rows found.</p>");
      return;
    }
    var h = "<div style='margin:8px 0;'><button type='button' class='button button-link-delete' id='nsc-components-cleanup-selected'>Cleanup selected</button></div>";
    h += "<table class='widefat striped'><thead><tr><th><input type='checkbox' id='nsc-components-select-all' /></th><th>Post</th><th>Field</th><th>Row Index</th><th>Reason</th><th>Residual Keys</th></tr></thead><tbody>";
    rows.forEach(function (r) {
      var postTitle = esc(r.post_title || "(no title)");
      var edit = esc(r.edit_link || "#");
      var reason = esc(r.reason || "");
      h += "<tr data-group='" + esc(r.group_key || "") + "'>";
      h += "<td><input class='nsc-components-group' type='checkbox' value='" + esc(r.group_key || "") + "' /></td>";
      h += "<td><a href='" + edit + "'>" + postTitle + "</a> <code>(" + esc(r.post_type || "post") + ")</code></td>";
      h += "<td><code>" + esc(r.field_root || "") + "</code></td>";
      h += "<td>" + esc(r.row_index || 0) + "</td>";
      h += "<td>" + reason + "</td>";
      h += "<td>" + esc(r.meta_count || 0) + "</td>";
      h += "</tr>";
    });
    h += "</tbody></table>";
    $results.html(h);
  }

  function selectedCompGroups() {
    var out = [];
    $("#nsc-components-opt-results input.nsc-components-group:checked").each(function () {
      out.push($(this).val());
    });
    return out;
  }

  $(document).on("click", "#nsc-components-select-all", function () {
    var on = $(this).is(":checked");
    $("#nsc-components-opt-results input.nsc-components-group").prop("checked", on);
  });

  $(document).on("click", "#nsc-components-scan-run", function (e) {
    e.preventDefault();
    var $status = $("#nsc-components-opt-status");
    var $results = $("#nsc-components-opt-results");
    $status.text(nscWpscanAdmin.i18n.compScanning);
    $results.empty();
    compReq("nsc_components_db_optimize_scan", {})
      .done(function (res) {
        if (!res || res.success !== true) {
          var msg = res && res.data && res.data.message ? res.data.message : nscWpscanAdmin.i18n.badResponse;
          $status.text(msg);
          return;
        }
        var d = res.data || {};
        $status.text((d.summary || nscWpscanAdmin.i18n.compScanDone));
        renderCompRows(d.rows || []);
      })
      .fail(function (xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
          ? xhr.responseJSON.data.message
          : nscWpscanAdmin.i18n.error;
        $status.text(msg);
      });
  });

  $(document).on("click", "#nsc-components-cleanup-selected", function (e) {
    e.preventDefault();
    var groups = selectedCompGroups();
    var $status = $("#nsc-components-opt-status");
    if (!groups.length) {
      $status.text(nscWpscanAdmin.i18n.compNoSelection);
      return;
    }
    if (!window.confirm(nscWpscanAdmin.i18n.compConfirmCleanup)) {
      return;
    }
    $status.text("Cleaning up...");
    compReq("nsc_components_db_optimize_cleanup", { groups: groups, groups_json: JSON.stringify(groups) })
      .done(function (res) {
        if (!res || res.success !== true) {
          var msg = res && res.data && res.data.message ? res.data.message : nscWpscanAdmin.i18n.badResponse;
          $status.text(msg);
          return;
        }
        var d = res.data || {};
        $status.text(d.summary || nscWpscanAdmin.i18n.compCleanupDone);
        renderCompRows(d.rows || []);
      })
      .fail(function (xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
          ? xhr.responseJSON.data.message
          : nscWpscanAdmin.i18n.error;
        $status.text(msg);
      });
  });
})(jQuery);
JS;

    \wp_add_inline_script('nsc-wpscan-admin', $js);
});

/**
 * Shared scan + option storage (used by REST and admin-ajax).
 *
 * @return array{html: string, httpStatus: int, statusLine: string}|\WP_Error
 */
function execute_wpscan_scan()
{
    $runner = \trailingslashit(\ABSPATH) . 'run-nsc-wpscan.php';
    if (!\is_readable($runner)) {
        return new \WP_Error(
            'nsc_wpscan_missing_runner',
            \sprintf(
                /* translators: %s: file name */
                \__('WPScan runner not found (%s). Deploy run-nsc-wpscan.php to the site root.', 'NscSoftware'),
                'run-nsc-wpscan.php'
            ),
            ['status' => 500]
        );
    }

    if (!\defined('NSC_WPSCAN_EMBEDDED')) {
        \define('NSC_WPSCAN_EMBEDDED', true);
    }

    require_once $runner;

    if (!\function_exists('nsc_wpscan_run_scan')) {
        return new \WP_Error(
            'nsc_wpscan_outdated',
            \__('WPScan runner is outdated (missing nsc_wpscan_run_scan). Update run-nsc-wpscan.php on the server.', 'NscSoftware'),
            ['status' => 500]
        );
    }

    $target = \home_url('/');
    try {
        $out = \nsc_wpscan_run_scan($target, 'json');
    } catch (\Throwable $e) {
        return new \WP_Error('nsc_wpscan_exception', $e->getMessage(), ['status' => 500]);
    }

    $body = $out['html'];
    $code = 200;

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

    return [
        'html' => $body,
        'httpStatus' => $code,
        'statusLine' => $statusLine,
    ];
}

/**
 * POST /wp-json/nsc/v1/wpscan-scan — preferred on hosts that block admin-ajax.php (e.g. some AWS WAF rules).
 */
function rest_wpscan_scan(\WP_REST_Request $request)
{
    unset($request);

    return \rest_ensure_response(execute_wpscan_scan());
}

\add_action('rest_api_init', static function (): void {
    \register_rest_route('nsc/v1', '/wpscan-scan', [
        'methods' => \WP_REST_Server::CREATABLE,
        'callback' => __NAMESPACE__ . '\\rest_wpscan_scan',
        'permission_callback' => static function (): bool {
            return \current_user_can(CAPABILITY);
        },
    ]);
});

add_action('wp_ajax_' . AJAX_ACTION, static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to run WPScan.', 'NscSoftware')], 403);
    }

    \check_ajax_referer(NONCE_ACTION, 'nonce');

    $result = execute_wpscan_scan();
    if (\is_wp_error($result)) {
        $status = (int) ($result->get_error_data()['status'] ?? 500);
        \wp_send_json_error(
            ['message' => $result->get_error_message()],
            $status >= 400 && $status < 600 ? $status : 500
        );
    }

    \wp_send_json_success($result);
});

add_action('wp_ajax_' . COMPONENTS_SCAN_AJAX_ACTION, static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to scan component data.', 'NscSoftware')], 403);
    }
    \check_ajax_referer(COMPONENTS_NONCE_ACTION, 'nonce');

    $rows = scan_components_db_residual_rows();
    $summary = \sprintf(
        /* translators: %d: orphan groups count */
        \__('Found %d residual component group(s).', 'NscSoftware'),
        \count($rows)
    );
    \wp_send_json_success(['rows' => $rows, 'summary' => $summary]);
});

add_action('wp_ajax_' . COMPONENTS_CLEANUP_AJAX_ACTION, static function (): void {
    if (!\current_user_can(CAPABILITY)) {
        \wp_send_json_error(['message' => \__('You do not have permission to clean component data.', 'NscSoftware')], 403);
    }
    \check_ajax_referer(COMPONENTS_NONCE_ACTION, 'nonce');

    $groups = [];
    if (isset($_POST['groups']) && \is_array($_POST['groups'])) {
        $groups = $_POST['groups'];
    } elseif (isset($_POST['groups_json'])) {
        $decoded = \json_decode((string) $_POST['groups_json'], true);
        if (\is_array($decoded)) {
            $groups = $decoded;
        }
    }
    $groups = \array_values(\array_unique(\array_filter(\array_map('strval', $groups))));
    if ($groups === []) {
        \wp_send_json_error(['message' => \__('No groups selected.', 'NscSoftware')], 400);
    }

    $deleted = cleanup_components_db_residual_groups($groups);
    $rows = scan_components_db_residual_rows();
    $summary = \sprintf(
        /* translators: 1: deleted meta rows, 2: remaining groups */
        \__('Deleted %1$d residual meta row(s). Remaining groups: %2$d.', 'NscSoftware'),
        $deleted,
        \count($rows)
    );
    \wp_send_json_success(['rows' => $rows, 'deleted' => $deleted, 'summary' => $summary]);
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
            <?php echo \esc_html(\__('NSC Scanner', 'NscSoftware')); ?>
            <span id="nsc-wpscan-spinner" class="spinner" style="float:none;margin-top:4px"></span>
        </h1>

        <h2 class="nav-tab-wrapper" style="margin-bottom:14px;">
            <a href="#" class="nav-tab nav-tab-active nsc-scanner-tab" data-tab="wpscan"><?php echo \esc_html(\__('WPScan', 'NscSoftware')); ?></a>
            <a href="#" class="nav-tab nsc-scanner-tab" data-tab="components"><?php echo \esc_html(\__('Components DB Optimize', 'NscSoftware')); ?></a>
        </h2>

        <div id="nsc-scanner-panel-wpscan" class="nsc-scanner-panel" aria-hidden="false">
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

        <div id="nsc-scanner-panel-components" class="nsc-scanner-panel" aria-hidden="true" style="display:none;">
            <p class="description">
                <?php echo \esc_html(\__('Scans postmeta for residual component rows (orphan flexible-content indices not used by current editor rows), then lets you clean them safely.', 'NscSoftware')); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="nsc-components-scan-run">
                    <?php echo \esc_html(\__('Scan now', 'NscSoftware')); ?>
                </button>
            </p>
            <p id="nsc-components-opt-status" style="margin:8px 0 12px;"></p>
            <div id="nsc-components-opt-results"></div>
        </div>

        <style>
            @keyframes nscWpscanSlide {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(320%); }
            }
        </style>
    </div>
    <?php
}

/**
 * @return array<int, array{
 *   group_key:string,
 *   post_id:int,
 *   post_title:string,
 *   post_type:string,
 *   edit_link:string,
 *   field_root:string,
 *   row_index:int,
 *   reason:string,
 *   meta_count:int
 * }>
 */
function scan_components_db_residual_rows(): array
{
    $map = build_components_residual_group_map();
    if ($map === []) {
        return [];
    }

    $out = [];
    foreach ($map as $gk => $row) {
        $postId = (int) $row['post_id'];
        $postTitle = (string) \get_the_title($postId);
        $postType = (string) \get_post_type($postId);
        $out[] = [
            'group_key' => (string) $gk,
            'post_id' => $postId,
            'post_title' => $postTitle !== '' ? $postTitle : \__('(no title)', 'NscSoftware'),
            'post_type' => $postType !== '' ? $postType : 'post',
            'edit_link' => (string) \get_edit_post_link($postId, 'raw'),
            'field_root' => (string) $row['field_root'],
            'row_index' => (int) $row['row_index'],
            'reason' => (string) ($row['reason'] ?? ''),
            'meta_count' => \count((array) $row['meta_ids']),
        ];
    }

    \usort($out, static function (array $a, array $b): int {
        if ((int) $a['post_id'] === (int) $b['post_id']) {
            return (int) $a['row_index'] <=> (int) $b['row_index'];
        }
        return (int) $a['post_id'] <=> (int) $b['post_id'];
    });

    return $out;
}

/**
 * @param list<string> $groups
 */
function cleanup_components_db_residual_groups(array $groups): int
{
    $map = build_components_residual_group_map();
    if ($map === []) {
        return 0;
    }

    global $wpdb;
    $deleted = 0;
    foreach ($groups as $gk) {
        if (!\is_string($gk) || $gk === '') {
            continue;
        }
        if (!isset($map[$gk])) {
            continue;
        }
        foreach ((array) ($map[$gk]['meta_ids'] ?? []) as $metaId) {
            $metaId = (int) $metaId;
            if ($metaId <= 0) {
                continue;
            }
            $wpdb->delete($wpdb->postmeta, ['meta_id' => $metaId], ['%d']);
            $deleted++;
        }
    }
    return $deleted;
}

/**
 * @return array<string, array{post_id:int,field_root:string,row_index:int,reason:string,meta_ids:list<int>}>
 */
function build_components_residual_group_map(): array
{
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT meta_id, post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE meta_key REGEXP '^_?[A-Za-z0-9_]+_[0-9]+_.+$'",
        ARRAY_A
    );
    if (!\is_array($rows) || $rows === []) {
        return [];
    }

    // 1) Build active layout map from current flexible rows.
    $activeLayouts = [];
    foreach ($rows as $row) {
        $postId = (int) ($row['post_id'] ?? 0);
        $metaKey = (string) ($row['meta_key'] ?? '');
        $parsed = parse_component_row_meta_key($metaKey);
        if ($postId <= 0 || $parsed === null) {
            continue;
        }
        if ($parsed['is_underscore'] || $parsed['rest'] !== 'acf_fc_layout') {
            continue;
        }

        $layout = \sanitize_key((string) ($row['meta_value'] ?? ''));
        if ($layout === '') {
            continue;
        }
        $activeLayouts[$postId][$parsed['root']][$parsed['index']] = $layout;
    }

    // 2) Group all component-row meta by (post|root|index).
    $groupRows = [];
    foreach ($rows as $row) {
        $metaId = (int) ($row['meta_id'] ?? 0);
        $postId = (int) ($row['post_id'] ?? 0);
        $metaKey = (string) ($row['meta_key'] ?? '');
        $parsed = parse_component_row_meta_key($metaKey);
        if ($metaId <= 0 || $postId <= 0 || $parsed === null) {
            continue;
        }
        $gk = $postId . '|' . $parsed['root'] . '|' . $parsed['index'];
        if (!isset($groupRows[$gk])) {
            $groupRows[$gk] = [
                'post_id' => $postId,
                'field_root' => $parsed['root'],
                'row_index' => $parsed['index'],
                'rows' => [],
            ];
        }
        $groupRows[$gk]['rows'][] = [
            'meta_id' => $metaId,
            'meta_key' => $metaKey,
            'rest' => $parsed['rest'],
        ];
    }

    // 3) Resolve residual rows by:
    // - orphan row index (no acf_fc_layout row)
    // - invalid text keys for active layout (field prefix diff).
    $residual = [];
    foreach ($groupRows as $gk => $group) {
        $postId = (int) $group['post_id'];
        $root = (string) $group['field_root'];
        $idx = (int) $group['row_index'];
        $layout = $activeLayouts[$postId][$root][$idx] ?? '';

        $residualMetaIds = [];
        if ($layout === '') {
            // Orphan index: keep layout marker excluded.
            foreach ((array) $group['rows'] as $r) {
                if ((string) ($r['rest'] ?? '') === 'acf_fc_layout') {
                    continue;
                }
                $residualMetaIds[] = (int) ($r['meta_id'] ?? 0);
            }
        } else {
            $allowedTokens = get_layout_allowed_field_tokens($root, $layout);
            if ($allowedTokens === []) {
                // Unknown layout definition: skip to avoid risky cleanup.
                continue;
            }

            foreach ((array) $group['rows'] as $r) {
                $rest = (string) ($r['rest'] ?? '');
                if ($rest === '' || $rest === 'acf_fc_layout') {
                    continue;
                }
                $token = \preg_replace('/_.*/', '', $rest);
                $token = \is_string($token) ? $token : '';
                if ($token === '' || isset($allowedTokens[$token])) {
                    continue;
                }
                $residualMetaIds[] = (int) ($r['meta_id'] ?? 0);
            }
        }

        $residualMetaIds = \array_values(\array_filter(\array_unique(\array_map('intval', $residualMetaIds))));
        if ($residualMetaIds === []) {
            continue;
        }

        $residual[$gk] = [
            'post_id' => $postId,
            'field_root' => $root,
            'row_index' => $idx,
            'reason' => $layout === '' ? (string) \__('Orphan row', 'NscSoftware') : (string) \__('Diff key', 'NscSoftware'),
            'meta_ids' => $residualMetaIds,
        ];
    }

    return $residual;
}

/**
 * @return array{root:string,index:int,rest:string,is_underscore:bool}|null
 */
function parse_component_row_meta_key(string $metaKey): ?array
{
    if (!\preg_match('/^(_)?([A-Za-z0-9_]+)_([0-9]+)_(.+)$/', $metaKey, $m)) {
        return null;
    }
    $root = (string) $m[2];
    if (!\preg_match('/Components$/', $root)) {
        return null;
    }

    return [
        'root' => $root,
        'index' => (int) $m[3],
        'rest' => (string) $m[4],
        'is_underscore' => !empty($m[1]),
    ];
}

/**
 * @return array<string, true> token => true
 */
function get_layout_allowed_field_tokens(string $root, string $layout): array
{
    static $cache = [];
    $cacheKey = $root . '|' . $layout;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $layout = \sanitize_key($layout);
    if ($root === '' || $layout === '' || !\function_exists('acf_get_field_groups') || !\function_exists('acf_get_fields')) {
        $cache[$cacheKey] = [];
        return $cache[$cacheKey];
    }

    $allowed = [];
    $groups = \acf_get_field_groups();
    if (!\is_array($groups)) {
        $cache[$cacheKey] = [];
        return $cache[$cacheKey];
    }

    foreach ($groups as $group) {
        $fields = \acf_get_fields($group);
        if (!\is_array($fields)) {
            continue;
        }
        foreach ($fields as $field) {
            if (!\is_array($field)) {
                continue;
            }
            collect_layout_tokens_from_field($field, $root, $layout, $allowed);
        }
    }

    $cache[$cacheKey] = $allowed;
    return $cache[$cacheKey];
}

/**
 * @param array<string, mixed> $field
 * @param array<string, true> $allowed
 */
function collect_layout_tokens_from_field(array $field, string $root, string $layout, array &$allowed): void
{
    $name = isset($field['name']) && \is_string($field['name']) ? $field['name'] : '';
    $type = isset($field['type']) && \is_string($field['type']) ? $field['type'] : '';

    if ($type === 'flexible_content' && $name === $root) {
        $layouts = isset($field['layouts']) && \is_array($field['layouts']) ? $field['layouts'] : [];
        foreach ($layouts as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $layoutName = isset($row['name']) && \is_string($row['name']) ? \sanitize_key($row['name']) : '';
            if ($layoutName !== $layout) {
                continue;
            }
            $sub = isset($row['sub_fields']) && \is_array($row['sub_fields']) ? $row['sub_fields'] : [];
            foreach ($sub as $sf) {
                if (\is_array($sf)) {
                    collect_subfield_tokens($sf, $allowed);
                }
            }
        }
        return;
    }

    $subFields = isset($field['sub_fields']) && \is_array($field['sub_fields']) ? $field['sub_fields'] : [];
    foreach ($subFields as $sf) {
        if (\is_array($sf)) {
            collect_layout_tokens_from_field($sf, $root, $layout, $allowed);
        }
    }
}

/**
 * @param array<string, mixed> $field
 * @param array<string, true> $allowed
 */
function collect_subfield_tokens(array $field, array &$allowed): void
{
    $name = isset($field['name']) && \is_string($field['name']) ? \sanitize_key($field['name']) : '';
    if ($name !== '') {
        $allowed[$name] = true;
    }
    $sub = isset($field['sub_fields']) && \is_array($field['sub_fields']) ? $field['sub_fields'] : [];
    foreach ($sub as $sf) {
        if (\is_array($sf)) {
            collect_subfield_tokens($sf, $allowed);
        }
    }
}
