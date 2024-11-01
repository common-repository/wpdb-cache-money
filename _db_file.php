<?php
/* 
WPDB Cache Money
http://wordpress.org/extend/plugins/wpdb-cache-money/
Version: 0.53

$_GET options for logged in admin 
   ?cache-clear 		- clear the entire cache on page request
   ?cache-profile		- see a summary of all queries in sequential order
   ?cache-profile=sort	- see a summary of all queries ordered by length to execute
*/

// load base database class so we can extend it
$path = ABSPATH.WPINC.'/wp-db.php';
require_once( $path );

class cacheMoney extends wpdb{
	// in seconds, the oldest data to use.  default is 1800 (30 minutes ago)
	private $threshold = 1800;
	
	// set to TRUE if you want to use the profiler() method to debug queries. 
	// default to FALSE because of extra overhead.
	private $useProfiler = FALSE;
	
	/*
	*	the above two varaibles should be the only three variables you need to change.
	*/
	
	// timestamp of last db update for this user
	private $cacheMoneyStamp = 0;	
	
	// file name for serialized data
	private $filename = '';	
	private $filestamp = 0;
	private $full_filepath = '';
	
	// this will get set to true or false once current_user_can() is available
	private $is_admin = NULL;
	
	// this isnt defined in wp-db.php
	public $query = '';	
	
	// the subdirectory for the serialized file
	private $subdir = '0';
	
	// current timestamp
	private $now = 0;
	
	// for stats()
	private $num_cached_queries = 0;
	
	// directory where serialized data is stored
	private $path = '';  
	
	// timestamp (microtime) right before query is performed.  used by profiler()
	private $start = 0;
	
	// trigger this to use actual sql afterwards for rest of request
	private $forceQuery = FALSE;			
	
	// real or cache.  used by profiler()
	private $queryType = 'real';
	private $last_query_type = 'real';
	
	// array of queries and length of time to perform. used by profiler()
	private $profile = array( 'real' => array(),
							  'cache' => array() );
							  
	private $stats = array( 'c' => '',		// number of cached queries
							'm' => '',		// memory used
							'q' => '',		// number of real database queries
							't' => '' );	// time for page to render
	
	/*
	*
	*
	*/			  
	public function __construct( $dbuser, $dbpassword, $dbname, $dbhost ){
		parent::__construct( $dbuser, $dbpassword, $dbname, $dbhost );
		
		if( !defined('WP_PLUGIN_DIR') )
			define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins' );
       
		$this->now = time();
		$this->path = WP_PLUGIN_DIR.'/wpdb-cache-money/cache_files/';
		
		// check to make sure cache directory exists and create it
		if( !is_dir($this->path) )
			mkdir( $this->path ); 
		
		// make sure htaccess is in place to protect cache files
		if( !file_exists(WP_PLUGIN_DIR.'/wpdb-cache-money/cache_files/.htaccess') )
			copy( WP_PLUGIN_DIR.'/wpdb-cache-money/_htaccess.php', 
				  WP_PLUGIN_DIR.'/wpdb-cache-money/cache_files/.htaccess' );
		
		// we will store the timestamp of last insert/delete etc in a session.
		if( !session_id() )
			session_start();
		
		if( !isset($_SESSION['cacheMoneyStamp']) ){
			// set in the past for first time on site
			$this->setSessionStamp( $this->now - $this->threshold ); 
		} else if( $_SESSION['cacheMoneyStamp'] < ($this->now - $this->threshold) ){
			// last insert was before the threshold
			$this->setSessionStamp( $this->now - $this->threshold );
		} else {
			// last insert was after the threshold
		}
		
		// $_SESSION['cacheMoneyStamp'] is the unix time stamp of the oldest cache we will use
		$this->cacheMoneyStamp = $_SESSION['cacheMoneyStamp'];
		
		// allow logged in admins to view cache info or clear cache with query strings
		if( isset($_GET['cache-profile']) ){
			$this->useProfiler = TRUE;
			register_shutdown_function( array($this,'forceProfiler'), $_GET['cache-profile'] == 'sort' );
		}
		
		// allow logged in admins to clear the entire cache
		if( isset($_GET['cache-clear']) ){
			register_shutdown_function( array($this,'forceClear'), $_GET['cache-clear'] );
		}
		
		// force cache clear when a user comments
		//add_action( 'wp_insert_comment', array($this,'setSessionStamp'), 0 );
	}
	 
