<?php
/**
 * Plugin Name:       Emoji Guard - DEV
 * Description:       Dev inc file
 * Version:           X.X.X
 * Requires at least: X.X
 * Author:            Palasthotel
 * Author URI:        https://palasthotel.de
 * Domain Path:       /public/languages
 */

include dirname( __FILE__ ) . "/public/plugin.php";

// public/plugin.php registers its activation hook for its own file, which is not the
// one WordPress activates while the repository itself is the plugin.
register_activation_hook( __FILE__, 'Palasthotel\EmojiGuard\on_activation' );
