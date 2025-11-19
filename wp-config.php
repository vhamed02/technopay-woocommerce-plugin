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
define( 'DB_NAME', 'technopay' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

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
define( 'AUTH_KEY',         '*-qAA`[/7y-88 :=4MhIweA/KG *k.j~q/,i7Wy#t}c<O{XTw ]c*%}qu;9[%PGw' );
define( 'SECURE_AUTH_KEY',  'z?Siu2&%AU_luDIMLS*}cue>.@u51ST>-&q:5aZPm:V3IX}7$vAvuNflB`c`&!v.' );
define( 'LOGGED_IN_KEY',    '{Bs!.=sEYj:oWR*i:LM.CtVSi@jA|k7<wIC_Y@v+oNAs0:[?lMejaFz5}Y e^RZf' );
define( 'NONCE_KEY',        'ceb_Ry$B;S}8u`ug9jq*B?r7nI:HC@:Sqfz@&F$CcpmD*c{s(:VjeD0v.:?RPNC/' );
define( 'AUTH_SALT',        '91v<P/) i6J.^`0D4):Lm8a4C+Kc!9v%X[Sp:y740d{v@>H6bBo,#E16I8tgu[zY' );
define( 'SECURE_AUTH_SALT', '|2ezvg}=xa$kn *F]A=e0.AxFSj|{ZV[uLqG!xm]5toWOA)[Z!D1y(RccifPP,Fa' );
define( 'LOGGED_IN_SALT',   'AOqyrKj @Pic=)I-hOOjE(&/!h<_KMGvgsf!;h2Pf(;elSk,g<v:ePq#Qvb!Pjlk' );
define( 'NONCE_SALT',       '*[,RVgz-tV[%r#J$f6:rcwQY|8+kL}^WDu,eG9BQiW=f.Pt5nUll U[`de}g_/V ' );

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

define( 'TECHNOPAY_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
