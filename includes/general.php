<?php
/**
 * Register hidden custom post type for layouts.
 *
 * @since 1.2.0
 */
function themeblvd_builder_register_post_type() {
	$args = apply_filters( 'themeblvd_builder_post_type_args', array(
		'labels' 			=> array( 'name' => 'Layouts', 'singular_name' => 'Layout' ),
		'public'			=> false,
		//'show_ui' 		=> true,	// Can uncomment for debugging
		'query_var' 		=> true,
		'capability_type' 	=> 'post',
		'hierarchical' 		=> false,
		'rewrite' 			=> false,
		'supports' 			=> array( 'title', 'custom-fields' ),
		'can_export'		=> true
	));
	register_post_type( 'tb_layout', $args );
}

/**
 * Display custom layout for themes with framework v2.5+
 *
 * @since 2.0.0
 *
 * @param string $context Where the custom layout is being outputted, main or footer
 */
function themeblvd_builder_layout( $context ) {

	global $post;

	// Check to make sure theme is up to date for this process.
	if ( ! function_exists( 'themeblvd_elements' ) ) {
		return;
	}

	// Where to pull custom layout data from. Will either be
	// current page or synced template.
	if ( $context == 'footer' ) {
		$post_id = themeblvd_set_att('footer_sync', themeblvd_config('bottom_builder_post_id') );
		$layout_name = themeblvd_config('bottom_builder');
	} else {
		$post_id = themeblvd_config('builder_post_id');
		$layout_name = themeblvd_config('builder');
	}

	// Get section data
	$section_data = get_post_meta( $post_id, '_tb_builder_sections', true );

	if ( ! $section_data ) {
		echo '<section class="element-section">';
		printf('<div class="element"><div class="alert alert-warning">%s</div></div>', esc_html__('The template has not been configured yet.', 'theme-blvd-layout-builder'));
		echo '</section>';
		return;
	}

	// Get elements for layout, which are organized within sections
	$sections = get_post_meta( $post_id, '_tb_builder_elements', true );

	// Loop through sections of elements
	if ( $sections ) {

		$counter = apply_filters('themeblvd_builder_section_start_count', 1);

		// Check for pagination handling
		$sections = themeblvd_builder_paginated_layout( $post_id, $sections );

		// Display sections of elements
		foreach ( $sections as $section_id => $elements ) {

			// Section classes
			$class = implode( ' ', themeblvd_get_section_class( $section_id, $section_data[$section_id], count($elements) ) );

			// Display settings for section
			$display = array();

			if ( isset( $section_data[$section_id]['display'] ) ) {
				$display = $section_data[$section_id]['display'];
			}

			// Open section
			do_action( 'themeblvd_section_before', $section_id, $layout_name, $section_data[$section_id], $counter );

			// Section ID
			$html_id = apply_filters( 'themeblvd_section_html_id', sprintf('%s-section-%s', $layout_name, $counter), $section_id, $layout_name, $section_data[$section_id], $counter );

			// Output section
			printf( '<section id="%s" class="%s">', esc_attr($html_id), esc_attr($class) );

			if ( $display ) {

				if ( in_array($display['bg_type'], array('image', 'imagetwo', 'imagelarge', 'slideshow', 'video')) && ! empty($display['apply_bg_shade']) ) {
					printf( '<div class="bg-shade" style="background-color: %s;"></div>', esc_attr( themeblvd_get_rgb( $display['bg_shade_color'], $display['bg_shade_opacity'] ) ) );
				}

				if ( function_exists('themeblvd_do_parallax') && themeblvd_do_parallax($display) ) { // framework 2.5.1+
					themeblvd_bg_parallax( $display );
				}

				if ( $display['bg_type'] == 'video' && ! empty($display['bg_video']) ) {
					themeblvd_bg_video( $display['bg_video'] );
				}

				if ( $display['bg_type'] == 'slideshow' && ! empty($display['bg_slideshow']) ) {

					$parallax = false;

					if ( ! empty($display['apply_bg_slideshow_parallax']) ) {
						$parallax = true;
					}

					themeblvd_bg_slideshow( $section_id, $display['bg_slideshow'], $parallax );
				}

				if ( ! empty( $display['blend_up'] ) || ! empty( $display['blend_down'] ) ) {

					$bg_color = apply_filters('themeblvd_blend_section_color_default', '#ffffff');

					if ( ! empty( $display['bg_color'] ) ) {
						$bg_color = $display['bg_color'];
					}

					if ( ! empty( $display['blend_up'] ) ) {
						printf('<span class="tb-blend up"><span class="blend-outer"><span class="blend-inner" style="background-color:%s"></span></span></span>', esc_attr($bg_color));
					}

					if ( ! empty( $display['blend_down'] ) ) {
						printf('<span class="tb-blend down"><span class="blend-outer"><span class="blend-inner" style="background-color:%s"></span></span></span>', esc_attr($bg_color));
					}

				}
			}

			do_action( 'themeblvd_section_top', $section_id, $layout_name, $section_data[$section_id], $counter );

			// Display elements
			themeblvd_elements( $section_id, $elements );

			// Close section
			do_action( 'themeblvd_section_bottom', $section_id, $layout_name, $section_data[$section_id], $counter );
			printf( '</section><!-- #%s (end) -->', esc_attr($section_id) );
			do_action( 'themeblvd_section_after', $section_id, $layout_name, $section_data[$section_id], $counter );

			// End section
			do_action( 'themeblvd_section_close', $section_id, $layout_name, $section_data[$section_id], $counter );

			$counter++;

		}

	} else {

		echo '<section class="element-section">';
		printf('<div class="element"><div class="alert alert-warning">%s</div></div>', esc_html__('No element data could be found for this custom layout.', 'theme-blvd-layout-builder'));
		echo '</section>';

	}

}

