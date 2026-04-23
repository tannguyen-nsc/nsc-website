<?php

declare(strict_types=1);

/**
 * Job apply form: AJAX CV staging, LinkedIn vs CV validation, admin email body + secure CV download.
 *
 * Requires Contact Form 7. Form markup from create-nsc-cf7-form.php (apply_only=1):
 * - Optional LinkedIn [url linkedin_profile] (required if no CV).
 * - Optional file [file cv_file] OR staged upload via AJAX (hidden nsc_cv_staging_token).
 * - Hidden nsc_job_title + nsc_job_url (prefilled on job singles; verified server-side, no posted ID).
 */

const NSC_JOB_CV_MAX_BYTES = 5242880; // 5 MB
const NSC_JOB_CV_TMP_SUBDIR = 'nsc-job-cv-tmp';
const NSC_JOB_CV_ARCHIVE_SUBDIR = 'nsc-job-cv';
const NSC_JOB_CV_STAGING_TTL = 7200; // 2 hours
const NSC_JOB_CV_DOWNLOAD_TTL = 2592000; // 30 days

/**
 * @return int CF7 form post ID or 0
 */
function nsc_job_apply_cf7_form_id(): int
{
    return (int) get_option('nsc_cf7_job_apply_form_id', 0);
}

/**
 * True when this CF7 post is the job-apply form (option ID, or seeded form markers when option is unset).
 */
function nsc_job_apply_cfdb7_form_post_is_job_apply(int $form_post_id): bool
{
    if ($form_post_id <= 0) {
        return false;
    }
    $opt = nsc_job_apply_cf7_form_id();
    if ($opt > 0) {
        return $form_post_id === $opt;
    }
    if (get_post_type($form_post_id) !== 'wpcf7_contact_form') {
        return false;
    }
    $form = get_post_meta($form_post_id, '_form', true);

    return is_string($form)
        && strpos($form, 'nsc_job_title') !== false
        && strpos($form, 'nsc_job_url') !== false;
}

function nsc_job_apply_is_target_form(?object $contact_form): bool
{
    if (!$contact_form instanceof \WPCF7_ContactForm) {
        return false;
    }

    $id = (int) $contact_form->id();
    $opt = nsc_job_apply_cf7_form_id();
    if ($opt > 0) {
        return $id === $opt;
    }
    $form = (string) $contact_form->prop('form');

    return strpos($form, 'nsc_job_title') !== false && strpos($form, 'nsc_job_url') !== false;
}

/**
 * CF7 posted values for select / pipe fields are arrays; (string) yields "Array".
 */
function nsc_job_apply_submission_string(\WPCF7_Submission $submission, string $fieldName): string
{
    return $submission->get_posted_string($fieldName);
}

/**
 * Single string from raw posted data (CF7 may use arrays for some field types).
 *
 * @param array<string, mixed> $posted
 */
function nsc_job_apply_posted_scalar(array $posted, string $key): string
{
    $v = $posted[$key] ?? '';
    if (is_array($v)) {
        $v = function_exists('wpcf7_array_flatten') ? wpcf7_array_flatten($v) : $v;
        $v = is_array($v) ? (string) reset($v) : (string) $v;
    }

    return trim((string) $v);
}

/**
 * Normalize job title for comparison (posted hidden vs get_the_title).
 */
function nsc_job_apply_normalize_title(string $title): string
{
    $title = wp_strip_all_tags($title);
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $collapsed = preg_replace('/\s+/u', ' ', trim($title));

    return is_string($collapsed) ? $collapsed : trim($title);
}

/**
 * Resolve job post ID from posted canonical URL + title. Does not trust a bare numeric ID.
 * Optional staged CV must have been created for the same job.
 */
function nsc_job_apply_verify_job_context_from_posted(array $posted): int
{
    $url = nsc_job_apply_posted_scalar($posted, 'nsc_job_url');
    $title = nsc_job_apply_posted_scalar($posted, 'nsc_job_title');
    if ($url === '' || $title === '') {
        return 0;
    }

    $postId = url_to_postid($url);
    if ($postId <= 0) {
        $postId = url_to_postid(untrailingslashit($url));
    }
    if ($postId <= 0) {
        $postId = url_to_postid(trailingslashit($url));
    }
    if ($postId <= 0 || get_post_type($postId) !== 'job' || get_post_status($postId) !== 'publish') {
        return 0;
    }

    $expectedTitle = nsc_job_apply_normalize_title(get_the_title($postId));
    $postedTitle = nsc_job_apply_normalize_title($title);
    if ($expectedTitle === '' || $postedTitle === '' || $expectedTitle !== $postedTitle) {
        return 0;
    }

    $token = isset($posted['nsc_cv_staging_token']) ? trim((string) $posted['nsc_cv_staging_token']) : '';
    if ($token !== '' && strlen($token) >= 16) {
        $row = get_transient(nsc_job_cv_staging_transient_key($token));
        if (is_array($row) && isset($row['job_id']) && (int) $row['job_id'] !== $postId) {
            return 0;
        }
    }

    return $postId;
}

/**
 * Current job post ID for prefilling the apply form (Timber / shortcode may not set is_singular()).
 */
function nsc_job_apply_current_job_post_id(): int
{
    $id = (int) get_queried_object_id();
    if ($id > 0 && get_post_type($id) === 'job' && get_post_status($id) === 'publish') {
        return $id;
    }
    global $post;
    if ($post instanceof \WP_Post && $post->post_type === 'job' && $post->post_status === 'publish') {
        return (int) $post->ID;
    }
    if (function_exists('get_the_ID')) {
        $tid = (int) get_the_ID();
        if ($tid > 0 && get_post_type($tid) === 'job' && get_post_status($tid) === 'publish') {
            return $tid;
        }
    }

    return 0;
}

/**
 * Set value="" on a hidden input by name (CF7 may render before wpcf7_form_tag values apply reliably).
 */
function nsc_job_apply_set_hidden_input_value(string $html, string $fieldName, string $value): string
{
    $nameRe = preg_quote($fieldName, '/');
    $out = preg_replace_callback(
        '/<input\b[^>]*\bname=(["\'])' . $nameRe . '\1[^>]*>/i',
        static function (array $m) use ($value): string {
            $tag = $m[0];
            if (preg_match('/\svalue\s*=\s*"[^"]*"/i', $tag)) {
                return (string) preg_replace('/\svalue\s*=\s*"[^"]*"/i', ' value="' . $value . '"', $tag, 1);
            }
            if (preg_match("/\svalue\s*=\s*'[^']*'/i", $tag)) {
                return (string) preg_replace("/\svalue\s*=\s*'[^']*'/i", ' value="' . $value . '"', $tag, 1);
            }
            if (preg_match('/\/>\s*$/', $tag)) {
                return (string) preg_replace('/\/>\s*$/', ' value="' . $value . '" />', $tag, 1);
            }
            if (preg_match('/>\s*$/', $tag)) {
                return (string) preg_replace('/>\s*$/', ' value="' . $value . '" />', $tag, 1);
            }

            return $tag;
        },
        $html,
        1
    );

    return is_string($out) ? $out : $html;
}

/**
 * Inject nsc_job_title / nsc_job_url hidden values into rendered CF7 HTML.
 */
function nsc_job_apply_inject_job_context_hidden_inputs(string $html): string
{
    if ($html === '' || strpos($html, 'nsc_job_title') === false) {
        return $html;
    }
    $applyId = nsc_job_apply_cf7_form_id();
    if ($applyId <= 0) {
        return $html;
    }
    $cf = \WPCF7_ContactForm::get_current();
    if (!$cf instanceof \WPCF7_ContactForm || (int) $cf->id() !== $applyId) {
        return $html;
    }
    $jobId = nsc_job_apply_current_job_post_id();
    if ($jobId <= 0) {
        return $html;
    }
    $titleRaw = (string) get_the_title($jobId);
    $urlRaw = (string) get_permalink($jobId);
    if ($titleRaw === '' || $urlRaw === '') {
        return $html;
    }
    $titleEsc = esc_attr($titleRaw);
    $urlEsc = esc_attr($urlRaw);
    $html = nsc_job_apply_set_hidden_input_value($html, 'nsc_job_title', $titleEsc);
    $html = nsc_job_apply_set_hidden_input_value($html, 'nsc_job_url', $urlEsc);

    return $html;
}

function nsc_job_cv_tmp_base_dir(): string
{
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return '';
    }

    return trailingslashit($upload['basedir']) . NSC_JOB_CV_TMP_SUBDIR;
}

function nsc_job_cv_archive_base_dir(): string
{
    $upload = wp_upload_dir();
    if (!empty($upload['error'])) {
        return '';
    }
    $ym = gmdate('Y/m');

    return trailingslashit($upload['basedir']) . NSC_JOB_CV_ARCHIVE_SUBDIR . '/' . $ym;
}

