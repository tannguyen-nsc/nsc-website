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

function nsc_job_apply_is_target_form(?object $contact_form): bool
{
    if (!$contact_form instanceof \WPCF7_ContactForm) {
        return false;
    }

    return (int) $contact_form->id() === nsc_job_apply_cf7_form_id() && nsc_job_apply_cf7_form_id() > 0;
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
    $token = isset($posted['nsc_cv_staging_token']) ? trim((string) $posted['nsc_cv_staging_token']) : '';
    $staging = $token !== '' ? nsc_job_cv_get_staging($token) : null;
    $uploaded = $submission->uploaded_files();
    $cf7CvPaths = isset($uploaded['cv_file']) ? (array) $uploaded['cv_file'] : [];
    $hasCf7Cv = !empty($cf7CvPaths);

    $cvDownloadUrl = '';
    $attachments = isset($components['attachments']) ? array_values(array_filter((array) $components['attachments'])) : [];

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
                $cvDownloadUrl = add_query_arg('nsc_job_cv_dl', $dlToken, home_url('/'));
            }
        }
    } elseif ($staging !== null) {
        $archived = nsc_job_cv_move_staging_to_archive($token, $staging);
        if ($archived !== null && is_readable($archived)) {
            $attachments[] = $archived;
            $dlToken = wp_generate_password(48, false, false);
            set_transient(
                nsc_job_cv_download_transient_key($dlToken),
                [
                    'path' => $archived,
                    'filename' => $staging['name'],
                ],
                NSC_JOB_CV_DOWNLOAD_TTL
            );
            $cvDownloadUrl = add_query_arg('nsc_job_cv_dl', $dlToken, home_url('/'));
        }
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