/**
 * Verify data from current custom layout is saved
 * properly with the current version of the Layout
 * Builder plugin.
 *
 * @since 2.0.0
 */
function themeblvd_builder_verify_data() {
	if ( themeblvd_config( 'builder' ) && themeblvd_config( 'builder_post_id' ) ) {
		$data = new Theme_Blvd_Layout_Builder_Data( themeblvd_config( 'builder_post_id' ) );
		$data->verify('elements');
		$data->verify('info');
		$data->finalize();
	}
}

/**
 * If we're on the second page of a paginated query,
 * we'll find the paginated element and see if all other
 * elements should be hidden. If so, we'll modify the sections
 * of elements to display.
 *
 * @since 2.0.0
 *
 * @param string $var Description
 * @return string $var Description
 */
function themeblvd_builder_paginated_layout( $post_id, $sections ) {

	if ( is_paged() ) {

		$show_section_id = '';
		$show_element_id = '';

		// Hunt for the actual ID's of section and element we're going to keep.
		foreach ( $sections as $section_id => $elements ) {
			if ( $elements ) {
				foreach ( $elements as $element_id => $element ) {

					if ( ! isset( $element['type'] ) ) {
						continue;
					}

					if ( $element['type'] == 'blog' || $element['type'] == 'post_list' || $element['type'] == 'post_grid' ) {

						if ( ! empty( $element['options']['paginated_hide'] ) ) {
							$show_section_id = $section_id;
							$show_element_id = $element_id;
						}

					} else if ( $element['type'] == 'columns' ) {

						$num = count( explode( '-', $element['options']['setup'] ) );

						for ( $i = 1; $i <= $num; $i++ ) {

							$blocks = get_post_meta( $post_id, '_tb_builder_'.$element_id.'_col_'.$i, true );

							if ( ! empty( $blocks['elements'] ) ) {
								foreach ( $blocks['elements'] as $block_id => $block ) {
									if ( ! empty( $block['options']['paginated_hide'] ) ) {
										$show_section_id = $section_id;
										$show_element_id = $element_id;
									}
								}
							}
						} // end for $i
					}
				} // end foreach $elements
			}
		} // end foreach $sections

		// Now remove everything that isn't part of what we want to keep.
		if ( $show_section_id && $show_element_id ) {
			foreach ( $sections as $section_id => $elements ) {

				if ( $section_id != $show_section_id ) {
					unset( $sections[$section_id] );
					continue;
				}

				if ( $elements ) {
					foreach ( $elements as $element_id => $element ) {
						if ( $element_id != $show_element_id ) {
							unset( $sections[$section_id][$element_id] );
						}
					}
				}
			}
		}

	} // end if ( is_paged() )

	return $sections;
}

