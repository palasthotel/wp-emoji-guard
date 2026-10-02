<?php

namespace Palasthotel\EmojiGuard;

/**
 * Plugin Name: Emoji Guard
 * Plugin URI: https://github.com/palasthotel/wp-emoji-guard
 * Description: Checks data integrity of emojis
 * Version: 1.0.1
 * Author: Palasthotel <webmaster@palasthotel.de>
 * Author URI: https://palasthotel.de
 * Text Domain: emoji-guard
 * Domain Path: /languages
 * Requires at least: 4.0
 * Tested up to: 7.1.2
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

const DOMAIN = "emoji-guard";

const OPTION_EMOJI_VALIDATION_KEY = "_emoji_guard_validation";
const OPTION_EMOJI_VALUE          = "🛡🦸‍♂️";

const FILTER_EMOJI_VALUE = "emoji_guard_value";

/**
 * Only administrators can act on a failed check, so only they see it.
 */
const CAPABILITY = "manage_options";

const NONCE_ACTION = "overwrite-emoji-guard-value";

function load_textdomain() {
	load_plugin_textdomain(
		'emoji-guard',
		false,
		plugin_basename( dirname( __FILE__ ) ) . '/languages'
	);
}

add_action( 'init', __NAMESPACE__ . '\load_textdomain' );

/**
 * The "update validation option" button. Handled before any output, so a refused
 * request gets a real 403, and answered with a redirect, so reloading the page does
 * not post it again.
 */
function handle_overwrite() {
	if ( ! isset( $_POST["emoji-guard-overwrite"] ) || "true" !== $_POST["emoji-guard-overwrite"] ) {
		return;
	}
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), 403 );
	}
	check_admin_referer( NONCE_ACTION );

	set_emoji_option_value( build_emoji_value() );

	$referer = wp_get_referer();
	wp_safe_redirect( add_query_arg( 'emoji-guard', 'updated', $referer ? $referer : admin_url() ) );
	exit;
}

function admin_notices() {
	if ( ! current_user_can( CAPABILITY ) ) {
		return;
	}

	if ( isset( $_GET['emoji-guard'] ) && 'updated' === $_GET['emoji-guard'] && check_emoji_integrity() ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong>Emoji Guard:</strong>
				<?php esc_html_e( 'Integrity value was updated.', 'emoji-guard' ); ?>
				<code><?php echo esc_html( build_emoji_value() ); ?></code>
			</p>
		</div>
		<?php
		return;
	}

	if ( check_emoji_integrity() ) {
		return;
	}

	$shouldBe    = build_emoji_value();
	$optionValue = get_emoji_option_value();
	?>
	<div class="notice notice-warning">
		<p>
			<strong>Emoji Guard:</strong>
			<?php esc_html_e( 'Integrity check failed.', 'emoji-guard' ); ?>
		</p>
		<p>
			<?php
			if ( false === $optionValue ) {
				printf(
					/* translators: %s: the expected emojis */
					esc_html__( 'Expected %s, but the stored value is missing or cannot be read - a migration usually damaged it.', 'emoji-guard' ),
					'<code>' . esc_html( $shouldBe ) . '</code>'
				);
			} else {
				printf(
					/* translators: 1: the expected emojis, 2: the emojis found in the database */
					esc_html__( 'Expected %1$s, found %2$s.', 'emoji-guard' ),
					'<code>' . esc_html( $shouldBe ) . '</code>',
					'<code>' . esc_html( $optionValue ) . '</code>'
				);
			}
			?>
		</p>
		<form method="post">
			<?php wp_nonce_field( NONCE_ACTION ); ?>
			<input type="hidden" name="emoji-guard-overwrite" value="true" />
			<p>
				<button class="button button-primary">
					<?php esc_html_e( 'Got the problem! Update validation option with valid emojis.', 'emoji-guard' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_page_access_denied', __NAMESPACE__ . '\admin_notices' );
function admin_init() {
	handle_overwrite();
	add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
}

add_action( 'admin_init', __NAMESPACE__ . '\admin_init' );

/**
 * does the emoji db value pass the integrity check?
 * @return bool
 */
function check_emoji_integrity() {
	try {
		$shouldBe    = build_emoji_value();
		$optionValue = get_emoji_option_value();

		return $shouldBe === $optionValue;
	} catch ( \Exception $exception ) {
		return false;
	}
}

/**
 * save emoji string in options
 *
 * @param string $emoji_string
 */
function set_emoji_option_value( $emoji_string ) {
	update_option( OPTION_EMOJI_VALIDATION_KEY, [ $emoji_string ], false );
}

/**
 * get emoji string from options
 * @return false|string
 */
function get_emoji_option_value() {
	$arr = get_option( OPTION_EMOJI_VALIDATION_KEY );
	if ( ! is_array( $arr ) || count( $arr ) != 1 ) {
		return false;
	}

	return $arr[0];
}

/**
 * build emoji string for validation
 * @return string
 */
function build_emoji_value() {
	return apply_filters( FILTER_EMOJI_VALUE, OPTION_EMOJI_VALUE, OPTION_EMOJI_VALUE );
}

/**
 * init components on activation
 */
function on_activation() {
	set_emoji_option_value( build_emoji_value() );
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\on_activation' );
