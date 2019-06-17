<?php

/**
 * Example on how to use the Settings API
 */
\defined('ABSPATH') or die();

require_once('Libs/SettingsApi.php');

fireSettingsApi();

function fireSettingsApi() {
    $settingsFilter = 'register_my_settings'; // Filtername for your settings. Make it as unique as possible.
    $defaultOptions = my_get_options_default(); // This is your own function that returns your default options array
    $settingsApi = new SettingsApi($settingsFilter, $defaultOptions);
    $settingsApi->init();

    \add_filter($settingsFilter, 'getMySettings');
}

function getMySettings() {
    $themeOptionsPage['my-settings-page-slug'] = [
        'type' => 'theme',
        'menu_title' => \__('Options', 'my-text-domain'),
        'page_title' => \__('My Settings', 'my-text-domain'),
        'option_name' => 'my_settings', // Your settings name. With this name your settings are saved in the database.
        'tabs' => [
            /**
             * general settings tab
             */
            'general-settings' => getGeneralSettings(),
            /**
             * background settings tab
             */
            'background-settings' => getBackgroundSettings(),
        ]
    ];

    return $themeOptionsPage;
}

function getGeneralSettings() {
    return [
        'tab_title' => \__('General Settings', 'my-text-domain'),
        'tab_description' => \__('General Theme Settings', 'my-text-domain'),
        'fields' => $this->getGeneralSettingsFields()
    ];
}

function getBackgroundSettings() {
    return [
        'tab_title' => \__('Background Settings', 'my-text-domain'),
        'tab_description' => \__('Background Settings', 'my-text-domain'),
        'fields' => getBackgroundSettingsFields()
    ];
}

function getGeneralSettingsFields() {
    return [
        'type' => [
            'type' => 'select',
            'choices' => [
                'choice_1' => \__('Choice #1', 'my-text-domain'),
                'choice_2' => \__('Choice #2', 'my-text-domain')
            ],
            'empty' => \__('Please Select', 'my-text-domain'),
            'title' => \__('My Select', 'my-text-domain'),
            'description' => \__('Make your choice', 'my-text-domain')
        ]
    ];
}

function getBackgroundSettingsFields() {
    return [
        '' => [
            'type' => 'info',
            'infotext' => \__('This Infotext will be displayed in a little infobox above the settings', 'my-text-domain')
        ],
        'use_background_image' => [
            'type' => 'checkbox',
            'title' => \__('Use Background Image', 'my-text-domain'),
            'choices' => [
                'yes' => \__('Yes, I want to use background images on this website.', 'my-text-domain')
            ],
            'description' => \__('If this option is checked, the website will use your selected (down below) background image instead of a simple colored background.', 'my-text-domain')
        ],
        'background_image' => [
            'type' => 'radio',
            'choices' => get_default_background_images(), // Your own function that returns an array with the background images
            'empty' => \__('Please Select', 'my-text-domain'),
            'title' => \__('Background Image', 'my-text-domain'),
            'description' => \__('Select one of the default Background images ...', 'my-text-domain'),
            'align' => 'horizontal'
        ],
        'background_image_upload' => [
            'type' => 'image',
            'title' => \__('', 'my-text-domain'),
            'description' => \__('... or upload your own', 'my-text-domain')
        ]
    ];
}
