=== WPDB Cache Money ===
Contributors: postpostmodern
Donate link: http://www.heifer.org/
Tags: db, database, cache, cash, money, bling
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: trunk

Database result serialization.

== Description ==
Cache Money stores all database result sets on the filesystem as serialized data, by using the oft-neglectged wp-content/db.php to extend the normal $wpdb class.  Result sets will be updated as needed on a user by user basis when they do actions that perform update queries.

Please note that this is not a full scale page cache, like WP-Cache or WP Super Cache, although this will work just fine in conjunction.  Cache Money is meant for sites with constantly changing content, where a full cache may not be suitable.

Tested with Wordpress and Wordpress MU 2.8.0 ~ 3.1 
Requires PHP 5, like all good PHP apps.

== Installation ==
1. Place entire /wpdb-cache-money/ directory to the /wp-content/plugins/ directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Bling

== Changelog ==
= .5 =
* Admin settings area added, major code cleanup, bumped min WP version to 3.0 *
= 0.35 =
* Minor code cleanup *
= 0.31 =
* Important security fix *
= 0.22 =
* Not caching options queries using autoload, was screwing up wp cron
= 0.21 =
* Added second parameter to wp->query(), to bypass reset of internal timer on inserts
= 0.176 =
* Using HEREDOC syntax for serialized data
= 0.175 =
* Improved caching with FOUND_ROWS() queries
= 0.171 =
* Fixed bug in escaped single quotes in serialized data.
= 0.166 =
* Allows a salt, used in the md5 generation of file names.
= 0.165 =
* Allows logged in administrators to clear all cache info with the variable ?cache-clear in query string
= 0.16 =
* Allows logged in administrators to view cache info with the variable ?cache-profile in query string, set to 'sort' to sort by length
= 0.15 =
* clearCache() method to empty cache files.  Called automatically on plugin deactivation.
= 0.14 =
* Improved styles in profiler().
= 0.13 =
* First pass at query browser.  This is not active - if this proves to be a bad idea, will be removed from later versions.
= 0.1 =
* Refined profiler() methods.
= 0.09 =
* Bug fixes. First pass at documentation, profiler() methods.
= 0.02 =
* Initial public release. No documentation.

== Frequently Asked Questions ==
= This doesn't do anything! =
Yes it does.  Hey, didn't you ask this question over on my other plugin too?

= I need the cache to be cleared at a different interval than 5 minutes. =
Change `$cacheMoney->threshold` to whatever number of seconds you wish.

= How can I tell if this is working? =
The directory wp-content/plugins/wpdb-cache-money/ should be full of files with names similar to `4a13fc6a615ac14cc3f58160ce9c52f3.php`.  If not, check to make sure this directory is writable by your server. `echo $wpdb->stats(TRUE)` will give you a result like `q: 9 | c: 6 | m: 9.055 t: 0.171`. `q` is the number of actual database queries, `c` is the number of cached queries, `m` is the memory usage in megabytes, and `t` is the time in seconds to render the page. `echo $wpdb->stats()` will do the same, wrapped in an html comment, for times when you need to be discrete.
There is also a profiler method - `echo $wpdb->profiler()`.  You will need to make sure `$cacheMoney->useProfiler` is set to `TRUE` to use this.  The default for the profiler is to sort the queries by length of time descending. `$wpdb->profiler(FALSE)` will show the queries in the order performed.

== Screenshots ==
1. I gots to get paid, son `/trunk/screenshot-1.png` 
photo by Andrew Magill http://www.flickr.com/photos/amagill/362201147/
