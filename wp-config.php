<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "ultramanager" );

/** Database username */
define( 'DB_USER', "root" );

/** Database password */
define( 'DB_PASSWORD', "" );

/** Database hostname */
define( 'DB_HOST', "localhost" );

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
define( 'AUTH_KEY',         'o_LVU4~q4<rNaTFLhoJxwpUOI4C`)XD/8F,Bm_M|3/-!CGl}$=HyR8),i$jxY~9R' );
define( 'SECURE_AUTH_KEY',  '5LC]N3te#=T [0`4yDw|nle@5jO~:LQjLkxmm}}[/q9ocq0Wk5}T,rN)eTl!qPCA' );
define( 'LOGGED_IN_KEY',    'b6iD6ee8md)OS.8KyE?OZaO@?Naqr!^Py6u-(+r`%nr<Ip/<Y+&;)>`kkeNSjUy$' );
define( 'NONCE_KEY',        ':rpjd[zrrhQ1XAu7ICosnGL0($ZGR:az80mq8ByzB/K$Kv`a(@p?4.7#;b];}AAA' );
define( 'AUTH_SALT',        '%!d:E,VY}a|D,I;A3;4oW*+x,g~M~*?{rpnT`W4:j/Ir>hR_$&c*O@S!^z/i}V2V' );
define( 'SECURE_AUTH_SALT', '*(gljCefBo=7X%qe0n}K/x&S]n_&V$&C--^]i0i^ fy`W97Alg#CaO.>e$+|uy2o' );
define( 'LOGGED_IN_SALT',   '<I#,qRksVt@g[JpUohs27ssOW%iJ-NysqK8F[V5KOO<$$AV972 jR_e2X7l@kf~D' );
define( 'NONCE_SALT',       'w:~>h@)Jm#CZiL6%VCwGG{wo(zQbf0l0Q>gjo<%[&4Dpn84s]#dDvjsHKZdc7:Y5' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



define( 'DUPLICATOR_AUTH_KEY', '0ddd374122461520844aa88ffa7b8062' );
define( 'WP_PLUGIN_DIR', 'C:/xampp2.0/htdocs/ultramanager/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', 'C:/xampp2.0/htdocs/ultramanager/wp-content/mu-plugins' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname(__FILE__) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
