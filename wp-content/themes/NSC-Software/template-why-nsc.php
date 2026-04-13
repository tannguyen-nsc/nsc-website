<?php
/**
 * Template Name: NSC Why NSC
 *
 * Renders the same as the default page template (page.twig + pageComponents).
 * After seeding, assign Default template in the editor if you still use this file.
 */

use Timber\Timber;

$context = Timber::context();
Timber::render('templates/page.twig', $context);