	/*
	*
	*	@param string the SQL to run
	*	@param bool set to TRUE to continue using cached queries after a write
	*	@return
	*/
	public function query( $query, $timerBypass = FALSE ){
		
		$this->start = microtime( TRUE );
		$query = trim( $query );
		
		if( preg_match("/^\\s*(alter table|create|insert|delete|update|replace|set names) /i", $query) ){
			// can't cache a write!
			if( !$timerBypass ){
				$this->setSessionStamp( time() );
				
				// this was shorting out too early - when updating option _transient_doing_cron
				//$this->forceQuery = TRUE;
				//ddbug($query);
			}
			
			parent::query( $query );
			$this->filename = null;
			$this->queryType = 'real';
			$this->profileSave( $query );
			return;
		} /*else if( preg_match("/WHERE autoload/i", $query) ){
			// dont cache options. screws up cron, etc.
			$this->filename = null;
			$this->queryType = 'real';
			$this->profileSave( $query );
			parent::query( $query );
			return;
		} */ else if( preg_match("/calc_found_rows/i", $query) ){
			// if the query contains SQL_CALC_FOUND_ROWS, tack on a comment with a unique hash
			// the same hash will be added to the FOUND_ROWS query
			$query .= " /* ".md5($query)." */";
		} else if( preg_match("/found_rows/i", $query) ){
			// tack on unique query information to force FOUND_ROWS queries.
			$query .= " /* ".md5( WPDB_Cache_Money::StripComments($this->last_query) )." */";
		} /*else if( preg_match("/_comments/i", $query) && !preg_match("/COALESCE/i", $query) ){
			// temp comments debugging
			
			$this->full_filepath = $this->path.$this->subdir.'/'.$this->filename;
			$stamp = filemtime( $this->full_filepath );
			
			dbug($this->filename); 
			
			dbug( $query );
			
			dbug( $this->cacheMoneyStamp, '$this->cacheMoneyStamp' );
			dbug( $stamp, '$stamp' );
			
			dbug( $this->cacheMoneyStamp < $stamp, '$this->cacheMoneyStamp < $stamp' );
			dbug( $this->is_admin !== TRUE, '$this->is_admin !== TRUE' );
			//die();
		} */
		
		$this->filename = md5( $query ).'.php';
		$this->subdir = substr( $this->filename, 0, 1 );
		
		// if we have triggered a write or for any reason want to force real queries
		// then go ahead and do that and skip the rest of the logic
		if( $this->forceQuery )
			return $this->realQueryAndCommit( $query );
		
		// make subdirectory in cache_files
		if( !is_dir($this->path.$this->subdir) )
			mkdir($this->path.$this->subdir);
		
		$this->full_filepath = $this->path.$this->subdir.'/'.$this->filename;
		
		if( file_exists($this->full_filepath) ){
			// when the file was created
			$stamp = filemtime( $this->full_filepath );
			
			if( !is_bool($this->is_admin) ){
				$this->is_admin = isset( $_COOKIE['wordpress_logged_in_'] ) && trim( $_COOKIE['wordpress_logged_in_'] );
			}
			
			// use the cache file if if its date is set past now,
			// but dont use the cache file if the user is an admin
			if( $this->cacheMoneyStamp < $stamp && $this->is_admin !== TRUE ){
				// including the file gives us $res, $sql, and $gen
				include $this->full_filepath;
				$res = @unserialize( $res );
				
				if( $res !== FALSE ){
					
					$this->last_result = $res;
					$this->last_query = $query;
					$this->last_query_type = 'cache';
					$this->num_cached_queries ++;
					$this->filestamp = $stamp;
					$this->queryType = 'cache';
					$this->profileSave( $query );
					
					unset($res);
					unset($sql);
					unset($gen);
					
					return TRUE;
				} else {
					// unserialization failed
					unlink( $this->full_filepath );
				}
			} else {
				// file exists but is too old
				//echo( '<br/>'.$this->filename.' exists but is too old - file stamp : '.$stamp.' - cache money stamp :'.$this->cacheMoneyStamp.'<br/>' );
			}
		}
		
		// didn't find a cached result.  do the query and save it.
		// real found_rows and cached calc_found_rows - must force the calc_found again
		if( preg_match("/ found_rows/i", $query) && $this->last_query_type == 'cache' ){
			parent::query( $this->last_query );
		}
		
		$this->realQueryAndCommit( $query );
	}
	