/**
 * Add external styles for Builder sections
 *
 * @since 2.0.0
 */
function themeblvd_builder_styles() {

	if ( ! function_exists('themeblvd_config') ) {
		return;
	}

	$layouts = array();

	if ( themeblvd_config('builder_post_id') ) {
		$layouts['main'] = themeblvd_config('builder_post_id');
	}

	if ( themeblvd_config('bottom_builder_post_id') ) {
		$layouts['bottom'] = themeblvd_config('bottom_builder_post_id');
	}

	if ( ! $layouts ) {
		return;
	}

	$print = '';

	foreach ( $layouts as $location => $post_id ) {

		$sections = get_post_meta( $post_id, '_tb_builder_sections', true );
		$count = apply_filters('themeblvd_builder_section_start_count', 1);

		if ( $sections ) {
			foreach ( $sections as $section_id => $section ) {

				$section_print = '';

				/**
				* START custom inline style function call
				* 
				* @since msb customization
				*/
				// $styles = themeblvd_get_display_inline_style( $section['display'], 'external' );
				$styles = themeblvd_builder_get_display_inline_style( $section['display'], 'external' );
				/**
				* END custom inline style function call
				* 
				* @since msb customization
				*/

				if ( $styles ) {
					foreach ( $styles as $type => $params ) {

						// Add extra top padding for transparent "suck up" header, if first section
						if ( $location == 'main' && $count == 1 && themeblvd_config('suck_up') ) {

							if ( $type == 'desktop' || $type == 'tablet' ) {

								if ( $type == 'tablet' ) {
									$top = intval(themeblvd_config('top_height_tablet'));
								} else {
									$top = intval(themeblvd_config('top_height'));
								}

								if ( empty( $params['padding-top'] ) ) { // user didn't set custom padding
									$top = $top + 60; // themelvd.css default top padding for section is 60
								} else {
									$top = $top + intval($params['padding-top']);
								}

								$params['padding-top'] = strval($top).'px';

							}

						}

						if ( ! $params ) {
							continue;
						}

						$indent = '';

						if ( $type != 'mobile' ) {
							$indent = "\t";
						}

						/**
						* START custom media queries
						* 
						* @since msb customization
						*/
						switch ( $type ) {
							case 'mobile_hires' :
								// < 800px (high resolution) = 800px wide = tb_x_large
								$section_print .= "@media (-webkit-min-device-pixel-ratio: 1.5), (min--moz-device-pixel-ratio: 1.5), (-o-min-device-pixel-ratio: 3/2), (min-resolution: 144dpi) {\n";
								break;
							case 'tablet' :
								// 800px - 1200px = 1200px wide = tb_x_large
								$section_print .= "@media only screen and (min-width: 50em) {\n";
								break;
							case 'tablet_hires' :
								// 800px - 1200px (high resolution) = tb_xx_large
								$section_print .= "@media (-webkit-min-device-pixel-ratio: 1.5) and (min-width: 37.5em), (min--moz-device-pixel-ratio: 1.5) and (min-width: 37.5em), (-o-min-device-pixel-ratio: 3/2) and (min-width: 37.5em), (min-resolution: 144dpi) and (min-width: 50em) {\n";
								break;
							case 'desktop' :
								// 1200px - 1800px = tb_xx_large
								$section_print .= "@media only screen and (min-width: 75em) {\n";
								break;
							case 'desktop_hires' :
								// 1200px and up (high resolution) = original
								$section_print .= "@media (-webkit-min-device-pixel-ratio: 1.5) and (min-width: 75em), (min--moz-device-pixel-ratio: 1.5) and (min-width: 75em), (-o-min-device-pixel-ratio: 3/2) and (min-width: 75em), (min-resolution: 144dpi) and (min-width: 75em) {\n";
								break;
							case 'massive' :
								// 1800px and up = original
								$section_print .= "@media only screen and (min-width: 112.5em) {\n";
								break;
						}
						/**
						* END custom media queries
						* 
						* @since msb customization
						*/

						if ( strpos($section_id, 'section_') === false ) {
							$section_id = 'section_'.$section_id;
						}

						$section_print .= $indent.sprintf("#custom-%s > .%s {\n", $location, $section_id);

						foreach ( $params as $prop => $value ) {
							$prop = str_replace('-2', '', $prop);
							$section_print .= $indent.sprintf("\t%s: %s;\n", $prop, $value);
						}

						$section_print .= $indent."}\n";

						// Add modified styles for any popout elements when section has custom padding
						if ( ! empty($params['padding-right']) || ! empty($params['padding-left']) ) {

							$section_print .= $indent.sprintf("#custom-%s > .%s > .element.popout {\n", $location, $section_id);

							if ( ! empty($params['padding-right']) ) {
								if ( $params['padding-right'] == '0px' ) {
									$section_print .= $indent."\tmargin-right: 0;\n";
								} else {
									$section_print .= $indent.sprintf("\tmargin-right: -%s;\n", $params['padding-right']);
								}

							}

							if ( ! empty($params['padding-left']) ) {
								if ( $params['padding-left'] == '0px' ) {
									$section_print .= $indent."\tmargin-left: 0;\n";
								} else {
									$section_print .= $indent.sprintf("\tmargin-left: -%s;\n", $params['padding-left']);
								}
							}

							$section_print .= $indent."}\n";
						}

						if ( ! empty($params['padding-top']) && $params['padding-top'] != '0px' ) {
							$section_print .= $indent.sprintf("#custom-%s > .%s > .element.popout.first {\n", $location, $section_id);
							$section_print .= $indent.sprintf("\tmargin-top: -%s;\n", $params['padding-top']);
							$section_print .= $indent."}\n";
						}

						if ( ! empty($params['padding-bottom']) && $params['padding-bottom'] != '0px' ) {
							$section_print .= $indent.sprintf("#custom-%s > .%s > .element.popout.last {\n", $location, $section_id);
							$section_print .= $indent.sprintf("\tmargin-bottom: -%s;\n", $params['padding-bottom']);
							$section_print .= $indent."}\n";
						}

						if ( $type != 'mobile' ) {
							$section_print .= "}\n";
						}

					}
				}

				// If this is the first section of a page's layout and using transparent,
				// header, look to see if first element uses the match to viewport height
				// feature. If yes, then we'll zero out the padding when the viewport is
				// larger than 992 x 500.
				if ( $count == 1 && $location == 'main' && themeblvd_config('suck_up') ) {

					$elements = get_post_meta( $post_id, '_tb_builder_elements', true );

					$id = $section_id;

					if ( $id == 'section_primary' ) {
						$id = 'primary';
					}

				    if ( ! empty($elements[$id]) ) {

						$first = current($elements[$id]);

						if ( $first['type'] == 'jumbotron' || $first['type'] == 'jumbotron_slider' && ! empty($first['display']['apply_popout']) ) {

							$section_print .= "@media (min-width: 992px) {\n";
							$section_print .= "\t#custom-main > .section_primary > .element.popout.first .jumbotron-outer {\n";
							$section_print .= sprintf("\t\tpadding-top: %spx;\n", intval(themeblvd_config('top_height')) + 60);
							$section_print .= "\t}\n";
							$section_print .= "}\n";

							$section_print .= "@media (max-width: 991px) and (min-width: 768px) {\n";
							$section_print .= "\t#custom-main > .section_primary > .element.popout.first .jumbotron-outer {\n";
							$section_print .= sprintf("\t\tpadding-top: %spx;\n", intval(themeblvd_config('top_height_tablet')) + 60);
							$section_print .= "\t}\n";
							$section_print .= "}\n";

						}

						if ( in_array('height-100vh', themeblvd_get_element_class($first) ) ) {
							$section_print .= "@media (min-width: 992px) and (min-height: 500px) {\n";
							$section_print .= sprintf("\t#custom-%s > .%s { /* First element is set to match viewport height w/suck up header */\n", $location, $section_id);
							$section_print .= "\t\tpadding-top: 0;\n";
							$section_print .= "\t\tpadding-bottom: 0;\n";
							$section_print .= "\t}\n";
							$section_print .= "}\n";
						}
				    }

				}

				if ( $section_print ) {
					$print .= sprintf("\n/* %s */\n", $section['label']);
					$print .= $section_print;
				}

				$count++;
			}

		}
	}

	// Sanitize
	$print = trim($print);
	$print = wp_kses( $print, array() );
	$print = htmlspecialchars_decode( $print );

	// Print after style.css
	if ( $print ) {
		wp_add_inline_style( 'themeblvd-theme', $print );
	}

}

