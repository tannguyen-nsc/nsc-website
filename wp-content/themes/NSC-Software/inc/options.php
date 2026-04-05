<?php

namespace NscSoftware\Options;

use NscSoftware\Utils\Options;

add_filter('NscSoftware/addComponentData', function ($data, $componentName) {
    // Get fields for this component.
    $options = array_reduce(Options::COMPONENT_MERGE_OPTION_TYPES, function ($carry, $optionType) use ($componentName) {
        $batch = Options::get($optionType, $componentName);
        if (!is_array($batch)) {
            return $carry;
        }

        return array_merge($carry, $batch);
    }, []);
    // Overlay ACF options on top of incoming $data so saved Theme Options win over empty defaults.
    return array_merge($data, $options);
}, 9, 2);