	/*
	*
	*	@param
	*	@param
	*	@return string
	*/
	private function heredoc( $string, $varname ){
		$heredoc = 'WPDBCACHEMONEY';
		
		// php may try to parse varaibles.  since NOWDOC is 5.3 > ...
		$string = str_replace( '$', '\$', $string );
		
		// if the delimiter is in the string, add random characters until it is unique
		while( strpos($string, $heredoc) || strlen($heredoc) < 3 ){
			$heredoc .= chr( rand(65,90) );
		}
		
		$line = "\$$varname = <<<$heredoc\n$string\n$heredoc;\n\n";
		
		return $line;
	}
	
	/*
	*	attached to register_shutdown_function() when $_GET['cache-clear'] is set by admin
	*	deletes all saved cache files
	*	@param int
	*/
	public function forceClear( $sec = 0 ){
		if( !current_user_can('manage_options') )
			return;
		
		WPDB_Cache_Money::ClearCache( $sec );
	}
	
	/*
	*	generate stats for page load
	*	@param bool show stats inside html comment
	*	@return string
	*/
	public function stats( $show = FALSE ){
		$this->generateStats();
		
		extract( $this->stats );
		
		$r = "q: $q | c: $c | m: $m | t: $t";
		
		if( !$show ){
			$r = "<!-- $r -->";
		}
		
		return $r;
	}
	
	/*
	*
	*	@param bool
	*/
	public function forceProfiler( $sort = TRUE ){
		if( !current_user_can('manage_options') )
			return;
		
		echo $this->profiler( $sort );
	}
	
	/*
	*
	*	@param bool
	*/
	public function profiler( $sort = TRUE ){
		if( !$this->useProfiler ){
			return FALSE;
		}
		
		$return = '<link rel="stylesheet" href="/wp-content/plugins/wpdb-cache-money/_cache_money.css" type="text/css" media="screen" />';
		
		if( $sort ){
			$cached = WPDB_Cache_Money::sort( $this->profile['cache'], 'length');
			$real = WPDB_Cache_Money::sort( $this->profile['real'], 'length');
			$order = "length of query";
		} else {
			$cached = $this->profile['cache'];
			$real = $this->profile['real'];
			$order = "order of query";
		}
		
		$this->generateStats();
		extract( $this->stats );
		
		$return .= '<div class="wpdb-cache-money-profile">';
		$return .= '<h2>Cached queries sorted by '.$order.' <span>('.$c.')</span></h2>';
		$return .= $this->profilePrint($cached);
		$return .= '<h2>Real queries sorted by '.$order.' <span>('.$q.')</span></h2>';
		$return .= $this->profilePrint($real);
		$return .= '<h2>Site Profile</h2>';
		$return .= "Queries: $q | Cached Queries: $c | Memory Usage: $m MB | Time: $t sec";
		$return .= '</div>';
		
		return $return;
	}
	
	/*
	*
	*/
	public function setSessionStamp( $stamp = 0 ){
		if( !$stamp )
			$stamp = time() + $this->threshold + 1;
		
		$this->cacheMoneyStamp = $_SESSION['cacheMoneyStamp'] = $stamp;
	}
	
	/*
	*
	*	
	*/
	private function generateStats(){
		$m = round( memory_get_peak_usage() / 1048576 , 3 );
		$q = $this->num_queries;
		$c = $this->num_cached_queries;
		$t = timer_stop();
		
		foreach( $this->stats as $k => $v ){
			if( isset($this->stats[$k]) ){
				$this->stats[$k] = $$k;
			}
		}
	}
	
