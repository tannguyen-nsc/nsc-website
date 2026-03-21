<?php

use NscSoftware\Utils\Options;
use Timber\Timber;

$context = Timber::context();
$context['post'] = Timber::get_post();
$context['blog_single'] = Options::getTranslatable('NSCBlogSingle') ?: [];

Timber::render('templates/single.twig', $context);
