<?php
/*
Plugin Name: Custom Option Pages for Elementor
Plugin URI: https://tomfransen.nl
Description: Implements custom option pages / reusable content via ACF and exposes them as dynamic tags in Elementor Pro.
Version: 1.1.2
Author: TomFransen Media
Author URI: https://tomfransen.nl
Text Domain: custom-option-pages-elementor
Plugin Icon: assets/icon-128x128.png
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// ------------------------------------
// Admin notice for missing dependencies
// ------------------------------------
add_action( 'admin_notices', function() {
    $missing = [];

    if ( ! class_exists( '\Elementor\Plugin' ) ) {
        $missing[] = 'Elementor Pro';
    }

    if ( ! function_exists( 'get_field' ) ) {
        $missing[] = 'Advanced Custom Fields (ACF)';
    }

    if ( ! post_type_exists('business-info') ) {
        $missing[] = 'a CPT or ACF options page called "business-info"';
    }

    if ( ! empty( $missing ) ) {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo '<strong>Custom Option Pages for Elementor:</strong> ';
        echo 'requires the following to be installed/active: ';
        echo implode( ', ', $missing );
        echo '.</p></div>';
    }
});

// ------------------------------------
// Load plugin classes safely
// ------------------------------------
add_action( 'init', function() {
    // Only load if all dependencies exist
    if ( ! class_exists( '\Elementor\Plugin' ) ) return;
    if ( ! function_exists( 'get_field' ) ) return;
    if ( ! post_type_exists('business-info') ) return;

    // Include dynamic tag classes
    require_once __DIR__ . '/includes/option-pages-for-elementor.php';
    require_once __DIR__ . '/includes/option-pages-for-elementor-images.php';
});

// --- GitHub auto-update setup ---
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

add_action('plugins_loaded', function() {
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/tomfransenmedia/custom-option-pages-elementor/',
        __FILE__,
        'custom-option-pages-elementor'
    );

    $myUpdateChecker->setBranch('main'); // adjust if needed
    $myUpdateChecker->getVcsApi()->enableReleaseAssets(); // for GitHub release zips
});