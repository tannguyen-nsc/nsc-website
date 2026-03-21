<?php

/**
 * PHP session + REST tick for blog single "min read" (merges estimated reading time with active reading seconds).
 */

namespace NscSoftware\NscReadingSession;

const SESSION_KEY = 'nsc_reading_seconds';

/**
 * @return void
 */
function maybe_start_session(): void
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    @session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/**
 * Estimated minutes from post HTML (200 wpm), minimum 1.
 */
function estimate_minutes_for_post(int $postId): int
{
    $post = get_post($postId);
    if (!$post instanceof \WP_Post) {
        return 1;
    }
    $words = str_word_count(wp_strip_all_tags((string) $post->post_content));
    $m = (int) floor($words / 200);

    return max(1, $m);
}

/**
 * Seconds accumulated in session for this post.
 */
function session_seconds_for_post(int $postId): int
{
    maybe_start_session();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return 0;
    }
    $bucket = $_SESSION[SESSION_KEY] ?? [];
    if (!is_array($bucket)) {
        return 0;
    }

    return (int) ($bucket[$postId] ?? 0);
}

/**
 * Display minutes: max(estimate, ceil(session_seconds/60)).
 */
function display_read_minutes(int $postId): int
{
    $est = estimate_minutes_for_post($postId);
    $sess = session_seconds_for_post($postId);
    $fromSession = (int) ceil($sess / 60);

    return max($est, $fromSession);
}

add_action('init', __NAMESPACE__ . '\\maybe_start_session', 0);

add_filter('body_class', function (array $classes): array {
    if (is_singular('post')) {
        $classes[] = 'page-blog-details';
    }

    return $classes;
});

add_action('rest_api_init', function (): void {
    register_rest_route('nsc/v1', '/reading-session', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (\WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!is_string($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new \WP_Error('forbidden', __('Invalid nonce.', 'NscSoftware'), ['status' => 403]);
            }
            $postId = (int) $request->get_param('post_id');
            $delta  = (int) $request->get_param('seconds');
            if ($postId < 1 || $delta < 1 || $delta > 120) {
                return new \WP_Error('bad_request', __('Invalid parameters.', 'NscSoftware'), ['status' => 400]);
            }
            $post = get_post($postId);
            if (!$post instanceof \WP_Post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
                return new \WP_Error('not_found', __('Post not found.', 'NscSoftware'), ['status' => 404]);
            }

            maybe_start_session();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return [
                    'minutes_display' => estimate_minutes_for_post($postId),
                    'total_seconds'   => 0,
                ];
            }
            if (!isset($_SESSION[SESSION_KEY]) || !is_array($_SESSION[SESSION_KEY])) {
                $_SESSION[SESSION_KEY] = [];
            }
            $_SESSION[SESSION_KEY][$postId] = (int) ($_SESSION[SESSION_KEY][$postId] ?? 0) + $delta;

            return [
                'minutes_display' => display_read_minutes($postId),
                'total_seconds'   => (int) $_SESSION[SESSION_KEY][$postId],
            ];
        },
    ]);
});

add_action('wp_enqueue_scripts', function (): void {
    if (!is_singular('post')) {
        return;
    }
    $id = get_queried_object_id();
    if ($id < 1) {
        return;
    }
    wp_add_inline_script(
        'nsc-software-build',
        'window.nscReadingSession=' . wp_json_encode([
            'postId' => $id,
            'url'    => esc_url_raw(rest_url('nsc/v1/reading-session')),
            'nonce'  => wp_create_nonce('wp_rest'),
        ]) . ';',
        'before'
    );
    $js = <<<'JS'
(function(){var cfg=window.nscReadingSession;if(!cfg||!cfg.url||!cfg.nonce)return;var el=document.querySelector(".blog-details__meta-read");if(!el)return;var s1=el.getAttribute("data-min-read-suffix-singular")||"min read";var s2=el.getAttribute("data-min-read-suffix-plural")||"mins read";function sfx(m){m=Number(m);return m===1?s1:s2;}var last=Date.now(),acc=0;function visible(){return!document.hidden;}function flush(){if(acc<1)return;var s=Math.round(acc);acc=0;if(s<1)return;fetch(cfg.url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":cfg.nonce},body:JSON.stringify({post_id:cfg.postId,seconds:s})}).then(function(r){return r.json();}).then(function(d){if(d&&typeof d.minutes_display==="number"){el.textContent=d.minutes_display+" "+sfx(d.minutes_display);}}).catch(function(){});}document.addEventListener("visibilitychange",function(){if(visible()){last=Date.now();}});setInterval(function(){if(visible()){acc+=(Date.now()-last)/1000;last=Date.now();}},1000);setInterval(flush,25000);window.addEventListener("pagehide",flush);})();
JS;
    wp_add_inline_script('nsc-software-build', $js, 'after');
}, 25);