	/*
	*
	*	@param array
	*/
	private function profilePrint( $array ){
		$return = '';
		
		foreach( $array as $k => $v ){
			$return .= '<span class="code">'.$v['query'].'</span>';
			$return .= '<span><span>performed in:</span>'. (float) round( $v['length'], 4 ).' seconds';
			
			// don't show for writes
			if( $v['filename'] ){
				$return .= '<br/><span>filename:</span>';
				if( FALSE && current_user_can('manage_options') ){
					// not ready for prime time
					$return .= '<a href="'.WP_PLUGIN_URL.'/wpdb-cache-money/_db.php?id='.$v['filename'].'" target="_blank">'.$v['filename'].'</a>';
				} else {
					$return .= $v['filename'];
				}
				$return .= '<br/><span>rows:</span>'.$v['rows'];
			}
			
			if( count($v['from']) ){
				$return .= '<br/><span>called from:</span>';
				foreach( $v['from'] as $from ){
					$return .= ''.$from.'<br/><span></span>';
				}
				// remove last span
				$return = substr($return, 0, -18);
			}
			
			$return .= '<br/><span>timestamp:</span>'.$v['stamp'].' '.date('h:i:s a', $v['stamp']);
			$return .= '<br/><span>cachestamp:</span>'.$v['cachestamp'].' '.date('h:i:s a', $v['stamp']);
			$return .= '<br/><span>filestamp:</span>'.$v['filestamp'].' '.date('h:i:s a', $v['stamp']);
			$return .= '</span>';
		}
		
		return $return;
	}
	
	/*
	*	save query and length information
	*	@param string
	*/
	private function profileSave( $query ){
		if( !$this->useProfiler )
			return FALSE;
		
		$end = microtime( TRUE );
		
		$x = debug_backtrace();
		
		$profile = array( 'query' => WPDB_Cache_Money::StripComments( $query, TRUE ),
						  'length' => $end - $this->start,
						  'filename' => $this->filename,
						  'rows' => count($this->last_result),
						  'stamp' => $end,
						  'cachestamp' => $_SESSION['cacheMoneyStamp'],
						  'filestamp' => $this->filestamp,
						  'from' => array() );
						  
		for( $i=2; $i<10; $i++ ){
			if( isset($x[$i]['file']) ){
				array_push( $profile['from'], $x[$i]['file'].': '.$x[$i]['line'] );
			}
		}
		
		array_push( $this->profile[$this->queryType], $profile );
	}
	
	/*
	*	perform a query against the database and write the result to a cache file
	*	@param string
	*	@return
	*/
	private function realQueryAndCommit( $query ){
		$return = parent::query( $query );
		
		$this->queryType = 'real';
		$this->last_query = $query;
		$this->last_query_type = 'real';
		
		$res = serialize( $this->last_result );
		
		$file = "<?php\n\n";
		$file .= $this->heredoc( $res, 'res' );
		$file .= $this->heredoc( $query, 'sql' );
		$file .= "\$gen = '".date( "D M jS, Y h:i:s a", $this->now )."';\n\n";
		
		$this->full_filepath = $this->path.$this->subdir.'/'.$this->filename;
		file_put_contents( $this->full_filepath, $file ); 
		
		// set the modification time of the file to when it expires
		// default is 30 minutes in the future 
		touch( $this->full_filepath, ($this->now + $this->threshold) );
		
		// save the query information if we are using the profiler
		$this->profileSave( $query );
		
		return $return;
	}
}

// overwrite existing $wpdb with new, extended version
$wpdb = new cacheMoney( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

if( file_exists(WP_PLUGIN_DIR.'/wpdb-cache-money/wpdb-cache-money.php') )
	require_once WP_PLUGIN_DIR.'/wpdb-cache-money/wpdb-cache-money.php';

// end of file 
// wp_content/db.php
// plugins/wpdb-cache-money/_db_file.php