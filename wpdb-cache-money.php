<?php
/*
Plugin Name: WPDB Cache Money
Plugin URI: http://findsubstance.com
Description: Caches database results as serialized PHP data. Requires PHP 5.
Version: 0.53
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
class WPDB_Cache_Money{

	protected static $wpdb;					// reference to global $wpdb;
	protected static $plugin_dir = '';		// absolute path of cache money folder, no trailing slash
	
	/*
	*
	*/
	public static function setup(){
		global $wpdb;
		self::$wpdb = &$wpdb;
		
		self::$plugin_dir = dirname( __FILE__ );
		
		// admin actions below
		if( !is_admin() )
			return;
		
		add_action( 'admin_menu', 'WPDB_Cache_Money::AdminMenu' );
		
		register_activation_hook( __FILE__, 'WPDB_Cache_Money::Activate' );
		register_deactivation_hook( __FILE__, 'WPDB_Cache_Money::Deactivate' );
	}
	
	/*
	*	called on `register_activation_hook` to check permissions on plugin directory
	*	and copy db file to wp-content/db.php
	*/
	public static function Activate(){
		// make sure directory is writable
		if( !is_writable(self::$plugin_dir) && !chmod(self::$plugin_dir, 0755) ){
			// @TODO better error message
			echo ( 'The cache directory <pre>'.self::$plugin_dir.'</pre> is not writable - please check permissions.' );
			return;
		}
		
		// move file to wp-content
		copy( WP_PLUGIN_DIR.'/wpdb-cache-money/_db_file.php', WP_CONTENT_DIR.'/db.php' );
		
		self::ClearCache();
	}
	
	/*
	*	called on `register_deactivation_hook` to remove wp-content/db.php
	*	and remove cache files
	*/
	public static function Deactivate(){
		$success = FALSE;
		
		// delete (move) file back to plugins directory
		if( file_exists(WP_CONTENT_DIR.'/db.php') ){
			$success = @unlink( WP_CONTENT_DIR.'/db.php' );
		}
		
		if( !$success ){
			// show a message?
		} else {
			self::ClearCache();
		}
	}
	
	/*
	*	called on `admin_menu` action to register menu
	*/
	public static function AdminMenu(){
		add_options_page( 'Cache Money Settings', 'Cache Money', 'manage_options', 
						  'cache_money', 'WPDB_Cache_Money::OptionsMenu' );
	}
	
	/*
	*	clear all cache files in the plugin dir
	*	@param int number of seconds in the past to allow cache files to stay
	*	
	*/
	public function ClearCache( $sec = 0 ){
		$sec = (int) $sec;
		$t = time() - $sec;
		
		$path = self::$plugin_dir.'/cache_files/';
		$dh = opendir( $path );
		
		while( FALSE !== ($obj = readdir($dh)) ){ 
			if( strpos($obj, '.') === 0 || strpos($obj, '_') === 0 || filectime($path.$obj) > $t ){
				continue;
			}
			
			if( is_dir($path.$obj) ){
				// delete directory contents
				$dh2 = opendir( $path.$obj );
				while( FALSE !== ($obj2 = readdir($dh2)) ){ 
					if( strpos($obj2, '.') === 0 || strpos($obj2, '_') === 0 || filectime($path.$obj.'/'.$obj2) > $t ){
						continue;
					}
					unlink( $path.$obj.'/'.$obj2 );
				}
			} else {
				// delete single file
				unlink( $path.$obj );
			}
		}
	}
	
	/*
	*	callback for `add_options_page` to render admin ui
	*/
	public static function OptionsMenu(){
		if( isset($_POST['clear-cache']) )
			self::ClearCache();
	
		self::Render( 'admin-settings' );
	}
	
	/*
	*	simple templating
	*	@param filename, no extension
	*	@param array
	*/
	public static function Render( $filename, $vars = array() ){
		extract( (array) $vars );
		
		include self::$plugin_dir.'/'.$filename.'.php';
	}
	
	/*
	*	sort a multidimensional array by key
	*	used in $wpdb->profiler()
	*	taken from http://www.php.net/manual/en/function.sort.php#75410 - alishahnovin@hotmail.com
	*	@param array
	*	@param
	*	@return array
	*/
	public static function Sort( $array, $id ){
        $temp_array = array();
	    while( count($array) >0 ){
	        $lowest_id = 0;
	        $index = 0;
	        foreach( $array as $item ){
	            if( isset($item[$id]) && $array[$lowest_id][$id]){
	                if( $item[$id]<$array[$lowest_id][$id] ){
	                    $lowest_id = $index;
	                }
	            }
	            $index ++;
	        }
	        $temp_array[] = $array[$lowest_id];
	        $array = array_merge( array_slice($array, 0,$lowest_id), array_slice($array, $lowest_id+1) );
	    }
	    
	    $temp_array = array_reverse($temp_array);
	   
		return $temp_array;
	}
	
	/*
	*	strip any comments (multiline style) from the query 
	*	@param string the sql query
	*	@param bool TRUE to format comment for html output
	*				FALSE to remove the comment
	*	@return string
	*/
	public static function StripComments( $query, $comment = FALSE ){
		if( $comment ){
			$query = preg_replace( '/\/\*.*?\*\//', '<em>$0</em>', $query );
		} else {
			$query = preg_replace( '/\/\*.*?\*\//', '', $query );
		}
		
		return trim( $query );
	}
} 

WPDB_Cache_Money::setup();