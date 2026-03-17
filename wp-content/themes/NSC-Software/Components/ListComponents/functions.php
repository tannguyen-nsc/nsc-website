<?php

namespace NscSoftware\Components\ListComponents;

use NscSoftware\FieldVariables;
use NscSoftware\ComponentManager;
use NscSoftware\Utils\Options;
use Parsedown;

add_filter('NscSoftware/addComponentData?name=ListComponents', function ($data) {
    if (!empty($data['componentBlocks'])) {
        $templatePaths = [
            'dir' => trailingslashit(get_template_directory()),
            'uri' => trailingslashit(get_template_directory_uri()),
        ];
        $data['componentBlocks'] = array_map(function ($block) use ($templatePaths) {
            $block['component'] = substr($block['component'], strpos($block['component'], 'Components/'));

            $imagePath = $templatePaths['dir'] . $block['component'] . 'screenshot.png';
            if (file_exists($imagePath)) {
                $src = $templatePaths['uri'] . $block['component'] . 'screenshot.png';
                list($width, $height) = getimagesize($imagePath);

                $block['componentScreenshot'] = [
                    'src' => $src . '?v=' . wp_get_theme()->get('Version'),
                    'aspect' => $width / $height
                ];
            }

            $readme = $templatePaths['dir'] . $block['component'] . 'README.md';

            if (file_exists($readme)) {
                $readmeLines = explode(PHP_EOL, Parsedown::instance()->setUrlsLinked(false)->text(file_get_contents($readme)));
                $block['readme'] = [
                    'title' => strip_tags($readmeLines[0]),
                    'description' => implode(PHP_EOL, array_slice($readmeLines, 1))
                ];
            }

            return $block;
        }, $data['componentBlocks']);
    }

    return $data;
});

add_filter('acf/load_field/name=component', function ($field) {
    $componentManager = ComponentManager::getInstance();
    $field['choices'] = array_flip($componentManager->getAll());
    return $field;
});

function getACFLayout()
{
    return [
        'name' => 'listComponents',
        'label' => __('List: Components', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
            ],
            [
                'label' => __('Title', 'NscSoftware'),
                'instructions' => __('Want to add a headline? And a paragraph? Go ahead! Or just leave it empty and nothing will be shown.', 'NscSoftware'),
                'name' => 'preContentHtml',
                'type' => 'wysiwyg',
                'tabs' => 'visual,text',
                'media_upload' => 0,
                'delay' => 0,
            ],
            [
                'label' => __('Component Blocks', 'NscSoftware'),
                'name' => 'componentBlocks',
                'type' => 'repeater',
                'collapsed' => 0,
                'min' => 1,
                'layout' => 'table',
                'button_label' => __('Add Component Block', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Component', 'NscSoftware'),
                        'name' => 'component',
                        'type' => 'select',
                        'ui' => 1,
                        'ajax' => 0,
                        'choices' => [],
                        'wrapper' => [
                            'width' => 50
                        ],
                    ],
                    [
                        'label' => __('Calls To Action', 'NscSoftware'),
                        'name' => 'ctas',
                        'type' => 'group',
                        'collapsed' => 0,
                        'layout' => 'row',
                        'sub_fields' => [
                            [
                                'label' => __('Preview', 'NscSoftware'),
                                'name' => 'primary',
                                'type' => 'text'
                            ],
                            [
                                'label' => __('GitHub', 'NscSoftware'),
                                'name' => 'secondary',
                                'type' => 'url'
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getTheme(),
                ],
            ]
        ]
    ];
}

Options::addTranslatable('ListComponents', [
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => '',
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Code', 'NscSoftware'),
                'name' => 'code',
                'type' => 'text',
                'default_value' =>  __('Code', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Preview', 'NscSoftware'),
                'name' => 'preview',
                'type' => 'text',
                'default_value' =>  __('Preview', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ]
]);
