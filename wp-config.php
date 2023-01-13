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
define( 'DB_NAME', 'market' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

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
define( 'AUTH_KEY',         '= YQ2s:)@JAX~@X$$oTUKko#kwO9X8:6PoaXwp$RWW3`_b`}s&wx#,a9qmhEy;Fg' );
define( 'SECURE_AUTH_KEY',  'SpYd~1w^=,Zr fBH,PX8cGW]dda-Ca{P<sv9`*]7&Ooeab+*fKuK^#j^-:QuY}B6' );
define( 'LOGGED_IN_KEY',    '|G_$J{Q+>=w+m-B&Q)^aB?i fX%y|v;?QSo1,Ww(SKqw@a|gY;QyjeT1Mk}m1IE5' );
define( 'NONCE_KEY',        'I5@2l1rQNu@}K3+S:a/.`X)r&p!mG:(]g)[>UVxHEi$CF>Nz6#FmsJV;*sw)Ex?l' );
define( 'AUTH_SALT',        'PTgZDiOa%T3@[3b0Oqv4b3@zjXuWfR}SN0ZT|{y5>a7MNQ<-{o{V{ wJQtlZ= b8' );
define( 'SECURE_AUTH_SALT', ': I9fr$7[?}sTU>IBSpSN=sG)@L<[vyoYgz0*48e4vl#~NB46A;Nt2wEIBD3q^+V' );
define( 'LOGGED_IN_SALT',   'q{>>,=;;F/qH>3FJUGvapff?8oA3[|tMc2@?@$)5kO+]M`@5U:=>{ `o=;-b5O4E' );
define( 'NONCE_SALT',       'jF%%qhV%O*/<csW>k3!x60}uhnSpOC9U[Dx?,vbH!=Y37UcRO`)-]$F8s/;2kyrl' );

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



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
