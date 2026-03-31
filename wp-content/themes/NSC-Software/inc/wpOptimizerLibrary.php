<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_Optimizer_Library')) {
    final class WP_Optimizer_Library
    {
        private const OPTION_KEY = 'wp_optimizer_options';
        private const PAGE_SLUG = 'wp-optimizer';
        private static bool $initialized = false;

        /**
         * @var array<string, mixed>|null
         */
        private static $optionsCache = null;

        public static function init(): void
        {
            if (self::$initialized) {
                return;
            }

            self::$initialized = true;

            add_action('admin_init', [self::class, 'registerSettings']);
            add_action('admin_menu', [self::class, 'registerMenu']);

            add_filter('show_admin_bar', [self::class, 'filterShowAdminBar']);
            add_filter('set-screen-option', [self::class, 'filterSetScreenOption'], 10, 3);
            add_action('admin_footer', [self::class, 'injectPerPageAllOptionScript']);

            add_filter('upload_mimes', [self::class, 'filterUploadMimes'], 10, 2);
            add_filter('mime_types', [self::class, 'filterMimeTypes']);
            add_filter('wp_check_filetype_and_ext', [self::class, 'filterSvgFiletypeAndExt'], 10, 5);
            add_filter('file_is_displayable_image', [self::class, 'filterDisplayableImage'], 10, 2);
            add_filter('wp_handle_upload_prefilter', [self::class, 'filterWebpUploadDimension']);
            add_filter('big_image_size_threshold', [self::class, 'filterBigImageSizeThreshold'], 10, 4);

            add_filter('media_row_actions', [self::class, 'filterMediaRowActions'], 10, 2);
            add_filter('wp_prepare_attachment_for_js', [self::class, 'filterAttachmentForJsUnusedChecker'], 10, 3);
            add_action('admin_footer', [self::class, 'injectMediaModalUnusedCheckerScript']);
            add_action('edit_form_after_title', [self::class, 'renderUnusedCheckerPanel']);
            add_action('attachment_submitbox_misc_actions', [self::class, 'renderAttachmentSubmitboxUnusedChecker']);
            add_action('wp_ajax_nht_unused_checker_start', [self::class, 'ajaxStartUnusedChecker']);
            add_action('wp_ajax_nht_unused_checker_step', [self::class, 'ajaxStepUnusedChecker']);
            add_action('wp_ajax_nht_unused_scan_all_start', [self::class, 'ajaxStartUnusedScanAll']);
            add_action('wp_ajax_nht_unused_scan_all_step', [self::class, 'ajaxStepUnusedScanAll']);
            add_action('wp_ajax_nht_unused_scan_all_bulk', [self::class, 'ajaxBulkUnusedScanAll']);
            add_action('admin_post_nht_unused_checker_action', [self::class, 'handleUnusedCheckerAction']);
            add_action('admin_notices', [self::class, 'renderUnusedCheckerNotice']);
        }

        /**
         * @return array<string, mixed>
         */
        private static function defaults(): array
        {
            return [
                'hide_admin_bar' => 0,
                'optimize_table_list' => 1,
                'enable_svg_webp' => 1,
                'optimize_wp_media' => 0,
                'unused_checker_scope' => 'both',
            ];
        }

        /**
         * @return array<string, mixed>
         */
        private static function options(): array
        {
            if (self::$optionsCache !== null) {
                return self::$optionsCache;
            }

            $raw = get_option(self::OPTION_KEY, []);
            if (!is_array($raw)) {
                $raw = [];
            }

            self::$optionsCache = wp_parse_args($raw, self::defaults());
            return self::$optionsCache;
        }

        private static function isEnabled(string $key): bool
        {
            $opts = self::options();
            return !empty($opts[$key]);
        }

        public static function registerMenu(): void
        {
            add_options_page(
                __('NHT WP Optimizer', 'wp-optimizer'),
                __('NHT WP Optimizer', 'wp-optimizer'),
                'manage_options',
                self::PAGE_SLUG,
                [self::class, 'renderSettingsPage']
            );
        }

        public static function registerSettings(): void
        {
            register_setting(self::OPTION_KEY, self::OPTION_KEY, [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitizeOptions'],
                'default' => self::defaults(),
            ]);

            add_settings_section(
                'wp_optimizer_main',
                __('Optimization options', 'wp-optimizer'),
                static function (): void {
                    echo '<p>' . esc_html__('Enable only the options you need.', 'wp-optimizer') . '</p>';
                },
                self::PAGE_SLUG
            );

            self::addCheckboxField(
                'hide_admin_bar',
                __('Hide #wpadminbar on frontend', 'wp-optimizer'),
                __('Hide the admin toolbar on the public site.', 'wp-optimizer')
            );

            self::addCheckboxField(
                'optimize_table_list',
                __('Optimize WP table list limitation', 'wp-optimizer'),
                __('Adds an "All" option and allows larger per-page values in admin list tables.', 'wp-optimizer')
            );

            self::addCheckboxField(
                'enable_svg_webp',
                __('Enable SVG/WebP upload', 'wp-optimizer'),
                __('Allows SVG and WebP uploads and keeps WebP display handling stable.', 'wp-optimizer')
            );

            self::addCheckboxField(
                'optimize_wp_media',
                __('Unused images / files checker', 'wp-optimizer'),
                ''
            );
        }

        private static function addCheckboxField(string $key, string $label, string $desc): void
        {
            add_settings_field(
                $key,
                $label,
                static function () use ($key, $desc): void {
                    $opts = self::options();
                    $checked = !empty($opts[$key]) ? 'checked' : '';
                    echo '<label>';
                    echo '<input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[' . esc_attr($key) . ']" value="1" ' . esc_attr($checked) . ' />';
                    if ($desc !== '') {
                        echo ' ' . esc_html($desc);
                    }

                    echo '</label>';
                    if ($key === 'optimize_wp_media') {
                        $scope = self::unusedCheckerScopeFromOptions($opts);
                        echo ' <select name="' . esc_attr(self::OPTION_KEY) . '[unused_checker_scope]">';
                        echo '<option value="db"' . selected($scope, 'db', false) . '>' . esc_html__('Database only', 'wp-optimizer') . '</option>';
                        echo '<option value="source"' . selected($scope, 'source', false) . '>' . esc_html__('Source code only', 'wp-optimizer') . '</option>';
                        echo '<option value="both"' . selected($scope, 'both', false) . '>' . esc_html__('Both', 'wp-optimizer') . '</option>';
                        echo '</select>';
                    }
                },
                self::PAGE_SLUG,
                'wp_optimizer_main'
            );
        }

        /**
         * @param mixed $raw
         * @return array<string, mixed>
         */
        public static function sanitizeOptions($raw): array
        {
            $defaults = self::defaults();
            $in = is_array($raw) ? $raw : [];
            $out = [];
            $out['hide_admin_bar'] = empty($in['hide_admin_bar']) ? 0 : 1;
            $out['optimize_table_list'] = empty($in['optimize_table_list']) ? 0 : 1;
            $out['enable_svg_webp'] = empty($in['enable_svg_webp']) ? 0 : 1;
            $out['optimize_wp_media'] = empty($in['optimize_wp_media']) ? 0 : 1;
            $scope = isset($in['unused_checker_scope']) ? sanitize_key((string) $in['unused_checker_scope']) : 'both';
            if (!in_array($scope, ['db', 'source', 'both'], true)) {
                $scope = 'both';
            }

            $out['unused_checker_scope'] = $scope;

            self::$optionsCache = null;
            return $out;
        }

        private static function unusedCheckerScope(): string
        {
            return self::unusedCheckerScopeFromOptions(self::options());
        }

        /**
         * @param array<string, mixed> $opts
         */
        private static function unusedCheckerScopeFromOptions(array $opts): string
        {
            $scope = isset($opts['unused_checker_scope']) ? sanitize_key((string) $opts['unused_checker_scope']) : 'both';
            if (!in_array($scope, ['db', 'source', 'both'], true)) {
                $scope = 'both';
            }

            return $scope;
        }

        public static function renderSettingsPage(): void
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $scanNonce = wp_create_nonce('nht_unused_scan_all_nonce');
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('NHT WP Optimizer', 'wp-optimizer') . '</h1>';
            echo '<form method="post" action="options.php">';
            settings_fields(self::OPTION_KEY);
            do_settings_sections(self::PAGE_SLUG);
            submit_button();
            echo '</form>';
            if (self::isEnabled('optimize_wp_media')) {
                echo '<hr />';
                echo '<h2>' . esc_html__('Scan all unused images/files', 'wp-optimizer') . '</h2>';
                echo '<div id="nht-scan-all-app" data-nonce="' . esc_attr($scanNonce) . '">';
                echo '<p style="margin:0 0 8px;">' . esc_html__('Runs with current "Unused images / files checker" scope option.', 'wp-optimizer') . '</p>';
                echo '<button type="button" class="button button-secondary" id="nht-scan-all-run">' . esc_html__('Run scan now', 'wp-optimizer') . '</button>';
                echo '<div style="height:10px;background:#f0f0f1;border-radius:99px;overflow:hidden;margin-top:10px;"><div id="nht-scan-all-progress" style="width:0%;height:100%;background:#2271b1;transition:width .2s ease;"></div></div>';
                echo '<p id="nht-scan-all-status" style="margin:8px 0 0;"></p>';
                echo '<div id="nht-scan-all-results" style="margin-top:12px;"></div>';
                echo '</div>';

                echo '<script>';
                echo '(function(){';
                echo 'var app=document.getElementById("nht-scan-all-app"); if(!app||typeof ajaxurl==="undefined"){return;}';
                echo 'var nonce=app.getAttribute("data-nonce"); var runBtn=document.getElementById("nht-scan-all-run"); var p=document.getElementById("nht-scan-all-progress"); var s=document.getElementById("nht-scan-all-status"); var results=document.getElementById("nht-scan-all-results");';
                echo 'function esc(x){return String(x||"").replace(/[&<>\\"\\\']/g,function(c){return({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\\\'":"&#039;"})[c];});}';
                echo 'function req(action,payload){var fd=new FormData();fd.append("action",action);fd.append("_ajax_nonce",nonce);for(var k in payload){if(Object.prototype.hasOwnProperty.call(payload,k)){var v=payload[k];if(Array.isArray(v)){v.forEach(function(one){fd.append(k+"[]",one);});}else{fd.append(k,v);}}}return fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.text().then(function(t){try{return JSON.parse(t);}catch(e){return {success:false,data:{message:t||("HTTP "+r.status)}};}});});}';
                echo 'function selectedScope(){var sel=document.querySelector("select[name=\"wp_optimizer_options[unused_checker_scope]\"]"); return sel?sel.value:"both";}';
                echo 'function collectSelectedIds(){var ids=[]; results.querySelectorAll("input.nht-scan-id:checked").forEach(function(i){ids.push(i.value);}); return ids;}';
                echo 'function renderTable(items){if(!items||!items.length){results.innerHTML="<p>No unused files found.</p>";return;} var h="<div style=\'margin-bottom:8px;\'><button type=\'button\' class=\'button button-link-delete\' id=\'nht-bulk-delete\'>Delete selected permanently</button></div>"; h+="<table class=\'widefat striped\'><thead><tr><th><input type=\'checkbox\' id=\'nht-select-all\' /></th><th>File</th><th>Actions</th></tr></thead><tbody>"; items.forEach(function(it){h+="<tr data-id=\'"+esc(it.id)+"\'><td><input class=\'nht-scan-id\' type=\'checkbox\' value=\'"+esc(it.id)+"\' /></td><td><strong>"+esc(it.filename||it.title||it.id)+"</strong><br><small>"+esc(it.url||"")+"</small></td><td><button type=\'button\' class=\'button button-link-delete nht-row-delete\'>Delete Permanently</button></td></tr>";}); h+="</tbody></table>"; results.innerHTML=h;';
                echo 'var all=document.getElementById("nht-select-all"); if(all){all.addEventListener("change",function(){results.querySelectorAll("input.nht-scan-id").forEach(function(i){i.checked=all.checked;});});}';
                echo 'var bulkDel=document.getElementById("nht-bulk-delete"); if(bulkDel){bulkDel.addEventListener("click",function(){bulkAction("delete",collectSelectedIds());});}';
                echo 'results.querySelectorAll(".nht-row-delete").forEach(function(btn){btn.addEventListener("click",function(){var tr=btn.closest("tr"); if(!tr)return; bulkAction("delete",[tr.getAttribute("data-id")]);});});';
                echo '}';
                echo 'function removeRows(ids){ids.forEach(function(id){var tr=results.querySelector("tr[data-id=\'"+id+"\']"); if(tr){tr.remove();}}); if(!results.querySelector("tbody tr")){results.innerHTML="<p>All listed files processed.</p>";}}';
                echo 'function bulkAction(mode,ids){if(!ids.length){alert("Please select at least one file."); return;} if(mode==="delete"&&!confirm("Delete permanently selected files?")){return;} req("nht_unused_scan_all_bulk",{mode:mode,ids:ids,ids_json:JSON.stringify(ids)}).then(function(res){if(!res||!res.success){var msg=(res&&res.data&&res.data.message)?res.data.message:"Bulk action failed."; alert(msg); return;} var d=res.data||{}; removeRows(ids); s.textContent=(d.message||"Done");}).catch(function(e){alert("Bulk action failed.");});}';
                echo 'function step(){req("nht_unused_scan_all_step",{}).then(function(res){if(!res||!res.success){s.textContent="Scan failed."; return;} var d=res.data||{}; p.style.width=String(d.percent||0)+"%"; s.textContent=(d.stage_label||"Scanning")+" | "+(d.current_file||""); if(d.done){renderTable(d.unused||[]);} else {setTimeout(step,120);} }).catch(function(){s.textContent="Scan failed.";});}';
                echo 'function start(){results.innerHTML=""; p.style.width="0%"; s.textContent="Starting scan..."; req("nht_unused_scan_all_start",{scope:selectedScope()}).then(function(res){if(!res||!res.success){s.textContent="Cannot start scan."; return;} step();}).catch(function(){s.textContent="Cannot start scan.";});}';
                echo 'if(runBtn){runBtn.addEventListener("click",start);}';
                echo 'var form=document.querySelector("form[action=\"options.php\"]"); if(form){form.addEventListener("submit",function(){try{sessionStorage.setItem("nhtScanAfterSave","1");}catch(e){}});}';
                echo 'var auto=(new URLSearchParams(window.location.search)).get("settings-updated")==="true"; var should=false; try{should=sessionStorage.getItem("nhtScanAfterSave")==="1";}catch(e){} if(auto&&should){try{sessionStorage.removeItem("nhtScanAfterSave");}catch(e){} start();}';
                echo '})();';
                echo '</script>';
            }

            echo '</div>';
        }

        /**
         * @param mixed $show
         * @return mixed
         */
        public static function filterShowAdminBar($show)
        {
            if (is_admin()) {
                return $show;
            }

            if (self::isEnabled('hide_admin_bar')) {
                return false;
            }

            return $show;
        }

        /**
         * @param mixed $status
         * @param string $option
         * @param mixed $value
         * @return mixed
         */
        public static function filterSetScreenOption($status, string $option, $value)
        {
            if (!self::isEnabled('optimize_table_list')) {
                return $status;
            }

            if (!preg_match('/_per_page$/', $option)) {
                return $status;
            }

            if (is_string($value) && strtolower($value) === 'all') {
                return 999999;
            }

            $num = (int) $value;
            if ($num <= 0) {
                $num = 20;
            }

            return min(999999, max(1, $num));
        }

        public static function injectPerPageAllOptionScript(): void
        {
            if (!self::isEnabled('optimize_table_list')) {
                return;
            }

            if (!is_admin()) {
                return;
            }

            echo '<script>';
            echo '(function(){';
            echo 'var s=document.getElementById("screen-options-wrap"); if(!s)return;';
            echo 'var selects=s.querySelectorAll("select.screen-per-page"); if(!selects.length)return;';
            echo 'for(var i=0;i<selects.length;i++){';
            echo 'var sel=selects[i]; if(sel.querySelector(\'option[value="999999"]\')) continue;';
            echo 'var o=document.createElement("option"); o.value="999999"; o.text="All"; sel.appendChild(o);';
            echo '}';
            echo '})();';
            echo '</script>';
        }

        /**
         * @param array<string, string> $mimes
         * @return array<string, string>
         */
        public static function filterUploadMimes(array $mimes, $user = null): array
        {
            if (!self::isEnabled('enable_svg_webp')) {
                return $mimes;
            }

            if (!self::currentUserCanUploadExtendedImages($user)) {
                return $mimes;
            }

            $mimes['svg'] = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
            $mimes['webp'] = 'image/webp';
            $mimes['avif'] = 'image/avif';
            return $mimes;
        }

        /**
         * @param array<string, string> $mimes
         * @return array<string, string>
         */
        public static function filterMimeTypes(array $mimes): array
        {
            if (!self::isEnabled('enable_svg_webp')) {
                return $mimes;
            }

            $mimes['svg'] = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
            $mimes['webp'] = 'image/webp';
            $mimes['avif'] = 'image/avif';
            return $mimes;
        }

        /**
         * @param array<string, mixed> $data
         * @return array<string, mixed>
         */
        public static function filterSvgFiletypeAndExt(array $data, $file, $filename, $mimes, $realMime): array
        {
            if (!self::isEnabled('enable_svg_webp')) {
                return $data;
            }

            $ext = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
            if ($ext === 'svg' || $ext === 'svgz') {
                $data['ext'] = 'svg';
                $data['type'] = 'image/svg+xml';
                $data['proper_filename'] = (string) $filename;
            } elseif ($ext === 'webp') {
                $data['ext'] = 'webp';
                $data['type'] = 'image/webp';
                $data['proper_filename'] = (string) $filename;
            } elseif ($ext === 'avif') {
                $data['ext'] = 'avif';
                $data['type'] = 'image/avif';
                $data['proper_filename'] = (string) $filename;
            }

            return $data;
        }

        public static function filterDisplayableImage(bool $result, string $path): bool
        {
            if ($result || !self::isEnabled('enable_svg_webp')) {
                return $result;
            }

            $info = @getimagesize($path);
            return !empty($info) && (int) $info[2] === IMAGETYPE_WEBP;
        }

        /**
         * @param array<string, mixed> $file
         * @return array<string, mixed>
         */
        public static function filterWebpUploadDimension(array $file): array
        {
            if (!self::isEnabled('enable_svg_webp')) {
                return $file;
            }

            $name = isset($file['name']) ? (string) $file['name'] : '';
            if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'webp') {
                return $file;
            }

            $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
            if ($tmp === '' || !file_exists($tmp)) {
                return $file;
            }

            // Set max <= 0 to disable manual dimension blocking.
            $max = (int) apply_filters('nht_optimizer_webp_max_dimension', 0);
            if ($max <= 0) {
                return $file;
            }

            $size = @getimagesize($tmp);
            if (!$size || empty($size[0]) || empty($size[1])) {
                return $file;
            }

            if ((int) $size[0] > $max || (int) $size[1] > $max) {
                $file['error'] = sprintf(
                    /* translators: %d is maximum allowed dimension in px */
                    __('WebP image is too large. Maximum allowed dimension is %dpx.', 'wp-optimizer'),
                    $max
                );
            }

            return $file;
        }

        /**
         * Avoid WP big-image scaling for modern web image formats to reduce processing failures.
         *
         * @param mixed $threshold
         * @param mixed $imagesize
         * @param mixed $file
         * @param mixed $attachmentId
         * @return mixed
         */
        public static function filterBigImageSizeThreshold($threshold, $imagesize = null, $file = null, $attachmentId = 0)
        {
            if (!self::isEnabled('enable_svg_webp')) {
                return $threshold;
            }

            $path = is_string($file) ? $file : '';
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['webp', 'avif', 'svg', 'svgz'], true)) {
                return false;
            }

            return $threshold;
        }

        /**
         * Add "Unused Checker" next to Edit in media list table.
         *
         * @param array<string, string> $actions
         * @return array<string, string>
         */
        public static function filterMediaRowActions(array $actions, \WP_Post $post): array
        {
            if (!self::isEnabled('optimize_wp_media') || $post->post_type !== 'attachment') {
                return $actions;
            }

            if (!current_user_can('upload_files')) {
                return $actions;
            }

            $url = add_query_arg(
                [
                    'post' => (int) $post->ID,
                    'action' => 'edit',
                    'unused_checker' => '1',
                ],
                admin_url('post.php')
            );
            $unusedLink = '<a href="' . esc_url($url) . '">' . esc_html__('Unused Checker', 'wp-optimizer') . '</a>';

            $ordered = [];
            if (isset($actions['edit'])) {
                $ordered['edit'] = $actions['edit'];
            }

            $ordered['nht_unused_checker'] = $unusedLink;
            if (isset($actions['trash'])) {
                $ordered['trash'] = $actions['trash'];
            }

            if (isset($actions['delete'])) {
                $ordered['delete'] = $actions['delete'];
            }

            foreach ($actions as $k => $v) {
                if (!isset($ordered[$k])) {
                    $ordered[$k] = $v;
                }
            }

            return $ordered;
        }

        /**
         * Render checker UI on attachment edit page.
         */
        public static function renderUnusedCheckerPanel(\WP_Post $post): void
        {
            if (
                !self::isEnabled('optimize_wp_media')
                || $post->post_type !== 'attachment'
                || !isset($_GET['unused_checker'])
                || !current_user_can('upload_files')
            ) {
                return;
            }

            $nonce = wp_create_nonce('nht_unused_checker_nonce');
            $attachmentId = (int) $post->ID;

            echo '<div class="notice notice-info" style="padding:12px 16px;margin:12px 0 18px;">';
            echo '<p style="margin:0 0 10px;"><strong>' . esc_html__('Unused Checker', 'wp-optimizer') . '</strong></p>';
            echo '<div id="nht-unused-checker-app" data-attachment-id="' . esc_attr((string) $attachmentId) . '" data-nonce="' . esc_attr($nonce) . '">';
            echo '<div style="height:10px;background:#f0f0f1;border-radius:99px;overflow:hidden;"><div id="nht-unused-progress" style="width:0%;height:100%;background:#2271b1;transition:width .2s ease;"></div></div>';
            echo '<p id="nht-unused-status" style="margin:8px 0 0;">' . esc_html__('Preparing scan...', 'wp-optimizer') . '</p>';
            echo '<div id="nht-unused-results" style="margin-top:10px;"></div>';
            echo '</div>';
            echo '</div>';

            echo '<script>';
            echo '(function(){';
            echo 'var root=document.getElementById("nht-unused-checker-app"); if(!root||typeof ajaxurl==="undefined"){return;}';
            echo 'var aid=root.getAttribute("data-attachment-id"); var nonce=root.getAttribute("data-nonce");';
            echo 'var progressEl=document.getElementById("nht-unused-progress"); var statusEl=document.getElementById("nht-unused-status"); var resultsEl=document.getElementById("nht-unused-results");';
            echo 'function post(action,payload){var fd=new FormData();fd.append("action",action);fd.append("_ajax_nonce",nonce);fd.append("attachment_id",aid);for(var k in payload){if(Object.prototype.hasOwnProperty.call(payload,k)){fd.append(k,payload[k]);}}return fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.json();});}';
            echo 'function esc(s){return String(s||"").replace(/[&<>\\"\\\']/g,function(c){return({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;","\\\'":"&#039;"})[c];});}';
            echo 'function renderDone(data){var rs=data.results||{};var posts=rs.posts||[];var metas=rs.meta||[];var files=rs.files||[];';
            echo 'var html="";';
            echo 'if(posts.length){html+="<h3 style=\'margin:8px 0 6px;\'>Used in post/page content</h3><ul>";posts.forEach(function(p){var t=esc(p.title||"(no title)");var l=esc(p.edit_link||"#");html+="<li><a href=\'"+l+"\'>"+t+"</a> <code>("+esc(p.type||"post")+")</code></li>";});html+="</ul>";}';
            echo 'if(metas.length){html+="<h3 style=\'margin:8px 0 6px;\'>Used in custom fields</h3><ul>";metas.forEach(function(p){var t=esc(p.title||"(no title)");var l=esc(p.edit_link||"#");var keys=(p.meta_keys||[]).map(esc).join(", ");html+="<li><a href=\'"+l+"\'>"+t+"</a> <code>("+esc(p.type||"post")+")</code> - "+keys+"</li>";});html+="</ul>";}';
            echo 'if(files.length){html+="<h3 style=\'margin:8px 0 6px;\'>Referenced in theme/plugin source</h3><ul>";files.forEach(function(f){html+="<li><code>"+esc(f)+"</code></li>";});html+="</ul>";}';
            echo 'if(!posts.length && !metas.length && !files.length){var rec=data.recommendation||{};html+="<p><strong>No usage found.</strong> Recommended: delete permanently.</p>";if(rec.delete_url){html+="<p><a class=\'button button-link-delete\' href=\'"+esc(rec.delete_url)+"\'>Delete Permanently</a></p>";}}';
            echo 'resultsEl.innerHTML=html;';
            echo '}';
            echo 'function tick(){post("nht_unused_checker_step",{}).then(function(res){if(!res||!res.success){statusEl.textContent="Scan failed.";return;}var d=res.data||{};var p=parseInt(String(d.percent||0),10);if(progressEl){progressEl.style.width=p+"%";}statusEl.textContent=(d.stage_label||"Scanning")+" ("+p+"%)";if(d.done){renderDone(d);}else{setTimeout(tick,120);}}).catch(function(){statusEl.textContent="Scan failed.";});}';
            echo 'post("nht_unused_checker_start",{}).then(function(res){if(!res||!res.success){statusEl.textContent="Cannot start checker.";return;}tick();}).catch(function(){statusEl.textContent="Cannot start checker.";});';
            echo '})();';
            echo '</script>';
        }

        /**
         * Add Unused Checker link in media edit sidebar (below dimensions section).
         */
        public static function renderAttachmentSubmitboxUnusedChecker(): void
        {
            global $post;
            if (
                !self::isEnabled('optimize_wp_media')
                || !($post instanceof \WP_Post)
                || $post->post_type !== 'attachment'
                || !current_user_can('upload_files')
            ) {
                return;
            }

            $url = add_query_arg(
                [
                    'post' => (int) $post->ID,
                    'action' => 'edit',
                    'unused_checker' => '1',
                ],
                admin_url('post.php')
            );

            echo '<script>';
            echo '(function(){';
            echo 'var url=' . wp_json_encode($url) . ';';
            echo 'var text=' . wp_json_encode(__('Unused Checker', 'wp-optimizer')) . ';';
            echo 'function mount(){';
            echo 'var container=document.querySelector(".misc-pub-attachment, .misc-pub-section.misc-pub-attachment");';
            echo 'if(!container){return;}';
            echo 'if(container.querySelector(".nht-unused-checker-inline")){return;}';
            echo 'var download=container.querySelector("a[href]");';
            echo 'if(!download){return;}';
            echo 'var link=document.createElement("a");';
            echo 'link.className="nht-unused-checker-inline";';
            echo 'link.href=url;';
            echo 'link.textContent=text;';
            echo 'var sep=document.createTextNode(" | ");';
            echo 'if(download.nextSibling){container.insertBefore(sep,download.nextSibling); container.insertBefore(link,sep.nextSibling);}else{container.appendChild(sep); container.appendChild(link);}';
            echo '}';
            echo 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",mount);}else{mount();}';
            echo '})();';
            echo '</script>';
        }

        public static function ajaxStartUnusedScanAll(): void
        {
            check_ajax_referer('nht_unused_scan_all_nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            }

            $scope = isset($_POST['scope']) ? sanitize_key((string) $_POST['scope']) : self::unusedCheckerScope();
            if (!in_array($scope, ['db', 'source', 'both'], true)) {
                $scope = self::unusedCheckerScope();
            }

            $ids = get_posts([
                'post_type' => 'attachment',
                'post_status' => ['inherit', 'private', 'publish'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);
            $ids = array_values(array_map('intval', is_array($ids) ? $ids : []));

            $files = ($scope === 'db') ? [] : self::collectScanFiles();
            $session = [
                'scope' => $scope,
                'attachment_ids' => $ids,
                'total' => count($ids),
                'index' => 0,
                'files' => $files,
                'unused' => [],
                'done' => false,
                'stage_label' => 'Starting scan',
                'current_file' => '',
            ];
            set_transient(self::scanAllSessionKey(), $session, 30 * MINUTE_IN_SECONDS);
            wp_send_json_success(['started' => true, 'total' => count($ids)]);
        }

        public static function ajaxStepUnusedScanAll(): void
        {
            check_ajax_referer('nht_unused_scan_all_nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            }

            $session = get_transient(self::scanAllSessionKey());
            if (!is_array($session)) {
                wp_send_json_error(['message' => 'Session expired'], 400);
            }

            $ids = (array) ($session['attachment_ids'] ?? []);
            $total = max(0, (int) ($session['total'] ?? count($ids)));
            $index = max(0, (int) ($session['index'] ?? 0));
            if ($index >= $total) {
                $session['done'] = true;
            } else {
                $attachmentId = (int) $ids[$index];
                $name = self::attachmentDisplayName($attachmentId);
                $session['current_file'] = $name;

                $terms = self::collectSearchTerms($attachmentId);
                $scope = (string) ($session['scope'] ?? 'both');
                $used = false;

                if ($scope !== 'source') {
                    $session['stage_label'] = 'Scanning database tables: posts, postmeta';
                    $postHits = self::findUsageInPosts($terms);
                    $metaHits = self::findUsageInPostMeta($terms, $attachmentId);
                    $used = !empty($postHits) || !empty($metaHits);
                }

                if (!$used && $scope !== 'db') {
                    $session['stage_label'] = 'Scanning source files: plugin/theme';
                    foreach ((array) ($session['files'] ?? []) as $file) {
                        if (self::fileContainsAny((string) $file, $terms)) {
                            $used = true;
                            break;
                        }
                    }
                }

                if (!$used) {
                    $session['unused'][] = [
                        'id' => $attachmentId,
                        'filename' => $name,
                        'url' => (string) wp_get_attachment_url($attachmentId),
                    ];
                }

                $session['index'] = $index + 1;
                if ($session['index'] >= $total) {
                    $session['done'] = true;
                    $session['stage_label'] = 'Scan completed';
                }
            }

            set_transient(self::scanAllSessionKey(), $session, 30 * MINUTE_IN_SECONDS);

            $indexNow = max(0, (int) ($session['index'] ?? 0));
            $totalNow = max(1, (int) ($session['total'] ?? 0));
            $percent = (int) floor(($indexNow / $totalNow) * 100);
            wp_send_json_success([
                'done' => !empty($session['done']),
                'percent' => max(0, min(100, $percent)),
                'stage_label' => (string) ($session['stage_label'] ?? 'Scanning'),
                'current_file' => (string) ($session['current_file'] ?? ''),
                'unused' => !empty($session['done']) ? (array) ($session['unused'] ?? []) : [],
            ]);
        }

        public static function ajaxBulkUnusedScanAll(): void
        {
            if (!check_ajax_referer('nht_unused_scan_all_nonce', '_ajax_nonce', false)) {
                wp_send_json_error(['message' => 'Security nonce expired. Please refresh the page and try again.'], 403);
            }

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            }

            $mode = isset($_POST['mode']) ? sanitize_key((string) $_POST['mode']) : '';
            if ($mode !== 'delete') {
                wp_send_json_error(['message' => 'Invalid mode'], 400);
            }

            $ids = [];
            if (isset($_POST['ids']) && is_array($_POST['ids'])) {
                $ids = $_POST['ids'];
            } elseif (isset($_POST['ids_json'])) {
                $rawJson = (string) $_POST['ids_json'];
                $decodedJson = json_decode($rawJson, true);
                if (is_array($decodedJson)) {
                    $ids = $decodedJson;
                }
            } elseif (isset($_POST['ids'])) {
                $raw = (string) $_POST['ids'];
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $ids = $decoded;
                }
            }

            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (empty($ids)) {
                wp_send_json_error(['message' => 'No IDs selected'], 400);
            }

            $ok = 0;
            $fail = 0;
            foreach ($ids as $id) {
                if ($id <= 0 || !current_user_can('delete_post', $id)) {
                    $fail++;
                    continue;
                }

                $result = wp_delete_attachment($id, true);
                if ($result === false || $result === null) {
                    $fail++;
                } else {
                    $ok++;
                }
            }

            $msg = sprintf(
                /* translators: 1: success count, 2: failure count */
                __('Processed %1$d item(s), failed %2$d item(s).', 'wp-optimizer'),
                $ok,
                $fail
            );

            wp_send_json_success([
                'ok' => $ok,
                'failed' => $fail,
                'message' => $msg,
            ]);
        }

        public static function ajaxStartUnusedChecker(): void
        {
            self::checkCheckerAjaxPermissions();
            $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
            $attachment = get_post($attachmentId);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                wp_send_json_error(['message' => 'Invalid attachment'], 400);
            }

            $terms = self::collectSearchTerms($attachmentId);
            if (empty($terms)) {
                wp_send_json_error(['message' => 'No search terms'], 400);
            }

            $scope = self::unusedCheckerScope();
            $files = ($scope === 'db') ? [] : self::collectScanFiles();
            $session = [
                'attachment_id' => $attachmentId,
                'scope' => $scope,
                'terms' => $terms,
                'files' => $files,
                'files_total' => count($files),
                'files_checked' => 0,
                'files_hits' => [],
                'db_posts_done' => ($scope === 'source'),
                'db_meta_done' => ($scope === 'source'),
                'db_posts_hits' => [],
                'db_meta_hits' => [],
                'done' => false,
            ];

            set_transient(self::checkerSessionKey($attachmentId), $session, 30 * MINUTE_IN_SECONDS);
            wp_send_json_success(['started' => true]);
        }

        public static function ajaxStepUnusedChecker(): void
        {
            self::checkCheckerAjaxPermissions();
            $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
            $session = get_transient(self::checkerSessionKey($attachmentId));
            if (!is_array($session)) {
                wp_send_json_error(['message' => 'Session expired'], 400);
            }

            if (!$session['db_posts_done']) {
                $session['db_posts_hits'] = self::findUsageInPosts((array) $session['terms']);
                $session['db_posts_done'] = true;
            } elseif (!$session['db_meta_done']) {
                $session['db_meta_hits'] = self::findUsageInPostMeta((array) $session['terms'], $attachmentId);
                $session['db_meta_done'] = true;
            } else {
                $chunk = 80;
                $limit = min((int) $session['files_total'], (int) $session['files_checked'] + $chunk);
                for ($i = (int) $session['files_checked']; $i < $limit; $i++) {
                    $file = (string) $session['files'][$i];
                    if (self::fileContainsAny($file, (array) $session['terms'])) {
                        $session['files_hits'][] = self::normalizePathForDisplay($file);
                    }

                    $session['files_checked'] = $i + 1;
                }

                if ((int) $session['files_checked'] >= (int) $session['files_total']) {
                    $session['done'] = true;
                }
            }

            if (($session['scope'] ?? 'both') === 'db' && !empty($session['db_posts_done']) && !empty($session['db_meta_done'])) {
                $session['done'] = true;
            }

            set_transient(self::checkerSessionKey($attachmentId), $session, 30 * MINUTE_IN_SECONDS);
            $progress = self::checkerProgress($session);

            $data = [
                'done' => !empty($session['done']),
                'percent' => $progress['percent'],
                'stage_label' => $progress['stage'],
            ];

            if (!empty($session['done'])) {
                $deleteUrl = self::buildAttachmentActionUrl($attachmentId, true);
                $posts = (array) $session['db_posts_hits'];
                $meta = (array) $session['db_meta_hits'];
                $files = array_values(array_unique((array) $session['files_hits']));
                $unused = empty($posts) && empty($meta) && empty($files);

                $data['results'] = [
                    'posts' => $posts,
                    'meta' => $meta,
                    'files' => $files,
                ];
                $data['recommendation'] = [
                    'unused' => $unused,
                    'delete_url' => is_string($deleteUrl) ? $deleteUrl : '',
                ];
            }

            wp_send_json_success($data);
        }

        private static function checkCheckerAjaxPermissions(): void
        {
            check_ajax_referer('nht_unused_checker_nonce');
            if (!current_user_can('upload_files')) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            }
        }

        /**
         * @return list<string>
         */
        private static function collectSearchTerms(int $attachmentId): array
        {
            $terms = [];
            $url = (string) wp_get_attachment_url($attachmentId);
            if ($url !== '') {
                $terms[] = $url;
                $urlPath = (string) wp_parse_url($url, PHP_URL_PATH);
                if ($urlPath !== '') {
                    $terms[] = wp_basename($urlPath);
                }
            }

            $meta = wp_get_attachment_metadata($attachmentId);
            if (is_array($meta) && !empty($meta['file']) && is_string($meta['file'])) {
                $terms[] = $meta['file'];
                $terms[] = wp_basename($meta['file']);
            } else {
                $path = (string) get_attached_file($attachmentId);
                if ($path !== '') {
                    $terms[] = wp_basename($path);
                }
            }

            // Keep filename matching extension-aware to avoid collisions on duplicate stems.

            $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));
            usort($terms, static function (string $a, string $b): int {
                return strlen($b) <=> strlen($a);
            });

            return $terms;
        }

        /**
         * @return list<string>
         */
        private static function collectScanFiles(): array
        {
            $roots = [get_template_directory()];
            $activePlugins = (array) get_option('active_plugins', []);
            foreach ($activePlugins as $pluginEntry) {
                if (!is_string($pluginEntry) || $pluginEntry === '') {
                    continue;
                }

                $dir = WP_PLUGIN_DIR . '/' . dirname($pluginEntry);
                if (is_dir($dir)) {
                    $roots[] = $dir;
                }
            }

            $roots = array_values(array_unique($roots));

            // Source mode scope: scan plugin/theme code files only.
            $extAllowed = ['php', 'js', 'css', 'twig', 'scss', 'json'];
            $files = [];
            foreach ($roots as $root) {
                try {
                    $it = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($it as $entry) {
                        if (!$entry instanceof \SplFileInfo || !$entry->isFile()) {
                            continue;
                        }

                        $ext = strtolower((string) $entry->getExtension());
                        if (!in_array($ext, $extAllowed, true)) {
                            continue;
                        }

                        $files[] = $entry->getPathname();
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            return array_values(array_unique($files));
        }

        /**
         * @param list<string> $terms
         * @return list<array{id:int,title:string,type:string,edit_link:string}>
         */
        private static function findUsageInPosts(array $terms): array
        {
            global $wpdb;
            if (empty($terms)) {
                return [];
            }

            $whereParts = [];
            $args = [];
            foreach ($terms as $term) {
                $like = '%' . $wpdb->esc_like($term) . '%';
                $whereParts[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
                $args[] = $like;
                $args[] = $like;
            }

            $sql = "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                    WHERE post_status NOT IN ('trash','auto-draft','inherit')
                      AND post_type <> 'attachment'
                      AND (" . implode(' OR ', $whereParts) . ")
                    ORDER BY post_date DESC
                    LIMIT 500";
            $prepared = $wpdb->prepare($sql, $args);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (!is_array($rows)) {
                return [];
            }

            $out = [];
            foreach ($rows as $row) {
                $id = (int) ($row['ID'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $out[] = [
                    'id' => $id,
                    'title' => (string) ($row['post_title'] ?? ''),
                    'type' => (string) ($row['post_type'] ?? ''),
                    'edit_link' => (string) get_edit_post_link($id, 'raw'),
                ];
            }

            return $out;
        }

        /**
         * @param list<string> $terms
         * @return list<array{id:int,title:string,type:string,edit_link:string,meta_keys:list<string>}>
         */
        private static function findUsageInPostMeta(array $terms, int $attachmentId): array
        {
            global $wpdb;
            if (empty($terms)) {
                return [];
            }

            $whereParts = [];
            $args = [];
            foreach ($terms as $term) {
                $like = '%' . $wpdb->esc_like($term) . '%';
                $whereParts[] = 'pm.meta_value LIKE %s';
                $args[] = $like;
            }

            if ($attachmentId > 0) {
                // Exact ID match for featured image / attachment ID custom fields.
                $whereParts[] = 'pm.meta_value = %s';
                $args[] = (string) $attachmentId;
            }

            $sql = "SELECT p.ID, p.post_title, p.post_type, pm.meta_key
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.post_id <> %d
                      AND (" . implode(' OR ', $whereParts) . ")
                    LIMIT 1200";
            array_unshift($args, $attachmentId);
            $prepared = $wpdb->prepare($sql, $args);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (!is_array($rows)) {
                return [];
            }

            $map = [];
            foreach ($rows as $row) {
                $id = (int) ($row['ID'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                if (!isset($map[$id])) {
                    $map[$id] = [
                        'id' => $id,
                        'title' => (string) ($row['post_title'] ?? ''),
                        'type' => (string) ($row['post_type'] ?? ''),
                        'edit_link' => (string) get_edit_post_link($id, 'raw'),
                        'meta_keys' => [],
                    ];
                }

                $mk = (string) ($row['meta_key'] ?? '');
                if ($mk !== '' && !in_array($mk, $map[$id]['meta_keys'], true)) {
                    $map[$id]['meta_keys'][] = $mk;
                }
            }

            return array_values($map);
        }

        /**
         * @param array<string,mixed> $session
         * @return array{percent:int,stage:string}
         */
        private static function checkerProgress(array $session): array
        {
            $scope = isset($session['scope']) ? (string) $session['scope'] : 'both';
            $includeDb = $scope !== 'source';
            $includeFiles = $scope !== 'db';
            $filesTotalRaw = (int) ($session['files_total'] ?? 0);
            $filesTotal = max(1, $filesTotalRaw);
            $filesChecked = min($filesTotal, (int) ($session['files_checked'] ?? 0));
            $dbUnits = $includeDb ? 2 : 0;
            $fileUnits = $includeFiles ? $filesTotal : 0;
            $totalUnits = max(1, $dbUnits + $fileUnits);
            $doneUnits = (!empty($session['db_posts_done']) ? 1 : 0) + (!empty($session['db_meta_done']) ? 1 : 0) + $filesChecked;
            $percent = (int) floor(($doneUnits / max(1, $totalUnits)) * 100);
            $percent = max(0, min(100, $percent));

            if ($includeDb && empty($session['db_posts_done'])) {
                $stage = 'Scanning database post/page content';
            } elseif ($includeDb && empty($session['db_meta_done'])) {
                $stage = 'Scanning custom fields';
            } elseif ($includeFiles && empty($session['done'])) {
                $stage = 'Scanning theme/plugin source files';
            } else {
                $stage = 'Scan completed';
            }

            return ['percent' => $percent, 'stage' => $stage];
        }

        /**
         * @param list<string> $terms
         */
        private static function fileContainsAny(string $path, array $terms): bool
        {
            $content = @file_get_contents($path);
            if (!is_string($content) || $content === '') {
                return false;
            }

            foreach ($terms as $term) {
                if ($term !== '' && strpos($content, $term) !== false) {
                    return true;
                }
            }

            return false;
        }

        private static function normalizePathForDisplay(string $path): string
        {
            $path = str_replace('\\', '/', $path);
            $base = str_replace('\\', '/', ABSPATH);
            if (strpos($path, $base) === 0) {
                return ltrim(substr($path, strlen($base)), '/');
            }

            return $path;
        }

        private static function checkerSessionKey(int $attachmentId): string
        {
            return 'nht_unused_checker_' . (int) get_current_user_id() . '_' . $attachmentId;
        }

        private static function scanAllSessionKey(): string
        {
            return 'nht_unused_scan_all_' . (int) get_current_user_id();
        }

        private static function attachmentDisplayName(int $attachmentId): string
        {
            $meta = wp_get_attachment_metadata($attachmentId);
            if (is_array($meta) && !empty($meta['file']) && is_string($meta['file'])) {
                return (string) wp_basename($meta['file']);
            }

            $path = (string) get_attached_file($attachmentId);
            if ($path !== '') {
                return (string) wp_basename($path);
            }

            return (string) get_the_title($attachmentId);
        }

        /**
         * Add Unused Checker URL to media JS payload (for modal UI).
         *
         * @param array<string,mixed> $response
         * @param array<string,mixed>|null $attachment
         * @return array<string,mixed>
         */
        public static function filterAttachmentForJsUnusedChecker($response, $attachment = null, $meta = null): array
        {
            if (!is_array($response) || !self::isEnabled('optimize_wp_media')) {
                return $response;
            }

            $id = isset($response['id']) ? (int) $response['id'] : 0;
            if ($id <= 0) {
                return $response;
            }

            $response['nhtUnusedCheckerUrl'] = add_query_arg(
                ['post' => $id, 'action' => 'edit', 'unused_checker' => '1'],
                admin_url('post.php')
            );
            return $response;
        }

        /**
         * Inject "Unused Checker" link into WP media modal below Edit Image.
         */
        public static function injectMediaModalUnusedCheckerScript(): void
        {
            if (!self::isEnabled('optimize_wp_media') || !is_admin() || !current_user_can('upload_files')) {
                return;
            }

            echo '<script>';
            echo '(function(){';
            echo 'function extractIdFromHref(h){if(!h)return 0;var m=String(h).match(/[?&]post=(\\d+)/);return m?parseInt(m[1],10):0;}';
            echo 'function addBetween(editEl,deleteEl,id){var parent=(editEl&&editEl.parentNode)||null; if(!parent)return; var exists=parent.querySelector(".nht-unused-checker-link"); if(exists){exists.remove();} var a=document.createElement("a"); a.className="nht-unused-checker-link"; a.style.display="block"; a.style.marginTop="6px"; a.href=(window.ajaxurl||"").replace("admin-ajax.php","post.php")+"?post="+id+"&action=edit&unused_checker=1"; a.textContent="Unused Checker"; if(deleteEl&&deleteEl.parentNode===parent){parent.insertBefore(a,deleteEl);} else if(editEl.nextSibling){parent.insertBefore(a,editEl.nextSibling);} else {parent.appendChild(a);} }';
            echo 'function render(){var boxes=document.querySelectorAll(".media-modal .attachment-details .details, .media-modal .attachment-details .actions, .media-modal .attachment-info .details, .media-modal .attachment-info .actions"); if(!boxes.length)return; boxes.forEach(function(box){var edit=box.querySelector("a.edit-attachment"); if(!edit){return;} var id=extractIdFromHref(edit.getAttribute("href")||""); if(!id){return;} var del=box.querySelector("button.delete-attachment, .delete-attachment, a.submitdelete"); if(!del){del=box.parentElement?box.parentElement.querySelector("button.delete-attachment, .delete-attachment, a.submitdelete"):null;} addBetween(edit,del,id);});}';
            echo 'var t=null; document.addEventListener("click",function(){clearTimeout(t); t=setTimeout(render,120);});';
            echo 'document.addEventListener("DOMContentLoaded",function(){setTimeout(render,300);});';
            echo 'setInterval(render,1200);';
            echo '})();';
            echo '</script>';
        }

        private static function buildAttachmentActionUrl(int $attachmentId, bool $forceDelete): string
        {
            $mode = $forceDelete ? 'delete' : 'trash';
            $base = add_query_arg(
                [
                    'action' => 'nht_unused_checker_action',
                    'mode' => $mode,
                    'attachment_id' => $attachmentId,
                ],
                admin_url('admin-post.php')
            );
            $url = wp_nonce_url($base, 'nht_unused_checker_action_' . $attachmentId . '_' . $mode);
            return html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
        }

        public static function handleUnusedCheckerAction(): void
        {
            if (!current_user_can('upload_files')) {
                wp_die(esc_html__('Unauthorized.', 'wp-optimizer'));
            }

            $attachmentId = isset($_GET['attachment_id']) ? (int) $_GET['attachment_id'] : 0;
            $mode = isset($_GET['mode']) ? sanitize_key((string) $_GET['mode']) : '';
            if ($attachmentId <= 0 || $mode !== 'delete') {
                wp_safe_redirect(add_query_arg('nht_unused_checker_notice', 'invalid', admin_url('upload.php')));
                exit;
            }

            check_admin_referer('nht_unused_checker_action_' . $attachmentId . '_' . $mode);

            if (!current_user_can('delete_post', $attachmentId)) {
                wp_safe_redirect(add_query_arg('nht_unused_checker_notice', 'forbidden', admin_url('upload.php')));
                exit;
            }

            $ok = false;
            $ok = (wp_delete_attachment($attachmentId, true) !== false);
            $notice = $ok ? 'delete_success' : 'delete_error';

            wp_safe_redirect(add_query_arg('nht_unused_checker_notice', $notice, admin_url('upload.php')));
            exit;
        }

        public static function renderUnusedCheckerNotice(): void
        {
            if (!is_admin()) {
                return;
            }

            global $pagenow;
            if ($pagenow !== 'upload.php' || !isset($_GET['nht_unused_checker_notice'])) {
                return;
            }

            $code = sanitize_key((string) $_GET['nht_unused_checker_notice']);
            $map = [
                'delete_success' => ['updated', __('Media item deleted permanently.', 'wp-optimizer')],
                'delete_error' => ['error', __('Failed to delete media item permanently.', 'wp-optimizer')],
                'forbidden' => ['error', __('You are not allowed to delete this media item.', 'wp-optimizer')],
                'invalid' => ['error', __('Invalid action request.', 'wp-optimizer')],
            ];
            if (!isset($map[$code])) {
                return;
            }

            $class = $map[$code][0] === 'updated' ? 'notice notice-success' : 'notice notice-error';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($map[$code][1]) . '</p></div>';
        }

        /**
         * Allow extended image uploads for administrators.
         *
         * @param mixed $user
         */
        private static function currentUserCanUploadExtendedImages($user = null): bool
        {
            $userId = 0;
            if (is_object($user) && isset($user->ID)) {
                $userId = (int) $user->ID;
            } elseif (is_numeric($user)) {
                $userId = (int) $user;
            } else {
                $userId = (int) get_current_user_id();
            }

            return user_can($userId, 'manage_options');
        }
    }
}
