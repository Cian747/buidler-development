<?php
namespace IndependentBuilder;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Site-wide design settings for all Independent Builder documents.
 * Stored once in wp_options rather than copied into each page document.
 */
final class Global_Styles {
    const OPTION_KEY = 'ib_global_design_system';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public static function defaults() {
        return array(
            'version' => 1,
            'colors' => array(
                'primary' => '#17365D',
                'secondary' => '#2874A6',
                'accent' => '#E3A72F',
                'text' => '#333333',
                'background' => '#FFFFFF',
            ),
            'typography' => array(
                'bodyFontFamily' => 'system-ui, sans-serif',
                'headingFontFamily' => 'system-ui, sans-serif',
                'bodyFontSize' => '16px',
                'bodyFontWeight' => '400',
                'bodyLineHeight' => '1.6',
                'headingFontWeight' => '700',
                'headingLineHeight' => '1.2',
                'h1FontSize' => '40px',
                'h2FontSize' => '32px',
                'h3FontSize' => '26px',
                'h4FontSize' => '21px',
            ),
            'spacing' => array(
                'xs' => '4px', 'sm' => '8px', 'md' => '16px',
                'lg' => '24px', 'xl' => '40px', 'xxl' => '64px',
            ),
            'borders' => array(
                'radius' => '8px', 'width' => '1px', 'color' => '#D0D5DD',
            ),
            'shadows' => array(
                'sm' => '0 1px 3px rgba(0,0,0,0.12)',
                'md' => '0 4px 12px rgba(0,0,0,0.12)',
                'lg' => '0 10px 30px rgba(0,0,0,0.15)',
            ),
        );
    }

    public static function is_configured() {
        return false !== get_option( self::OPTION_KEY, false );
    }

    public static function get_settings() {
        $stored = get_option( self::OPTION_KEY, false );
        if ( false === $stored || ! is_array( $stored ) ) {
            return self::defaults();
        }
        return self::sanitize_settings( $stored );
    }

