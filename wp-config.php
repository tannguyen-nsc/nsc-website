<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'nsc' );

/** Database username */
define( 'DB_USER', 'dev' );

/** Database password */
define( 'DB_PASSWORD', '123456' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'v`eKGMe[@OZiml#xj1f;s+-j?Mz~puL#%(,]|IP4bUoEY*j8[cZ7gB(v<sY~&i^V' );
define( 'SECURE_AUTH_KEY',  'YGEzc?+G~wzAB~v$m,O;.}3eE;rwe|+|JRPQN_1 ^m:v-%+m)u7_4#Xd8)&7yFA=' );
define( 'LOGGED_IN_KEY',    'L6;|*>2sb?b4|5q>iK(.[H^rkyscbg spHYT5xqfnD9}Va.Ol[RTj+EUe73g^-hU' );
define( 'NONCE_KEY',        'T@ALYE3n,|?`V~`?|Yu,8?-g2r=sQ{,Wj1fs?XcT#z?>h!D iA<LR!IQEsvR@}TH' );
define( 'AUTH_SALT',        'y+<EhbX+59M ?jyd.iA+#v_:]&@tz?gKy!gX@8>4LaE!HAq{~B>@U#;Ia:|V w,|' );
define( 'SECURE_AUTH_SALT', '6v.6@r%#1x!@N{QjgGrDOpq7iA%+~QYiq2{=iQ~<e;jG Q9WCC*afd|<2%=4ZePb' );
define( 'LOGGED_IN_SALT',   ')HGJ?SWJdhe7`Ri~9GBzovjrq]EW[my2x]1};1W*0Fwi690ixo@())b)<;zTO{Ge' );
define( 'NONCE_SALT',       '7[.W?8mAP=4XnjuT]dKbEhd^Np9n|Kf<eH5Pj+Hbz4l(>17<z7{_B W/?kQFNC)~' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'nsc_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
