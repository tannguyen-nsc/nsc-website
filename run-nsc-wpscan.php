<?php
declare(strict_types=1);

/**
 * NSC WPScan runner (browser + wp-admin Tools → NSC WPScan).
 *
 * Usage (same token pattern as other create-nsc-*.php scripts):
 *   https://yoursite.test/run-nsc-wpscan.php?token=nsc-wpscan-2026
 * Optional:
 *   &url=https://yoursite.test/   (default: home_url())
 *   &format=text                  (default: json — pretty-printed in the page)
 *
 * Requires WPScan CLI:
 *   Windows: tools/wpscan.cmd + tools/wpscan-curl-bin (see tools/wpscan.cmd)
 *   Linux:   gem install wpscan — binary on PATH (e.g. /usr/local/bin/wpscan)
 *
 * Optional wp-config.php:
 *   define( 'NSC_WPSCAN_API_TOKEN', 'your-wpscan-com-token' );
 */

$requiredToken = 'nsc-wpscan-2026';
$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    echo "Use: ?token={$requiredToken}\n";
    exit;
}

$wpLoadPath = __DIR__ . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found at: {$wpLoadPath}\n";
    exit;
}

require_once $wpLoadPath;

if (!function_exists('home_url')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "WordPress bootstrap failed.\n";
    exit;
}

set_time_limit(600);
if (function_exists('ini_set')) {
    @ini_set('max_execution_time', '600');
}

$targetUrl = isset($_GET['url']) ? esc_url_raw((string) $_GET['url']) : home_url('/');
if ($targetUrl === '') {
    $targetUrl = home_url('/');
}

$outFormat = isset($_GET['format']) ? strtolower((string) $_GET['format']) : 'json';
if ($outFormat !== 'text' && $outFormat !== 'json') {
    $outFormat = 'json';
}

$args = [
    '--url=' . $targetUrl,
    '--no-banner',
    '--plugins-detection', 'passive',
    '--themes-detection', 'passive',
];

if ($outFormat === 'json') {
    $args[] = '--format';
    $args[] = 'json';
} else {
    $args[] = '--format';
    $args[] = 'cli-no-colour';
}

if (defined('NSC_WPSCAN_API_TOKEN')) {
    $apiTok = constant('NSC_WPSCAN_API_TOKEN');
    if (is_string($apiTok) && trim($apiTok) !== '') {
        $args[] = '--api-token';
        $args[] = trim($apiTok);
    }
}

$host = wp_parse_url($targetUrl, PHP_URL_HOST);
if (is_string($host) && $host !== '') {
    $h = strtolower($host);
    if ($h === 'localhost' || $h === '127.0.0.1' || str_ends_with($h, '.test') || str_ends_with($h, '.local')) {
        $args[] = '--disable-tls-checks';
    }
}

$result = nsc_run_wpscan_cli($args);

$stdout = $result['stdout'];
$stderr = $result['stderr'];
$code = $result['code'];

header('Content-Type: text/html; charset=utf-8');
$n = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$decoded = null;
if ($outFormat === 'json' && $stdout !== '') {
    $decoded = json_decode($stdout, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $decoded = null;
    }
}

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>NSC WPScan</title>';
echo '<style>';
echo 'body{font-family:Arial,sans-serif;padding:24px;max-width:1100px;margin:0 auto;background:#f6f7f7;color:#1d2327;}';
echo 'h1{font-size:1.35em;margin:0 0 .5em;color:#1d2327;}';
echo 'h2{font-size:1.1em;margin:1.25em 0 .5em;color:#1d2327;}';
echo '.nsc-wpscan-meta{font-size:14px;color:#50575e;margin:0 0 1em;line-height:1.5;}';
echo '.nsc-wpscan-summary{display:flex;flex-wrap:wrap;gap:12px;margin:1em 0 1.25em;}';
echo '.nsc-wpscan-summary div{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 16px;min-width:120px;}';
echo '.nsc-wpscan-summary strong{display:block;font-size:1.35em;color:#1d2327;}';
echo '.nsc-wpscan-summary span{font-size:12px;color:#50575e;}';
echo '.nsc-wpscan-summary .warn strong{color:#b32d2e;}';
echo 'table{border-collapse:collapse;width:100%;max-width:1100px;background:#fff;margin:0 0 1em;}';
echo 'th,td{border:1px solid #ddd;padding:8px 10px;text-align:left;vertical-align:top;font-size:13px;}';
echo 'th{background:#f7f7f7;}';
echo '.ok{color:#0a7f2e;}';
echo '.warn{color:#b32d2e;}';
echo 'pre.nsc-wpscan-out{white-space:pre-wrap;word-break:break-word;background:#fff;border:1px solid #c3c4c7;padding:14px 16px;margin:0 0 1em;overflow:auto;max-height:50vh;font-size:12px;line-height:1.45;}';
echo 'details{margin:1em 0;}summary{cursor:pointer;font-weight:600;margin-bottom:8px;}';
echo '</style></head><body>';
echo '<h1>NSC WPScan</h1>';
echo '<p class="nsc-wpscan-meta"><strong>Target:</strong> ' . $n($targetUrl) . '<br>';
echo '<strong>Exit code:</strong> ' . (int) $code . ' &nbsp;|&nbsp; <strong>Format:</strong> ' . $n($outFormat) . '</p>';

