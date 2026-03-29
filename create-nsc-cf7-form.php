<?php
declare(strict_types=1);

/**
 * NSC Contact Form 7 bootstrapper.
 *
 * Usage:
 *   Default (both forms — main contact + job application):
 *     http://localhost/nsc/create-nsc-cf7-form.php?token=nsc-create-cf7-2026
 *
 *   Job application form only (legacy / direct link):
 *     http://localhost/nsc/create-nsc-cf7-form.php?token=nsc-create-cf7-2026&apply_only=1
 *
 * Main form: options nsc_cf7_primary_form_id / nsc_cf7_primary_form_title.
 * Job form: nsc_cf7_job_apply_form_id / nsc_cf7_job_apply_form_title. Job singles use it when
 * NSC Theme Options → Careers → CF7 shortcode is empty.
 *
 * The theme includes `wpcf7_autop_or_not` so Additional Settings `autop: off` is respected.
 * Job apply forms also get `acceptance_as_validation: on`.
 *
 * Polylang (optional): seed_lang / seed_lang=all like create-nsc-pages.php — sync runs per form after each upsert.
 */

$requiredToken = 'nsc-create-cf7-2026';
$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    echo "Use: ?token={$requiredToken}\n";
    echo "Job apply only: add &apply_only=1\n";
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

$nscSeedPolylang = get_template_directory() . '/inc/nscSeedPolylang.php';
if (is_readable($nscSeedPolylang)) {
    require_once $nscSeedPolylang;
}
$nscSeedCf7Pl = get_template_directory() . '/inc/nscSeedCf7Polylang.php';
if (is_readable($nscSeedCf7Pl)) {
    require_once $nscSeedCf7Pl;
}
if (function_exists('nsc_seed_bootstrap_acf_polylang_default_language')) {
    nsc_seed_bootstrap_acf_polylang_default_language();
}

if (!class_exists('WPCF7_ContactForm')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Contact Form 7 is not active.\n";
    echo "Please install and activate plugin 'contact-form-7' first.\n";
    exit;
}

/**
 * @return int Post ID or 0
 */
function nsc_cf7_find_form_id_by_title(string $title): int
{
    $posts = get_posts([
        'post_type'      => 'wpcf7_contact_form',
        'post_status'    => 'any',
        'posts_per_page' => 100,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ]);
    foreach ($posts as $p) {
        if ($p instanceof WP_Post && $p->post_title === $title) {
            return (int) $p->ID;
        }
    }

    return 0;
}

/**
 * Prefer option-stored ID (survives title edits in admin), then title match.
 *
 * @return int Post ID or 0
 */
function nsc_cf7_resolve_form_id(string $optionIdKey, string $formTitle): int
{
    $storedId = (int) get_option($optionIdKey, 0);
    if ($storedId > 0) {
        $p = get_post($storedId);
        if ($p instanceof WP_Post
            && $p->post_type === 'wpcf7_contact_form'
            && $p->post_status !== 'trash') {
            return $storedId;
        }
    }

    return nsc_cf7_find_form_id_by_title($formTitle);
}

/**
 * Trash other published CF7 posts with the same title (cleanup from older seed runs / renames).
 */
