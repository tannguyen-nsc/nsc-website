<?php

namespace NscSoftware\Components\NSCFooter;

use NscSoftware\Menu;
use NscSoftware\Utils\Options;
use Timber\Timber;

add_action('init', function () {
    register_nav_menus([
        'navigation_footer' => __('Navigation Footer', 'NscSoftware'),
        'sitemap_footer' => __('Footer Sitemap', 'NscSoftware'),
    ]);
});

/**
 * Default offices from frontend/build/index.html footer (when Options have none).
 *
 * @return array<int, array{title: string, address: string, phone: string, phoneLink: string}>
 */
function getDefaultOffices(): array
{
    return [
        ['title' => 'NSC Software Headquarters:', 'address' => "Level 22, PVI Tower, Pham Van Bach, Cau Giay, Hanoi, Vietnam", 'phone' => '(+84) 866 639 497', 'phoneLink' => 'tel:+84866639497'],
        ['title' => 'NSC Software Ho Chi Minh:', 'address' => 'Level 10, Five Star Tower, 28 Bis, Ho Chi Minh, Vietnam', 'phone' => '(+84) 866 639 497', 'phoneLink' => 'tel:+84866639497'],
        ['title' => 'NSC Software USA:', 'address' => '4245 N Central Expy, #490, Dallas, TX, USA 75205', 'phone' => '+1 (713) 428 2289', 'phoneLink' => 'tel:+17134282289'],
        ['title' => 'NSC Software Australia:', 'address' => 'Level 24, Three International Towers, 300 Barangaroo Avenue, Sydney NSW 2000, Australia', 'phone' => '(+61) 0488 860 719', 'phoneLink' => 'tel:+61488860719'],
        ['title' => 'NSC Software Europe:', 'address' => 'Am Hauptbahnhof 16, D-60306 Frankfurt am Main, Germany', 'phone' => '(+49) 170 1633520', 'phoneLink' => 'tel:+491701633520'],
    ];
}

add_filter('NscSoftware/addComponentData?name=NSCFooter', function ($data) {
    $data['menu'] = Timber::get_menu('navigation_footer') ?? Timber::get_pages_menu();
    Menu\ensure_menu_item_classes($data['menu']);
    $sitemapMenu = Timber::get_menu('sitemap_footer');
    Menu\ensure_menu_item_classes($sitemapMenu);
    $data['sitemapMenu'] = $sitemapMenu;
    $rawItems = $sitemapMenu && !empty($sitemapMenu->items) ? $sitemapMenu->items : ($data['menu'] && !empty($data['menu']->items) ? $data['menu']->items : []);
    $sitemapItems = is_array($rawItems) ? $rawItems : (is_countable($rawItems) ? iterator_to_array($rawItems, false) : []);
    $data['sitemapItems'] = $sitemapItems;
    $n = count($sitemapItems);
    // First column: 2 items. Last column: 2 items. Middle column: the rest (no overlap).
    $data['sitemapCol1'] = array_slice($sitemapItems, 0, 2);
    $data['sitemapCol3'] = $n >= 4 ? array_slice($sitemapItems, -2) : ($n === 3 ? array_slice($sitemapItems, 2, 1) : []);
    $data['sitemapCol2'] = $n > 4 ? array_slice($sitemapItems, 2, $n - 4) : [];
    $options = Options::getTranslatable('NSCFooter');
    $data['companyName'] = $options['companyName'] ?? get_bloginfo('name');
    $data['companyDescription'] = $options['companyDescription'] ?? '';
    $data['businessNumber'] = $options['businessNumber'] ?? '';
    $data['email'] = $options['email'] ?? '';
    $data['offices'] = !empty($options['offices']) ? $options['offices'] : getDefaultOffices();
    $data['copyright'] = $options['copyright'] ?? '© ' . date_i18n('Y') . ' ' . get_bloginfo('name');
    $data['legalLinks'] = $options['legalLinks'] ?? [];
    $data['socialLinks'] = $options['socialLinks'] ?? [];
    $data['logo'] = $options['logo'] ?? null;
    $data['logoMobile'] = $options['logoMobile'] ?? null;
    $data['certifications'] = $options['certifications'] ?? [];
    $data['buildUri'] = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    return $data;
});