function nsc_job_cv_staging_transient_key(string $token): string
{
    return 'nsc_cvst_' . md5($token);
}

function nsc_job_cv_download_transient_key(string $token): string
{
    return 'nsc_cvdl_' . md5($token);
}

/**
 * @return array{path: string, name: string, mime: string}|null
 */
function nsc_job_cv_get_staging(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }
    $data = get_transient(nsc_job_cv_staging_transient_key($token));
    if (!is_array($data) || empty($data['path'])) {
        return null;
    }
    $path = (string) $data['path'];
    if (!is_readable($path) || !is_file($path)) {
        return null;
    }

    return [
        'path' => $path,
        'name' => (string) ($data['name'] ?? basename($path)),
        'mime' => (string) ($data['mime'] ?? 'application/octet-stream'),
    ];
}

function nsc_job_cv_delete_staging_transient(string $token): void
{
    delete_transient(nsc_job_cv_staging_transient_key($token));
}

/**
 * @param array{path: string, name: string, mime: string}|null $staging
 */
function nsc_job_cv_copy_upload_to_archive(string $sourceAbs, string $originalName): ?string
{
    if ($sourceAbs === '' || !is_readable($sourceAbs) || !is_file($sourceAbs)) {
        return null;
    }
    $archiveDir = nsc_job_cv_archive_base_dir();
    if ($archiveDir === '' || !wp_mkdir_p($archiveDir)) {
        return null;
    }
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $ext = is_string($ext) ? strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?? '') : '';
    if (!in_array($ext, nsc_job_cv_allowed_extensions(), true)) {
        $ext = 'pdf';
    }
    $destName = wp_unique_filename($archiveDir, 'cv-' . wp_generate_password(8, false) . '.' . $ext);
    $dest = $archiveDir . '/' . $destName;
    if (!@copy($sourceAbs, $dest)) {
        return null;
    }
    @chmod($dest, 0640);

    return $dest;
}

function nsc_job_cv_move_staging_to_archive(string $token, ?array $staging): ?string
{
    if ($staging === null) {
        nsc_job_cv_delete_staging_transient($token);

        return null;
    }
    $archiveDir = nsc_job_cv_archive_base_dir();
    if ($archiveDir === '' || !wp_mkdir_p($archiveDir)) {
        return null;
    }
    $ext = pathinfo($staging['name'], PATHINFO_EXTENSION);
    $ext = is_string($ext) ? strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?? '') : '';
    if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
        $ext = 'pdf';
    }
    $destName = wp_unique_filename($archiveDir, 'cv-' . wp_generate_password(8, false) . '.' . $ext);
    $dest = $archiveDir . '/' . $destName;
    if (!@rename($staging['path'], $dest)) {
        if (!@copy($staging['path'], $dest)) {
            return null;
        }
        @unlink($staging['path']);
    }
    nsc_job_cv_delete_staging_transient($token);

    return $dest;
}

function nsc_job_apply_cfdb7_current_user_can_restore(): bool
{
    if (current_user_can('manage_options')) {
        return true;
    }
    if (!function_exists('wpcf7')) {
        return false;
    }

    return current_user_can('wpcf7_edit_contact_forms') || current_user_can('wpcf7_read_contact_forms');
}

/**
 * Create a fresh signed front-end download URL for an on-disk CV (new transient).
 */
/**
 * Extract ?nsc_job_cv_dl= token from a saved full URL (legacy rows).
 */
function nsc_job_apply_cv_download_token_from_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $q = wp_parse_url($url, PHP_URL_QUERY);
    if (!is_string($q) || $q === '') {
        return '';
    }
    parse_str($q, $parts);

    return isset($parts['nsc_job_cv_dl']) ? trim((string) $parts['nsc_job_cv_dl']) : '';
}

/**
 * Public download URL for a CV download token (same as email / front).
 */
function nsc_job_apply_cv_download_public_url(string $token): string
{
    $token = trim($token);

    return add_query_arg('nsc_job_cv_dl', $token, home_url('/'));
}

function nsc_job_apply_signed_download_url_for_archive_path(string $absPath, string $downloadFilename): ?string
{
    $absPath = wp_normalize_path($absPath);
    if ($absPath === '' || !is_readable($absPath) || !is_file($absPath)) {
        return null;
    }
    $upload = wp_upload_dir();
    $baseReal = isset($upload['basedir']) ? realpath($upload['basedir']) : false;
    $fileReal = realpath($absPath);
    if ($fileReal === false || $baseReal === false) {
        return null;
    }
    $fileNorm = wp_normalize_path($fileReal);
    $baseNorm = trailingslashit(wp_normalize_path($baseReal));
    if (strpos($fileNorm, $baseNorm) !== 0) {
        return null;
    }
    $dlToken = wp_generate_password(48, false, false);
    set_transient(
        nsc_job_cv_download_transient_key($dlToken),
        [
            'path' => $fileReal,
            'filename' => $downloadFilename !== '' ? $downloadFilename : basename($fileReal),
        ],
        NSC_JOB_CV_DOWNLOAD_TTL
    );

    return add_query_arg('nsc_job_cv_dl', $dlToken, home_url('/'));
}

/**
 * CFDB7-stored copy under uploads/cfdb7_uploads/.
 *
 * @param array<string, mixed> $data
 */
function nsc_job_apply_cfdb7_resolve_cfdb7_upload_path(array $data): ?string
{
    $fn = $data['cv_filecfdb7_file'] ?? '';
    if (!is_string($fn) || $fn === '') {
        return null;
    }
    $upload = wp_upload_dir();
    if (!empty($upload['error']) || empty($upload['basedir'])) {
        return null;
    }
    $path = trailingslashit($upload['basedir']) . 'cfdb7_uploads/' . $fn;
    $path = wp_normalize_path($path);

    return is_readable($path) && is_file($path) ? $path : null;
}

/**
 * Unix timestamp for CFDB7 form_date (site timezone).
 */
function nsc_job_apply_cfdb7_form_timestamp(string $formDateMysql): int
{
    if ($formDateMysql === '') {
        return 0;
    }
    $u = mysql2date('U', $formDateMysql, false);
    if (is_numeric($u) && (int) $u > 0) {
        return (int) $u;
    }
    $t = strtotime($formDateMysql);

    return $t !== false ? $t : 0;
}

/**
 * @return array<int, array{path: string, mtime: int}>
 */
function nsc_job_apply_cfdb7_list_nsc_archive_cv_files(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $upload = wp_upload_dir();
    if (!empty($upload['error']) || empty($upload['basedir'])) {
        $cache = [];

        return $cache;
    }
    $base = trailingslashit($upload['basedir']) . NSC_JOB_CV_ARCHIVE_SUBDIR;
    if (!is_dir($base)) {
        $cache = [];

        return $cache;
    }
    $out = [];
    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
        );
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (!preg_match('/^cv-.*\.(pdf|doc|docx)$/i', $name)) {
                continue;
            }
            $path = wp_normalize_path($file->getPathname());
            $mt = $file->getMTime();
            $out[] = ['path' => $path, 'mtime' => $mt];
        }
    } catch (Throwable $e) {
        $cache = [];

        return $cache;
    }
    usort(
        $out,
        static function (array $a, array $b): int {
            if ($a['mtime'] === $b['mtime']) {
                return strcmp($a['path'], $b['path']);
            }

            return $a['mtime'] <=> $b['mtime'];
        }
    );
    $cache = $out;

    return $cache;
}

/**
 * Closest on-disk archive file to the submission time, excluding paths already consumed in this batch (not marked here).
 *
 * @param array<string, true> $usedPaths
 */
function nsc_job_apply_cfdb7_find_closest_unused_archive_path(int $formTs, array $usedPaths): ?string
{
    if ($formTs <= 0) {
        return null;
    }
    $slack = (int) apply_filters('nsc_job_apply_cfdb7_restore_archive_slack_seconds', 72 * HOUR_IN_SECONDS);
    $files = nsc_job_apply_cfdb7_list_nsc_archive_cv_files();
    $bestPath = null;
    $bestDist = PHP_INT_MAX;
    foreach ($files as $row) {
        $path = $row['path'];
        if (isset($usedPaths[$path])) {
            continue;
        }
        $d = abs($row['mtime'] - $formTs);
        if ($d > $slack) {
            continue;
        }
        if ($d < $bestDist) {
            $bestDist = $d;
            $bestPath = $path;
        }
    }

    return $bestPath;
}

/**
 * True when this row likely included a CV file (CFDB7 copy, staged upload, or ambiguous without LinkedIn-only signal).
 *
 * @param array<string, mixed> $data
 */
