<?php
/*
Plugin Name: WPLMS MyCred ADDON
Plugin URI: http://www.vibethemes.com/
Description: This plugin integrates MyCred points system with WPLMS theme [4.x compatible]
Version: 4.2
Author: Mr.Vibe
Author URI: http://www.vibethemes.com/
Text Domain: wplms-mycred
Domain Path: /languages/
*/
if ( !defined( 'ABSPATH' ) ) exit;
/*  Copyright 2013 VibeThemes  (email: vibethemes@gmail.com)

    This is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This plugin is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WPLMS MYcred Addon.  If not, see <http://www.gnu.org/licenses/>.
*/
      


if( !defined('VIBE_PLUGIN_URL')){
    define('VIBE_PLUGIN_URL',plugins_url());
}
if( !defined('WPLMS_MYCRED_PLUGIN_VERSION')){
    define('WPLMS_MYCRED_PLUGIN_VERSION','4.2');
}


if(in_array('mycred/mycred.php', apply_filters('active_plugins', get_option('active_plugins'))) || (function_exists('is_plugin_active') && is_plugin_active( 'mycred/mycred.php'))){
    /*====== BEGIN INCLUDING FILES ======*/

    include_once('includes/class.init.php');
    include_once('includes/myCRED-addon-wplms.php');
    include_once('includes/class.points-awarding-system.php');
    include_once('includes/dashboard_widget.php');

    add_action('plugins_loaded',function(){
        include_once('vibebp/includes/class.init.php');

        include_once('vibebp/includes/class.api.php');
    });

    /*====== END INCLUDING FILES ======*/
}else{

    add_action( 'admin_notices', 'wplms_mycred_install_admin_notice' );
}

add_action('plugins_loaded','wplms_mycred_translations');
function wplms_mycred_translations(){
    $locale = apply_filters("plugin_locale", get_locale(), 'wplms-mycred');
    $lang_dir = dirname( __FILE__ ) . '/languages/';
    $mofile        = sprintf( '%1$s-%2$s.mo', 'wplms-mycred', $locale );
    $mofile_local  = $lang_dir . $mofile;
    $mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

    if ( file_exists( $mofile_global ) ) {
        load_textdomain( 'wplms-mycred', $mofile_global );
    } else {
        load_textdomain( 'wplms-mycred', $mofile_local );
    }  
}

function wplms_mycred_install_admin_notice(){
    $class = "error";
    $message = _x('MyCred plugin missing. Please install MyCred plugin.','Install mycred error message','wplms-mycred');
    echo"<div class=\"$class\"> <p>$message</p></div>"; 
}