/**
 * Get the ID of an attachment from its url
 * 
 * @since msb customization
*/
function themeblvd_builder_id_from_url( $attachment_url = '' ) {
	global $wpdb;
	$attachment_id = false;
 
	// If there is no url, return.
	if ( '' == $attachment_url )
		return;
 
	// Get the upload directory paths
	$upload_dir_paths = wp_upload_dir();
 
	// Make sure the upload path base directory exists in the attachment URL, to verify that we're working with a media library image
	//if ( false !== strpos( $attachment_url, $upload_dir_paths['baseurl'] ) ) {

	// If this is the URL of an auto-generated thumbnail, get the URL of the original image
	$attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );

	// Remove the upload path base directory from the attachment URL
	//$attachment_url = str_replace( $upload_dir_paths['baseurl'] . '/', '', $attachment_url );
	$uploads = parse_url($upload_dir_paths['baseurl']);

	if (count($uploads) > 0) {
		$attachment_url = substr($attachment_url, (strpos($attachment_url, $uploads['path']) + strlen($uploads['path']) + 1));
 
		// Finally, run a custom database query to get the attachment ID from the modified attachment URL
		$attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'", $attachment_url ) );
 
	}
 
	return $attachment_id;
}

/**
 * Get inline styles for a set of display options.
 *
 * @since msb customization
 *
 * @param array $display Display options
 * @param string $print How to return the styles, use "inline" or "external"
 * @return string $style Inline style line to be used
 */