function nsc_job_apply_cfdb7_submission_likely_had_cv_file(array $data): bool
{
    if (!empty($data['cv_filecfdb7_file'])) {
        return true;
    }
    $tok = isset($data['nsc_cv_staging_token']) ? trim((string) $data['nsc_cv_staging_token']) : '';
    if (strlen($tok) >= 16) {
        return true;
    }
    $li = isset($data['linkedin_profile']) ? $data['linkedin_profile'] : '';
    if (is_array($li)) {
        $li = trim(implode(' ', array_map('strval', $li)));
    } else {
        $li = trim((string) $li);
    }
    if ($li !== '') {
        return false;
    }

    return (bool) apply_filters('nsc_job_apply_cfdb7_restore_try_rows_without_cv_or_linkedin_signals', false);
}

/**
 * @param array<string, mixed> $data
 * @param array<string, true>  $usedArchivePaths
 * @return array{url: string, archive_path: ?string}|null
 */
function nsc_job_apply_cfdb7_try_build_restored_download_url(array $data, string $formDateMysql, array $usedArchivePaths = []): ?array
{
    $cfdbPath = nsc_job_apply_cfdb7_resolve_cfdb7_upload_path($data);
    if ($cfdbPath !== null) {
        $fn = $data['cv_filecfdb7_file'] ?? '';
        $url = nsc_job_apply_signed_download_url_for_archive_path(
            $cfdbPath,
            is_string($fn) && $fn !== '' ? basename($fn) : basename($cfdbPath)
        );
        if ($url === null) {
            return null;
        }

        return ['url' => $url, 'archive_path' => null];
    }
    if (!nsc_job_apply_cfdb7_submission_likely_had_cv_file($data)) {
        return null;
    }
    $ts = nsc_job_apply_cfdb7_form_timestamp($formDateMysql);
    $arch = nsc_job_apply_cfdb7_find_closest_unused_archive_path($ts, $usedArchivePaths);
    if ($arch === null) {
        return null;
    }
    $url = nsc_job_apply_signed_download_url_for_archive_path($arch, basename($arch));
    if ($url === null) {
        return null;
    }

    return ['url' => $url, 'archive_path' => $arch];
}

/**
 * Persist short download token for CFDB7 list (full URL is derived for email/detail).
 *
 * @param array<string, mixed> $data
 */
function nsc_job_apply_cfdb7_persist_download_token(int $form_id, array $data, string $downloadUrl): bool
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';
    $tok = nsc_job_apply_cv_download_token_from_url($downloadUrl);
    if ($tok === '') {
        return false;
    }
    unset(
        $data['nsc_cv_staging_token'],
        $data['_nsc_cv_download_url'],
        $data['nsc_cv_download'],
        $data['nsc_cv_dl_token']
    );
    $data['nsc_cv_download_url'] = $tok;

    return $wpdb->update(
        $table_name,
        ['form_value' => serialize($data)],
        ['form_id' => $form_id],
        ['%s'],
        ['%d']
    ) !== false;
}

/**
 * Remove transient + on-disk file for a stored download token (before replacing a submission CV).
 */
function nsc_job_apply_cfdb7_release_stored_cv_download_raw(?string $raw): void
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return;
    }
    $tok = stripos($raw, 'http') === 0 ? nsc_job_apply_cv_download_token_from_url($raw) : $raw;
    $tok = trim($tok);
    if ($tok === '' || strlen($tok) < 16) {
        return;
    }
    $key = nsc_job_cv_download_transient_key($tok);
    $prev = get_transient($key);
    delete_transient($key);
    if (!is_array($prev) || empty($prev['path'])) {
        return;
    }
    $path = wp_normalize_path((string) $prev['path']);
    $upload = wp_upload_dir();
    if (!empty($upload['error']) || empty($upload['basedir'])) {
        return;
    }
    $base = trailingslashit(wp_normalize_path($upload['basedir']));
    if (strpos($path, $base) !== 0 || !is_file($path)) {
        return;
    }
    @unlink($path);
}

/**
 * Theme option + same capability as CV link restore: replace CV from CFDB7 list.
 */
function nsc_job_apply_cfdb7_current_user_can_reupload_cv(): bool
{
    if (!function_exists('nsc_feature_cfdb7_cv_reupload_enabled') || !nsc_feature_cfdb7_cv_reupload_enabled()) {
        return false;
    }

    return nsc_job_apply_cfdb7_current_user_can_restore();
}

/**
 * Admin: replace archived CV + download token for one CFDB7 row (job apply form).
 *
 * @param array<string, mixed> $file `$_FILES['cv_file']`
 * @return true|\WP_Error
 */
function nsc_job_apply_cfdb7_replace_submission_cv(int $form_post_id, int $form_id, array $file)
{
    if ($form_post_id <= 0 || $form_id <= 0) {
        return new \WP_Error('nsc_cv_bad', __('Invalid submission.', 'NscSoftware'));
    }
    if (!nsc_job_apply_cfdb7_form_post_is_job_apply($form_post_id)) {
        return new \WP_Error('nsc_cv_bad', __('Not a job application form.', 'NscSoftware'));
    }
    if (!nsc_job_apply_cfdb7_current_user_can_reupload_cv()) {
        return new \WP_Error('nsc_cv_cap', __('You do not have permission to replace this CV.', 'NscSoftware'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT form_value FROM {$table_name} WHERE form_post_id = %d AND form_id = %d LIMIT 1",
            $form_post_id,
            $form_id
        )
    );
    if ($row === null || !isset($row->form_value) || !is_string($row->form_value)) {
        return new \WP_Error('nsc_cv_missing', __('Submission not found.', 'NscSoftware'));
    }

    $data = @unserialize($row->form_value, ['allowed_classes' => false]);
    if (!is_array($data)) {
        return new \WP_Error('nsc_cv_bad', __('Invalid stored data.', 'NscSoftware'));
    }

    $err = nsc_job_cv_validate_uploaded_file($file);
    if ($err !== null) {
        return new \WP_Error('nsc_cv_file', $err);
    }

    $origName = isset($file['name']) ? sanitize_file_name((string) $file['name']) : 'cv.pdf';
    $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return new \WP_Error('nsc_cv_file', __('Invalid upload.', 'NscSoftware'));
    }

    $oldDl = isset($data['nsc_cv_download_url']) ? trim((string) $data['nsc_cv_download_url']) : '';
    nsc_job_apply_cfdb7_release_stored_cv_download_raw($oldDl);

    $upload = wp_upload_dir();
    if (!empty($upload['error']) || empty($upload['basedir'])) {
        return new \WP_Error('nsc_cv_fs', __('Upload directory is not available.', 'NscSoftware'));
    }
    $cfDir = trailingslashit($upload['basedir']) . 'cfdb7_uploads/';
    $oldCf = isset($data['cv_filecfdb7_file']) ? (string) $data['cv_filecfdb7_file'] : '';
    if ($oldCf !== '') {
        $oldCfPath = $cfDir . basename($oldCf);
        if (is_file($oldCfPath)) {
            @unlink($oldCfPath);
        }
    }

    $archived = nsc_job_cv_copy_upload_to_archive($tmp, $origName);
    if ($archived === null) {
        return new \WP_Error('nsc_cv_archive', __('Could not store the CV file.', 'NscSoftware'));
    }

    $signedUrl = nsc_job_apply_signed_download_url_for_archive_path($archived, basename($archived));
    if ($signedUrl === null || $signedUrl === '') {
        @unlink($archived);

        return new \WP_Error('nsc_cv_token', __('Could not create a download link.', 'NscSoftware'));
    }

    $newTok = nsc_job_apply_cv_download_token_from_url($signedUrl);
    if ($newTok === '') {
        @unlink($archived);

        return new \WP_Error('nsc_cv_token', __('Could not create a download link.', 'NscSoftware'));
    }

    unset(
        $data['nsc_cv_staging_token'],
        $data['_nsc_cv_download_url'],
        $data['nsc_cv_download'],
        $data['nsc_cv_dl_token']
    );
    $data['nsc_cv_download_url'] = $newTok;

    if (wp_mkdir_p($cfDir)) {
        $newCfName = wp_unique_filename($cfDir, $origName !== '' ? $origName : basename($archived));
        $cfDest = $cfDir . $newCfName;
        if (@copy($archived, $cfDest)) {
            @chmod($cfDest, 0640);
            $data['cv_filecfdb7_file'] = $newCfName;
        }
    }

    $ok = $wpdb->update(
        $table_name,
        ['form_value' => serialize($data)],
        [
            'form_id' => $form_id,
            'form_post_id' => $form_post_id,
        ],
        ['%s'],
        ['%d', '%d']
    );

    if ($ok === false) {
        return new \WP_Error('nsc_cv_db', __('Could not save the submission.', 'NscSoftware'));
    }

    return true;
}

/**
 * @return void
 */