Options::addTranslatable('NSCFooter', [
    [
        'label' => __('Company', 'NscSoftware'),
        'name' => 'companyTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Company name', 'NscSoftware'),
        'name' => 'companyName',
        'type' => 'text',
        'default_value' => 'NSC Software Co., LTD',
    ],
    [
        'label' => __('Company description', 'NscSoftware'),
        'name' => 'companyDescription',
        'type' => 'textarea',
        'default_value' => "Vietnam's Premier Software Development & Consulting Company",
    ],
    [
        'label' => __('Business number', 'NscSoftware'),
        'name' => 'businessNumber',
        'type' => 'text',
        'default_value' => '0110524817',
    ],
    [
        'label' => __('Email', 'NscSoftware'),
        'name' => 'email',
        'type' => 'email',
        'default_value' => 'contact@nscsoftware.com',
    ],
    [
        'label' => __('Logo (desktop footer)', 'NscSoftware'),
        'name' => 'logo',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
    ],
    [
        'label' => __('Logo (mobile footer)', 'NscSoftware'),
        'name' => 'logoMobile',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
    ],
    [
        'label' => __('Certifications', 'NscSoftware'),
        'name' => 'certificationsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Certification badges', 'NscSoftware'),
        'name' => 'certifications',
        'type' => 'repeater',
        'min' => 0,
        'max' => 6,
        'layout' => 'block',
        'instructions' => __('Upload certification images (e.g. ISO). They appear in the footer. Leave empty to use default ISO badges.', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Image', 'NscSoftware'),
                'name' => 'image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
            ],
        ],
    ],
    [
        'label' => __('Offices', 'NscSoftware'),
        'name' => 'officesTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Offices', 'NscSoftware'),
        'name' => 'offices',
        'type' => 'repeater',
        'min' => 0,
        'layout' => 'block',
        'collapsed' => 'field_translatable_NSCFooter_offices_title',
        'sub_fields' => [
            [
                'label' => __('Title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
            ],
            [
                'label' => __('Address / details', 'NscSoftware'),
                'name' => 'address',
                'type' => 'textarea',
            ],
            [
                'label' => __('Phone', 'NscSoftware'),
                'name' => 'phone',
                'type' => 'text',
            ],
            [
                'label' => __('Phone link (text only, e.g. +84 866 639 497)', 'NscSoftware'),
                'name' => 'phoneLink',
                'type' => 'text',
                'instructions' => __('Enter phone number as text. It will be used as a tel: link. Example: +84 866 639 497', 'NscSoftware'),
            ],
        ],
    ],
    [
        'label' => __('Bottom', 'NscSoftware'),
        'name' => 'bottomTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Copyright text', 'NscSoftware'),
        'name' => 'copyright',
        'type' => 'text',
        'default_value' => 'NSC@2026 All copyrights reserved',
    ],
    [
        'label' => __('Legal links', 'NscSoftware'),
        'name' => 'legalLinks',
        'type' => 'repeater',
        'min' => 0,
        'layout' => 'table',
        'sub_fields' => [
            [
                'label' => __('Label', 'NscSoftware'),
                'name' => 'label',
                'type' => 'text',
            ],
            [
                'label' => __('URL', 'NscSoftware'),
                'name' => 'url',
                'type' => 'url',
                'default_value' => home_url('/'),
            ],
            [
                'label' => __('Open in new tab', 'NscSoftware'),
                'name' => 'openInNewTab',
                'type' => 'true_false',
                'default_value' => 0,
            ],
        ],
    ],
    [
        'label' => __('Social links', 'NscSoftware'),
        'name' => 'socialLinks',
        'type' => 'repeater',
        'min' => 0,
        'layout' => 'table',
        'sub_fields' => [
            [
                'label' => __('Platform', 'NscSoftware'),
                'name' => 'platform',
                'type' => 'select',
                'choices' => [
                    'linkedin' => 'LinkedIn',
                    'facebook' => 'Facebook',
                    'youtube' => 'YouTube',
                ],
            ],
            [
                'label' => __('URL', 'NscSoftware'),
                'name' => 'url',
                'type' => 'url',
                'default_value' => home_url('/'),
            ],
            [
                'label' => __('Aria label', 'NscSoftware'),
                'name' => 'ariaLabel',
                'type' => 'text',
            ],
        ],
    ],
]);
