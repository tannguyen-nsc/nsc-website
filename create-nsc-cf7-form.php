<?php
declare(strict_types=1);

/**
 * NSC Contact Form 7 bootstrapper.
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-cf7-form.php?token=nsc-create-cf7-2026
 */

$requiredToken = 'nsc-create-cf7-2026';
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

if (!class_exists('WPCF7_ContactForm')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Contact Form 7 is not active.\n";
    echo "Please install and activate plugin 'contact-form-7' first.\n";
    exit;
}

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
  [textarea* message id:message placeholder "Enter Your Message here"]
</div>
[submit class:btn "Send"]
FORM;

$existing = get_posts([
    'post_type' => 'wpcf7_contact_form',
    'post_status' => 'any',
    'title' => $formTitle,
    'numberposts' => 1,
]);

if (!empty($existing) && $existing[0] instanceof WP_Post) {
    $formId = (int) $existing[0]->ID;
    wp_update_post([
        'ID' => $formId,
        'post_title' => $formTitle,
        'post_status' => 'publish',
    ]);
    $action = 'updated';
} else {
    $formId = wp_insert_post([
        'post_type' => 'wpcf7_contact_form',
        'post_status' => 'publish',
        'post_title' => $formTitle,
    ], true);

    if (is_wp_error($formId)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Failed to create CF7 form: ' . $formId->get_error_message() . "\n";
        exit;
    }
    $action = 'created';
}

$formId = (int) $formId;

$mail = [
    'active' => true,
    'recipient' => get_option('admin_email'),
    'sender' => '[name] <[email]>',
    'subject' => '[NSC Website] New contact from [name]',
    'body' => "Name: [name]\nEmail: [email]\n\nMessage:\n[message]\n\n--\nThis email was sent from [_site_title] ([_site_url])",
    'additional_headers' => 'Reply-To: [email]',
    'attachments' => '',
    'use_html' => false,
    'exclude_blank' => false,
];

$mail2 = [
    'active' => false,
    'recipient' => '[email]',
    'sender' => '[_site_title] <wordpress@' . wp_parse_url(home_url(), PHP_URL_HOST) . '>',
    'subject' => 'We received your message',
    'body' => "Hi [name],\n\nThanks for contacting NSC Software. Our team will get back to you soon.\n\nRegards,\nNSC Software",
    'additional_headers' => '',
    'attachments' => '',
    'use_html' => false,
    'exclude_blank' => false,
];

update_post_meta($formId, '_locale', get_locale());
update_post_meta($formId, '_form', $formMarkup);
update_post_meta($formId, '_mail', $mail);
update_post_meta($formId, '_mail_2', $mail2);
update_post_meta($formId, '_messages', []);
update_post_meta($formId, '_additional_settings', "autop: off");

update_option('nsc_cf7_primary_form_id', $formId);
update_option('nsc_cf7_primary_form_title', $formTitle);

$shortcode = sprintf('[contact-form-7 id="%d" title="%s"]', $formId, $formTitle);

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC CF7 Setup</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px;line-height:1.5}code{background:#f4f4f4;padding:2px 6px;border-radius:4px}</style>';
echo '</head><body>';
echo '<h1>NSC Contact Form 7 Setup</h1>';
echo '<p>Status: <strong>' . esc_html($action) . '</strong></p>';
echo '<p>Form ID: <strong>' . esc_html((string) $formId) . '</strong></p>';
echo '<p>Stored option: <code>nsc_cf7_primary_form_id</code></p>';
echo '<p>Shortcode: <code>' . esc_html($shortcode) . '</code></p>';
echo '<p>You can re-run this script safely.</p>';
echo '</body></html>';
