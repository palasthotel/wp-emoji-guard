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
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

const DOMAIN = "emoji-guard";

const OPTION_EMOJI_VALIDATION_KEY = "_emoji_guard_validation";
const OPTION_EMOJI_VALUE          = "🛡🦸‍♂️";

/**
 * Set once the reference in the content tables exists, so a missing one can be told
 * apart from one that was never written - the case of every site updating from 1.0.
 */
const OPTION_CONTENT_REFERENCE = "_emoji_guard_content_reference";

/**
 * A post nobody sees: not public, no admin screen, not exported. It carries the
 * reference value in wp_posts and, serialized, in wp_postmeta - the tables the real
 * content lives in, which a migration can damage while wp_options stays intact.
 */
const POST_TYPE = "emoji_guard";
const META_KEY  = "_emoji_guard_validation";

const FILTER_EMOJI_VALUE = "emoji_guard_value";

/**
 * Only administrators can act on a failed check, so only they see it.
 */
const CAPABILITY = "manage_options";

const NONCE_ACTION = "overwrite-emoji-guard-value";

const SITE_HEALTH_TEST = "emoji_guard";

function load_textdomain() {
	load_plugin_textdomain(
		'emoji-guard',
		false,
		plugin_basename( dirname( __FILE__ ) ) . '/languages'
	);
}

add_action( 'init', __NAMESPACE__ . '\load_textdomain' );

function register_post_type() {
	if ( post_type_exists( POST_TYPE ) ) {
		return;
	}
	\register_post_type( POST_TYPE, array(
		'label'               => 'Emoji Guard',
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'show_in_nav_menus'   => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'query_var'           => false,
		'rewrite'             => false,
		'can_export'          => false,
		'delete_with_user'    => false,
		'supports'            => array(),
	) );
}

add_action( 'init', __NAMESPACE__ . '\register_post_type' );

// -----------------------------------------------------------------------------
// the checks
// -----------------------------------------------------------------------------

/**
 * build emoji string for validation
 * @return string
 */
function build_emoji_value() {
	return apply_filters( FILTER_EMOJI_VALUE, OPTION_EMOJI_VALUE, OPTION_EMOJI_VALUE );
}

/**
 * The reference value in each table, read from the database itself: a persistent
 * object cache would otherwise keep reporting the values it cached before the
 * database was replaced.
 *
 * @return array[] one entry per place: table, found (string|false), ok (bool)
 */
function get_checks() {
	global $wpdb;
	$expected = build_emoji_value();
	$checks   = array();

	$checks[] = array(
		'table' => $wpdb->options,
		'found' => unwrap( $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
			OPTION_EMOJI_VALIDATION_KEY
		) ) ),
	);

	if ( get_option( OPTION_CONTENT_REFERENCE ) ) {
		$post = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_content FROM $wpdb->posts WHERE post_type = %s ORDER BY ID LIMIT 1",
			POST_TYPE
		) );
		$checks[] = array(
			'table' => $wpdb->posts,
			'found' => $post ? $post->post_content : false,
		);
		$checks[] = array(
			'table' => $wpdb->postmeta,
			'found' => $post ? unwrap( $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post->ID,
				META_KEY
			) ) ) : false,
		);
	}

	foreach ( $checks as $i => $check ) {
		$checks[ $i ]['ok'] = ( $expected === $check['found'] );
	}

	return $checks;
}

/**
 * The value is stored as a serialized one-element array, so a search and replace that
 * changes its length without fixing the serialization makes it unreadable - which is
 * one of the things the check is there to notice.
 *
 * @param string|null $stored
 *
 * @return string|false
 */
function unwrap( $stored ) {
	if ( null === $stored ) {
		return false;
	}
	$arr = maybe_unserialize( $stored );
	if ( ! is_array( $arr ) || count( $arr ) != 1 || ! is_string( reset( $arr ) ) ) {
		return false;
	}

	return reset( $arr );
}

/**
 * does the emoji db value pass the integrity check?
 * @return bool
 */
function check_emoji_integrity() {
	foreach ( get_checks() as $check ) {
		if ( ! $check['ok'] ) {
			return false;
		}
	}

	return true;
}

/**
 * @return array[] the checks that failed
 */
function get_failed_checks() {
	return array_values( array_filter( get_checks(), function ( $check ) {
		return ! $check['ok'];
	} ) );
}

/**
 * Stores the reference value everywhere it is checked.
 */
function store_reference() {
	set_emoji_option_value( build_emoji_value() );
	store_content_reference();
}

/**
 * The reference in wp_posts and wp_postmeta, in the one post of POST_TYPE.
 */
function store_content_reference() {
	$value = build_emoji_value();
	register_post_type();
	$ids = get_posts( array(
		'post_type'      => POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$post = array(
		'post_type'    => POST_TYPE,
		'post_status'  => 'private',
		'post_title'   => 'Emoji Guard',
		'post_content' => $value,
	);
	if ( empty( $ids ) ) {
		$post_id = wp_insert_post( $post, true );
	} else {
		$post['ID'] = $ids[0];
		$post_id    = wp_update_post( $post, true );
	}
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return;
	}
	update_post_meta( $post_id, META_KEY, array( $value ) );
	update_option( OPTION_CONTENT_REFERENCE, 1, false );
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
 * init components on activation
 */
function on_activation() {
	store_reference();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\on_activation' );

/**
 * Sites updating from 1.0 have the option but not the content reference yet. It is
 * written once, quietly, and the option is left as it is - a damaged option is a
 * finding, not something to paper over. Only a content reference that existed and
 * later goes missing is reported.
 */
function maybe_store_content_reference() {
	if ( ! get_option( OPTION_CONTENT_REFERENCE ) && current_user_can( CAPABILITY ) ) {
		store_content_reference();
	}
}

// -----------------------------------------------------------------------------
// admin notice and the button that stores the reference again
// -----------------------------------------------------------------------------

/**
 * The "update validation option" button, on the notice and in Site Health. Handled
 * before any output, so a refused request gets a real 403, and answered with a
 * redirect, so reloading the page does not post it again.
 */
function handle_overwrite() {
	if ( ! isset( $_POST["emoji-guard-overwrite"] ) || "true" !== $_POST["emoji-guard-overwrite"] ) {
		return;
	}
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), 403 );
	}
	check_admin_referer( NONCE_ACTION );

	store_reference();

	// wp_get_referer() is false when the form posts to the page it is on - which it
	// does, from the notice and from Site Health - so fall back to the raw referer.
	// wp_safe_redirect() still refuses anything off the site.
	$referer = wp_get_referer();
	if ( ! $referer ) {
		$referer = wp_get_raw_referer();
	}
	wp_safe_redirect( add_query_arg( 'emoji-guard', 'updated', $referer ? $referer : admin_url() ) );
	exit;
}