function nsc_job_apply_cfdb7_ajax_reupload_cv(): void
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['nonce'])), 'nsc_cfdb7_reupload_cv')) {
        wp_send_json_error(['message' => __('Security check failed.', 'NscSoftware')], 403);
    }
    if (!function_exists('nsc_feature_cfdb7_cv_reupload_enabled') || !nsc_feature_cfdb7_cv_reupload_enabled()) {
        wp_send_json_error(['message' => __('This feature is disabled in NSC Theme Options.', 'NscSoftware')], 403);
    }
    if (!nsc_job_apply_cfdb7_current_user_can_reupload_cv()) {
        wp_send_json_error(['message' => __('You do not have permission.', 'NscSoftware')], 403);
    }

    $form_post_id = isset($_POST['form_post_id']) ? (int) $_POST['form_post_id'] : 0;
    $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
    if (empty($_FILES['cv_file']) || !is_array($_FILES['cv_file'])) {
        wp_send_json_error(['message' => __('No file received.', 'NscSoftware')], 400);
    }

    $result = nsc_job_apply_cfdb7_replace_submission_cv($form_post_id, $form_id, $_FILES['cv_file']);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success(['message' => __('CV replaced. The new signed link is active.', 'NscSoftware')]);
}

add_action('wp_ajax_nsc_cfdb7_reupload_cv', 'nsc_job_apply_cfdb7_ajax_reupload_cv');

/**
 * Coalesce old `nsc_cv_dl_token` or legacy full URL in `nsc_cv_download_url` into token-only storage.
 *
 * @param array<string, mixed> $data
 */
function nsc_job_apply_cfdb7_migrate_cv_field_storage(int $form_id, array $data): bool
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';
    $changed = false;
    if (!empty($data['nsc_cv_dl_token']) && is_string($data['nsc_cv_dl_token'])) {
        $t = trim($data['nsc_cv_dl_token']);
        if ($t !== '') {
            unset($data['nsc_cv_dl_token']);
            $data['nsc_cv_download_url'] = $t;
            $changed = true;
        }
    }
    if (!$changed) {
        $legacy = $data['nsc_cv_download_url'] ?? '';
        if (is_string($legacy) && $legacy !== '' && stripos(trim($legacy), 'http') === 0) {
            $tok = nsc_job_apply_cv_download_token_from_url($legacy);
            if ($tok !== '') {
                unset($data['nsc_cv_staging_token'], $data['_nsc_cv_download_url'], $data['nsc_cv_download']);
                $data['nsc_cv_download_url'] = $tok;
                $changed = true;
            }
        }
    }
    if (!$changed) {
        return false;
    }

    return $wpdb->update(
        $table_name,
        ['form_value' => serialize($data)],
        ['form_id' => $form_id],
        ['%s'],
        ['%d']
    ) !== false;
}

/**
 * Backfill nsc_cv_download_url for one DB row when the file still exists (CFDB7 copy or nsc-job-cv archive).
 *
 * @param array<string, true> $usedArchivePaths
 * @return bool True if the row was updated
 */
function nsc_job_apply_cfdb7_restore_row_if_missing(int $form_post_id, int $form_id, string $form_date_mysql, array $data, array &$usedArchivePaths = []): bool
{
    if (nsc_job_apply_cfdb7_migrate_cv_field_storage($form_id, $data)) {
        return true;
    }
    $cvRaw = isset($data['nsc_cv_download_url']) ? trim((string) $data['nsc_cv_download_url']) : '';
    if ($cvRaw !== '' && stripos($cvRaw, 'http') !== 0) {
        return false;
    }
    $built = nsc_job_apply_cfdb7_try_build_restored_download_url($data, $form_date_mysql, $usedArchivePaths);
    if ($built === null || $built['url'] === '') {
        return false;
    }
    $url = $built['url'];
    $ok = nsc_job_apply_cfdb7_persist_download_token($form_id, $data, $url);
    if ($ok && $built['archive_path'] !== null && $built['archive_path'] !== '') {
        $usedArchivePaths[$built['archive_path']] = true;
    }

    return $ok;
}

/**
 * Process up to $max rows missing a download URL (oldest first).
 */
function nsc_job_apply_cfdb7_batch_restore_missing_cv_urls(int $form_post_id, int $max = 50): int
{
    if (!nsc_job_apply_cfdb7_current_user_can_restore()) {
        return 0;
    }
    if (!nsc_job_apply_cfdb7_form_post_is_job_apply($form_post_id)) {
        return 0;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT form_id, form_value, form_date FROM {$table_name} WHERE form_post_id = %d ORDER BY form_id ASC LIMIT 300",
            $form_post_id
        ),
        ARRAY_A
    );
    if (!is_array($rows)) {
        return 0;
    }
    $updated = 0;
    /** @var array<string, true> $usedArchivePaths */
    $usedArchivePaths = [];
    foreach ($rows as $row) {
        if ($updated >= $max) {
            break;
        }
        $form_id = isset($row['form_id']) ? (int) $row['form_id'] : 0;
        if ($form_id <= 0) {
            continue;
        }
        $data = @unserialize((string) ($row['form_value'] ?? ''), ['allowed_classes' => false]);
        if (!is_array($data)) {
            continue;
        }
        $cv = isset($data['nsc_cv_download_url']) ? trim((string) $data['nsc_cv_download_url']) : '';
        if ($cv !== '' && stripos($cv, 'http') !== 0) {
            continue;
        }
        if (nsc_job_apply_cfdb7_restore_row_if_missing($form_post_id, $form_id, (string) ($row['form_date'] ?? ''), $data, $usedArchivePaths)) {
            ++$updated;
        }
    }

    return $updated;
}

function nsc_job_cv_allowed_extensions(): array
{
    return ['pdf', 'doc', 'docx'];
}

function nsc_job_cv_validate_uploaded_file(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return __('Upload failed. Please try again.', 'NscSoftware');
    }
    if (($file['size'] ?? 0) <= 0) {
        return __('The file is empty. Please choose a valid CV file.', 'NscSoftware');
    }
    if (($file['size'] ?? 0) > NSC_JOB_CV_MAX_BYTES) {
        return __('File is too large (max 5 MB).', 'NscSoftware');
    }
    $checked = wp_check_filetype_and_ext($file['tmp_name'], $file['name'] ?? '');
    $ext = isset($checked['ext']) ? strtolower((string) $checked['ext']) : '';
    if (!in_array($ext, nsc_job_cv_allowed_extensions(), true)) {
        return __('Only DOC, DOCX, or PDF files are allowed.', 'NscSoftware');
    }

    return null;
}

/**
 * AJAX: stage CV in uploads tmp; returns token for hidden field.
 */
function nsc_job_cv_ajax_stage(): void
{
    $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
    if ($jobId <= 0 || get_post_status($jobId) !== 'publish' || get_post_type($jobId) !== 'job') {
        wp_send_json_error(['message' => __('Invalid job.', 'NscSoftware')], 400);
    }
    $nonce = isset($_POST['nonce']) ? (string) $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'nsc_job_apply_cv_' . $jobId)) {
        wp_send_json_error(['message' => __('Security check failed. Please reload the page.', 'NscSoftware')], 403);
    }
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        wp_send_json_error(['message' => __('No file received.', 'NscSoftware')], 400);
    }
    $err = nsc_job_cv_validate_uploaded_file($_FILES['file']);
    if ($err !== null) {
        wp_send_json_error(['message' => $err], 400);
    }
    $tmpBase = nsc_job_cv_tmp_base_dir();
    if ($tmpBase === '' || !wp_mkdir_p($tmpBase)) {
        wp_send_json_error(['message' => __('Could not prepare upload directory.', 'NscSoftware')], 500);
    }
    $origName = isset($_FILES['file']['name']) ? sanitize_file_name((string) $_FILES['file']['name']) : 'cv.pdf';
    $unique = wp_unique_filename($tmpBase, $origName);
    $dest = $tmpBase . '/' . $unique;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        wp_send_json_error(['message' => __('Could not save file.', 'NscSoftware')], 500);
    }
    @chmod($dest, 0640);
    $token = wp_generate_password(48, false, false);
    $mime = isset($_FILES['file']['type']) ? (string) $_FILES['file']['type'] : 'application/octet-stream';
    set_transient(
        nsc_job_cv_staging_transient_key($token),
        [
            'path' => $dest,
            'name' => $origName,
            'mime' => $mime,
            'job_id' => $jobId,
            'time' => time(),
        ],
        NSC_JOB_CV_STAGING_TTL
    );
    wp_send_json_success([
        'token' => $token,
        'name' => $origName,
    ]);
}

add_action('wp_ajax_nsc_job_stage_cv', 'nsc_job_cv_ajax_stage');
add_action('wp_ajax_nopriv_nsc_job_stage_cv', 'nsc_job_cv_ajax_stage');

/**
 * Signed download: ?nsc_job_cv_dl=TOKEN
 */
