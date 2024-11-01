<?php
/*
Plugin Name: WPDB Cache Money
Plugin URI: http://findsubstance.com
Description: Caches database results as serialized PHP data. Requires PHP 5.
Version: 0.35
Author: Eric Eaglstun
Author URI: http://ericeaglstun.com 

DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
				   Version 2, December 2004

Copyright (C) 2004 Sam Hocevar
14 rue de Plaisance, 75014 Paris, France
Everyone is permitted to copy and distribute verbatim or modified
copies of this license document, and changing it is allowed as long
as the name is changed.

	DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
	TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

	0. You just DO WHAT THE FUCK YOU WANT TO. 
 
*/

if( !function_exists('cache_money_activate') && function_exists('register_activation_hook') ){
	
	register_activation_hook( __FILE__, 'cache_money_activate' );
	register_deactivation_hook( __FILE__, 'cache_money_deactivate' );
	
	function cache_money_activate(){
		
		// make sure directory is writable
		if( !is_writable(WP_PLUGIN_DIR.'/wpdb-cache-money') && !chmod( WP_PLUGIN_DIR.'/wpdb-cache-money', 0755) ){
			die( 'The cache directory '.WP_PLUGIN_DIR.'/wpdb-cache-money/ is not writable - please check permissions.' );
		}
		
		// move file to wp-content
		copy( WP_PLUGIN_DIR.'/wpdb-cache-money/_db_file.php', WP_CONTENT_DIR.'/db.php' );
	}
	
	function cache_money_deactivate(){
		global $wpdb;
		$success = FALSE;
		
		// delete (move) file back to plugins directory
		if( file_exists(WP_CONTENT_DIR.'/db.php') ){
			$success = @unlink( WP_CONTENT_DIR.'/db.php' );
		}
		
		if( !$success ){
			// show a message?
		} else {
			if( method_exists($wpdb, 'clearCache') ){
				$wpdb->clearCache();
			}
		}
	}
}

// query browser
if( isset($_GET['id']) ){
	// not ready for prime time
	return;
	
	require '../../../wp-load.php';
	
	if( current_user_can('manage_options') && file_exists($_GET['id']) ){
		include($_GET['id'] );
		$res = unserialize($res);
		
		echo '<link rel="stylesheet" href="_cache_money.css" type="text/css" media="screen" />';
		
		echo '<table class="wpdb-cache-money-profile">';
		
		echo '<tr>';
		echo '<td class="code" colspan="'.count((array) $res[0]).'">';
		echo $sql;
		echo '</td>';
		echo '</tr>';
		
		echo '<tr>';
		if( count($res[0]) ){
			foreach( $res[0] as $k=>$v ){
				echo '<td class="head">';
					echo $k;
				echo '</td>';
			}
		} else {
			echo '<td class="body">';
			echo 'No results';
			echo '</td>';
		}
		echo '</tr>';
		
		foreach( $res as $k=>$v ){
			echo '<tr>';
			foreach( $v as $x ){
				echo '<td class="body">';
					echo $x;
				echo '</td>';
			}
			echo '</tr>';
		}
		
		echo '</table>';
	}
}

// end of file 
// plugins/wpdb-cache-money/_db.php