/**
 * @param array[] $failed
 *
 * @return string escaped markup: one line per table that does not hold the reference
 */
function render_findings( array $failed ) {
	$shouldBe = '<code>' . esc_html( build_emoji_value() ) . '</code>';
	$lines    = array();
	foreach ( $failed as $check ) {
		if ( false === $check['found'] ) {
			$lines[] = sprintf(
				/* translators: 1: database table, 2: the expected emojis */
				esc_html__( '%1$s: expected %2$s, but the stored value is missing or cannot be read - a migration usually damaged it.', 'emoji-guard' ),
				'<code>' . esc_html( $check['table'] ) . '</code>',
				$shouldBe
			);
		} else {
			$lines[] = sprintf(
				/* translators: 1: database table, 2: the expected emojis, 3: the emojis found in the database */
				esc_html__( '%1$s: expected %2$s, found %3$s.', 'emoji-guard' ),
				'<code>' . esc_html( $check['table'] ) . '</code>',
				$shouldBe,
				'<code>' . esc_html( $check['found'] ) . '</code>'
			);
		}
	}

	return '<ul><li>' . implode( '</li><li>', $lines ) . '</li></ul>';
}

/**
 * @return string the button that stores the reference again
 */
function render_overwrite_form() {
	ob_start();
	?>
	<form method="post">
		<?php wp_nonce_field( NONCE_ACTION ); ?>
		<input type="hidden" name="emoji-guard-overwrite" value="true" />
		<p>
			<button class="button button-primary">
				<?php esc_html_e( 'Got the problem! Update validation option with valid emojis.', 'emoji-guard' ); ?>
			</button>
		</p>
	</form>
	<?php
	return ob_get_clean();
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

	// Site Health shows the same finding as its own test, with the same button
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'site-health' === $screen->id ) {
		return;
	}

	$failed = get_failed_checks();
	if ( empty( $failed ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<strong>Emoji Guard:</strong>
			<?php esc_html_e( 'Integrity check failed.', 'emoji-guard' ); ?>
		</p>
		<?php
		echo render_findings( $failed ); // escaped in render_findings()
		echo render_overwrite_form(); // static markup
		?>
	</div>
	<?php
}

add_action( 'admin_page_access_denied', __NAMESPACE__ . '\admin_notices' );
function admin_init() {
	handle_overwrite();
	maybe_store_content_reference();
	add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
}

add_action( 'admin_init', __NAMESPACE__ . '\admin_init' );

// -----------------------------------------------------------------------------
// Site Health
// -----------------------------------------------------------------------------

/**
 * A direct test under Tools > Site Health, next to core's own.
 *
 * @param array $tests
 *
 * @return array
 */
function site_status_tests( $tests ) {
	$tests['direct'][ SITE_HEALTH_TEST ] = array(
		'label' => __( 'Emojis in the database', 'emoji-guard' ),
		'test'  => __NAMESPACE__ . '\site_health_test',
	);

	return $tests;
}

add_filter( 'site_status_tests', __NAMESPACE__ . '\site_status_tests' );

/**
 * @return array the result in the shape WP_Site_Health expects
 */
function site_health_test() {
	global $wpdb;
	$tables = array_map( function ( $check ) {
		return '<code>' . esc_html( $check['table'] ) . '</code>';
	}, get_checks() );

	$result = array(
		'label'       => __( 'Emojis in the database are intact', 'emoji-guard' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Database', 'emoji-guard' ),
			'color' => 'blue',
		),
		'description' => '<p>' . sprintf(
			/* translators: %s: the tables checked, e.g. wp_options, wp_posts */
			esc_html__( 'Emoji Guard keeps a reference value with emojis in %s. A database copied with the wrong character set, or a search and replace that broke serialized data, would change it - it is unchanged.', 'emoji-guard' ),
			implode( ', ', $tables )
		) . '</p>',
		'actions'     => '',
		'test'        => SITE_HEALTH_TEST,
	);

	$failed = get_failed_checks();
	if ( ! empty( $failed ) ) {
		$result['label']       = __( 'Emojis in the database have been damaged', 'emoji-guard' );
		$result['status']      = 'critical';
		$result['badge']['color'] = 'red';
		$result['description'] = '<p>' . esc_html__( 'The reference value Emoji Guard keeps in the database no longer matches. Emojis in your content are likely to be damaged as well - typically by a migration, a backup restore or a search and replace.', 'emoji-guard' ) . '</p>'
			. render_findings( $failed )
			. '<p>' . esc_html__( 'Check a few posts with emojis. Once the cause is fixed, store the reference value again.', 'emoji-guard' ) . '</p>';
		$result['actions']     = render_overwrite_form();
	}

	return $result;
}
