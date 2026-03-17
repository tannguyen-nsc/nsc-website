<?php

namespace NscSoftware\SliderOptions;

use NscSoftware\Utils\Options;

Options::addTranslatable('SliderOptions', [
    [
        'label' => __('Accessibility', 'NscSoftware'),
        'instructions' => __('Text labels for screen readers.', 'NscSoftware'),
        'name' => 'a11y',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Next Slide Button Text', 'NscSoftware'),
                'name' => 'nextSlideMessage',
                'type' => 'text',
                'default_value' => __('Next Slide', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Previous Slide Button Text', 'NscSoftware'),
                'name' => 'prevSlideMessage',
                'type' => 'text',
                'default_value' => __('Previous Slide', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('First Slide Text', 'NscSoftware'),
                'instructions' => __('Text for previous button when swiper is on first slide.', 'NscSoftware'),
                'name' => 'firstSlideMessage',
                'type' => 'text',
                'default_value' => __('This is the first slide', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Last Slide Text', 'NscSoftware'),
                'instructions' => __('Text for previous button when swiper is on last slide.', 'NscSoftware'),
                'name' => 'lastSlideMessage',
                'type' => 'text',
                'default_value' => __('This is the last slide', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Pagination Bullet Message', 'NscSoftware'),
                'instructions' => '`{{index}}` will be replaced for the slide number.',
                'name' => 'paginationBulletMessage',
                'type' => 'text',
                'default_value' => __('Go to slide {{index}}', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
