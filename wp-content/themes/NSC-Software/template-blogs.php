<?php
/*
Template Name: NSC Blogs (static)
*
* Renders the static frontend build blogs.html.
* For a component-based Blogs page with full post list and category filter:
* 1. In Pages, edit the Blogs page and set "Template" to "Default" (or "NSC Page").
* 2. Add the "NSC Block: Blogs (Archive)" component to the page.
* 3. You can then show the Blogs item in the menu (if it was hidden previously).
*/

$buildTemplate = 'blogs.html';
require __DIR__ . '/template-static-build-page.php';
