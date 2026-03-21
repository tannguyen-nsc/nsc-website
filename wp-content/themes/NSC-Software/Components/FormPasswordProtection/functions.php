<?php

namespace NscSoftware\Components\FormPasswordProtection;

use Timber\Timber;

add_filter('the_password_form', function () {
    $context = Timber::context();
    $context['form'] = [
        'url' => site_url('/wp-login.php?action=postpass', 'login_post'),
    ];
    $context['contentHtml'] = sprintf(
        '<h1 class="h3">%1$s</h1><p>%2$s %3$s</p>',
        __('Enter Password', 'NscSoftware'),
        __('This content is password protected.', 'NscSoftware'),
        __('To view it please enter your password below:', 'NscSoftware')
    );
    $context['labels'] = [
        'inputAriaLabel' => __('Password', 'NscSoftware'),
        'inputPlaceholder' => __('Enter password', 'NscSoftware'),
        'buttonSubmit' => __('Enter', 'NscSoftware'),
    ];

    return Timber::compile('index.twig', $context);
});
