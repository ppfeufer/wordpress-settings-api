<?php

/**
 * Example on how to use the Settings API
 */
defined(constant_name: 'ABSPATH') or die();

require_once('Libs/SettingsApi.php');

fireSettingsApi();

function fireSettingsApi(): void
{
    $settingsFilter = 'register_my_settings'; // Filtername for your settings. Make it as unique as possible.
    $defaultOptions = my_get_options_default(); // This is your own function that returns your default options array
    $settingsApi = new SettingsApi(
        settingsFilter: $settingsFilter, defaultOptions: $defaultOptions
    );
    $settingsApi->init();

    add_filter(hook_name: $settingsFilter, callback: 'getMySettings');
}

function getMySettings(): array
{
    $themeOptionsPage['my-settings-page-slug'] = [
        'type' => 'theme',
        'menu_title' => __(text: 'Options', domain: 'my-text-domain'),
        'page_title' => __(text: 'My Settings', domain: 'my-text-domain'),
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

function getGeneralSettings(): array
{
    return [
        'tab_title' => __(text: 'General Settings', domain: 'my-text-domain'),
        'tab_description' => __(text: 'General Theme Settings', domain: 'my-text-domain'),
        'fields' => getGeneralSettingsFields()
    ];
}

function getBackgroundSettings(): array
{
    return [
        'tab_title' => __(text: 'Background Settings', domain: 'my-text-domain'),
        'tab_description' => __(text: 'Background Settings', domain: 'my-text-domain'),
        'fields' => getBackgroundSettingsFields()
    ];
}

function getGeneralSettingsFields(): array
{
    return [
        'type' => [
            'type' => 'select',
            'choices' => [
                'choice_1' => __(text: 'Choice #1', domain: 'my-text-domain'),
                'choice_2' => __(text: 'Choice #2', domain: 'my-text-domain')
            ],
            'empty' => __(text: 'Please Select', domain: 'my-text-domain'),
            'title' => __(text: 'My Select', domain: 'my-text-domain'),
            'description' => __(text: 'Make your choice', domain: 'my-text-domain')
        ]
    ];
}

function getBackgroundSettingsFields(): array
{
    return [
        '' => [
            'type' => 'info',
            'infotext' => __(
                text: 'This Infotext will be displayed in a little infobox above the settings',
                domain: 'my-text-domain'
            )
        ],
        'use_background_image' => [
            'type' => 'checkbox',
            'title' => __(text: 'Use Background Image', domain: 'my-text-domain'),
            'choices' => [
                'yes' => __(
                    text: 'Yes, I want to use background images on this website.',
                    domain: 'my-text-domain'
                )
            ],
            'description' => __(
                text: 'If this option is checked, the website will use your selected (down below) background image instead of a simple colored background.',
                domain: 'my-text-domain'
            )
        ],
        'background_image' => [
            'type' => 'radio',
            'choices' => get_default_background_images(), // Your own function that returns an array with the background images
            'empty' => __(text: 'Please Select', domain: 'my-text-domain'),
            'title' => __(text: 'Background Image', domain: 'my-text-domain'),
            'description' => __(
                text: 'Select one of the default Background images ...',
                domain: 'my-text-domain'
            ),
            'align' => 'horizontal'
        ],
        'background_image_upload' => [
            'type' => 'image',
            'title' => __(text: '', domain: 'my-text-domain'),
            'description' => __(text: '... or upload your own', domain: 'my-text-domain')
        ]
    ];
}