if ($stderr !== '') {
    echo '<p class="nsc-wpscan-meta warn"><strong>stderr:</strong> ' . $n($stderr) . '</p>';
}

if ($outFormat === 'json' && is_array($decoded)) {
    $report = nsc_wpscan_build_report_tables($decoded);
    echo $report['summary_html'];
    echo $report['tables_html'];
    $pretty = (string) wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    echo '<details><summary>Raw JSON</summary><pre class="nsc-wpscan-out">' . $n($pretty) . '</pre></details>';
} elseif ($outFormat === 'json' && $stdout !== '') {
    echo '<p class="warn"><strong>Could not parse JSON.</strong> Showing raw output.</p>';
    echo '<pre class="nsc-wpscan-out">' . $n(trim($stdout . "\n" . $stderr)) . '</pre>';
} else {
    echo '<pre class="nsc-wpscan-out">' . $n(trim($stdout . ($stderr !== '' ? "\n\n--- stderr ---\n" . $stderr : '')) ?: '(no output)') . '</pre>';
}

echo '</body></html>';

/**
 * @param array<string, mixed> $data Decoded WPScan JSON
 *
 * @return array{summary_html: string, tables_html: string}
 */
function nsc_wpscan_build_report_tables(array $data): array
{
    $vulnRows = [];
    $appendV = static function (array $list, string $component, string $slug) use (&$vulnRows): void {
        foreach ($list as $v) {
            if (!is_array($v)) {
                continue;
            }
            $title = isset($v['title']) && is_string($v['title']) ? $v['title'] : '';
            $fixed = '';
            if (isset($v['fixed_in'])) {
                $fixed = is_string($v['fixed_in']) || is_numeric($v['fixed_in']) ? (string) $v['fixed_in'] : '';
            }
            $vulnRows[] = [
                'component' => $component,
                'slug' => $slug,
                'title' => $title,
                'fixed_in' => $fixed,
            ];
        }
    };

    if (!empty($data['version']['vulnerabilities']) && is_array($data['version']['vulnerabilities'])) {
        $appendV($data['version']['vulnerabilities'], 'WordPress core', 'core');
    }

    if (!empty($data['main_theme']['vulnerabilities']) && is_array($data['main_theme']['vulnerabilities'])) {
        $slug = isset($data['main_theme']['slug']) && is_string($data['main_theme']['slug']) ? $data['main_theme']['slug'] : 'theme';
        $appendV($data['main_theme']['vulnerabilities'], 'Theme', $slug);
    }

    if (!empty($data['plugins']) && is_array($data['plugins'])) {
        foreach ($data['plugins'] as $slug => $plugin) {
            if (!is_array($plugin) || empty($plugin['vulnerabilities']) || !is_array($plugin['vulnerabilities'])) {
                continue;
            }
            $slugStr = is_string($slug) ? $slug : '';
            $appendV($plugin['vulnerabilities'], 'Plugin', $slugStr);
        }
    }

    if (!empty($data['themes']) && is_array($data['themes'])) {
        foreach ($data['themes'] as $slug => $theme) {
            if (!is_array($theme) || empty($theme['vulnerabilities']) || !is_array($theme['vulnerabilities'])) {
                continue;
            }
            $slugStr = is_string($slug) ? $slug : '';
            $appendV($theme['vulnerabilities'], 'Theme', $slugStr);
        }
    }

    $findings = [];
    if (!empty($data['interesting_findings']) && is_array($data['interesting_findings'])) {
        foreach ($data['interesting_findings'] as $f) {
            if (!is_array($f)) {
                continue;
            }
            $type = isset($f['type']) && is_string($f['type']) ? $f['type'] : '';
            $conf = 0;
            if (isset($f['confidence'])) {
                $conf = is_numeric($f['confidence']) ? (int) round((float) $f['confidence']) : 0;
            }
            $entries = '';
            $entryList = null;
            if (!empty($f['interesting_entries']) && is_array($f['interesting_entries'])) {
                $entryList = $f['interesting_entries'];
            } elseif (!empty($f['entries']) && is_array($f['entries'])) {
                $entryList = $f['entries'];
            }
            if ($entryList !== null) {
                $entries = implode("\n", array_map('strval', $entryList));
            }
            $findings[] = ['type' => $type, 'confidence' => $conf, 'entries' => $entries];
        }
    }

    $wpVer = '';
    if (!empty($data['version']) && is_array($data['version'])) {
        $v = $data['version'];
        if (isset($v['number']) && (is_string($v['number']) || is_numeric($v['number']))) {
            $wpVer = (string) $v['number'];
        }
    }

    $themeName = '';
    $themeVer = '';
    if (!empty($data['main_theme']) && is_array($data['main_theme'])) {
        $t = $data['main_theme'];
        if (isset($t['slug']) && is_string($t['slug'])) {
            $themeName = $t['slug'];
        }
        if (!empty($t['version']) && is_array($t['version']) && isset($t['version']['number'])
            && (is_string($t['version']['number']) || is_numeric($t['version']['number']))) {
            $themeVer = (string) $t['version']['number'];
        }
    }

    $pluginCount = 0;
    if (!empty($data['plugins']) && is_array($data['plugins'])) {
        $pluginCount = count($data['plugins']);
    }

    $abort = '';
    if (isset($data['scan_aborted'])) {
        if (is_string($data['scan_aborted'])) {
            $abort = $data['scan_aborted'];
        } elseif ($data['scan_aborted'] === true) {
            $abort = 'yes';
        }
    }

    $n = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $vulnCount = count($vulnRows);
    $findingCount = count($findings);

    ob_start();
    echo '<div class="nsc-wpscan-summary">';
    echo '<div' . ($vulnCount > 0 ? ' class="warn"' : '') . '><strong>' . (int) $vulnCount . '</strong><span>vulnerabilities</span></div>';
    echo '<div><strong>' . (int) $findingCount . '</strong><span>interesting findings</span></div>';
    echo '<div><strong>' . (int) $pluginCount . '</strong><span>plugins detected</span></div>';
    echo '</div>';

    if ($abort !== '') {
        echo '<p class="warn"><strong>Scan note:</strong> ' . $n($abort) . '</p>';
    }

    $summaryHtml = ob_get_clean();

    ob_start();
    echo '<h2>Overview</h2>';
    echo '<table><thead><tr><th>Item</th><th>Value</th></tr></thead><tbody>';
    echo '<tr><td>WordPress version</td><td>' . $n($wpVer !== '' ? $wpVer : '—') . '</td></tr>';
    echo '<tr><td>Active theme</td><td>' . $n($themeName !== '' ? $themeName : '—') . '</td></tr>';
    echo '<tr><td>Theme version</td><td>' . $n($themeVer !== '' ? $themeVer : '—') . '</td></tr>';
    echo '<tr><td>Plugins (count)</td><td>' . (int) $pluginCount . '</td></tr>';
    echo '</tbody></table>';

    if ($vulnCount > 0) {
        echo '<h2>Vulnerabilities (' . (int) $vulnCount . ')</h2>';
        echo '<table><thead><tr><th>Type</th><th>Slug</th><th>Title</th><th>Fixed in</th></tr></thead><tbody>';
        foreach ($vulnRows as $row) {
            echo '<tr>';
            echo '<td>' . $n($row['component']) . '</td>';
            echo '<td>' . $n($row['slug']) . '</td>';
            echo '<td>' . $n($row['title']) . '</td>';
            echo '<td>' . $n($row['fixed_in']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<h2>Vulnerabilities</h2>';
        echo '<p class="ok">No known vulnerabilities reported for this scan (WPScan database + passive detection).</p>';
    }

    if ($findingCount > 0) {
        echo '<h2>Interesting findings (' . (int) $findingCount . ')</h2>';
        echo '<table><thead><tr><th>Type</th><th>Confidence</th><th>Details</th></tr></thead><tbody>';
        foreach ($findings as $f) {
            echo '<tr>';
            echo '<td>' . $n($f['type']) . '</td>';
            echo '<td>' . (int) $f['confidence'] . '%</td>';
            echo '<td>' . nl2br($n($f['entries'])) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    $pluginRows = [];
    if (!empty($data['plugins']) && is_array($data['plugins'])) {
        foreach ($data['plugins'] as $slug => $plugin) {
            if (!is_array($plugin)) {
                continue;
            }
            $slugStr = is_string($slug) ? $slug : '';
            $ver = '';
            if (!empty($plugin['version']) && is_array($plugin['version']) && isset($plugin['version']['number'])
                && (is_string($plugin['version']['number']) || is_numeric($plugin['version']['number']))) {
                $ver = (string) $plugin['version']['number'];
            }
            $pvc = 0;
            if (!empty($plugin['vulnerabilities']) && is_array($plugin['vulnerabilities'])) {
                $pvc = count($plugin['vulnerabilities']);
            }
            $pluginRows[] = ['slug' => $slugStr, 'version' => $ver, 'vulns' => $pvc];
        }
    }

    if (!empty($pluginRows)) {
        echo '<h2>Plugins</h2>';
        echo '<table><thead><tr><th>Slug</th><th>Version</th><th>Vulnerabilities</th></tr></thead><tbody>';
        foreach ($pluginRows as $pr) {
            echo '<tr>';
            echo '<td>' . $n($pr['slug']) . '</td>';
            echo '<td>' . $n($pr['version']) . '</td>';
            echo '<td>' . ($pr['vulns'] > 0 ? '<span class="warn">' . (int) $pr['vulns'] . '</span>' : '0') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    $tablesHtml = ob_get_clean();

    return ['summary_html' => $summaryHtml, 'tables_html' => $tablesHtml];
}

/**
 * @param list<string> $args wpscan arguments (no binary)
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function nsc_run_wpscan_cli(array $args): array
{
    $isWin = PHP_OS_FAMILY === 'Windows';

    if ($isWin) {
        $bat = rtrim((string) ABSPATH, '/\\') . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'wpscan.cmd';
        if (!is_file($bat)) {
            return [
                'code' => -1,
                'stdout' => '',
                'stderr' => 'Missing tools/wpscan.cmd. Install WPScan (gem) and ensure tools/wpscan.cmd + tools/wpscan-curl-bin exist.',
            ];
        }
        // Windows: single cmd /c argument via escapeshellarg($inner) so --url stays on the same command as the .cmd.
        $bat = str_replace('/', '\\', $bat);
        $comspec = getenv('ComSpec');
        if (!is_string($comspec) || $comspec === '') {
            $comspec = 'C:\\Windows\\System32\\cmd.exe';
        }
        $argLine = '';
        foreach ($args as $a) {
            $argLine .= ($argLine === '' ? '' : ' ') . escapeshellarg($a);
        }
        $inner = 'call ' . escapeshellarg($bat) . ($argLine !== '' ? ' ' . $argLine : '');
        $cmdline = escapeshellarg($comspec) . ' /c ' . escapeshellarg($inner);
        $cwd = dirname($bat);

        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmdline, $desc, $pipes, $cwd, null);
    } else {
        $bin = '/usr/local/bin/wpscan';
        if (!is_executable($bin)) {
            $bin = '/usr/bin/wpscan';
        }
        if (!is_executable($bin)) {
            return [
                'code' => -1,
                'stdout' => '',
                'stderr' => 'wpscan not found. Install: gem install wpscan',
            ];
        }
        $inner = [];
        foreach (array_merge([$bin], $args) as $p) {
            $inner[] = escapeshellarg($p);
        }
        $cmdline = implode(' ', $inner);
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmdline, $desc, $pipes, null, null);
    }

    if (!is_resource($proc)) {
        return ['code' => -1, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], true);
    stream_set_blocking($pipes[2], true);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = (int) proc_close($proc);

    return ['code' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
}
