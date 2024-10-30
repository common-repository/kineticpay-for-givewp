<?php

/**
 * Class Give_RLTKNPAY_Settings
 *
 * @since 1.0.0
 */
class Give_RLTKNPAY_Settings
{

    /**
     * @access private
     * @var Give_RLTKNPAY_Settings $instance
     */
    private static $instance;

    /**
     * @access private
     * @var string $section_id
     */
    private $section_id;

    /**
     * @access private
     *
     * @var string $section_label
     */
    private $section_label;

    /**
     * Give_RLTKNPAY_Settings constructor.
     */
    private function __construct()
    {

    }

    /**
     * get class object.
     *
     * @return Give_RLTKNPAY_Settings
     */
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

        $this->section_id = 'kineticpay';
        $this->section_label = __('Kineticpay', 'give-kineticpay');

        if (is_admin()) {
            // Add settings.
            add_filter('give_get_settings_gateways', array($this, 'add_settings'), 99);
            add_filter('give_get_sections_gateways', array($this, 'add_sections'), 99);
        }
    }

    /**
     * Add setting section.
     *
     * @param array $sections Array of section.
     *
     * @return array
     */
    public function add_sections($sections)
    {
        $sections[$this->section_id] = $this->section_label;

        return $sections;
    }

    /**
     * Add plugin settings.
     *
     * @param array $settings Array of setting fields.
     *
     * @return array
     */
    public function add_settings($settings)
    {
        $current_section = give_get_current_setting_section();

        if ($current_section != 'kineticpay') {
            return $settings;
        }

        $give_kineticpay_settings = array(
            array(
                'name' => __('Kineticpay Settings', 'give-kineticpay'),
                'id' => 'give_title_gateway_kineticpay',
                'type' => 'title',
            ),
            array(
                'name' => __('Merchent Key', 'give-kineticpay'),
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
            array(
                'type' => 'sectionend',
                'id' => 'give_title_gateway_kineticpay',
            ),
        );

        return array_merge($settings, $give_kineticpay_settings);
    }
}

Give_RLTKNPAY_Settings::get_instance()->setup_hooks();
