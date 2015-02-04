<?php
/*
Plugin Name: Custom CSSTidy
Plugin URI: 
Description: In a world of custom things, let us introduce yet another plugin that creates a custom CSS box in the customizer and safely stores it in the database so you can output the CSS directly, no need to escape it. Probably. Most likely not.
Author: Jose Castaneda
Version: 0.1
Author URI: http://blog.josemcastaneda.com

=====================================================================================
Copyright (C) 2014 Jose Castaneda

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WordPress; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
=====================================================================================

*/


add_action( 'customize_register', 'custom_csstidy' );
function custom_csstidy( $wp_customize ){
	$wp_customize->add_section( 'custom_csstidy', array( 
		'title' => __( 'Custom CSS', 'custom-csstidy' ),
		'priority' => 150 
	) );
	$wp_customize->add_setting( 'csstidy_code', array( 
		'type' => 'theme_mod', 
		'transport' => 'refresh',
		// This is the key part of the setting!
		'sanitize_callback' => 'csstidy_sani_cb'
	) );
	
	$wp_customize->add_control( 'csstidy_code', array(
		'label' => __( 'Custom CSS', 'custom-csstidy' ),
		'description' => __( 'Input Valid CSS', 'custom-csstidy' ),
		'type' => 'textarea',
		'section' => 'custom_csstidy'
	) );
}

function csstidy_sani_cb( $input ){
	// load the needed file to validate/sanitize CSS
	require_once ( plugin_dir_path( __FILE__ ) . '/lib/class.csstidy.php' );
	
	$csstidy = new csstidy();
	$csstidy->set_cfg( 'remove_bslash',              false );
	$csstidy->set_cfg( 'compress_colors',            false );
	$csstidy->set_cfg( 'compress_font-weight',       false );
	$csstidy->set_cfg( 'optimise_shorthands',        0 );
	$csstidy->set_cfg( 'remove_last_;',              false );
	$csstidy->set_cfg( 'case_properties',            false );
	$csstidy->set_cfg( 'discard_invalid_properties', true );
	$csstidy->set_cfg( 'css_level',                  'CSS3.0' );
	$csstidy->set_cfg( 'preserve_css',               true );
	$csstidy->set_cfg( 'template', plugin_dir_path( __FILE__ ) . '/lib/wordpress-standard.tpl' );
	
	// Sanitize of random things because we always, always trust user input, right?
	$css = preg_replace( '/\\\\([0-9a-fA-F]{4})/', '\\\\\\\\$1', $input );
	// Some people put weird stuff in their CSS, KSES tends to be greedy
	$css = str_replace( '<=', '&lt;=', $css );
	// Why KSES instead of strip_tags?  Who knows?
	$css = wp_kses_split( $css, array(), array() );
	$css = str_replace( '&gt;', '>', $css ); // kses replaces lone '>' with &gt;
	// Why both KSES and strip_tags?  Because we just added some '>'.
	$css = strip_tags( $css );
	
	// Finally we parse the CSS
	$csstidy->parse( $css );
	
	// This is the part that returns the printed CSS to save to the database
	$valid = $csstidy->print->plain();
	
	return $valid;
}

add_action( 'wp_head', 'csstidy_css_output' );
function csstidy_css_output(){
	$css = get_theme_mod( 'csstidy_code' );
	
echo "
<style id='csstidy-css'>
{$css} 
</style>
";
}