function themeblvd_builder_get_display_inline_style( $display, $print = 'inline' ) {
	$bg_type = '';
	$style = '';
	$params_mobile = array();

	$params = array(
		'mobile'	=> array(),
		'mobile_hires'	=> array(),
		'tablet'	=> array(),
		'tablet_hires'	=> array(),
		'desktop'	=> array(),
		'desktop_hires'	=> array(),
		'massive'	=> array()
	);

	if ( empty( $display['bg_type'] ) ) {
		$bg_type = 'none';
	} else {
		$bg_type = $display['bg_type'];
	}

	$parallax = false;

	if ( $bg_type == 'image' && ! empty( $display['bg_image']['attachment'] ) && $display['bg_image']['attachment'] == 'parallax' ) {
		$parallax = true;
	}

	if ( $bg_type == 'texture' && ! empty($display['apply_bg_texture_parallax']) ) {
		$parallax = true;
	}

	if ( in_array( $bg_type, array('color', 'texture', 'image', 'imagelarge', 'imagetwo', 'video', 'none') ) ) {

		if ( ( $bg_type == 'none' && empty($display['bg_content']) ) || $parallax ) {

			$params_mobile['background-color'] = 'transparent';

		} else if ( ! empty( $display['bg_color'] ) ) {

			$bg_color = $display['bg_color'];

			$params_mobile['background-color'] = $bg_color; // non-rgba, for old browsers

			if ( ! empty( $display['bg_color_opacity'] ) ) {
				$bg_color = themeblvd_get_rgb( $bg_color, $display['bg_color_opacity'] );
			}

			$params_mobile['background-color-2'] = $bg_color;

		}

		if ( $bg_type == 'texture' && ! $parallax ) {

			$textures = themeblvd_get_textures();

			if ( ! empty( $display['bg_texture'] ) && ! empty( $textures[$display['bg_texture']] ) ) {

				$texture = $textures[$display['bg_texture']];

				$params_mobile['background-image'] = sprintf('url(%s)', esc_url($texture['url']));
				$params_mobile['background-position'] = $texture['position'];
				$params_mobile['background-repeat'] = $texture['repeat'];
				$params_mobile['background-size'] = $texture['size'];

			}

		} else if ( ($bg_type == 'image' || $bg_type == 'imagelarge' || $bg_type == 'imagetwo') && ! $parallax ) {
			$repeat = false;

			if ( ! empty( $display['bg_image']['image'] ) ) {
				/*
				*
				* NOTE: small screen = 800px or less = mobile
				*
				*/

				$attachment_id = themeblvd_builder_id_from_url($display['bg_image']['image']);
				$img_meta = wp_get_attachment_metadata($attachment_id);

				// print '<pre>';
				// print_r($img_meta);
				// print '<pre>' . "<br />\n";

				// load the sizes that are added by jumpstart by default
				$img_tb_large = wp_get_attachment_image_src( $attachment_id, 'tb_large', false );
				$img_tb_x_large = wp_get_attachment_image_src( $attachment_id, 'tb_x_large', false );

				// Now load the sizes for the smaller images
				if ($bg_type == 'imagetwo' && ! empty( $display['bg_image_sm']['image'] ) ) {
					$attachment_sm_id = themeblvd_builder_id_from_url($display['bg_image_sm']['image']);

					// load the sizes that are added by jumpstart by default
					$img_sm_tb_large = wp_get_attachment_image_src( $attachment_sm_id, 'tb_large', false );
					$img_sm_tb_x_large = wp_get_attachment_image_src( $attachment_sm_id, 'tb_x_large', false );
				} elseif (! empty( $display['bg_image']['image'] )) {
					$img_sm_tb_large = $img_tb_large;
					$img_sm_tb_x_large = $img_tb_x_large;
				}

				// load the small screen setting
				if ($bg_type == 'image' || $bg_type == 'imagetwo') {
					$params['mobile']['background-image'] = sprintf('url(%s)', esc_url($img_sm_tb_large[0]));
					$params['mobile_hires']['background-image'] = sprintf('url(%s)', esc_url($img_sm_tb_x_large[0]));
				}

				$params['tablet']['background-image'] = sprintf('url(%s)', esc_url($img_tb_x_large[0]));

				// tb_xx_large or original
				if (isset($img_meta['sizes']['tb_xx_large'])) {
					$img_tb_xx_large = wp_get_attachment_image_src( $attachment_id, 'tb_xx_large', false );

					$params['tablet_hires']['background-image'] = sprintf('url(%s)', esc_url($img_tb_xx_large[0]));
					$params['desktop']['background-image'] = sprintf('url(%s)', esc_url($img_tb_xx_large[0]));

					//use original image for huge backgrounds
					$params['desktop_hires']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
					$params['massive']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
				}else{
					// extra extra large thumbnail doesn't exist
					//use original image for larger sizes
					$params['tablet_hires']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
					$params['desktop']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
					$params['desktop_hires']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
					$params['massive']['background-image'] = sprintf('url(%s)', esc_url($display['bg_image']['image']));
				}

			}

			if ( ! empty( $display['bg_image']['repeat'] ) ) {

				if ( $display['bg_image']['repeat'] != 'no-repeat' ) {
					$repeat = true;
				}

				$params['mobile']['background-repeat'] = $display['bg_image']['repeat'];
			}

			if ( ! $repeat && ! empty( $display['bg_image']['size'] ) ) {
				$params['mobile']['background-size'] = $display['bg_image']['size'];
			}

			if ( ! wp_is_mobile() && ! empty( $display['bg_image']['attachment'] ) ) {
				$params['mobile']['background-attachment'] = $display['bg_image']['attachment'];
			}

			if ( ! empty( $display['bg_image']['position'] ) ) {
				$params['mobile']['background-position'] = $display['bg_image']['position'];
			}

		} else if ( $bg_type == 'video' ) {

			if ( ! empty( $display['bg_video']['fallback'] ) ) {
				$params_mobile['background-image'] = sprintf('url(%s)', esc_url($display['bg_video']['fallback']));
			}

		}

	}

	if ( ! empty( $display['apply_border_top'] ) ) {

		$params_mobile['border-top-style'] = 'solid';

		if ( ! empty( $display['border_top_width'] ) ) {
			$params_mobile['border-top-width'] = $display['border_top_width'];
		}

		if ( ! empty( $display['border_top_color'] ) ) {
			$params_mobile['border-top-color'] = $display['border_top_color'];
		}

	}

	if ( ! empty( $display['apply_border_bottom'] ) ) {

		$params_mobile['border-bottom-style'] = 'solid';

		if ( ! empty( $display['border_bottom_width'] ) ) {
			$params_mobile['border-bottom-width'] = $display['border_bottom_width'];
		}

		if ( ! empty( $display['border_bottom_color'] ) ) {
			$params_mobile['border-bottom-color'] = $display['border_bottom_color'];
		}

	}

	if ( ! empty( $display['apply_padding'] ) ) {

		if ( ! empty( $display['padding_top'] ) ) {
			$params_mobile['padding-top'] = $display['padding_top'];
		}

		if ( ! empty( $display['padding_bottom'] ) ) {
			$params_mobile['padding-bottom'] = $display['padding_bottom'];
		}

		if ( ! empty( $display['padding_right'] ) ) {

			$params_mobile['padding-right'] = $display['padding_right'];

			if ( ! empty($display['apply_popout']) ) {
				$params_mobile['padding-right'] .= ' !important'; // override popout
			}
		}

		if ( ! empty( $display['padding_left'] ) ) {

			$params_mobile['padding-left'] = $display['padding_left'];

			if ( ! empty($display['apply_popout']) ) {
				$params_mobile['padding-left'] .= ' !important'; // override popout
			}
		}

	}

	if ( $print == 'external' ) {

		foreach ( $params as $key => $value ) {

			if ( $key == 'mobile' ) {
				continue;
			}

			if ( ! empty( $display['apply_padding_'.$key] ) ) {

				if ( ! empty( $display['padding_top_'.$key] ) ) {
					$params[$key]['padding-top'] = $display['padding_top_'.$key];
				}

				if ( ! empty( $display['padding_bottom_'.$key] ) ) {
					$params[$key]['padding-bottom'] = $display['padding_bottom_'.$key];
				}

				if ( ! empty( $display['padding_right_'.$key] ) ) {
					$params[$key]['padding-right'] = $display['padding_right_'.$key];
				}

				if ( ! empty( $display['padding_left_'.$key] ) ) {
					$params[$key]['padding-left'] = $display['padding_left_'.$key];
				}

			}

		}

	}

	$params['mobile'] = array_merge($params['mobile'],$params_mobile);

	$params = apply_filters( 'themeblvd_display_inline_style', $params, $display );

	if ( $print == 'inline' ) {

		foreach ( $params as $key => $value ) {
			$key = str_replace('-2', '', $key);
			$style .= sprintf( '%s: %s; ', $key, $value );
		}

		return trim( esc_attr($style) );
	}

	return $params;
}