function nsc_job_cv_maybe_download(): void
{
    if (!isset($_GET['nsc_job_cv_dl'])) {
        return;
    }
    $token = sanitize_text_field(wp_unslash((string) $_GET['nsc_job_cv_dl']));
    if (strlen($token) < 16) {
        status_header(404);
        nsc_job_cv_die_not_found();
    }
    $data = get_transient(nsc_job_cv_download_transient_key($token));
    if (!is_array($data) || empty($data['path'])) {
        status_header(410);
        wp_die(esc_html__('This download link has expired or is invalid.', 'NscSoftware'), '', ['response' => 410]);
    }
    $path = (string) $data['path'];
    $fileReal = realpath($path);
    $upload = wp_upload_dir();
    $baseReal = isset($upload['basedir']) ? realpath($upload['basedir']) : false;
    if ($fileReal === false || $baseReal === false) {
        status_header(404);
        nsc_job_cv_die_not_found();
    }
    $fileNorm = wp_normalize_path($fileReal);
    $baseNorm = trailingslashit(wp_normalize_path($baseReal));
    if (strpos($fileNorm, $baseNorm) !== 0) {
        status_header(404);
        nsc_job_cv_die_not_found();
    }
    if (!is_readable($fileReal) || !is_file($fileReal)) {
        status_header(404);
        nsc_job_cv_die_not_found();
    }
    $filename = isset($data['filename']) ? (string) $data['filename'] : basename($fileReal);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Content-Length: ' . (string) filesize($fileReal));
    header('X-Robots-Tag: noindex, nofollow');
    readfile($fileReal);
    exit;
}

function nsc_job_cv_die_not_found(): void
{
    wp_die(esc_html__('File not found.', 'NscSoftware'), '', ['response' => 404]);
}

add_action('init', 'nsc_job_cv_maybe_download', 0);

/** Ensure job context hiddens have values in final HTML (Timber often breaks is_singular + tag values). */
add_filter('wpcf7_form_elements', static function ($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    return nsc_job_apply_inject_job_context_hidden_inputs($html);
}, 19, 1);

/** Prefill hidden job title + canonical URL on job singles (no numeric ID in form). */
add_filter('wpcf7_form_tag', static function ($tag, $unused = null) {
    $isHidden = ($tag->basetype ?? '') === 'hidden' || ($tag->type ?? '') === 'hidden';
    if (!is_object($tag) || !$isHidden) {
        return $tag;
    }
    $name = (string) ($tag->name ?? '');
    $jobId = nsc_job_apply_current_job_post_id();
    if ($jobId <= 0) {
        return $tag;
    }
    if ($name === 'nsc_job_title') {
        $tag->values = [get_the_title($jobId)];
    } elseif ($name === 'nsc_job_url') {
        $tag->values = [get_permalink($jobId)];
    }

    return $tag;
}, 25, 2);

/**
 * Migrate stored form: optional LinkedIn, hidden fields (re-run create script also works).
 *
 * @param string|null $form
 */
add_filter('wpcf7_contact_form_property_form', static function ($form, $contact_form) {
    if (!$contact_form instanceof \WPCF7_ContactForm
        || !nsc_job_apply_is_target_form($contact_form)
        || !is_string($form)
        || $form === ''
    ) {
        return $form;
    }
    if (strpos($form, '[url* linkedin_profile') !== false) {
        $form = str_replace('[url* linkedin_profile', '[url linkedin_profile', $form);
    }
    if (strpos($form, 'nsc_cv_staging_token') === false) {
        $inject = "[hidden nsc_cv_staging_token]\n[hidden nsc_job_title]\n[hidden nsc_job_url]\n";
        $pos = strpos($form, '<div class="career-details__apply-row career-details__apply-row--2">');
        if ($pos !== false) {
            $form = substr_replace($form, $inject, $pos, 0);
        }
    }
    // Legacy forms: replace job post ID hidden with title + URL.
    if (strpos($form, '[hidden nsc_job_post_id]') !== false) {
        $form = str_replace(
            "[hidden nsc_cv_staging_token]\n[hidden nsc_job_post_id]\n",
            "[hidden nsc_cv_staging_token]\n[hidden nsc_job_title]\n[hidden nsc_job_url]\n",
            $form
        );
        $form = str_replace('[hidden nsc_job_post_id]', "[hidden nsc_job_title]\n[hidden nsc_job_url]", $form);
    }

    return $form;
}, 12, 2);

/** Cross-field validation: LinkedIn OR CV (direct upload or staged). */
add_filter('wpcf7_validate', static function ($result, $tags) {
    $contact_form = \WPCF7_ContactForm::get_current();
    if (!nsc_job_apply_is_target_form($contact_form)) {
        return $result;
    }
    $submission = \WPCF7_Submission::get_instance();
    if (!$submission instanceof \WPCF7_Submission) {
        return $result;
    }
    $posted = $submission->get_posted_data();
    $linkedin = isset($posted['linkedin_profile']) ? trim((string) $posted['linkedin_profile']) : '';
    if ($linkedin !== '') {
        $url = $linkedin;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result->invalidate('linkedin_profile', __('Please enter a valid URL for LinkedIn.', 'NscSoftware'));
        }
    }
    $token = isset($posted['nsc_cv_staging_token']) ? trim((string) $posted['nsc_cv_staging_token']) : '';
    $staging = $token !== '' ? nsc_job_cv_get_staging($token) : null;
    $hasStagedCv = $staging !== null;
    $files = $_FILES['cv_file'] ?? null;
    $hasDirectCv = is_array($files)
        && !empty($files['name'])
        && (int) ($files['size'] ?? 0) > 0
        && (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($linkedin === '' && !$hasStagedCv && !$hasDirectCv) {
        $result->invalidate(
            'linkedin_profile',
            __('Please enter your LinkedIn profile URL or upload your CV.', 'NscSoftware')
        );
    }
    $phone = isset($posted['applicant_phone']) ? trim((string) $posted['applicant_phone']) : '';
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone) ?? '') < 8) {
        $result->invalidate('applicant_phone', __('Please enter a valid phone number.', 'NscSoftware'));
    }

    if (nsc_job_apply_verify_job_context_from_posted($posted) <= 0) {
        $result->invalidate(
            'nsc_job_url',
            __('Invalid job context. Please reload the page and try again.', 'NscSoftware')
        );
    }

    return $result;
}, 30, 2);

/**
 * Runs before CFDB7 (wpcf7_before_send_mail @10): archive CV, set download transient once, cache result for mail + DB injection.
 *
 * @return array{url: string, dl_token: string, staging_attachment: ?string}
 */
function nsc_job_apply_prepare_cv_download_bundle(?\WPCF7_ContactForm $contact_form = null): array
{
    static $done = false;
    /** @var array{url: string, dl_token: string, staging_attachment: ?string} */
    static $cached = ['url' => '', 'dl_token' => '', 'staging_attachment' => null];

    if ($done) {
        return $cached;
    }
    $done = true;

    if ($contact_form === null) {
        $submission = \WPCF7_Submission::get_instance();
        $contact_form = $submission instanceof \WPCF7_Submission ? $submission->get_contact_form() : null;
    }
    if (!$contact_form instanceof \WPCF7_ContactForm || !nsc_job_apply_is_target_form($contact_form)) {
        return $cached;
    }
    $submission = \WPCF7_Submission::get_instance();
    if (!$submission instanceof \WPCF7_Submission) {
        return $cached;
    }

    $posted = $submission->get_posted_data();
    $token = isset($posted['nsc_cv_staging_token']) ? trim((string) $posted['nsc_cv_staging_token']) : '';
    $staging = $token !== '' ? nsc_job_cv_get_staging($token) : null;
    $uploaded = $submission->uploaded_files();
    $cf7CvPaths = isset($uploaded['cv_file']) ? (array) $uploaded['cv_file'] : [];
    $hasCf7Cv = !empty($cf7CvPaths);

    if ($hasCf7Cv) {
        $first = (string) reset($cf7CvPaths);
        if ($first !== '' && is_readable($first)) {
            $copy = nsc_job_cv_copy_upload_to_archive($first, basename($first));
            if ($copy !== null) {
                $dlToken = wp_generate_password(48, false, false);
                set_transient(
                    nsc_job_cv_download_transient_key($dlToken),
                    [
                        'path' => $copy,
                        'filename' => basename($first),
                    ],
                    NSC_JOB_CV_DOWNLOAD_TTL
                );
                $cached['dl_token'] = $dlToken;
                $cached['url'] = add_query_arg('nsc_job_cv_dl', $dlToken, home_url('/'));
            }
        }
    } elseif ($staging !== null) {
        $archived = nsc_job_cv_move_staging_to_archive($token, $staging);
        if ($archived !== null && is_readable($archived)) {
            $cached['staging_attachment'] = $archived;
            $dlToken = wp_generate_password(48, false, false);
            set_transient(
                nsc_job_cv_download_transient_key($dlToken),
                [
                    'path' => $archived,
                    'filename' => $staging['name'],
                ],
                NSC_JOB_CV_DOWNLOAD_TTL
            );
            $cached['dl_token'] = $dlToken;
            $cached['url'] = add_query_arg('nsc_job_cv_dl', $dlToken, home_url('/'));
        }
    }

    return $cached;
}

