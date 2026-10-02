<?php

namespace Palasthotel\EmojiGuard;

/**
 * Plugin Name: Emoji Guard
 * Plugin URI: https://github.com/palasthotel/emoji-guard
 * Description: Checks data integrity of emojis
 * Version: 1.0.0
 * Author: Palasthotel (in Person: Edward Bock, Enno Welbers)
 * Author URI: https://palasthotel.de
 * Text Domain: emoji-guard
 * Domain Path: /languages
 */



const DOMAIN = "emoji-guard";

load_plugin_textdomain(
	DOMAIN,
	false,
	plugin_basename( dirname( __FILE__ ) ) . '/languages'
);

const OPTION_EMOJI_VALIDATION_KEY = "_emoji_guard_validation";
const OPTION_EMOJI_VALUE          = "🛡🦸‍♂️";

const FILTER_EMOJI_VALUE = "emoji_guard_value";

function admin_notices() {
	if(
	        isset($_POST["emoji-guard-overwrite"]) && $_POST["emoji-guard-overwrite"] === "true"
        &&
	        check_admin_referer( 'overwrite-emoji-guard-value' )
    ){
	    $value = build_emoji_value();
		set_emoji_option_value( $value );
		?>
        <div class="notice notice-success">
            <h2>Emoji Guard</h2>
            <p>
				<?php _e( "Integrity value was updated.", DOMAIN ); ?>
                <br/>
                <code><?= $value; ?></code>
            </p>
        </div>
		<?php
	} else if ( ! check_emoji_integrity() ) {

		$shouldBe    = build_emoji_value();
		$optionValue = get_emoji_option_value();
		?>
		<div class="notice notice-warning">
			<h2>Emoji Guard</h2>
			<p>
				<?php _e( "Integrity check failed.", DOMAIN ); ?>
				<br/>
				<code><?= $shouldBe; ?></code> !== <code><?= $optionValue; ?></code>
			</p>
            <?php
            if(current_user_can("manage_options")):
            ?>
			<p>
				<form method="post">
                    <?php
                    wp_nonce_field( 'overwrite-emoji-guard-value' );
                    ?>
					<input type="hidden" name="emoji-guard-overwrite" value="true" />
					<button class="button button-primary">
						<?php _e('Got the problem! Update validation option with valid emojis.', DOMAIN); ?>
					</button>
				</form>
			</p>
            <?php endif; ?>
		</div>
		<?php
	}
}

add_action( 'admin_page_access_denied', __NAMESPACE__ . '\admin_notices' );
function admin_init() {
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