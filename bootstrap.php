<?php
/**
 * Plugin Name: Post Thumbnails
 * Description: Enable multiple post thumbnails for post type.
 * Version:     2.2.0
 * Author:      starise
 * Author URI:  http://stari.se
 */

/*  Copyright 2010 Chris Scott (cscott@voceconnect.com)
    Copyright 2014-2015 Andrea Brandi (info@andreabrandi.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PT_PATH', plugin_dir_path(__FILE__) );
define( 'PT_URL', plugins_url( DIRECTORY_SEPARATOR, __FILE__ ) );

class PtAutoloader
{
	const BASENAME = 'starise';
	const BASESRC = 'lib';

	/**
	 * Static loader method
	 * @param string $class
	 */
	public static function load( $class ) {
		if ( self::BASENAME == substr( $class, 0, 7 ) ) {
			$classPath = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
			require_once PT_PATH . self::BASESRC . DIRECTORY_SEPARATOR . $classPath . '.php';
		}
	}
}

/**
 * The autoload-stack could be inactive so the function will return false
 */
if ( in_array( '__autoload', (array) spl_autoload_functions() ) ) {
	spl_autoload_register( '__autoload' );
}
spl_autoload_register( [ 'PtAutoloader', 'load' ] );