add_action('wpcf7_before_send_mail', static function ($contact_form) {
    if ($contact_form instanceof \WPCF7_ContactForm) {
        nsc_job_apply_prepare_cv_download_bundle($contact_form);
    }
}, 9, 1);

/**
 * Admin email: full details, job URL, CV download link; attach staged CV when needed.
 *
 * @param array<string, mixed> $components
 * @return array<string, mixed>
 */
add_filter('wpcf7_mail_components', static function ($components, $contact_form, $mail) {
    if (!nsc_job_apply_is_target_form($contact_form)) {
        return $components;
    }
    if (!$mail instanceof \WPCF7_Mail || $mail->get_template_name() !== 'mail') {
        return $components;
    }
    $submission = \WPCF7_Submission::get_instance();
    if (!$submission instanceof \WPCF7_Submission) {
        return $components;
    }
    $posted = $submission->get_posted_data();
    $jobId = nsc_job_apply_verify_job_context_from_posted($posted);
    $jobUrl = ($jobId > 0) ? (string) get_permalink($jobId) : nsc_job_apply_submission_string($submission, 'nsc_job_url');
    $jobTitle = ($jobId > 0) ? (string) get_the_title($jobId) : nsc_job_apply_submission_string($submission, 'nsc_job_title');

    $bundle = nsc_job_apply_prepare_cv_download_bundle($contact_form);
    $cvDownloadUrl = $bundle['url'];
    $attachments = isset($components['attachments']) ? array_values(array_filter((array) $components['attachments'])) : [];
    if ($bundle['staging_attachment'] !== null && $bundle['staging_attachment'] !== '') {
        $attachments[] = $bundle['staging_attachment'];
    }

    $firstName = nsc_job_apply_submission_string($submission, 'first_name');
    $lastName = nsc_job_apply_submission_string($submission, 'last_name');
    $email = nsc_job_apply_submission_string($submission, 'applicant_email');
    $phone = nsc_job_apply_submission_string($submission, 'applicant_phone');
    $location = nsc_job_apply_submission_string($submission, 'applicant_location');
    $position = nsc_job_apply_submission_string($submission, 'job_position');
    $comment = nsc_job_apply_submission_string($submission, 'applicant_comment');
    $linkedin = nsc_job_apply_submission_string($submission, 'linkedin_profile');
    $privacy = nsc_job_apply_submission_string($submission, 'privacy_accept');

    $lines = [
        '[NSC Careers] New job application',
        '',
        'Job title: ' . $jobTitle,
        'Job URL: ' . ($jobUrl !== '' ? $jobUrl : '(n/a)'),
        '',
        'Position (submitted): ' . $position,
        'Name: ' . trim($firstName . ' ' . $lastName),
        'Email: ' . $email,
        'Phone: ' . $phone,
        'Location: ' . $location,
        'LinkedIn: ' . ($linkedin !== '' ? $linkedin : '(not provided — CV supplied)'),
        '',
        'Comment:',
        $comment !== '' ? $comment : '(none)',
        '',
        'Privacy policy accepted: ' . (!empty($privacy) ? 'Yes' : 'No'),
        '',
    ];
    if ($cvDownloadUrl !== '') {
        $lines[] = 'CV download link (valid ~30 days, treat as confidential):';
        $lines[] = $cvDownloadUrl;
        $lines[] = '(The CV is also attached to this email when applicable.)';
    } else {
        $lines[] = 'CV: (none)';
    }
    $lines[] = '';
    $lines[] = '—';
    $lines[] = 'Site: ' . home_url('/');

    $components['body'] = implode("\n", $lines);
    $components['subject'] = sprintf(
        '[NSC Careers] Application: %s — %s %s',
        $position !== '' ? $position : $jobTitle,
        $firstName,
        $lastName
    );
    $components['attachments'] = $attachments;

    return $components;
}, 20, 3);

/** Remove orphaned staged file when applicant used direct CF7 upload instead. */
add_action('wpcf7_mail_sent', static function ($contact_form) {
    if (!nsc_job_apply_is_target_form($contact_form)) {
        return;
    }
    $submission = \WPCF7_Submission::get_instance();
    if (!$submission instanceof \WPCF7_Submission) {
        return;
    }
    $posted = $submission->get_posted_data();
    $token = isset($posted['nsc_cv_staging_token']) ? trim((string) $posted['nsc_cv_staging_token']) : '';
    if ($token === '') {
        return;
    }
    $uploaded = $submission->uploaded_files();
    if (empty($uploaded['cv_file'])) {
        return;
    }
    $staging = nsc_job_cv_get_staging($token);
    if ($staging !== null && !empty($staging['path']) && is_file($staging['path'])) {
        @unlink($staging['path']);
    }
    nsc_job_cv_delete_staging_transient($token);
}, 30, 1);

/**
 * Contact Form CFDB7 persists during wpcf7_before_send_mail @10; prepare runs at @9 so the signed CV URL can be stored (no CFDB7 plugin changes).
 * Store download token in `nsc_cv_download_url` (short string); full URL is built from the token for email/detail.
 * Staging token is not useful after save and is removed to declutter the list.
 *
 * @param array<string, mixed> $form_data
 * @return array<string, mixed>
 */
add_filter('cfdb7_before_save_data', static function ($form_data) {
    if (!is_array($form_data)) {
        return $form_data;
    }
    $bundle = nsc_job_apply_prepare_cv_download_bundle();
    $tok = $bundle['dl_token'] ?? '';
    unset(
        $form_data['nsc_cv_staging_token'],
        $form_data['_nsc_cv_download_url'],
        $form_data['nsc_cv_download'],
        $form_data['nsc_cv_dl_token']
    );
    if ($tok === '') {
        unset($form_data['nsc_cv_download_url']);

        return $form_data;
    }

    return array_merge(['nsc_cv_download_url' => $tok], $form_data);
}, 99, 1);

/**
 * Job apply submission detail: signed CV URL + clickable LinkedIn at the top (also listed as normal fields below).
 */
function nsc_job_apply_cfdb7_render_submission_application_box(int $form_post_id): void
{
    if (!nsc_job_apply_cfdb7_form_post_is_job_apply((int) $form_post_id)) {
        return;
    }
    $ufid = isset($_GET['ufid']) ? (int) $_GET['ufid'] : 0;
    if ($ufid <= 0) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'db7_forms';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT form_value, form_date FROM {$table_name} WHERE form_post_id = %d AND form_id = %d LIMIT 1",
            $form_post_id,
            $ufid
        )
    );
    if ($row === null || !isset($row->form_value) || !is_string($row->form_value)) {
        return;
    }

    if (nsc_job_apply_cfdb7_current_user_can_restore()) {
        $pre = @unserialize($row->form_value, ['allowed_classes' => false]);
        if (is_array($pre)) {
            $usedArchivePathsSingle = [];
            nsc_job_apply_cfdb7_restore_row_if_missing(
                $form_post_id,
                $ufid,
                isset($row->form_date) ? (string) $row->form_date : '',
                $pre,
                $usedArchivePathsSingle
            );
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT form_value, form_date FROM {$table_name} WHERE form_post_id = %d AND form_id = %d LIMIT 1",
                    $form_post_id,
                    $ufid
                )
            );
            if ($row === null || !isset($row->form_value) || !is_string($row->form_value)) {
                return;
            }
        }
    }

    $data = @unserialize($row->form_value, ['allowed_classes' => false]);
    if (!is_array($data)) {
        return;
    }

    $cvUrl = '';
    if (!empty($data['nsc_cv_download_url']) && is_string($data['nsc_cv_download_url'])) {
        $raw = trim($data['nsc_cv_download_url']);
        if ($raw !== '') {
            $cvUrl = stripos($raw, 'http') === 0 ? $raw : nsc_job_apply_cv_download_public_url($raw);
        }
    }
    if ($cvUrl === '' && !empty($data['nsc_cv_dl_token']) && is_string($data['nsc_cv_dl_token'])) {
        $cvUrl = nsc_job_apply_cv_download_public_url(trim($data['nsc_cv_dl_token']));
    }
    if ($cvUrl === '' && !empty($data['_nsc_cv_download_url']) && is_string($data['_nsc_cv_download_url'])) {
        $cvUrl = $data['_nsc_cv_download_url'];
    }
    if ($cvUrl === '' && !empty($data['nsc_cv_download']) && is_string($data['nsc_cv_download'])) {
        $cvUrl = $data['nsc_cv_download'];
    }

    $linkedinRaw = $data['linkedin_profile'] ?? '';
    $linkedin = is_array($linkedinRaw)
        ? trim(implode(', ', array_map('strval', $linkedinRaw)))
        : trim((string) $linkedinRaw);

    if ($cvUrl === '' && $linkedin === '') {
        return;
    }

    echo '<div class="nsc-cfdb7-application-box" style="background:#f0f6fc;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0;max-width:720px">';
    echo '<p style="margin:0 0 8px"><strong>' . esc_html__('Application links', 'NscSoftware') . '</strong></p>';

    if ($cvUrl !== '') {
        $cvLabel = '';
        if (!empty($data['nsc_cv_download_url']) && is_string($data['nsc_cv_download_url'])) {
            $r = trim($data['nsc_cv_download_url']);
            if ($r !== '' && stripos($r, 'http') !== 0) {
                $cvLabel = $r;
            }
        }
        if ($cvLabel === '' && !empty($data['nsc_cv_dl_token']) && is_string($data['nsc_cv_dl_token'])) {
            $cvLabel = trim($data['nsc_cv_dl_token']);
        }
        if ($cvLabel === '') {
            $cvLabel = nsc_job_apply_cv_download_token_from_url($cvUrl);
        }
        if ($cvLabel === '' && preg_match('/[?&]nsc_job_cv_dl=([^&]+)/', $cvUrl, $m)) {
            $cvLabel = rawurldecode((string) $m[1]);
        }
        if ($cvLabel === '') {
            $cvLabel = $cvUrl;
        }
        echo '<p style="margin:0 0 8px"><strong>' . esc_html__('CV download (signed, ~30 days)', 'NscSoftware') . '</strong><br />';
        echo '<a href="' . esc_url($cvUrl) . '" target="_blank" rel="noopener noreferrer">' . esc_html($cvLabel) . '</a></p>';
    }
    if ($linkedin !== '') {
        $href = $linkedin;
        if (!preg_match('#^https?://#i', $href)) {
            $href = 'https://' . ltrim($href, '/');
        }
        echo '<p style="margin:0"><strong>' . esc_html__('LinkedIn profile', 'NscSoftware') . '</strong><br />';
        echo '<a href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer">' . esc_html($linkedin) . '</a></p>';
    }
    echo '</div>';
}

