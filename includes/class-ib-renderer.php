<?php
namespace IndependentBuilder;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Renderer {
    public static function render_document( $doc ) {
        if ( empty( $doc['nodes'] ) || ! is_array( $doc['nodes'] ) ) { return ''; }
        $legacy_css = '';
        $legacy_settings = isset( $doc['settings']['globalStyles'] ) && is_array( $doc['settings']['globalStyles'] ) ? $doc['settings']['globalStyles'] : array();
        if ( ! empty( $legacy_settings ) ) {
            $settings = $legacy_settings;
            $colors = isset( $settings['colors'] ) && is_array( $settings['colors'] ) ? $settings['colors'] : array();
            $vars = array();
            foreach ( array( 'primary', 'secondary', 'text', 'background' ) as $token ) {
                if ( isset( $colors[ $token ] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $colors[ $token ] ) ) { $vars[] = '--ib-' . $token . ':' . $colors[ $token ]; }
            }
            $font = isset( $settings['fontFamily'] ) ? $settings['fontFamily'] : 'system-ui, sans-serif';
            $allowed_fonts = array( 'Arial, sans-serif', 'Georgia, serif', 'Tahoma, sans-serif', 'Verdana, sans-serif', 'system-ui, sans-serif' );
            if ( ! in_array( $font, $allowed_fonts, true ) ) { $font = 'system-ui, sans-serif'; }
            $vars[] = '--ib-font-family:' . $font;
            if ( $vars ) { $legacy_css = '.ib-page{' . implode( ';', $vars ) . ';font-family:var(--ib-font-family)}.ib-page .ib-button{background:var(--ib-primary,#2271b1);color:#fff}'; }
        }
        $css = self::responsive_css( $doc['nodes'] );
        $style_tag = ( $legacy_css || $css ) ? '<style class="ib-generated-styles">' . $legacy_css . $css . '</style>' : '';
        $html = '<div class="ib-page">';
        foreach ( $doc['nodes'] as $node ) { $html .= self::render_node( $node ); }
        return $style_tag . $html . '</div>';
    }
    private static function render_node( $node ) {
        if ( ! is_array( $node ) || empty( $node['type'] ) ) { return ''; }
        $type = $node['type']; $a = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
        $id = isset( $node['id'] ) ? sanitize_html_class( $node['id'] ) : '';
        $class = 'ib-element ib-' . sanitize_html_class( $type ) . ( $id ? ' ib-node-' . $id : '' );
        $style = self::style( $a );
        $open = '<div class="' . esc_attr( $class ) . '" data-ib-id="' . esc_attr( $id ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
        if ( 'container' === $type ) { $inner = ''; foreach ( ( isset( $node['children'] ) && is_array( $node['children'] ) ) ? $node['children'] : array() as $child ) { $inner .= self::render_node( $child ); } return $open . $inner . '</div>'; }
        if ( 'heading' === $type || 'text' === $type ) { $tag = 'heading' === $type ? ( $a['tag'] ?? 'h2' ) : 'p'; if ( ! in_array( $tag, array( 'h1','h2','h3','h4','p' ), true ) ) { $tag = 'p'; } return $open . '<' . $tag . '>' . esc_html( $a['text'] ?? '' ) . '</' . $tag . '></div>'; }
        if ( 'image' === $type ) { $media_id = absint( $a['mediaId'] ?? 0 ); return $open . ( $media_id ? wp_get_attachment_image( $media_id, 'large', false, array( 'alt' => esc_attr( $a['alt'] ?? '' ), 'loading' => 'lazy' ) ) : '' ) . '</div>'; }
        if ( 'button' === $type ) { $url = esc_url( $a['url'] ?? '#' ); $target = ( $a['target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : ''; return $open . '<a class="ib-button" href="' . $url . '"' . $target . '>' . esc_html( $a['text'] ?? 'Button' ) . '</a></div>'; }
        if ( 'spacer' === $type ) { return $open . '<span class="ib-spacer" aria-hidden="true"></span></div>'; }
        if ( 'divider' === $type ) { return $open . '<hr /></div>'; }
        return '';
    }
    private static function style( $a ) {
        $out = array(); $map = array( 'background'=>'background-color','color'=>'color','fontSize'=>'font-size','padding'=>'padding','margin'=>'margin','width'=>'width','height'=>'height','borderRadius'=>'border-radius','borderWidth'=>'border-width','gap'=>'gap','maxWidth'=>'max-width','minHeight'=>'min-height' );
        foreach ( $map as $key => $prop ) { if ( empty( $a[$key] ) ) { continue; } $v = (string) $a[$key];
            if ( in_array( $key, array( 'background','color' ), true ) ) { if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ) { $out[] = $prop . ':' . $v; } }
            elseif ( preg_match( '/^(auto|0|[0-9]{1,4}(px|%|rem|em|vh|vw)|var\(--ib-space-(xs|sm|md|lg|xl|xxl)\))$/', $v ) ) { $out[] = $prop . ':' . $v; }
        }
        foreach ( array( 'alignItems'=>'align-items', 'justifyContent'=>'justify-content', 'flexDirection'=>'flex-direction' ) as $key => $prop ) { if ( ! empty( $a[$key] ) ) { $allowed = array( 'alignItems'=>array('stretch','flex-start','center','flex-end'), 'justifyContent'=>array('flex-start','center','flex-end','space-between','space-around'), 'flexDirection'=>array('column','row') ); if ( in_array( $a[$key], $allowed[$key], true ) ) { $out[] = $prop . ':' . $a[$key]; } } }
        if ( ! empty( $a['borderColor'] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $a['borderColor'] ) ) { $out[] = 'border-color:' . $a['borderColor']; }
        if ( ! empty( $a['borderWidth'] ) && preg_match( '/^[0-9]{1,3}(px|rem)$/', $a['borderWidth'] ) ) { $out[] = 'border-style:solid'; }
        if ( ! empty( $a['boxShadow'] ) && in_array( $a['boxShadow'], array('none','0 2px 8px rgba(0,0,0,.12)','0 4px 16px rgba(0,0,0,.16)'), true ) ) { $out[] = 'box-shadow:' . $a['boxShadow']; }
        return implode( ';', $out );
    }
    private static function responsive_css( $nodes ) {
        $tablet = array(); $mobile = array();
        $walk = function( $items ) use ( &$walk, &$tablet, &$mobile ) {
            foreach ( $items as $node ) {
                $id = isset( $node['id'] ) ? sanitize_html_class( $node['id'] ) : '';
                $responsive = isset( $node['attrs']['responsive'] ) && is_array( $node['attrs']['responsive'] ) ? $node['attrs']['responsive'] : array();
                foreach ( array( 'tablet' => &$tablet, 'mobile' => &$mobile ) as $device => &$bucket ) {
                    if ( empty( $responsive[ $device ] ) || ! $id ) { continue; }
                    $map = array( 'fontSize'=>'font-size','padding'=>'padding','margin'=>'margin','width'=>'width','height'=>'height','gap'=>'gap','maxWidth'=>'max-width','minHeight'=>'min-height','borderRadius'=>'border-radius','color'=>'color','background'=>'background-color' );
                    $declarations = array();
                    foreach ( $map as $key => $property ) {
                        if ( ! isset( $responsive[ $device ][ $key ] ) ) { continue; }
                        $value = (string) $responsive[ $device ][ $key ];
                        if ( in_array( $key, array( 'color','background' ), true ) ) { if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) { $declarations[] = $property . ':' . $value; } }
                        elseif ( preg_match( '/^(auto|0|[0-9]{1,4}(px|%|rem|em|vh|vw)|var\(--ib-space-(xs|sm|md|lg|xl|xxl)\))$/', $value ) ) { $declarations[] = $property . ':' . $value; }
                    }
                    if ( $declarations ) { $bucket[] = '.ib-node-' . $id . '{' . implode( ';', $declarations ) . '}'; }
                }
                unset( $bucket );
                if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) { $walk( $node['children'] ); }
            }
        };
        $walk( $nodes );
        $css = '';
        if ( $tablet ) { $css .= '@media(max-width:768px){' . implode( '', $tablet ) . '}'; }
        if ( $mobile ) { $css .= '@media(max-width:480px){' . implode( '', $mobile ) . '}'; }
        return $css;
    }
}
