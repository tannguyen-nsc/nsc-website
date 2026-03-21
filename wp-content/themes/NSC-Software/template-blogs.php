<?php
/*
Template Name: NSC Blogs (static build)
*
* Legacy: renders the static frontend build blogs.html via template-static-build-page.php.
*
* Recommended: use the default page template for the Blogs page and add components
* (NSC Block: Hero + NSC Block: Blogs (Archive)) — see create-nsc-pages.php getBlogsPageComponents().
*/

$buildTemplate = 'blogs.html';
require __DIR__ . '/template-static-build-page.php';
