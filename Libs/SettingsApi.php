<?php
/**
 * WordPress Settings API
 * by H.-Peter Pfeufer
 * Inspired by: http://www.wp-load.com/register-settings-api/
 *
 * Usage:
 *      $settingsApi = new SettingsApi($settingsFilterName, $defaultOptions);
 *      $settingsApi->init();
 *
 * @version 0.1
 */

// Do not load this file directly
\defined('ABSPATH') or die();

require_once('Helper/StringHelper.php');

class SettingsApi {
    /**
     * Settings Arguments
     *
     * @var array
     */
    private $args = null;

    /**
     * Settings Array
     *
     * @var array
     */
    private $settingsArray = null;

    /**
     * Settings Filter Name
     *
     * @var string
     */
    private $settingsFilter = null;

    /**
     * Default Options
     *
     * @var array
     */
    private $optionsDefault = null;

    /**
     * Section ID
     *
     * @var type
     */
    private $sectionId;

    /**
     * Constructor
     *
     * @param string $settingsFilter The name of your settings filter
     * @param array $defaultOptions Your default options array
     */
    public function __construct($settingsFilter, $defaultOptions) {
        $this->settingsFilter = $settingsFilter;
        $this->optionsDefault = $defaultOptions;
    }

    /**
     * Initializing all actions
     */
    public function init() {
        \add_action('init', [$this, 'initSettings']);
        \add_action('admin_menu', [$this, 'menuPage']);
        \add_action('admin_init', [$this, 'registerFields']);
        \add_action('admin_init', [$this, 'registerCallback']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * Init settings runs before admin_init
     * Put $settingsArray to private variable
     * Add admin_head for needed inline scripts
     */
    public function initSettings() {
        if(\is_admin()) {
            $this->settingsArray = \apply_filters($this->settingsFilter, []);

            if($this->isSettingsPage() === true) {
                \add_action('admin_head', [$this, 'adminScripts']);
            }
        }
    }

    /**
     * Getting the URI to a specific file within the current directory.
     * Unfortunately this is needed, becaue I do not know if this API
     * is used for a theme or a plugin.
     *
     * @param string $file
     * @return mixed string|boolean
     */
    private function getUri($file = null) {
        $returnValue = false;
        $filePath = \trailingslashit(\str_replace('\\', '/', \str_replace(\str_replace('/', '\\', \WP_CONTENT_DIR), '', \dirname(\dirname(__FILE__)))));

        if($filePath) {
            $returnValue = \content_url($filePath . $file);
        }

        return $returnValue;
    }

    /**
     * Creating pages and menus from the settingsArray
     */
    public function menuPage() {
        foreach($this->settingsArray as $menu_slug => $options) {
            if(!empty($options['page_title']) && !empty($options['menu_title']) && !empty($options['option_name'])) {
                /**
                 * Set capabilities
                 * If none is set, 'manage_options' will be default
                 */
                $options['capability'] = (!empty($options['capability']) ) ? $options['capability'] : 'manage_options';

                /**
                 * Set type
                 * If none is set, 'plugin' will be default
                 *
                 * Supported types:
                 * 		theme	=> Adds the options page to Appearance menu
                 * 		plugin	=> Adds the options page to Settings menu
                 */
                $options['type'] = (!empty($options['type']) ) ? $options['type'] : 'plugin';

                switch($options['type']) {
                    // Adding theme settings page
                    case 'theme':
                        \add_theme_page(
                            $options['page_title'],
                            $options['menu_title'],
                            $options['capability'],
                            $menu_slug,
                            [
                                $this,
                                'renderOptions'
                            ]
                        );
                        break;

                    // Adding plugin settings page
                    case 'plugin':
                        \add_options_page(
                            $options['page_title'],
                            $options['menu_title'],
                            $options['capability'],
                            $menu_slug,
                            [
                                $this,
                                'renderOptions'
                            ]
                        );
                        break;
                }
            }
        }
    }

    /**
     * Register all fields and settings bound to it from the settingsArray
     */
    public function registerFields() {
        foreach($this->settingsArray as $pageId => $settings) {
            if(!empty($settings['tabs']) && \is_array($settings['tabs'])) {
                foreach($settings['tabs'] as $tabId => $item) {
                    $sanitizedTabId = \sanitize_title($tabId);
                    $tabDescription = (!empty($item['tab_description']) ) ? $item['tab_description'] : '';
                    $this->sectionId = $sanitizedTabId;
                    $settingArgs = [
                        'option_group' => 'section_page_' . $pageId . '_' . $sanitizedTabId,
                        'option_name' => $settings['option_name']
                    ];

                    \register_setting($settingArgs['option_group'], $settingArgs['option_name']);

                    $sectionArgs = [
                        'id' => 'section_id_' . $sanitizedTabId,
                        'title' => $tabDescription,
                        'callback' => 'callback',
                        'menu_page' => $pageId . '_' . $sanitizedTabId
                    ];

                    \add_settings_section(
                        $sectionArgs['id'], $sectionArgs['title'], [$this, $sectionArgs['callback']], $sectionArgs['menu_page']
                    );

                    if(!empty($item['fields']) && is_array($item['fields'])) {
                        foreach($item['fields'] as $fieldId => $field) {
                            if(\is_array($field)) {
                                $sanitizedFieldId = \sanitize_title($fieldId);
                                $title = (!empty($field['title']) ) ? $field['title'] : '';
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

                                \add_settings_field(
                                    $fieldArgs['id'], $fieldArgs['title'], [$this, $fieldArgs['callback']], $fieldArgs['menu_page'], $fieldArgs['section'], $fieldArgs['args']
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
    public function registerCallback() {
        if($this->isSettingsPage() === true) {
            $getCallback = \filter_input(\INPUT_GET, 'callback');
            $getWpNonce = \filter_input(\INPUT_GET, '_wpnonce');
            $getPage = \filter_input(\INPUT_GET, 'page');

            if(!empty($getCallback)) {
                $nonce = \wp_verify_nonce($getWpNonce);

                if(!empty($nonce)) {
                    if(\function_exists($getCallback)) {
                        $message = \call_user_func($getCallback);
                        \update_option('rsa-message', $message);

                        $url = admin_url('options-general.php?page=' . $getPage);
                        \wp_redirect($url);

                        die;
                    }
                }
            }
        }
    }

    /**
     * Check if the current page is a settings page
     *
     * @return boolean
     */
    public function isSettingsPage() {
        $menus = [];
        $getPage = \filter_input(\INPUT_GET, 'page');
        $settingsPage = (!empty($getPage) ) ? $getPage : '';
        $returnValue = false;

        /* @var $menu string */
        /* @var $page string */
        foreach($this->settingsArray as $menu => $page) {
            $menus[] = $menu;

            // not used at this moment
            unset($page);
        }

        if(\in_array($settingsPage, $menus)) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Return an array for the choices in a select field type
     */
    public function selectChoices() {
        $items = [];

        if(!empty($this->args['choices']) && \is_array($this->args['choices'])) {
            foreach($this->args['choices'] as $slug => $choice) {
                $items[$slug] = $choice;
            }
        }

        return $items;
    }

    /**
     * Get values from built in WordPress functions
     */
    public function get() {
        if(!empty($this->args['get'])) {
            $itemArray = \call_user_func_array([$this, 'get' . Helper\StringHelper::camelCase($this->args['get'], true)], [$this->args]);
        } elseif(!empty($this->args['choices'])) {
            $itemArray = $this->selectChoices($this->args);
        } else {
            $itemArray = [];
        }

        return $itemArray;
    }

    /**
     * Get users from WordPress, used by the select field type
     */
    public function getUsers() {
        $items = [];
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $users = \get_users($args);

        foreach($users as $user) {
            $items[$user->ID] = $user->display_name;
        }

        return $items;
    }

    /**
     * Get menus from WordPress, used by the select field type
     */
    public function getMenus() {
        $items = [];
        $menus = \get_registered_nav_menus();

        if(!empty($menus)) {
            foreach($menus as $location => $description) {
                $items[$location] = $description;
            }
        }

        return $items;
    }

    /**
     * Get posts from WordPress, used by the select field type
     */
    public function getPosts() {
        $items = null;

        if($this->args['get'] === 'posts' && !empty($this->args['post_type'])) {
            $args = [
                'category' => 0,
                'post_type' => $this->args['post_type'],
                'post_status' => 'publish',
                'orderby' => 'post_title',
                'order' => 'ASC',
                'suppress_filters' => true
            ];

            $theQuery = new \WP_Query($args);

            if($theQuery->have_posts()) {
                while($theQuery->have_posts()) {
                    $theQuery->the_post();

                    global $post;

                    $items[$post->ID] = \get_the_title();
                }
            }

            \wp_reset_postdata();
        }

        return $items;
    }

    /**
     * Get terms from WordPress, used by the select field type
     */
    public function getTerms() {
        $items = [];
        $taxonomies = (!empty($this->args['taxonomies']) ) ? $this->args['taxonomies'] : null;
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $terms = \get_terms($taxonomies, $args);

        if(!empty($terms)) {
            foreach($terms as $key => $term) {
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
    public function getTaxonomies() {
        $items = [];
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $taxonomies = \get_taxonomies($args, 'objects');

        if(!empty($taxonomies)) {
            foreach($taxonomies as $taxonomy) {
                $items[$taxonomy->name] = $taxonomy->label;
            }
        }

        return $items;
    }

    /**
     * Get sidebars from WordPress, used by the select field type
     */
    public function getSidebars() {
        $items = [];

        global $wp_registered_sidebars;

        if(!empty($wp_registered_sidebars)) {
            foreach($wp_registered_sidebars as $sidebar) {
                $items[$sidebar['id']] = $sidebar['name'];
            }
        }

        return $items;
    }

    /**
     * Get themes from WordPress, used by the select field type
     */
    public function getThemes() {
        $items = [];
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $themes = \wp_get_themes($args);

        if(!empty($themes)) {
            foreach($themes as $key => $theme) {
                $items[$key] = $theme->get('Name');
            }
        }

        return $items;
    }

    /**
     * Get plugins from WordPress, used by the select field type
     */
    public function getPlugins() {
        $items = [];
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $plugins = \get_plugins($args);

        if(!empty($plugins)) {
            foreach($plugins as $key => $plugin) {
                $items[$key] = $plugin['Name'];
            }
        }

        return $items;
    }

    /**
     * Get post_types from WordPress, used by the select field type
     */
    public function getPostTypes() {
        $items = [];
        $args = (!empty($this->args['args'])) ? $this->args['args'] : null;
        $postTypes = \get_post_types($args, 'objects');

        if(!empty($postTypes)) {
            foreach($postTypes as $key => $postType) {
                $items[$key] = $postType->name;
            }
        }

        return $items;
    }

    /**
     * Find a selected value in select or multiselect field type
     */
    public function selected($key) {
        $returnValue = null;

        if($this->valueType() == 'array') {
            $returnValue = $this->multiselectedValue($key);
        } else {
            $returnValue = $this->selectedValue($key);
        }

        return $returnValue;
    }

    /**
     * Return selected html if the value is selected in select field type
     */
    public function selectedValue($key) {
        $result = '';

        if($this->value($this->options, $this->args) === $key) {
            $result = ' selected="selected"';
        }

        return $result;
    }

    /**
     * Return selected html if the value is selected in multiselect field type
     */
    public function multiselectedValue($key) {
        $result = '';
        $value = $this->value($this->options, $this->args, $key);

        if(\is_array($value) && \in_array($key, $value)) {
            $result = ' selected="selected"';
        }

        return $result;
    }

    /**
     * Return checked html if the value is checked in radio or checkboxes
     */
    public function checked($slug) {
        $value = $this->value();

        if($this->valueType() == 'array') {
            $checked = (!empty($value) && \in_array($slug, $this->value())) ? ' checked="checked"' : '';
        } else {
            $checked = (!empty($value) && $slug == $this->value()) ? ' checked="checked"' : '';
        }

        return $checked;
    }

    /**
     * Return the value. If the value is not saved the default value is used if
     * exists in the settingsArray.
     *
     * Return as string or array
     */
    public function value($key = null) {
        $value = '';

        // not used at this moment
        unset($key);

        if($this->valueType() == 'array') {
            $default = (!empty($this->args['default']) && \is_array($this->args['default'])) ? $this->args['default'] : [];
        } else {
            $default = (!empty($this->args['default'])) ? $this->args['default'] : '';
        }

        $value = (isset($this->options[$this->args['field_id']])) ? $this->options[$this->args['field_id']] : $default;

        return $value;
    }

    /**
     * Check if the current value type is a single value or a multiple value
     * field type, return string or array
     */
    public function valueType() {
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

        if(\in_array($this->args['type'], $defaultSingle)) {
            $returnValue = 'string';
        } elseif(\in_array($this->args['type'], $defaultMultiple)) {
            $returnValue = 'array';
        }

        return $returnValue;
    }

    /**
     * Check if a checkbox has items
     */
    public function hasItems() {
        $returnValue = false;

        if(!empty($this->args['choices']) && \is_array($this->args['choices'])) {
            $returnValue = true;
        }

        return $returnValue;
    }

    /**
     * Return the html name of the field
     */
    public function name($slug = '') {
        $returnValue = null;
        $optionName = \sanitize_title($this->args['option_name']);

        if($this->valueType() == 'array') {
            $returnValue = $optionName . '[' . $this->args['field_id'] . '][' . $slug . ']';
        } else {
            $returnValue = $optionName . '[' . $this->args['field_id'] . ']';
        }

        return $returnValue;
    }

    /**
     * Return the size of a multiselect type. If not set it will calculate it
     */
    public function size($items) {
        $size = '';

        if($this->args['type'] == 'multiselect') {
            if(!empty($this->args['size'])) {
                $count = $this->args['size'];
            } else {
                $countItems = \count($items);
                $count = (!empty($this->args['empty'])) ? $countItems + 1 : $countItems;
            }

            $size = ' size="' . $count . '"';
        }

        return $size;
    }

    /**
     * All the field types in html
     */
    public function renderFields($args) {
        $args['field_id'] = \sanitize_title($args['field_id']);
        $this->args = $args;

        $options = \get_option($args['option_name'], $this->optionsDefault);
        $this->options = $options;

        // not used at this moment
//        $screen = \get_current_screen();

        // Not used at this moment
//        $callback_base = \admin_url() . $screen->parent_file;

        $optionName = \sanitize_title($args['option_name']);
        $out = '';

        if(!empty($args['type'])) {
            switch($args['type']) {
                case 'info':
                    if(!empty($args['infotext'])) {
                        $out .= '<div class="notice notice-warning"><p>' . $args['infotext'] . '</p></div>';
                    }
                    break;

                case 'select':
                case 'multiselect':
                    $multiple = ($args['type'] == 'multiselect') ? ' multiple' : '';
                    $items = $this->get($args);
                    $out .= '<select' . $multiple . ' name="' . $this->name() . '"' . $this->size($items) . '>';

                    if(!empty($args['empty'])) {
                        $out .= '<option value="" ' . $this->selected('') . '>' . $args['empty'] . '</option>';
                    }

                    foreach($items as $key => $choice) {
                        $key = \sanitize_title($key);
                        $out .= '<option value="' . $key . '" ' . $this->selected($key) . '>' . $choice . '</option>';
                    }

                    $out .= '</select>';
                    break;

                case 'radio':
                case 'checkbox':
                    if($this->hasItems()) {
                        $horizontal = (isset($args['align']) && (string) $args['align'] == 'horizontal') ? ' class="horizontal"' : '';

                        $out .= '<ul class="settings-group settings-type-' . $args['type'] . '">';

                        foreach($args['choices'] as $slug => $choice) {
                            $checked = $this->checked($slug);

                            $out .= '<li' . $horizontal . '><label>';
                            $out .= '<input value="' . $slug . '" type="' . $args['type'] . '" name="' . $this->name($slug) . '"' . $checked . '>';
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
                    $rows = (isset($args['rows'])) ? $args['rows'] : 5;
                    $out .= '<textarea rows="' . $rows . '" class="large-text" name="' . $this->name() . '">' . $this->value() . '</textarea>';
                    break;

                case 'tinymce':
                    $rows = (isset($args['rows'])) ? $args['rows'] : 5;
                    $tinymceSettings = [
                        'textarea_rows' => $rows,
                        'textarea_name' => $optionName . '[' . $args['field_id'] . ']',
                    ];

                    \wp_editor($this->value(), $args['field_id'], $tinymceSettings);
                    break;

                case 'image':
                    $imageObj = (!empty($options[$args['field_id']])) ? \wp_get_attachment_image_src($options[$args['field_id']], 'thumbnail') : '';
                    $image = (!empty($imageObj)) ? $imageObj[0] : '';
                    $uploadStatus = (!empty($imageObj)) ? ' style="display: none"' : '';
                    $removeStatus = (!empty($imageObj)) ? '' : ' style="display: none"';
                    $value = (!empty($options[$args['field_id']])) ? $options[$args['field_id']] : '';
                    ?>
                    <div data-id="<?php echo $args['field_id']; ?>">
                        <div class="upload" data-field-id="<?php echo $args['field_id']; ?>"<?php echo $uploadStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-upload"></i>
                                    <?php echo \__('Upload'); ?>
                                </a>
                            </span>
                        </div>
                        <div class="image">
                            <img class="uploaded-image" src="<?php echo $image; ?>" id="<?php echo $args['field_id']; ?>" />
                        </div>
                        <div class="remove"<?php echo $removeStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-trash"></i>
                                    <?php echo \__('Remove'); ?>
                                </a>
                            </span>
                        </div>
                        <input type="hidden" class="attachment_id" value="<?php echo $value; ?>" name="<?php echo $optionName; ?>[<?php echo $args['field_id']; ?>]">
                    </div>
                    <?php
                    break;

                case 'file':
                    $fileUrl = (!empty($options[$args['field_id']])) ? \wp_get_attachment_url($options[$args['field_id']]) : '';
                    $uploadStatus = (!empty($fileUrl)) ? ' style="display: none"' : '';
                    $removeStatus = (!empty($fileUrl)) ? '' : ' style="display: none"';
                    $value = (!empty($options[$args['field_id']])) ? $options[$args['field_id']] : '';
                    ?>
                    <div data-id="<?php echo $args['field_id']; ?>">
                        <div class="upload" data-field-id="<?php echo $args['field_id']; ?>"<?php echo $uploadStatus; ?>>
                            <span class="button upload-button">
                                <a href="#">
                                    <i class="fa fa-upload"></i>
                                    <?php echo \__('Upload'); ?>
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
                                    <?php echo \__('Remove'); ?>
                                </a>
                            </span>
                        </div>
                        <input type="hidden" class="attachment_id" value="<?php echo $value; ?>" name="<?php echo $optionName; ?>[<?php echo $args['field_id']; ?>]">
                    </div>
                    <?php
                    break;

                case 'button':
                    $getPage = \filter_input(\INPUT_GET, 'page');
                    $warningMessage = (!empty($args['warning-message'])) ? $args['warning-message'] : 'Unsaved settings will be lost. Continue?';
                    $warning = (!empty($args['warning'])) ? ' onclick="return confirm(' . "'" . $warningMessage . "'" . ')"' : '';
                    $label = (!empty($args['label'])) ? $args['label'] : '';
                    $completeUrl = \wp_nonce_url(\admin_url('options-general.php?page=' . $getPage . '&callback=' . $args['callback']));
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

                    if($args['content'] !== null) {
                        echo $args['content'];
                    }

                    if($args['callback'] !== null) {
                        \call_user_func($args['callback'], $data);
                    }
                    break;
            }
        }

        echo $out;

        if(!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</div>';
        }
    }

    /**
     * Callback for field registration. It's required by WordPress but not used by this plugin
     */
    public function callback() {

    }

    /**
     * Final output on the settings page
     */
    public function renderOptions() {
//        global $wp_settings_sections;

        $page = \filter_input(\INPUT_GET, 'page');
        $settings = $this->settingsArray[$page];
        $message = \get_option('rsa-message');

        if(!empty($settings['tabs']) && \is_array($settings['tabs'])) {
            $tabCount = \count($settings['tabs']);
            ?>
            <div class="wrap">
                <?php
                if(!empty($settings['before_tabs_text'])) {
                    echo $settings['before_tabs_text'];
                }
                ?>
                <form action='options.php' method='post'>
                    <?php
                    if($tabCount > 1) {
                        ?>
                        <h2 class="nav-tab-wrapper">
                            <?php
                            $i = 0;
                            foreach($settings['tabs'] as $settingsId => $section) {
                                $sanitizedId = \sanitize_title($settingsId);
                                $tabTitle = (!empty($section['tab_title'])) ? $section['tab_title'] : $sanitizedId;
                                $active = ($i == 0) ? ' nav-tab-active' : '';

                                echo '<a class="nav-tab nav-tab-' . $sanitizedId . $active . '" href="#tab-content-' . $sanitizedId . '">' . $tabTitle . '</a>';

                                $i++;
                            }
                            ?>
                        </h2>

                        <?php
                        if(!empty($message)) {
                            ?>
                            <div class="updated settings-error">
                                <p><strong><?php echo $message; ?></strong></p>
                            </div>
                            <?php
                            \update_option('rsa-message', '');
                        }
                    } // END if($tab_count > 1)

                    $i = 0;
                    foreach($settings['tabs'] as $settingsId => $section) {
                        $sanitizedId = \sanitize_title($settingsId);
                        $pageId = $page . '_' . $sanitizedId;

                        $display = ($i == 0) ? ' style="display: block;"' : ' style="display:none;"';

                        echo '<div class="tab-content" id="tab-content-' . $sanitizedId . '"' . $display . '>';
                        echo \settings_fields('section_page_' . $page . '_' . $sanitizedId);

                        \do_settings_sections($pageId);

                        echo '</div>';

                        $i++;
                    }

                    \submit_button();
                    ?>
                </form>
            <?php
            if(!empty($settings['after_tabs_text'])) {
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
    public function enqueueScripts() {
        if($this->isSettingsPage() === true) {
            \wp_enqueue_media();
            \wp_enqueue_script('wp-color-picker');
            \wp_enqueue_script('jquery-ui-datepicker');
            \wp_enqueue_script(
                'settings-api',
                (\WP_DEBUG === true) ? $this->getUri('Assets/JavaScript/settings-api.js') : $this->getUri('Assets/JavaScript/settings-api.min.js')
            );
        }
    }

    /**
     * Register styles
     */
    public function enqueueStyles() {
        if($this->isSettingsPage() === true) {
            \wp_enqueue_style('wp-color-picker');
            \wp_enqueue_style('jquery-ui', $this->getUri('Assets/Css/jquery-ui.min.css'));
            \wp_enqueue_style(
                'font-awesome',
                (\WP_DEBUG === true) ? $this->getUri('Assets/Libraries/font-awesome/4.6.3/css/font-awesome.css') : $this->getUri('Assets/Libraries/font-awesome/4.6.3/css/font-awesome.min.css')
            );
            \wp_enqueue_style(
                'settings-api',
                (\WP_DEBUG === true) ? $this->getUri('Assets/Css/settings-api.css') : $this->getUri('Assets/Css/settings-api.min.css')
            );
        }
    }

    public function adminScripts() {
        if($this->isSettingsPage() === true) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                <?php
                $settingsArray = $this->settingsArray;

                foreach($settingsArray as $page) {
                    foreach($page['tabs'] as $tab) {
                        foreach($tab['fields'] as $fieldKey => $field) {
                            if($field['type'] == 'datepicker') {
                                $wpDateFormat = \get_option('date_format');
                                if(empty($wpDateFormat)) {
                                    $wpDateFormat = 'yy-mm-dd';
                                }

                                $dateFormat = (!empty($field['format']) ) ? $field['format'] : $wpDateFormat;
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