    public function register_routes() {
        register_rest_route( 'independent-builder/v1', '/global-styles', array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_settings_endpoint' ),
                'permission_callback' => array( $this, 'can_read' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'save_settings_endpoint' ),
                'permission_callback' => array( $this, 'can_manage' ),
                'args' => array(
                    'settings' => array( 'required' => true, 'type' => 'object' ),
                ),
            ),
        ) );
    }

    public function can_read() {
        return current_user_can( 'edit_pages' );
    }

    public function can_manage() {
        return current_user_can( 'manage_options' );
    }

    public function get_settings_endpoint() {
        return rest_ensure_response( array(
            'settings' => self::get_settings(),
            'configured' => self::is_configured(),
            'canManage' => current_user_can( 'manage_options' ),
        ) );
    }

    public function save_settings_endpoint( $request ) {
        $input = $request->get_param( 'settings' );
        if ( ! is_array( $input ) ) {
            return new \WP_Error( 'ib_invalid_global_settings', __( 'Global settings must be an object.', 'independent-builder' ), array( 'status' => 400 ) );
        }
        $clean = self::sanitize_settings( $input );
        update_option( self::OPTION_KEY, $clean, false );
        return rest_ensure_response( array(
            'saved' => true,
            'settings' => $clean,
            'configured' => true,
            'updated' => current_time( 'mysql' ),
        ) );
    }

    public static function sanitize_settings( $input ) {
        $defaults = self::defaults();
        $input = is_array( $input ) ? $input : array();
        $colors_input = isset( $input['colors'] ) && is_array( $input['colors'] ) ? $input['colors'] : array();
        $typography_input = isset( $input['typography'] ) && is_array( $input['typography'] ) ? $input['typography'] : array();
        $spacing_input = isset( $input['spacing'] ) && is_array( $input['spacing'] ) ? $input['spacing'] : array();
        $borders_input = isset( $input['borders'] ) && is_array( $input['borders'] ) ? $input['borders'] : array();
        $shadows_input = isset( $input['shadows'] ) && is_array( $input['shadows'] ) ? $input['shadows'] : array();
        $out = $defaults;

        $color_keys = array_keys( $defaults['colors'] );
        foreach ( $color_keys as $key ) {
            if ( isset( $colors_input[ $key ] ) && is_scalar( $colors_input[ $key ] ) ) {
                $color = sanitize_text_field( (string) $colors_input[ $key ] );
                if ( preg_match( '/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
                    $out['colors'][ $key ] = strtoupper( $color );
                }
            }
        }

        $font_families = array(
            'system-ui, sans-serif', 'Arial, sans-serif', 'Georgia, serif',
            'Tahoma, sans-serif', 'Verdana, sans-serif', 'Trebuchet MS, sans-serif',
            'Times New Roman, serif', 'Courier New, monospace',
        );
        foreach ( array( 'bodyFontFamily', 'headingFontFamily' ) as $key ) {
            if ( isset( $typography_input[ $key ] ) && is_scalar( $typography_input[ $key ] ) ) {
                $font = sanitize_text_field( (string) $typography_input[ $key ] );
                if ( in_array( $font, $font_families, true ) ) {
                    $out['typography'][ $key ] = $font;
                }
            }
        }

        foreach ( array( 'bodyFontSize', 'h1FontSize', 'h2FontSize', 'h3FontSize', 'h4FontSize' ) as $key ) {
            if ( isset( $typography_input[ $key ] ) && is_scalar( $typography_input[ $key ] ) ) {
                $value = sanitize_text_field( (string) $typography_input[ $key ] );
                if ( self::is_dimension( $value ) ) { $out['typography'][ $key ] = $value; }
            }
        }
        foreach ( array( 'bodyFontWeight', 'headingFontWeight' ) as $key ) {
            if ( isset( $typography_input[ $key ] ) && is_scalar( $typography_input[ $key ] ) ) {
                $weight = sanitize_text_field( (string) $typography_input[ $key ] );
                if ( in_array( $weight, array( '300', '400', '500', '600', '700', '800' ), true ) ) {
                    $out['typography'][ $key ] = $weight;
                }
            }
        }
        foreach ( array( 'bodyLineHeight', 'headingLineHeight' ) as $key ) {
            if ( isset( $typography_input[ $key ] ) && is_scalar( $typography_input[ $key ] ) ) {
                $line = sanitize_text_field( (string) $typography_input[ $key ] );
                if ( is_numeric( $line ) && (float) $line >= 0.9 && (float) $line <= 2.5 ) {
                    $out['typography'][ $key ] = (string) (float) $line;
                }
            }
        }

        foreach ( array_keys( $defaults['spacing'] ) as $key ) {
            if ( isset( $spacing_input[ $key ] ) && is_scalar( $spacing_input[ $key ] ) ) {
                $value = sanitize_text_field( (string) $spacing_input[ $key ] );
                if ( self::is_dimension( $value ) ) { $out['spacing'][ $key ] = $value; }
            }
        }

        foreach ( array( 'radius', 'width' ) as $key ) {
            if ( isset( $borders_input[ $key ] ) && is_scalar( $borders_input[ $key ] ) ) {
                $value = sanitize_text_field( (string) $borders_input[ $key ] );
                if ( self::is_dimension( $value ) ) { $out['borders'][ $key ] = $value; }
            }
        }
        if ( isset( $borders_input['color'] ) && is_scalar( $borders_input['color'] ) ) {
            $color = sanitize_text_field( (string) $borders_input['color'] );
            if ( preg_match( '/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
                $out['borders']['color'] = strtoupper( $color );
            }
        }

        $shadow_values = array(
            'none',
            '0 1px 3px rgba(0,0,0,0.12)',
            '0 4px 12px rgba(0,0,0,0.12)',
            '0 10px 30px rgba(0,0,0,0.15)',
        );
        foreach ( array( 'sm', 'md', 'lg' ) as $key ) {
            if ( isset( $shadows_input[ $key ] ) && is_scalar( $shadows_input[ $key ] ) ) {
                $shadow = sanitize_text_field( (string) $shadows_input[ $key ] );
                if ( in_array( $shadow, $shadow_values, true ) ) { $out['shadows'][ $key ] = $shadow; }
            }
        }
        return $out;
    }

    private static function is_dimension( $value ) {
        return (bool) preg_match( '/^(?:0|[0-9]{1,4}(?:\.[0-9]{1,2})?(?:px|rem|em|%))$/', $value );
    }

    public static function get_css() {
        $settings = self::get_settings();
        $c = $settings['colors'];
        $t = $settings['typography'];
        $s = $settings['spacing'];
        $b = $settings['borders'];
        $sh = $settings['shadows'];
        $vars = array(
            '--ib-primary:' . $c['primary'],
            '--ib-secondary:' . $c['secondary'],
            '--ib-accent:' . $c['accent'],
            '--ib-text:' . $c['text'],
            '--ib-background:' . $c['background'],
            '--ib-color-primary:' . $c['primary'],
            '--ib-color-secondary:' . $c['secondary'],
            '--ib-color-accent:' . $c['accent'],
            '--ib-color-text:' . $c['text'],
            '--ib-color-background:' . $c['background'],
            '--ib-font-family:' . $t['bodyFontFamily'],
            '--ib-font-body:' . $t['bodyFontFamily'],
            '--ib-font-heading:' . $t['headingFontFamily'],
            '--ib-font-size-body:' . $t['bodyFontSize'],
            '--ib-font-size-h1:' . $t['h1FontSize'],
            '--ib-font-size-h2:' . $t['h2FontSize'],
            '--ib-font-size-h3:' . $t['h3FontSize'],
            '--ib-font-size-h4:' . $t['h4FontSize'],
            '--ib-font-weight-body:' . $t['bodyFontWeight'],
            '--ib-font-weight-heading:' . $t['headingFontWeight'],
            '--ib-line-height-body:' . $t['bodyLineHeight'],
            '--ib-line-height-heading:' . $t['headingLineHeight'],
            '--ib-space-xs:' . $s['xs'], '--ib-space-sm:' . $s['sm'],
            '--ib-space-md:' . $s['md'], '--ib-space-lg:' . $s['lg'],
            '--ib-space-xl:' . $s['xl'], '--ib-space-xxl:' . $s['xxl'],
            '--ib-border-radius:' . $b['radius'], '--ib-border-width:' . $b['width'],
            '--ib-border-color:' . $b['color'],
            '--ib-shadow-sm:' . $sh['sm'], '--ib-shadow-md:' . $sh['md'], '--ib-shadow-lg:' . $sh['lg'],
        );
        $css = '.ib-page{' . implode( ';', $vars ) . ';color:var(--ib-text);background-color:var(--ib-background);font-family:var(--ib-font-body);font-size:var(--ib-font-size-body);font-weight:var(--ib-font-weight-body);line-height:var(--ib-line-height-body)}';
        $css .= '.ib-page h1,.ib-page h2,.ib-page h3,.ib-page h4{font-family:var(--ib-font-heading);font-weight:var(--ib-font-weight-heading);line-height:var(--ib-line-height-heading)}';
        $css .= '.ib-page h1{font-size:var(--ib-font-size-h1)}.ib-page h2{font-size:var(--ib-font-size-h2)}.ib-page h3{font-size:var(--ib-font-size-h3)}.ib-page h4{font-size:var(--ib-font-size-h4)}';
        $css .= '.ib-page .ib-button{background:var(--ib-primary);border-radius:var(--ib-border-radius);box-shadow:var(--ib-shadow-sm)}';
        $css .= '.ib-page .ib-divider hr{border-color:var(--ib-border-color);border-width:var(--ib-border-width)}';
        return $css;
    }
}