add_action('cfdb7_after_formdetails_title', 'nsc_job_apply_cfdb7_render_submission_application_box', 10, 1);

/**
 * CFDB7 list: friendlier “CV download” label; LinkedIn column; drop duplicate token-only column key.
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
add_filter('cfdb7_admin_subpage_columns', static function ($columns, $form_post_id) {
    if (!nsc_job_apply_cfdb7_form_post_is_job_apply((int) $form_post_id)) {
        return $columns;
    }
    unset($columns['nsc_cv_dl_token']);
    /**
     * CFDB7 only adds columns from the newest row’s first four fields; if that row has no CV token
     * (e.g. LinkedIn-only), `nsc_cv_download_url` never appears. Always inject so the column exists.
     */
    $inject = [
        'nsc_cv_download_url' => __('CV download', 'NscSoftware'),
        'linkedin_profile' => __('LinkedIn', 'NscSoftware'),
    ];
    if (nsc_job_apply_cfdb7_current_user_can_reupload_cv()) {
        $inject['nsc_cv_reupload'] = __('Replace CV', 'NscSoftware');
    }
    $out = [];
    foreach ($columns as $k => $v) {
        if ($k === 'cb') {
            $out[$k] = $v;
            foreach ($inject as $ik => $il) {
                if (!isset($out[$ik])) {
                    $out[$ik] = $il;
                }
            }

            continue;
        }
        if (isset($inject[$k])) {
            continue;
        }
        $out[$k] = $v;
    }

    return $out;
}, 25, 2);

/**
 * Backfill missing nsc_cv_download_url rows when the admin clicks “Regenerate…” on the CFDB7 list (GET + nonce + redirect).
 */
add_action('admin_init', static function (): void {
    if (!is_admin()) {
        return;
    }
    if (($_GET['page'] ?? '') !== 'cfdb7-list.php') {
        return;
    }
    if (($_GET['nsc_cv_regen'] ?? '') !== '1') {
        return;
    }
    if (isset($_GET['ufid'])) {
        return;
    }
    $fid = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
    if ($fid <= 0 || !nsc_job_apply_cfdb7_form_post_is_job_apply($fid)) {
        return;
    }
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'nsc_cv_regen_' . $fid)) {
        return;
    }
    if (!nsc_job_apply_cfdb7_current_user_can_restore()) {
        set_transient('nsc_cv_regen_err_' . get_current_user_id(), 'cap', 120);
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'cfdb7-list.php',
                    'fid' => $fid,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }
    $restored = nsc_job_apply_cfdb7_batch_restore_missing_cv_urls($fid, 100);
    set_transient('nsc_cv_regen_done_' . get_current_user_id(), $restored, 120);
    wp_safe_redirect(
        add_query_arg(
            [
                'page' => 'cfdb7-list.php',
                'fid' => $fid,
            ],
            admin_url('admin.php')
        )
    );
    exit;
}, 25);

/**
 * CFDB7 wraps every cell in a link to the submission detail page; retarget CV / LinkedIn cells to the real URL.
 */
