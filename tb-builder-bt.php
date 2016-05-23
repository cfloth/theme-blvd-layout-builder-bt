<?php
/*
Plugin Name: Theme Blvd Layout Builder (BT)
Description: When using a Theme Blvd theme, this plugin gives you slick interface to build custom layouts. This is a modification of the original plugin by Theme Blvd, adding more background options.
Version: 2.0.9.5
Author: Blue Tonic LLC
Author URI: http://circlecf.com
License: GPL2

    Original: Copyright 2015  Theme Blvd

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2,
    as published by the Free Software Foundation.

    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can likely be found here:
    http://www.gnu.org/licenses/gpl-2.0.html

*/

define( 'TB_BUILDER_PLUGIN_VERSION', '2.0.9.5' );
define( 'TB_BUILDER_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'TB_BUILDER_PLUGIN_URI', plugins_url( '' , __FILE__ ) );

/**
 * Run Layout Builder plugin
 *
 * @since 1.0.0
 */
function themeblvd_builder_init() {

	global $_themeblvd_export_layouts;
	global $_themeblvd_layout_builder;

	// Include general items
	include_once( TB_BUILDER_PLUGIN_DIR . '/includes/class-tb-layout-builder-data.php' );
	include_once( TB_BUILDER_PLUGIN_DIR . '/includes/class-tb-layout-builder-notices.php' );
	include_once( TB_BUILDER_PLUGIN_DIR . '/includes/general.php' );

	// Error handling
	$notices = Theme_Blvd_Layout_Builder_Notices::get_instance();

	if ( $notices->do_stop() ) {
		// Stop plugin from running
		return;
	}

	if ( version_compare( TB_FRAMEWORK_VERSION, '2.5.0', '<' ) ) {
		include_once( TB_BUILDER_PLUGIN_DIR . '/includes/legacy.php' ); // @deprecated functions used by older themes
	}

	// DEBUG/DEV Mode
	if ( ! defined( 'TB_BUILDER_DEBUG' ) ) {
		define( 'TB_BUILDER_DEBUG', false );
	}

	// Register custom layout hidden post type
	add_action( 'init', 'themeblvd_builder_register_post_type' );

	// Verify custom layout's data
	add_action( 'template_redirect', 'themeblvd_builder_verify_data' );

	// Frontend actions
	if ( version_compare( TB_FRAMEWORK_VERSION, '2.5.0', '>=' ) ) {

		/**
		 * Prints out any specific CSS in the <head> that the user
		 * has configured from the Builder. Note in this funciton
		 * we're using wp_add_inline_style() for the theme's style.css.
		 * Since the theme's style.css is hooked at priorty 20, we're
		 * using 25 here.
		 */
		add_action( 'wp_enqueue_scripts', 'themeblvd_builder_styles', 25 );

		/**
		 * Hooks to action in the theme's template_builder.php page
		 * template and footer.php template in order to display the
		 * custom layout. Requires that the theme has the function
		 * themeblvd_elements(), Theme Blvd Framework 2.5+.
		 */
		add_action( 'themeblvd_builder_content', 'themeblvd_builder_layout', 10, 1 );

	} else {
		// @deprecated
		add_action( 'themeblvd_builder_content', 'themeblvd_builder_content' );
		add_action( 'themeblvd_featured', 'themeblvd_builder_featured' );
		add_action( 'themeblvd_featured_below', 'themeblvd_builder_featured_below' );
		add_filter( 'themeblvd_frontend_config', 'themeblvd_builder_legacy_config' );
	}

	// Homepage layout (@deprecated -- With current themes, user must set a static frontpage with the template applied)
	if ( function_exists('themeblvd_builder_legacy_homepage') ) {
		themeblvd_builder_legacy_homepage();
	}

	// Legacy sample layouts (@deprecated)
	if ( function_exists('themeblvd_builder_legacy_samples') ) {
		add_filter( 'themeblvd_sample_layouts', 'themeblvd_builder_legacy_samples' );
	}

	// Template Footer Sync
	if ( themeblvd_supports( 'display', 'footer_sync' ) ) {

		$link = sprintf('<a href="%s">%s</a>', esc_url( add_query_arg( array('page' => 'themeblvd_builder'), admin_url('admin.php') ) ), __('Templates', 'theme-blvd-layout-builder'));

		$option = array( // This option won't actually get displayed, but registered for sanitization process
			'name' 		=> null,
			'desc' 		=> null,
			'id' 		=> 'footer_sync',
			'std' 		=> '0',
			'type' 		=> 'checkbox'
		);
		themeblvd_add_option( 'layout', 'footer', 'footer_sync', $option );

		$option = array(
			'name' 		=> __( 'Custom Template', 'theme-blvd-layout-builder' ),
			'desc' 		=> sprintf(__( 'Select from the custom templates you\'ve built at the %s area. This template will be used to populate the bottom of your site.', 'theme-blvd-layout-builder' ), $link),
			'id' 		=> 'footer_template',
			'std' 		=> '',
			'type' 		=> 'select',
			'select' 	=> 'templates',
			'class'		=> 'hide footer-template-setup'
		);
		themeblvd_add_option( 'layout', 'footer', 'footer_template', $option );

	}

	// Admin Layout Builder
	if ( is_admin() ){

		// Check to make sure admin interface isn't set to be
		// hidden and for the appropriate user capability
		if ( themeblvd_supports( 'admin', 'builder' ) && current_user_can( themeblvd_admin_module_cap( 'builder' ) ) ) {

			// Setup exporting capabilities
			if ( class_exists('Theme_Blvd_Export') ) { // Theme Blvd framework 2.5+ and Theme Blvd Import plugin

				include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/class-tb-export-layout.php' );
				include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/class-tb-import-layout.php' );

				$args = array(
					'filename'	=> 'template-{name}.xml', // string {name} will be dynamically replaced with each export
					'base_url'	=> admin_url('admin.php?page=themeblvd_builder')
				);

				$_themeblvd_export_layout = new Theme_Blvd_Export_Layout( 'layout', $args ); // Extends class Theme_Blvd_Export
			}

			include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/builder-samples.php' );
			include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/class-tb-layout-builder-ajax.php' );
			include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/class-tb-layout-builder.php' );

			// Setup Builder interface
			$_themeblvd_layout_builder = new Theme_Blvd_Layout_Builder();

		}
	}

}
add_action( 'after_setup_theme', 'themeblvd_builder_init' );

/**
 * Setup Layout Builder API
 *
 * @since 1.2.0
 */
function themeblvd_builder_api_init() {

	// Include screen options class (not currently
	// used in API, but could potentially be later on)
	// include_once( TB_BUILDER_PLUGIN_DIR . '/includes/admin/class-tb-layout-builder-screen.php' );

	// Instantiate single object for Builder "Screen Options" tab.
	// Theme_Blvd_Layout_Builder_Screen::get_instance();

	// Include Theme_Blvd_Builder_API class.
	include_once( TB_BUILDER_PLUGIN_DIR . '/includes/api/class-tb-builder-api.php' );

	// Instantiate single object for Builder API.
	// Helper functions are located within theme
	// framework.
	Theme_Blvd_Builder_API::get_instance();

}
add_action( 'themeblvd_api', 'themeblvd_builder_api_init' );

/**
 * Register text domain for localization.
 *
 * @since 1.0.0
 */
function themeblvd_builder_textdomain() {
	load_plugin_textdomain('theme-blvd-layout-builder');
}
add_action( 'init', 'themeblvd_builder_textdomain' );

/**
 * Add in resposive images background option.
 * 
 * @since msb customization
 */
function themeblvd_builder_options( $options, $type, $element_type ) {
	$options['bg_type']['options']['image'] = 'Custom color + image, same on all screens';

	$new_arr = array();
	foreach($options['bg_type']['options'] as $key => $val) {
		$new_arr[$key] = $val;
		if ($key == 'image') {
			$new_arr['imagelarge'] = 'Custom color + image, only on large screens';
			$new_arr['imagetwo'] = 'Custom color + image, different on small & large screens';
		}
	}
	$options['bg_type']['options'] = $new_arr;

	// Add the small screen image option in the right place of the array
	$new_arr = array();
	foreach($options as $key => $val) {
		$new_arr[$key] = $val;
		if ($key == 'subgroup_end_2') {
			$new_arr['subgroup_start_img_sm'] = array(
				'type'		=> 'subgroup_start',
				'class'		=> 'hide receiver receiver-imagetwo'
			);
			$new_arr['bg_image_sm'] = array(
				'id'		=> 'bg_image_sm',
				'name'		=> __('Small Background Image', 'theme-blvd-layout-builder'),
				'desc'		=> __('Select a background image for small screens (smaller than 800px).', 'theme-blvd-layout-builder'),
				'type'		=> 'background',
				'color'		=> false,
				'parallax'	=> false
			);
			$new_arr['subgroup_end_img_sm'] = array(
				'type'		=> 'subgroup_end'
			);
		}
	}
	$options = $new_arr;

	$options['bg_image']['name'] = __('Large Background Image', 'theme-blvd-layout-builder');
	$options['bg_image']['desc'] = __('Select a background image for larger screens (800px or larger).', 'theme-blvd-layout-builder');

	$options['text_color']['class'] = $options['text_color']['class'] . ' receiver-imagetwo receiver-imagelarge';
	$options['bg_color']['class'] = $options['bg_color']['class'] . ' receiver-imagetwo receiver-imagelarge';
	$options['bg_color_opacity']['class'] = $options['bg_color_opacity']['class'] . ' receiver-imagetwo receiver-imagelarge';
	$options['subgroup_start_2']['class'] = $options['subgroup_start_2']['class'] . ' receiver-imagetwo receiver-imagelarge';
	$options['subgroup_start_3']['class'] = $options['subgroup_start_3']['class'] . ' receiver-imagetwo receiver-imagelarge';

	// print 'options type: ' . $type . "<br />\n";
	// print 'element_type: ' . $element_type . "<br />\n";
	// print '<pre>';
	// print_r($options);
	// print '<pre>' . "<br />\n";

    return $options;
}
add_filter('themeblvd_builder_display_options', 'themeblvd_builder_options', 10, 3);

/**
 * Register extra extra large background size.
 * 
 * @since msb customization
 */
function themeblvd_builder_bigimage() {
	add_image_size( 'tb_xx_large', 2399, 9999, false );
}
add_action( 'admin_init', 'themeblvd_builder_bigimage' );