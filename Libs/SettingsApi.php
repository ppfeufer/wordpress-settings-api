<?php
/**
 * WordPress Settings API
 * by H. Peter Pfeufer
 *
 * Usage:
 *     $settingsApi = new SettingsApi($settingsFilterName, $defaultOptions);
 *     $settingsApi->init();
 *
 * @version 0.1
 */

// Do not load this file directly
defined(constant_name: 'ABSPATH') or die();

require_once('Helper/StringHelper.php');

#[AllowDynamicProperties] class SettingsApi
{
    /**
     * Settings Arguments
     *
     * @var array
     */
    private array $args;

    /**
     * Settings Array
     *
     * @var array
     */
    private array $settingsArray;

    /**
     * Settings Filter Name
     *
     * @var string
     */
    private string $settingsFilter;

    /**
     * Default Options
     *
     * @var array
     */
    private array $optionsDefault;

    /**
     * Constructor
     *
     * @param string $settingsFilter The name of your settings filter
     * @param array $defaultOptions Your default options array
     */
    public function __construct(string $settingsFilter, array $defaultOptions)
    {
        $this->settingsFilter = $settingsFilter;
        $this->optionsDefault = $defaultOptions;
    }

    /**
     * Initializing all actions
     */
    public function init(): void
    {
        add_action(hook_name: 'init', callback: [$this, 'initSettings']);
        add_action(hook_name: 'admin_menu', callback: [$this, 'menuPage']);
        add_action(hook_name: 'admin_init', callback: [$this, 'registerFields']);
        add_action(hook_name: 'admin_init', callback: [$this, 'registerCallback']);
        add_action(hook_name: 'admin_enqueue_scripts', callback: [$this, 'enqueueScripts']);
        add_action(hook_name: 'admin_enqueue_scripts', callback: [$this, 'enqueueStyles']);
    }

    /**
     * Init settings runs before admin_init
     * Put $settingsArray to private variable
     * Add admin_head for needed inline scripts
     */
    public function initSettings(): void
    {
        if (is_admin()) {
            $this->settingsArray = apply_filters($this->settingsFilter, []);

            if ($this->isSettingsPage() === true) {
                add_action(hook_name: 'admin_head', callback: [$this, 'adminScripts']);
            }
        }
    }

    /**
     * Check if the current page is a settings page
     *
     * @return boolean
     */
    public function isSettingsPage(): bool
    {
        $menus = [];
        $getPage = filter_input(type: INPUT_GET, var_name: 'page');
        $settingsPage = (!empty($getPage)) ? $getPage : '';
        $returnValue = false;

        /* @var $menu string */
        /* @var $page string */
        foreach ($this->settingsArray as $menu => $page) {
            $menus[] = $menu;

            // not used at this moment
            unset($page);
        }

        if (in_array($settingsPage, $menus)) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Creating pages and menus from the settingsArray
     */
    public function menuPage(): void
    {
        foreach ($this->settingsArray as $menu_slug => $options) {
            if (!empty($options['page_title']) && !empty($options['menu_title']) && !empty($options['option_name'])) {
                /**
                 * Set capabilities
                 * If none is set, 'manage_options' will be default
                 */
                $options['capability'] = (!empty($options['capability']))
                    ? $options['capability']
                    : 'manage_options';

                /**
                 * Set type
                 * If none is set, 'plugin' will be default
                 *
                 * Supported types:
                 *     theme    => Adds the options page to Appearance menu
                 *     plugin   => Adds the options page to Settings menu
                 */
                $options['type'] = (!empty($options['type'])) ? $options['type'] : 'plugin';

                switch ($options['type']) {
                    // Adding theme settings page
                    case 'theme':
                        add_theme_page(
                            page_title: $options['page_title'],
                            menu_title: $options['menu_title'],
                            capability: $options['capability'],
                            menu_slug: $menu_slug,
                            callback: [$this, 'renderOptions']
                        );
                        break;

                    // Adding plugin settings page
                    case 'plugin':
                        add_options_page(
                            page_title: $options['page_title'],
                            menu_title: $options['menu_title'],
                            capability: $options['capability'],
                            menu_slug: $menu_slug,
                            callback: [$this, 'renderOptions']
                        );
                        break;
                }
            }
        }
    }

    /**
     * Register all fields and settings bound to it from the settingsArray
     */
    public function registerFields(): void
    {
        foreach ($this->settingsArray as $pageId => $settings) {
            if (!empty($settings['tabs']) && is_array($settings['tabs'])) {
                foreach ($settings['tabs'] as $tabId => $item) {
                    $sanitizedTabId = sanitize_title($tabId);
                    $tabDescription = (!empty($item['tab_description']))
                        ? $item['tab_description']
                        : '';
                    $settingArgs = [
                        'option_group' => 'section_page_' . $pageId . '_' . $sanitizedTabId,
                        'option_name' => $settings['option_name']
                    ];

                    register_setting(
                        option_group: $settingArgs['option_group'],
                        option_name: $settingArgs['option_name']
                    );

                    $sectionArgs = [
                        'id' => 'section_id_' . $sanitizedTabId,
                        'title' => $tabDescription,
                        'callback' => 'callback',
                        'menu_page' => $pageId . '_' . $sanitizedTabId
                    ];

                    add_settings_section(
                        id: $sectionArgs['id'],
                        title: $sectionArgs['title'],
                        callback: [$this, $sectionArgs['callback']],
                        page: $sectionArgs['menu_page']
                    );

                    if (!empty($item['fields']) && is_array($item['fields'])) {
                        foreach ($item['fields'] as $fieldId => $field) {
                            if (is_array(value: $field)) {
                                $sanitizedFieldId = sanitize_title(title: $fieldId);
                                $title = (!empty($field['title']))
                                    ? $field['title']
                                    : '';
                                $field['field_id'] = $sanitizedFieldId;
                                $field['option_name'] = $settings['option_name'];
                                $fieldArgs = [
                                    'id' => 'field' . $sanitizedFieldId,
                                    'title' => $title,
                                    'callback' => 'renderFields',
                                    'menu_page' => $pageId . '_' . $sanitizedTabId,
                                    'section' => 'section_id_' . $sanitizedTabId,
                                    'args' => $field
                                ];

                                add_settings_field(
                                    id: $fieldArgs['id'],
                                    title: $fieldArgs['title'],
                                    callback: [$this, $fieldArgs['callback']],
                                    page: $fieldArgs['menu_page'],
                                    section: $fieldArgs['section'],
                                    args: $fieldArgs['args']
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Register callback is used for the button field type when user click the button
     * For now only works with plugin settings
     */
    public function registerCallback(): void
    {
        if ($this->isSettingsPage() === true) {
            $getCallback = filter_input(type: INPUT_GET, var_name: 'callback');
            $getWpNonce = filter_input(type: INPUT_GET, var_name: '_wpnonce');
            $getPage = filter_input(type: INPUT_GET, var_name: 'page');

            if (!empty($getCallback)) {
                $nonce = wp_verify_nonce(nonce: $getWpNonce);

                if (!empty($nonce)) {
                    if (function_exists(function: $getCallback)) {
                        $message = call_user_func(callback: $getCallback);
                        update_option(option: 'rsa-message', value: $message);

                        $url = admin_url(path: 'options-general.php?page=' . $getPage);
                        wp_redirect(location: $url);

                        die;
                    }
                }
            }
        }
    }

    /**
     * Get users from WordPress, used by the select field type
     */
    public function getUsers(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $users = get_users(args: $args);

        foreach ($users as $user) {
            $items[$user->ID] = $user->display_name;
        }

        return $items;
    }

    /**
     * Get menus from WordPress, used by the select field type
     */
    public function getMenus(): array
    {
        $items = [];
        $menus = get_registered_nav_menus();

        if (!empty($menus)) {
            foreach ($menus as $location => $description) {
                $items[$location] = $description;
            }
        }

        return $items;
    }

    /**
     * Get posts from WordPress, used by the select field type
     */
    public function getPosts(): array
    {
        $items = null;

        if ($this->args['get'] === 'posts' && !empty($this->args['post_type'])) {
            $args = [
                'category' => 0,
                'post_type' => $this->args['post_type'],
                'post_status' => 'publish',
                'orderby' => 'post_title',
                'order' => 'ASC',
                'suppress_filters' => true,
                'posts_per_page' => -1
            ];

            $theQuery = new WP_Query(query: $args);

            if ($theQuery->have_posts()) {
                while ($theQuery->have_posts()) {
                    $theQuery->the_post();

                    global $post;

                    $items[$post->ID] = get_the_title();
                }
            }

            wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Get terms from WordPress, used by the select field type
     */
    public function getTerms(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $args['taxonomy'] = (!empty($this->args['taxonomies']))
            ? $this->args['taxonomies']
            : null;
        $terms = get_terms(args: $args);

        if (!empty($terms)) {
            foreach ($terms as $key => $term) {
                $items[$term->term_id] = $term->name;

                // not used at the moment
                unset($key);
            }
        }

        return $items;
    }

    /**
     * Get taxonomies from WordPress, used by the select field type
     */
    public function getTaxonomies(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $taxonomies = get_taxonomies(args: $args, output: 'objects');

        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $items[$taxonomy->name] = $taxonomy->label;
            }
        }

        return $items;
    }

    /**
     * Get sidebars from WordPress, used by the select field type
     */
    public function getSidebars(): array
    {
        $items = [];

        global $wp_registered_sidebars;

        if (!empty($wp_registered_sidebars)) {
            foreach ($wp_registered_sidebars as $sidebar) {
                $items[$sidebar['id']] = $sidebar['name'];
            }
        }

        return $items;
    }

    /**
     * Get themes from WordPress, used by the select field type
     */
    public function getThemes(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $themes = wp_get_themes(args: $args);

        if (!empty($themes)) {
            foreach ($themes as $key => $theme) {
                $items[$key] = $theme->get(header: 'Name');
            }
        }

        return $items;
    }

    /**
     * Get values from built in WordPress functions
     */
    public function get(): mixed
    {
        if (!empty($this->args['get'])) {
            $itemArray = call_user_func_array(
                callback: [$this, 'get' . Helper\StringHelper::camelCase($this->args['get'], true)],
                args: [$this->args]
            );
        } elseif (!empty($this->args['choices'])) {
            $itemArray = $this->selectChoices();
        } else {
            $itemArray = [];
        }

        return $itemArray;
    }

    /**
     * Return an array for the choices in a select field type
     */
    public function selectChoices(): array
    {
        $items = [];

        if (!empty($this->args['choices']) && is_array(value: $this->args['choices'])) {
            foreach ($this->args['choices'] as $slug => $choice) {
                $items[$slug] = $choice;
            }
        }

        return $items;
    }

    /**
     * Get plugins from WordPress, used by the select field type
     */
    public function getPlugins(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $plugins = get_plugins(plugin_folder: $args);

        if (!empty($plugins)) {
            foreach ($plugins as $key => $plugin) {
                $items[$key] = $plugin['Name'];
            }
        }

        return $items;
    }

    /**
     * Get post_types from WordPress, used by the select field type
     */
    public function getPostTypes(): array
    {
        $items = [];
        $args = (!empty($this->args['args']))
            ? $this->args['args']
            : null;
        $postTypes = get_post_types(args: $args, output: 'objects');

        if (!empty($postTypes)) {
            foreach ($postTypes as $key => $postType) {
                $items[$key] = $postType->name;
            }
        }

        return $items;
    }

    /**
     * All the field types in html
     */
    public function renderFields(array $args): void
    {
        $args['field_id'] = sanitize_title(title: $args['field_id']);
        $this->args = $args;

        $options = get_option(
            option: $args['option_name'], default_value: $this->optionsDefault
        );
        $this->options = $options;

        $optionName = sanitize_title(title: $args['option_name']);
        $out = '';

        if (!empty($args['type'])) {
            switch ($args['type']) {
                case 'info':
                    if (!empty($args['infotext'])) {
                        $out .= '<div class="notice notice-warning"><p>' . $args['infotext'] . '</p></div>';
                    }
                    break;

                case 'select':
                case 'multiselect':
                    $multiple = ($args['type'] == 'multiselect')
                        ? ' multiple'
                        : '';
                    $items = $this->get();
                    $out .= '<select' . $multiple . ' name="' . $this->name() . '"' . $this->size(items: $items) . '>';

                    if (!empty($args['empty'])) {
                        $out .= '<option value="" ' . $this->selected(key: '') . '>' . $args['empty'] . '</option>';
                    }

                    foreach ($items as $key => $choice) {
                        $key = sanitize_title(title: $key);
                        $out .= '<option value="' . $key . '" ' . $this->selected(key: $key) . '>' . $choice . '</option>';
                    }

                    $out .= '</select>';
                    break;

                case 'radio':
                case 'checkbox':
                    if ($this->hasItems()) {
                        $horizontal = (isset($args['align']) && (string) $args['align'] == 'horizontal')
                            ? ' class="horizontal"'
                            : '';

                        $out .= '<ul class="settings-group settings-type-' . $args['type'] . '">';

                        foreach ($args['choices'] as $slug => $choice) {
                            $checked = $this->checked($slug);

                            $out .= '<li' . $horizontal . '><label>';
                            $out .= '<input value="' . $slug . '" type="' . $args['type'] . '" name="' . $this->name(slug: $slug) . '"' . $checked . '>';
                            $out .= $choice;
                            $out .= '</label></li>';
                        }

                        $out .= '</ul>';
                    }
                    break;

                case 'text':
                case 'email':
                case 'url':
                case 'color':
                case 'date':
                case 'number':
                case 'password':
                case 'colorpicker':
                case 'datepicker':
                    $out = '<input type="' . $args['type'] . '" value="' . $this->value() . '" name="' . $this->name() . '" class="' . $args['type'] . '" data-id="' . $args['field_id'] . '">';
                    break;

                case 'textarea':
                    $rows = (isset($args['rows']))
                        ? $args['rows']
                        : 5;
                    $out .= '<textarea rows="' . $rows . '" class="large-text" name="' . $this->name() . '">' . $this->value() . '</textarea>';
                    break;

                case 'tinymce':
                    $rows = (isset($args['rows']))
                        ? $args['rows']
                        : 5;
                    $tinymceSettings = [
                        'textarea_rows' => $rows,
                        'textarea_name' => $optionName . '[' . $args['field_id'] . ']',
                    ];

                    wp_editor($this->value(), $args['field_id'], $tinymceSettings);
                    break;

                case 'image':
                    $imageObj = (!empty($options[$args['field_id']]))
                        ? wp_get_attachment_image_src(attachment_id: $options[$args['field_id']])
                        : '';
                    $image = (!empty($imageObj))
                        ? $imageObj[0]
                        : '';
                    $uploadStatus = (!empty($imageObj))
                        ? ' style="display: none"'
                        : '';
                    $removeStatus = (!empty($imageObj))
                        ? ''
                        : ' style="display: none"';
                    $value = (!empty($options[$args['field_id']]))
                        ? $options[$args['field_id']]
                        : '';
                    ?>
                    <div data-id="<?php echo $args['field_id']; ?>">
                        <div class="upload" data-field-id="<?php echo $args['field_id']; ?>"<?php echo $uploadStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-upload"></i>
                                    <?php echo __(text: 'Upload'); ?>
                                </a>
                            </span>
                        </div>
                        <div class="image">
                            <img class="uploaded-image" src="<?php echo $image; ?>" id="<?php echo $args['field_id']; ?>"/>
                        </div>
                        <div class="remove"<?php echo $removeStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-trash"></i>
                                    <?php echo __(text: 'Remove'); ?>
                                </a>
                            </span>
                        </div>
                        <input type="hidden" class="attachment_id" value="<?php echo $value; ?>" name="<?php echo $optionName; ?>[<?php echo $args['field_id']; ?>]">
                    </div>
                    <?php
                    break;

                case 'file':
                    $fileUrl = (!empty($options[$args['field_id']]))
                        ? wp_get_attachment_url(attachment_id: $options[$args['field_id']])
                        : '';
                    $uploadStatus = (!empty($fileUrl))
                        ? ' style="display: none"'
                        : '';
                    $removeStatus = (!empty($fileUrl))
                        ? ''
                        : ' style="display: none"';
                    $value = (!empty($options[$args['field_id']]))
                        ? $options[$args['field_id']]
                        : '';
                    ?>
                    <div data-id="<?php echo $args['field_id']; ?>">
                        <div class="upload" data-field-id="<?php echo $args['field_id']; ?>"<?php echo $uploadStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-upload"></i>
                                    <?php echo __(text: 'Upload'); ?>
                                </a>
                            </span>
                        </div>
                        <div class="url">
                            <code class="uploaded-file-url" title="Attachment ID: <?php echo $value; ?>" data-field-id="<?php echo $args['field_id']; ?>">
                                <?php echo $fileUrl; ?>
                            </code>
                        </div>
                        <div class="remove"<?php echo $removeStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-trash"></i>
                                    <?php echo __(text: 'Remove'); ?>
                                </a>
                            </span>
                        </div>
                        <input type="hidden" class="attachment_id" value="<?php echo $value; ?>" name="<?php echo $optionName; ?>[<?php echo $args['field_id']; ?>]">
                    </div>
                    <?php
                    break;

                case 'button':
                    $getPage = filter_input(type: INPUT_GET, var_name: 'page');
                    $warningMessage = (!empty($args['warning-message']))
                        ? $args['warning-message']
                        : 'Unsaved settings will be lost. Continue?';
                    $warning = (!empty($args['warning']))
                        ? ' onclick="return confirm(' . "'" . $warningMessage . "'" . ')"'
                        : '';
                    $label = (!empty($args['label']))
                        ? $args['label']
                        : '';
                    $completeUrl = wp_nonce_url(
                        actionurl: admin_url(
                            path: 'options-general.php?page=' . $getPage . '&callback=' . $args['callback']
                        )
                    );
                    ?>
                    <a href="<?php echo $completeUrl; ?>" class="button button-secondary"<?php echo $warning; ?>><?php echo $label; ?></a>
                    <?php
                    break;

                case 'custom':
                    $value = (!empty($options[$args['field_id']])) ? $options[$args['field_id']] : null;
                    $data = [
                        'value' => $value,
                        'name' => $this->name(),
                        'args' => $args
                    ];

                    if ($args['content'] !== null) {
                        echo $args['content'];
                    }

                    if ($args['callback'] !== null) {
                        call_user_func(callback: $args['callback'], args: $data);
                    }
                    break;
            }
        }

        echo $out;

        if (!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</div>';
        }
    }

    /**
     * Return the html name of the field
     */
    public function name(string $slug = ''): string
    {
        $optionName = sanitize_title(title: $this->args['option_name']);

        if ($this->valueType() === 'array') {
            $returnValue = $optionName . '[' . $this->args['field_id'] . '][' . $slug . ']';
        } else {
            $returnValue = $optionName . '[' . $this->args['field_id'] . ']';
        }

        return $returnValue;
    }

    /**
     * Check if the current value type is a single value or a multiple value
     * field type, return string or array
     */
    public function valueType(): ?string
    {
        $returnValue = null;
        $defaultSingle = [
            'select',
            'radio',
            'text',
            'email',
            'url',
            'color',
            'date',
            'number',
            'password',
            'colorpicker',
            'textarea',
            'datepicker',
            'tinymce',
            'image',
            'file'
        ];
        $defaultMultiple = [
            'multiselect',
            'checkbox'
        ];

        if (in_array(needle: $this->args['type'], haystack: $defaultSingle)) {
            $returnValue = 'string';
        } elseif (in_array(needle: $this->args['type'], haystack: $defaultMultiple)) {
            $returnValue = 'array';
        }

        return $returnValue;
    }

    /**
     * Return the size of a multiselect type. If not set it will calculate it
     *
     * @param array|Countable $items
     * @return string
     */
    public function size(array|Countable $items): string
    {
        $size = '';

        if ($this->args['type'] == 'multiselect') {
            if (!empty($this->args['size'])) {
                $count = $this->args['size'];
            } else {
                $countItems = count(value: $items);
                $count = (!empty($this->args['empty']))
                    ? $countItems + 1
                    : $countItems;
            }

            $size = ' size="' . $count . '"';
        }

        return $size;
    }

    /**
     * Find a selected value in select or multiselect field type
     */
    public function selected(string $key): string
    {
        if ($this->valueType() === 'array') {
            $returnValue = $this->multiselectedValue(key: $key);
        } else {
            $returnValue = $this->selectedValue(key: $key);
        }

        return $returnValue;
    }

    /**
     * Return selected html if the value is selected in multiselect field type
     */
    public function multiselectedValue(string $key): string
    {
        $result = '';
        $value = $this->value();

        if (is_array(value: $value) && in_array(needle: $key, haystack: $value)) {
            $result = ' selected="selected"';
        }

        return $result;
    }

    /**
     * Return the value. If the value is not saved the default value is used if
     * exists in the settingsArray.
     *
     * @return string|array
     */
    public function value(): string|array
    {
        if ($this->valueType() == 'array') {
            $default = (!empty($this->args['default']) && is_array(value: $this->args['default']))
                ? $this->args['default']
                : [];
        } else {
            $default = (!empty($this->args['default']))
                ? $this->args['default']
                : '';
        }

        return (isset($this->options[$this->args['field_id']]))
            ? $this->options[$this->args['field_id']]
            : $default;
    }

    /**
     * Return selected html if the value is selected in select field type
     */
    public function selectedValue(string $key): string
    {
        $result = '';

        if ($this->value() === $key) {
            $result = ' selected="selected"';
        }

        return $result;
    }

    /**
     * Check if a checkbox has items
     */
    public function hasItems(): bool
    {
        $returnValue = false;

        if (!empty($this->args['choices']) && is_array(value: $this->args['choices'])) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Return checked html if the value is checked in radio or checkboxes
     */
    public function checked(string $slug): string
    {
        if ($this->valueType() == 'array') {
            $checked = (!empty($this->value()) && in_array(needle: $slug, haystack: $this->value()))
                ? ' checked="checked"'
                : '';
        } else {
            $checked = (!empty($this->value()) && $slug == $this->value())
                ? ' checked="checked"'
                : '';
        }

        return $checked;
    }

    /**
     * Callback for field registration. It's required by WordPress but not used by this plugin
     */
    public function callback()
    {

    }

    /**
     * Final output on the settings page
     */
    public function renderOptions(): void
    {
        $page = filter_input(type: INPUT_GET, var_name: 'page');
        $settings = $this->settingsArray[$page];
        $message = get_option(option: 'rsa-message');

        if (!empty($settings['tabs']) && is_array(value: $settings['tabs'])) {
            $tabCount = count(value: $settings['tabs']);
            ?>
            <div class="wrap">
                <?php
                if (!empty($settings['before_tabs_text'])) {
                    echo $settings['before_tabs_text'];
                }
                ?>
                <form action='options.php' method='post'>
                    <?php
                    if ($tabCount > 1) {
                        ?>
                        <h2 class="nav-tab-wrapper">
                            <?php
                            $i = 0;
                            foreach ($settings['tabs'] as $settingsId => $section) {
                                $sanitizedId = sanitize_title(title: $settingsId);
                                $tabTitle = (!empty($section['tab_title']))
                                    ? $section['tab_title']
                                    : $sanitizedId;
                                $active = ($i == 0) ? ' nav-tab-active' : '';

                                echo '<a class="nav-tab nav-tab-' . $sanitizedId . $active . '" href="#tab-content-' . $sanitizedId . '">' . $tabTitle . '</a>';

                                $i++;
                            }
                            ?>
                        </h2>

                        <?php
                        if (!empty($message)) {
                            ?>
                            <div class="updated settings-error">
                                <p><strong><?php echo $message; ?></strong></p>
                            </div>
                            <?php
                            update_option(option: 'rsa-message', value: '');
                        }
                    } // END if($tab_count > 1)

                    $i = 0;
                    foreach ($settings['tabs'] as $settingsId => $section) {
                        $sanitizedId = sanitize_title($settingsId);
                        $pageId = $page . '_' . $sanitizedId;

                        $display = ($i == 0)
                            ? ' style="display: block;"'
                            : ' style="display:none;"';

                        echo '<div class="tab-content" id="tab-content-' . $sanitizedId . '"' . $display . '>';

                        settings_fields(option_group: 'section_page_' . $page . '_' . $sanitizedId);
                        do_settings_sections($pageId);

                        echo '</div>';

                        $i++;
                    }

                    submit_button();
                    ?>
                </form>

                <?php
                if (!empty($settings['after_tabs_text'])) {
                    echo $settings['after_tabs_text'];
                } // END if(!empty($settings['after_tabs_text']))
                ?>
            </div>
            <?php
        }
    }

    /**
     * Register scripts
     */
    public function enqueueScripts(): void
    {
        if ($this->isSettingsPage() === true) {
            wp_enqueue_media();
            wp_enqueue_script(handle: 'wp-color-picker');
            wp_enqueue_script(handle: 'jquery-ui-datepicker');
            wp_enqueue_script(
                handle: 'settings-api',
                src: $this->getUri(file: 'Assets/JavaScript/settings-api.min.js')
            );
        }
    }

    /**
     * Getting the URI to a specific file within the current directory.
     * Unfortunately this is needed, becaue I do not know if this API
     * is used for a theme or a plugin.
     *
     * @param string|null $file
     * @return string|false
     */
    private function getUri(string $file = null): string|false
    {
        $returnValue = false;
        $filePath = trailingslashit(
            str_replace(
                search: '\\',
                replace: '/',
                subject: str_replace(
                    search: str_replace(
                        search: '/',
                        replace: '\\',
                        subject: WP_CONTENT_DIR
                    ),
                    replace: '',
                    subject: dirname(path: __FILE__, levels: 2)
                )
            )
        );

        if ($filePath) {
            $returnValue = content_url(path: $filePath . $file);
        }

        return $returnValue;
    }

    /**
     * Register styles
     */
    public function enqueueStyles(): void
    {
        if ($this->isSettingsPage() === true) {
            wp_enqueue_style(handle: 'wp-color-picker');
            wp_enqueue_style(
                handle: 'jquery-ui',
                src: $this->getUri(file: 'Assets/Libraries/jQueryUI/1.10.3/jquery-ui.min.css')
            );
            wp_enqueue_style(
                handle: 'font-awesome',
                src: $this->getUri(file: 'Assets/Libraries/font-awesome/4.6.3/css/font-awesome.min.css')
            );
            wp_enqueue_style(
                handle: 'settings-api',
                src: $this->getUri(file: 'Assets/Css/settings-api.min.css')
            );
        }
    }

    public function adminScripts(): void
    {
        if ($this->isSettingsPage() === true) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    <?php
                    $settingsArray = $this->settingsArray;

                    foreach($settingsArray as $page) {
                        foreach($page['tabs'] as $tab) {
                            foreach($tab['fields'] as $fieldKey => $field) {
                                if($field['type'] == 'datepicker') {
                                    $wpDateFormat = get_option(option: 'date_format');

                                    if (empty($wpDateFormat)) {
                                        $wpDateFormat = 'yy-mm-dd';
                                    }

                                    $dateFormat = (!empty($field['format'])) ? $field['format'] : $wpDateFormat;
                                    ?>
                                    $('[data-id="<?php echo $fieldKey; ?>"]').datepicker({
                                        dateFormat: '<?php echo $dateFormat; ?>'
                                    });
                                    <?php
                                }
                            }
                        }
                    }
                    ?>
                });
            </script>
            <?php
        }
    }
}