add_action('admin_print_footer_scripts', static function (): void {
    if (!is_admin()) {
        return;
    }
    if (($_GET['page'] ?? '') !== 'cfdb7-list.php') {
        return;
    }
    $fid = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
    if ($fid <= 0 || !nsc_job_apply_cfdb7_form_post_is_job_apply($fid)) {
        return;
    }
    $homeJson = wp_json_encode(home_url('/'));
    echo '<script>window.nscCvDlBase=' . $homeJson . ';</script>';
    echo <<<'JS'
<script>
(function () {
  function fixCvDownloadCell(td) {
    var a = td.querySelector('a');
    if (!a) {
      return;
    }
    var t = (a.textContent || '').trim();
    if (/^https?:\/\//i.test(t) && t.indexOf('nsc_job_cv_dl') !== -1) {
      a.setAttribute('href', t);
      var tm = t.match(/[?&]nsc_job_cv_dl=([^&]+)/);
      if (tm) {
        try {
          a.textContent = decodeURIComponent(tm[1]);
        } catch (e) {
          a.textContent = tm[1];
        }
      }
    } else if (t.length >= 16 && !/\s/.test(t) && window.nscCvDlBase) {
      var b = window.nscCvDlBase;
      var sep = b.indexOf('?') >= 0 ? '&' : '?';
      a.setAttribute('href', b + sep + 'nsc_job_cv_dl=' + encodeURIComponent(t));
    } else {
      return;
    }
    a.setAttribute('target', '_blank');
    a.setAttribute('rel', 'noopener noreferrer');
  }
  document.querySelectorAll('td.column-nsc_cv_download_url, td.column-nsc_cv_dl_token').forEach(fixCvDownloadCell);
  document.querySelectorAll('td.column-linkedin_profile').forEach(function (td) {
    var a = td.querySelector('a');
    if (!a) {
      return;
    }
    var t = (a.textContent || '').trim();
    if (!/^https?:\/\//i.test(t)) {
      return;
    }
    a.setAttribute('href', t);
    a.setAttribute('target', '_blank');
    a.setAttribute('rel', 'noopener noreferrer');
  });
})();
</script>
JS;
    if (nsc_job_apply_cfdb7_current_user_can_reupload_cv()) {
        $reuploadCfg = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nsc_cfdb7_reupload_cv'),
            'formPostId' => $fid,
            'l10n' => [
                'button' => __('Upload', 'NscSoftware'),
                'pick' => __('Choose a PDF, DOC, or DOCX file first.', 'NscSoftware'),
                'uploading' => __('Uploading…', 'NscSoftware'),
                'done' => __('CV replaced. Reloading…', 'NscSoftware'),
                'err' => __('Upload failed.', 'NscSoftware'),
            ],
        ];
        echo '<script>window.nscCfdb7CvReupload=' . wp_json_encode($reuploadCfg, JSON_UNESCAPED_SLASHES) . ';</script>';
        echo <<<'JS'
<script>
(function () {
  var cfg = window.nscCfdb7CvReupload;
  if (!cfg || !cfg.ajaxUrl) {
    return;
  }
  var L = cfg.l10n || {};
  function run() {
    document.querySelectorAll('td.column-nsc_cv_reupload').forEach(function (td) {
      var tr = td.closest('tr');
      if (!tr) {
        return;
      }
      var cb = tr.querySelector('input[name="contact_form[]"]');
      if (!cb) {
        return;
      }
      var formId = cb.value;
      td.style.verticalAlign = 'middle';
      td.innerHTML =
        '<input type="file" class="nsc-cfdb7-cv-file" accept=".pdf,.doc,.docx,application/pdf" style="max-width:12rem" /> ' +
        '<button type="button" class="button button-small nsc-cfdb7-cv-btn">' +
        (L.button || 'Upload') +
        '</button> <span class="nsc-cfdb7-cv-msg" style="font-size:11px;color:#646970"></span>';
      var fileInput = td.querySelector('.nsc-cfdb7-cv-file');
      var btn = td.querySelector('.nsc-cfdb7-cv-btn');
      var msg = td.querySelector('.nsc-cfdb7-cv-msg');
      btn.addEventListener('click', function () {
        msg.textContent = '';
        if (!fileInput.files || !fileInput.files[0]) {
          msg.textContent = L.pick || '';
          return;
        }
        var fd = new FormData();
        fd.append('action', 'nsc_cfdb7_reupload_cv');
        fd.append('nonce', cfg.nonce);
        fd.append('form_post_id', String(cfg.formPostId));
        fd.append('form_id', String(formId));
        fd.append('cv_file', fileInput.files[0]);
        msg.textContent = L.uploading || '';
        btn.disabled = true;
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            btn.disabled = false;
            if (data && data.success) {
              msg.textContent = L.done || '';
              window.location.reload();
            } else {
              var m =
                data && data.data && data.data.message
                  ? data.data.message
                  : L.err || '';
              msg.textContent = m;
            }
          })
          .catch(function () {
            btn.disabled = false;
            msg.textContent = L.err || '';
          });
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
</script>
JS;
    }
}, 99);

/**
 * Submission detail: CFDB7 prints nsc_cv_download_url as plain text (often a full URL). Show token as link label only.
 */
add_action(
    'cfdb7_after_formdetails',
    static function (int $form_post_id): void {
        if (!nsc_job_apply_cfdb7_form_post_is_job_apply($form_post_id)) {
            return;
        }
        echo <<<'JS'
<script>
(function () {
  document.querySelectorAll('.cfdb7-panel-content .welcome-panel-column-container p').forEach(function (p) {
    var html = p.innerHTML;
    if (html.indexOf('nsc_job_cv_dl') === -1) {
      return;
    }
    var m = html.match(/(https?:\/\/[^<\s'"&]+)/);
    if (!m) {
      return;
    }
    var url = m[1].replace(/&amp;/g, '&');
    var tm = url.match(/[?&]nsc_job_cv_dl=([^&]+)/);
    if (!tm) {
      return;
    }
    var token;
    try {
      token = decodeURIComponent(tm[1]);
    } catch (e) {
      token = tm[1];
    }
    var b = p.querySelector('b');
    if (!b) {
      return;
    }
    var bl = (b.textContent || '').toLowerCase();
    if (bl.indexOf('cv download') === -1) {
      return;
    }
    function escAttr(s) {
      return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function escText(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    p.innerHTML = b.outerHTML + ': <a href="' + escAttr(url) + '" target="_blank" rel="noopener noreferrer">' + escText(token) + '</a>';
  });
})();
</script>
JS;
    },
    10,
    1
);

/**
 * List screen: regenerate button + help text for job apply CFDB7 form.
 */
add_action('admin_notices', static function (): void {
    if (!is_admin()) {
        return;
    }
    if (($_GET['page'] ?? '') !== 'cfdb7-list.php') {
        return;
    }
    $fid = isset($_GET['fid']) ? (int) $_GET['fid'] : 0;
    if ($fid <= 0 || !nsc_job_apply_cfdb7_form_post_is_job_apply($fid)) {
        return;
    }

    $uid = get_current_user_id();
    $doneKey = 'nsc_cv_regen_done_' . $uid;
    $errKey = 'nsc_cv_regen_err_' . $uid;
    if (get_transient($errKey) !== false) {
        delete_transient($errKey);
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo esc_html__('NSC: You do not have permission to regenerate CV download links.', 'NscSoftware');
        echo '</p></div>';
    }
    $restored = get_transient($doneKey);
    if ($restored !== false) {
        delete_transient($doneKey);
        $restored = (int) $restored;
        echo '<div class="notice notice-success is-dismissible"><p>';
        if ($restored > 0) {
            echo esc_html(
                sprintf(
                    /* translators: %d: number of submissions updated */
                    _n(
                        'NSC: Regenerated %d missing CV download link from files under uploads (CFDB7 copy or nsc-job-cv). Click the button again if you have more old rows to process (up to 100 per run).',
                        'NSC: Regenerated %d missing CV download links from files under uploads (CFDB7 copy or nsc-job-cv). Click the button again if you have more old rows to process (up to 100 per run).',
                        $restored,
                        'NscSoftware'
                    ),
                    $restored
                )
            );
        } else {
            echo esc_html__(
                'NSC: No missing links were regenerated — every checked row already had a link, or no matching file was found under uploads.',
                'NscSoftware'
            );
        }
        echo '</p></div>';
    }

    $regenUrl = wp_nonce_url(
        add_query_arg(
            [
                'page' => 'cfdb7-list.php',
                'fid' => $fid,
                'nsc_cv_regen' => '1',
            ],
            admin_url('admin.php')
        ),
        'nsc_cv_regen_' . $fid
    );

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>' . esc_html__('NSC — Job application CV / LinkedIn links', 'NscSoftware') . '</strong></p>';
    echo '<p>' . esc_html__(
        'Opening a single submission still tries to restore that row’s link if the file exists. For the whole list, use the button below (up to 100 submissions per click).',
        'NscSoftware'
    ) . '</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($regenUrl) . '">' . esc_html__(
        'Regenerate missing CV download links',
        'NscSoftware'
    ) . '</a></p>';
    echo '<p>' . esc_html__(
        'In the table, CV and LinkedIn cells are adjusted so the visible URL opens in a new tab (instead of opening the CFDB7 detail screen).',
        'NscSoftware'
    ) . '</p>';
    echo '</div>';
});

add_action('wp_enqueue_scripts', static function () {
    if (!is_singular('job') || !class_exists('WPCF7')) {
        return;
    }
    if (nsc_job_apply_cf7_form_id() <= 0) {
        return;
    }
    $base = get_template_directory();
    $uri = get_template_directory_uri();

    $lockPath = $base . '/frontend/build/js/job-apply/nsc-job-apply-position-lock.js';
    if (is_readable($lockPath)) {
        wp_enqueue_script(
            'nsc-job-apply-position-lock',
            $uri . '/frontend/build/js/job-apply/nsc-job-apply-position-lock.js',
            [],
            (string) filemtime($lockPath),
            true
        );
        wp_localize_script('nsc-job-apply-position-lock', 'nscJobApplyUi', [
            /* Demo alert: define('NSC_JOB_APPLY_DEBUG_SUBMIT', true); or add_filter('nsc_job_apply_debug_submit_click', '__return_true'); */
            'debugSubmitClick' => (bool) apply_filters(
                'nsc_job_apply_debug_submit_click',
                (defined('NSC_JOB_APPLY_DEBUG_SUBMIT') && constant('NSC_JOB_APPLY_DEBUG_SUBMIT'))
                || (defined('WP_DEBUG') && WP_DEBUG)
            ),
            'debugSubmitMessage' => (string) apply_filters(
                'nsc_job_apply_debug_submit_message',
                __('Check the browser console for a log line when the submit control is clicked.', 'NscSoftware')
            ),
        ]);
    }

    $path = $base . '/frontend/build/js/job-apply/nsc-job-apply-cv.js';
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_script(
        'nsc-job-apply-cv',
        $uri . '/frontend/build/js/job-apply/nsc-job-apply-cv.js',
        [],
        (string) filemtime($path),
        true
    );
    $jobId = get_queried_object_id();
    $form = class_exists('WPCF7_ContactForm')
        ? \WPCF7_ContactForm::get_instance(nsc_job_apply_cf7_form_id())
        : null;
    $rawMsgs = ($form instanceof \WPCF7_ContactForm) ? $form->prop('messages') : null;
    $rawMsgs = is_array($rawMsgs) ? $rawMsgs : [];
    $cvMessageKeys = [
        'upload_file_type_invalid',
        'upload_file_too_large',
        'upload_failed',
        'nsc_cv_uploading',
        'nsc_cv_uploaded',
        'nsc_cv_network_error',
        'nsc_cv_empty_file',
        'nsc_cv_bad_mime',
    ];
    $messages = [];
    foreach ($cvMessageKeys as $k) {
        $messages[$k] = isset($rawMsgs[$k]) ? (string) $rawMsgs[$k] : '';
    }
    wp_localize_script('nsc-job-apply-cv', 'nscJobApplyCv', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => 'nsc_job_stage_cv',
        'nonce' => wp_create_nonce('nsc_job_apply_cv_' . $jobId),
        'jobId' => $jobId,
        'maxBytes' => NSC_JOB_CV_MAX_BYTES,
        'messages' => $messages,
        'removeCvLabel' => __('Remove uploaded CV', 'NscSoftware'),
    ]);
}, 30);