function nsc_cf7_trash_duplicate_forms_same_title(int $keepId, string $formTitle): void
{
    if ($keepId <= 0 || $formTitle === '') {
        return;
    }
    $posts = get_posts([
        'post_type'      => 'wpcf7_contact_form',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);
    foreach ($posts as $pid) {
        $pid = (int) $pid;
        if ($pid === $keepId || $pid <= 0) {
            continue;
        }
        $t = get_the_title($pid);
        if ($t === $formTitle) {
            wp_trash_post($pid);
        }
    }
}

/**
 * Create or update one NSC CF7 form. Runs Polylang translation sync when helpers exist.
 *
 * @return array{formId: int, action: string, formTitle: string, optionIdKey: string, shortcode: string, applyOnly: bool}
 */
function nsc_cf7_seed_one_form(bool $applyOnly, string $mailHost): array
{
    if ($applyOnly) {
        $formTitle = 'NSC Job Application';
        $formMarkup = <<<'FORM'
[hidden nsc_cv_staging_token]
[hidden nsc_job_title]
[hidden nsc_job_url]
<div class="career-details__apply-row career-details__apply-row--2">
<label class="career-details__field"><span class="career-details__label">First name *</span>[text* first_name autocomplete:given-name class:career-details__input placeholder "FIRST NAME *"]</label>
<label class="career-details__field"><span class="career-details__label">Last name *</span>[text* last_name autocomplete:family-name class:career-details__input placeholder "LAST NAME *"]</label>
</div>
<div class="career-details__apply-row career-details__apply-row--2">
<label class="career-details__field"><span class="career-details__label">Email *</span>[email* applicant_email autocomplete:email class:career-details__input placeholder "EMAIL *"]</label>
<label class="career-details__field"><span class="career-details__label">Phone number *</span>[tel* applicant_phone autocomplete:tel class:career-details__input placeholder "PHONE NUMBER *"]</label>
</div>
<div class="career-details__apply-row career-details__apply-row--2">
<label class="career-details__field"><span class="career-details__label">Position</span>[text job_position class:career-details__input class:career-details__input--position-lock placeholder "Position"]</label>
<label class="career-details__field career-details__field--select"><span class="career-details__label">My location *</span>[select* applicant_location class:career-details__select first_as_label "MY LOCATION *" "Vietnam" "Europe" "United States" "Australia" "Other / Remote"]</label>
</div>
<div class="career-details__field career-details__field--full"><span class="career-details__label">Comment</span>[textarea applicant_comment 40x4 class:career-details__textarea placeholder "COMMENT"]</div>
<div class="career-details__field career-details__field--full"><span class="career-details__label">LinkedIn (or upload CV)</span>[url linkedin_profile class:career-details__input placeholder "LINKEDIN URL"]</div>
<p class="career-details__apply-or" aria-hidden="true">OR</p>
<div class="career-details__upload"><label for="career-cv" class="career-details__upload-label"><span class="career-details__upload-icon" aria-hidden="true"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M24 8v20M16 16l8-8 8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 28v8a4 4 0 004 4h16a4 4 0 004-4v-8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><span class="career-details__upload-text">Drop your CV here, or <strong>Browse</strong></span><span class="career-details__upload-hint">Support DOC, DOCX, PDF, max size: 5MB</span></label>[file cv_file id:career-cv class:career-details__upload-input filetypes:doc|docx|pdf limit:5242880]</div>
<div class="career-details__apply-footer"><label class="career-details__consent">[acceptance privacy_accept] I am familiar with NSC Software's Privacy Policy *</label><button type="submit" class="wpcf7-form-control wpcf7-submit career-details__submit">Submit application <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></div>
FORM;

        $mail = [
            'active'             => true,
            'recipient'          => get_option('admin_email'),
            'sender'             => '[_site_title] <wordpress@' . $mailHost . '>',
            'subject'            => '[NSC Careers] Application: [job_position] — [first_name] [last_name]',
            'body'               => "Job application (body replaced by NSC theme for admin mail — see nscJobApplySubmission.php)\n\n"
                . "Position: [job_position]\n"
                . "Job title: [nsc_job_title]\n"
                . "Job URL: [nsc_job_url]\n"
                . "Name: [first_name] [last_name]\n"
                . "Email: [applicant_email]\n"
                . "Phone: [applicant_phone]\n"
                . "Location: [applicant_location]\n"
                . "LinkedIn: [linkedin_profile]\n\n"
                . "Comment:\n[applicant_comment]\n\n"
                . "CV: [cv_file]\n\n"
                . "Privacy accepted: [privacy_accept]\n"
                . "--\nSent from [_site_url]",
            'additional_headers' => 'Reply-To: [applicant_email]',
            'attachments'        => '',
            'use_html'           => false,
            'exclude_blank'      => false,
        ];

        $optionIdKey = 'nsc_cf7_job_apply_form_id';
        $optionTitleKey = 'nsc_cf7_job_apply_form_title';
    } else {
        $formTitle = 'NSC Main Contact Form';
        $formMarkup = <<<'FORM'
<div class="form-group">
  <label>Your Full Name</label>
  [text* name id:name placeholder "Enter your full name"]
</div>
<div class="form-group">
  <label>Your Work Email</label>
  [email* email id:email placeholder "Enter your Email"]
</div>
<div class="form-group">
  <label>Your Message</label>
  [textarea* message id:message placeholder rows:5 "Enter Your Message here"]
</div>
[submit class:btn "Send"]
FORM;

        $mail = [
            'active'             => true,
            'recipient'          => get_option('admin_email'),
            'sender'             => '[name] <[email]>',
            'subject'            => '[NSC Website] New contact from [name]',
            'body'               => "Name: [name]\nEmail: [email]\n\nMessage:\n[message]\n\n--\nThis email was sent from [_site_title] ([_site_url])",
            'additional_headers' => 'Reply-To: [email]',
            'attachments'        => '',
            'use_html'           => false,
            'exclude_blank'      => false,
        ];

        $optionIdKey = 'nsc_cf7_primary_form_id';
        $optionTitleKey = 'nsc_cf7_primary_form_title';
    }

    $mail2 = [
        'active'             => false,
        'recipient'          => '[email]',
        'sender'             => '[_site_title] <wordpress@' . $mailHost . '>',
        'subject'            => 'We received your message',
        'body'               => "Hi [name],\n\nThanks for contacting NSC Software. Our team will get back to you soon.\n\nRegards,\nNSC Software",
        'additional_headers' => '',
        'attachments'        => '',
        'use_html'           => false,
        'exclude_blank'      => false,
    ];

    if ($applyOnly) {
        $mail2 = [
            'active'             => false,
            'recipient'          => '[applicant_email]',
            'sender'             => '[_site_title] <wordpress@' . $mailHost . '>',
            'subject'            => 'We received your application',
            'body'               => "Hi [first_name],\n\nThank you for applying. Our team will review your profile and get back to you.\n\nRegards,\nNSC Software",
            'additional_headers' => '',
            'attachments'        => '',
            'use_html'           => false,
            'exclude_blank'      => false,
        ];
    }

    $formId = nsc_cf7_resolve_form_id($optionIdKey, $formTitle);

    if ($formId > 0) {
        wp_update_post([
            'ID'          => $formId,
            'post_title'  => $formTitle,
            'post_status' => 'publish',
        ]);
        $action = 'updated';
    } else {
        $inserted = wp_insert_post([
            'post_type'   => 'wpcf7_contact_form',
            'post_status' => 'publish',
            'post_title'  => $formTitle,
        ], true);

        if (is_wp_error($inserted)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Failed to create CF7 form: ' . $inserted->get_error_message() . "\n";
            exit;
        }
        $formId = (int) $inserted;
        $action = 'created';
    }

    $formId = (int) $formId;
    nsc_cf7_trash_duplicate_forms_same_title($formId, $formTitle);

    update_post_meta($formId, '_locale', get_locale());
    update_post_meta($formId, '_form', $formMarkup);
    update_post_meta($formId, '_mail', $mail);
    update_post_meta($formId, '_mail_2', $mail2);
    if ($applyOnly) {
        $cfMessages = [];
        foreach (wpcf7_messages() as $key => $arr) {
            $cfMessages[$key] = is_array($arr) && isset($arr['default']) ? (string) $arr['default'] : '';
        }
        if (function_exists('nsc_job_apply_cf7_message_overrides')) {
            $cfMessages = array_merge($cfMessages, nsc_job_apply_cf7_message_overrides());
        }
        update_post_meta($formId, '_messages', $cfMessages);
    } else {
        update_post_meta($formId, '_messages', []);
    }
    $additionalSettings = $applyOnly
        ? "autop: off\nacceptance_as_validation: on"
        : 'autop: off';
    update_post_meta($formId, '_additional_settings', $additionalSettings);

    update_option($optionIdKey, $formId);
    update_option($optionTitleKey, $formTitle);

    if (function_exists('nsc_seed_polylang_set_default_language_on_post')) {
        nsc_seed_polylang_set_default_language_on_post($formId);
    }
    if (function_exists('nsc_seed_cf7_sync_linked_translations')) {
        nsc_seed_cf7_sync_linked_translations($formId);
    }

    $shortcode = sprintf('[contact-form-7 id="%d"]', $formId);

    return [
        'formId'       => $formId,
        'action'       => $action,
        'formTitle'    => $formTitle,
        'optionIdKey'  => $optionIdKey,
        'shortcode'    => $shortcode,
        'applyOnly'    => $applyOnly,
    ];
}

$applyOnlyRequest = isset($_GET['apply_only']) && (string) $_GET['apply_only'] === '1';

$mailHost = wp_parse_url(home_url(), PHP_URL_HOST);
$mailHost = is_string($mailHost) && $mailHost !== '' ? $mailHost : 'localhost';

$results = [];
if ($applyOnlyRequest) {
    $results[] = nsc_cf7_seed_one_form(true, $mailHost);
} else {
    $results[] = nsc_cf7_seed_one_form(false, $mailHost);
    $results[] = nsc_cf7_seed_one_form(true, $mailHost);
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC CF7 Setup</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}code{background:#f4f4f4;padding:2px 6px;border-radius:4px}</style>';
echo '</head><body>';
echo '<h1>NSC Contact Form 7 Setup</h1>';
if ($applyOnlyRequest) {
    echo '<p><strong>Mode:</strong> job application form only (<code>apply_only=1</code>). Default mode seeds both forms. Options: <code>nsc_cf7_primary_form_id</code> / <code>nsc_cf7_job_apply_form_id</code>. Re-run updates by stored option ID or title match; duplicate same-title forms are trashed. Optional <code>seed_lang</code> / Polylang: same as <code>create-nsc-pages.php</code>.</p>';
} else {
    echo '<p>Seeded or updated <strong>' . count($results) . '</strong> form(s). Main contact + job application. Options: <code>nsc_cf7_primary_form_id</code>, <code>nsc_cf7_job_apply_form_id</code>. Re-run updates by stored option ID or title match; duplicate same-title forms are trashed. Pages: <code>create-nsc-pages.php</code>. Optional <code>seed_lang</code> / Polylang like other seeders.</p>';
}
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $slug = !empty($row['applyOnly']) ? 'cf7-job-apply' : 'cf7-main';
    $status = $row['action'] ?? 'unknown';
    $statusClass = $status === 'error' ? 'error' : 'ok';
    $hint = !empty($row['applyOnly'])
        ? 'Job singles use this when Careers → CF7 shortcode is empty.'
        : 'Primary form for contact blocks from create-nsc-pages.php.';
    $message = sprintf(
        'title=%s, option=%s, form_id=%d, shortcode=%s. %s',
        $row['formTitle'] ?? '',
        $row['optionIdKey'] ?? '',
        (int) ($row['formId'] ?? 0),
        $row['shortcode'] ?? '',
        $hint
    );
    echo '<tr>';
    echo '<td>' . esc_html($slug) . '</td>';
    echo '<td class="' . esc_attr($statusClass) . '">' . esc_html($status) . '</td>';
    echo '<td>' . esc_html($message) . '</td>';
    echo '</tr>';
}
echo '</tbody></table>';
echo '</body></html>';
