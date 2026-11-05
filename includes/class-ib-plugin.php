<?php
namespace IndependentBuilder;
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Plugin {
    private static $instance = null;
    public static function instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
        add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
        add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
        new REST();
        new Global_Styles();
    }
    public function admin_menu() { add_menu_page( 'Independent Builder', 'Page Builder', 'edit_pages', 'independent-builder', array( $this, 'render_landing' ), 'dashicons-layout', 58 ); }
    public function render_landing() {
        if ( ! current_user_can( 'edit_pages' ) ) { return; }
        echo '<div class="wrap"><h1>Independent Builder</h1>';
        if ( isset( $_GET['post_id'] ) ) {
            $id = absint( $_GET['post_id'] );
            if ( current_user_can( 'edit_post', $id ) ) { echo '<div id="ib-editor-root"></div></div>'; return; }
        }
        echo '<p>Choose a page or post to open in the visual editor.</p><ul>';
        $items = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 100 ) );
        foreach ( $items as $item ) { if ( current_user_can( 'edit_post', $item->ID ) ) {
            $url = add_query_arg( array( 'page' => 'independent-builder', 'post_id' => $item->ID ), admin_url( 'admin.php' ) );
            echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $item ) ?: '(no title)' ) . '</a> — ' . esc_html( $item->post_type ) . '</li>';
        } }
        echo '</ul></div>';
    }
    public function row_action( $actions, $post ) {
        if ( in_array( $post->post_type, array( 'page', 'post' ), true ) && current_user_can( 'edit_post', $post->ID ) ) {
            $url = add_query_arg( array( 'page' => 'independent-builder', 'post_id' => $post->ID ), admin_url( 'admin.php' ) );
            $actions['ib_builder'] = '<a href="' . esc_url( $url ) . '">Open Builder</a>';
        }
        return $actions;
    }
    public function enqueue_editor( $hook ) {
        if ( 'toplevel_page_independent-builder' !== $hook || empty( $_GET['post_id'] ) ) { return; }
        $id = absint( $_GET['post_id'] ); if ( ! current_user_can( 'edit_post', $id ) ) { return; }
        wp_enqueue_media();
        wp_enqueue_style( 'ib-editor', IB_URL . 'assets/css/editor.css', array(), IB_VERSION );
        wp_enqueue_script( 'ib-editor', IB_URL . 'assets/js/editor.js', array( 'wp-element', 'wp-api-fetch' ), IB_VERSION, true );
        wp_localize_script( 'ib-editor', 'IB_CONFIG', array(
            'postId' => $id, 'restUrl' => esc_url_raw( rest_url( 'independent-builder/v1/document/' . $id ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ), 'adminUrl' => admin_url(), 'title' => get_the_title( $id ),
            'breakpoints' => array( 'tablet' => 768, 'mobile' => 480 ),
            'globalStylesUrl' => esc_url_raw( rest_url( 'independent-builder/v1/global-styles' ) ),
            'globalDefaults' => Global_Styles::defaults(),
            'canManageGlobalStyles' => current_user_can( 'manage_options' ),
            'strings' => array( 'saving' => 'Saving…', 'saved' => 'Saved', 'error' => 'Save failed. Your changes remain in this editor.' ),
        ) );
    }
    public function enqueue_frontend() {
        if ( ! is_singular() ) { return; }
        $id = get_queried_object_id();
        $document = $id ? get_post_meta( $id, REST::META_KEY, true ) : false;
        if ( $id && is_array( $document ) ) {
            wp_enqueue_style( 'ib-frontend', IB_URL . 'assets/css/frontend.css', array(), IB_VERSION );
            if ( Global_Styles::is_configured() ) {
                wp_add_inline_style( 'ib-frontend', Global_Styles::get_css() );
            }
        }
    }
    public function filter_content( $content ) {
        if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) { return $content; }
        $doc = get_post_meta( get_the_ID(), REST::META_KEY, true );
        if ( ! is_array( $doc ) || empty( $doc['nodes'] ) ) { return $content; }
        return Renderer::render_document( $doc );
    }
}
