<?php

class Give_RLTKNPAY_Settings_Metabox
{
    private static $instance;

    private function __construct()
    {

    }

    public static function get_instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Setup hooks.
     */
    public function setup_hooks()
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_js'));
            add_filter('give_forms_kineticpay_metabox_fields', array($this, 'give_kineticpay_add_settings'));
            add_filter('give_metabox_form_data_settings', array($this, 'add_kineticpay_setting_tab'), 0, 1);
        }
    }

    public function add_kineticpay_setting_tab($settings)
    {
        if (give_is_gateway_active('kineticpay')) {
            $settings['kineticpay_options'] = apply_filters('give_forms_kineticpay_options', array(
                'id' => 'kineticpay_options',
                'title' => __('Kineticpay', 'give'),
                'icon-html' => '<span class="give-icon give-icon-purse"></span>',
                'fields' => apply_filters('give_forms_kineticpay_metabox_fields', array()),
            ));
        }

        return $settings;
    }

    public function give_kineticpay_add_settings($settings)
    {

        // Bailout: Do not show offline gateways setting in to metabox if its disabled globally.
        if (in_array('kineticpay', (array) give_get_option('gateways'))) {
            return $settings;
        }

        $is_gateway_active = give_is_gateway_active('kineticpay');

        //this gateway isn't active
        if (!$is_gateway_active) {
            //return settings and bounce
            return $settings;
        }

        //Fields
        $check_settings = array(

            array(
                'name' => __('Kineticpay', 'give-kineticpay'),
                'desc' => __('Do you want to customize the donation instructions for this form?', 'give-kineticpay'),
                'id' => 'kineticpay_customize_kineticpay_donations',
                'type' => 'radio_inline',
                'default' => 'global',
                'options' => apply_filters('give_forms_content_options_select', array(
                    'global' => __('Global Option', 'give-kineticpay'),
                    'enabled' => __('Customize', 'give-kineticpay'),
                    'disabled' => __('Disable', 'give-kineticpay'),
                )
                ),
            ),
            array(
                'name' => __('API Secret Key', 'give-kineticpay'),
                'desc' => __('Enter your Merchent Key, Obtain your merchant key from your kineticPay dashboard.', 'give-kineticpay'),
                'id' => 'kineticpay_api_key',
                'type' => 'text',
                'row_classes' => 'give-kineticpay-key',
            ),
            array(
                'name' => __('Bill Description', 'give-kineticpay'),
                'desc' => __('Enter description to be included in the bill.', 'give-kineticpay'),
                'id' => 'kineticpay_description',
                'type' => 'text',
                'row_classes' => 'give-kineticpay-key',
            ),
        );

        return array_merge($settings, $check_settings);
    }

    public function enqueue_js($hook)
    {
        if ('post.php' === $hook || $hook === 'post-new.php') {
            wp_enqueue_script('give_kineticpay_each_form', GIVE_RLTKNPAY_PLUGIN_URL . '/includes/js/meta-box.js');
        }
    }

}
Give_RLTKNPAY_Settings_Metabox::get_instance()->setup_hooks();
