<?php

use JazzMan\WpDuplicatePost\Duplicate;

/**
 * Plugin Name:         wp-duplicate-post
 * Plugin URI:          https://github.com/Jazz-Man/wp-duplicate-post
 * Description:         Duplicate Posts, Pages and Custom Posts easily using single click. You can duplicate your pages, posts and custom post by just one click and it will save as your selected options (draft, private, public, pending).
 * Author:              Vasyl Sokolyk
 * Author URI:          https://www.linkedin.com/in/sokolyk-vasyl
 * Requires at least:   5.2
 * Requires PHP:        7.4
 * License:             MIT
 * Update URI:          https://github.com/Jazz-Man/wp-duplicate-post.
 */

if ( 
    
    function_exists('app_autoload_classes') && class_exists(Duplicate::class)) {
    app_autoload_classes([
        Duplicate::class,
    ]);
}
