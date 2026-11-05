<?php
namespace IndependentBuilder;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class REST {
    const META_KEY = '_ib_document_v1';
    const MAX_NODES = 300;
    public function __construct() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
    public function routes() {
        register_rest_route( 'independent-builder/v1', '/document/(?P<id>\d+)', array(
            array( 'methods' => 'GET', 'callback' => array( $this, 'get_document' ), 'permission_callback' => array( $this, 'can_edit' ) ),
            array( 'methods' => 'POST', 'callback' => array( $this, 'save_document' ), 'permission_callback' => array( $this, 'can_edit' ),
                'args' => array( 'document' => array( 'required' => true, 'type' => 'object' ) ) ),
        ) );
    }
    public function can_edit( $request ) { $id = absint( $request['id'] ); return $id && current_user_can( 'edit_post', $id ); }
    public function get_document( $request ) {
        $doc = get_post_meta( absint( $request['id'] ), self::META_KEY, true );
        if ( ! is_array( $doc ) ) { $doc = array( 'version' => 1, 'nodes' => array(), 'settings' => array() ); }
        return rest_ensure_response( $doc );
    }
    public function save_document( $request ) {
        $doc = $this->validate_document( $request->get_param( 'document' ) );
        if ( is_wp_error( $doc ) ) { return $doc; }
        update_post_meta( absint( $request['id'] ), self::META_KEY, $doc );
        return rest_ensure_response( array( 'saved' => true, 'updated' => current_time( 'mysql' ) ) );
    }
    private function validate_document( $doc ) {
        if ( ! is_array( $doc ) || ! isset( $doc['nodes'] ) || ! is_array( $doc['nodes'] ) ) { return new \WP_Error( 'ib_invalid_document', 'Invalid document structure.', array( 'status' => 400 ) ); }
        if ( count( $doc['nodes'] ) > self::MAX_NODES ) { return new \WP_Error( 'ib_document_too_large', 'Too many top-level elements.', array( 'status' => 400 ) ); }
        $nodes = array(); foreach ( $doc['nodes'] as $node ) { $clean = $this->clean_node( $node, 0 ); if ( is_wp_error( $clean ) ) { return $clean; } $nodes[] = $clean; }
        $settings = isset( $doc['settings'] ) && is_array( $doc['settings'] ) ? $doc['settings'] : array();
        $global = isset( $settings['globalStyles'] ) && is_array( $settings['globalStyles'] ) ? $settings['globalStyles'] : array();
        $clean_global = array();
        foreach ( array( 'primary', 'secondary', 'text', 'background' ) as $token ) {
            if ( isset( $global['colors'][ $token ] ) ) {
                $value = sanitize_text_field( (string) $global['colors'][ $token ] );
                if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) { $clean_global['colors'][ $token ] = $value; }
            }
        }
        if ( isset( $global['fontFamily'] ) ) {
            $font = sanitize_text_field( (string) $global['fontFamily'] );
            $clean_global['fontFamily'] = in_array( $font, array( 'Arial, sans-serif', 'Georgia, serif', 'Tahoma, sans-serif', 'Verdana, sans-serif', 'system-ui, sans-serif' ), true ) ? $font : 'system-ui, sans-serif';
        }
        return array( 'version' => 2, 'nodes' => $nodes, 'settings' => array( 'globalStyles' => $clean_global ) );
    }
    private function clean_node( $node, $depth ) {
        if ( $depth > 8 || ! is_array( $node ) ) { return new \WP_Error( 'ib_invalid_node', 'Invalid or deeply nested element.', array( 'status' => 400 ) ); }
        $allowed = array( 'container', 'heading', 'text', 'image', 'button', 'spacer', 'divider' );
        $type = isset( $node['type'] ) ? sanitize_key( $node['type'] ) : '';
        if ( ! in_array( $type, $allowed, true ) ) { return new \WP_Error( 'ib_unknown_widget', 'Unknown widget type.', array( 'status' => 400 ) ); }
        $id = isset( $node['id'] ) ? sanitize_html_class( $node['id'] ) : wp_generate_uuid4();
        $attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array(); $clean = array();
        foreach ( array( 'text','tag','url','mediaUrl','target','alt','background','color','fontSize','padding','margin','width','height','borderRadius','borderWidth','borderColor','boxShadow','gap','alignItems','justifyContent','flexDirection','maxWidth','minHeight' ) as $key ) {
            if ( ! array_key_exists( $key, $attrs ) ) { continue; }
            if ( in_array( $key, array( 'url', 'mediaUrl' ), true ) ) { $clean[$key] = esc_url_raw( $attrs[$key] ); }
            elseif ( in_array( $key, array( 'fontSize','padding','margin','width','height','borderRadius','borderWidth','gap','maxWidth','minHeight' ), true ) ) { $v = sanitize_text_field( (string) $attrs[$key] ); $clean[$key] = preg_match( '/^(auto|0|[0-9]{1,4}(px|%|rem|em|vh|vw)|var\(--ib-space-(xs|sm|md|lg|xl|xxl)\))$/', $v ) ? $v : ''; }
            elseif ( in_array( $key, array( 'alignItems','justifyContent','flexDirection' ), true ) ) { $v = sanitize_key( $attrs[$key] ); $allowed_values = array( 'alignItems' => array('stretch','flex-start','center','flex-end'), 'justifyContent' => array('flex-start','center','flex-end','space-between','space-around'), 'flexDirection' => array('column','row') ); $clean[$key] = in_array( $v, $allowed_values[$key], true ) ? $v : ''; }
            elseif ( in_array( $key, array( 'borderColor','boxShadow' ), true ) ) { $clean[$key] = sanitize_text_field( (string) $attrs[$key] ); }
            elseif ( 'tag' === $key ) { $v = strtolower( sanitize_key( $attrs[$key] ) ); $clean[$key] = in_array( $v, array( 'h1','h2','h3','h4' ), true ) ? $v : 'h2'; }
            elseif ( 'target' === $key ) { $clean[$key] = '_blank' === $attrs[$key] ? '_blank' : '_self'; }
            else { $clean[$key] = sanitize_text_field( (string) $attrs[$key] ); }
        }
        if ( isset( $attrs['responsive'] ) && is_array( $attrs['responsive'] ) ) {
            $clean['responsive'] = array();
            foreach ( array( 'tablet', 'mobile' ) as $device ) {
                if ( empty( $attrs['responsive'][ $device ] ) || ! is_array( $attrs['responsive'][ $device ] ) ) { continue; }
                foreach ( array( 'fontSize', 'padding', 'margin', 'width', 'height', 'gap', 'maxWidth', 'minHeight', 'borderRadius' ) as $style_key ) {
                    if ( ! isset( $attrs['responsive'][ $device ][ $style_key ] ) ) { continue; }
                    $v = sanitize_text_field( (string) $attrs['responsive'][ $device ][ $style_key ] );
                    if ( preg_match( '/^(auto|0|[0-9]{1,4}(px|%|rem|em|vh|vw)|var\(--ib-space-(xs|sm|md|lg|xl|xxl)\))$/', $v ) ) { $clean['responsive'][ $device ][ $style_key ] = $v; }
                }
                foreach ( array( 'color', 'background' ) as $color_key ) {
                    if ( isset( $attrs['responsive'][ $device ][ $color_key ] ) ) {
                        $v = sanitize_text_field( (string) $attrs['responsive'][ $device ][ $color_key ] );
                        if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ) { $clean['responsive'][ $device ][ $color_key ] = $v; }
                    }
                }
            }
        }
        if ( isset( $attrs['mediaId'] ) ) { $clean['mediaId'] = absint( $attrs['mediaId'] ); }
        $children = array(); if ( 'container' === $type && isset( $node['children'] ) && is_array( $node['children'] ) ) {
            foreach ( $node['children'] as $child ) { $c = $this->clean_node( $child, $depth + 1 ); if ( is_wp_error( $c ) ) { return $c; } $children[] = $c; }
        }
        return array( 'id' => $id, 'type' => $type, 'attrs' => $clean, 'children' => $children );
    }
}
