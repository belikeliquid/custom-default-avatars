<?php
/**
 * Admin screen: Settings > Avatar Randomizer.
 *
 * @package Liquid_Avatar_Randomizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register settings.
 */
function lmar_register_settings() {
	register_setting(
		'lmar_settings',
		LMAR_OPT_IMAGES,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'lmar_sanitize_images',
			'default'           => lmar_default_images(),
		)
	);
	register_setting(
		'lmar_settings',
		LMAR_OPT_MODE,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'lmar_sanitize_mode',
			'default'           => 'gravatar',
		)
	);
	register_setting(
		'lmar_settings',
		'lmar_make_default',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'lmar_sanitize_make_default',
		)
	);
}
add_action( 'admin_init', 'lmar_register_settings' );

/**
 * The form posts a comma separated list; store it as a clean array.
 */
function lmar_sanitize_images( $value ) {
	if ( is_string( $value ) ) {
		$value = explode( ',', $value );
	}
	if ( ! is_array( $value ) ) {
		return lmar_default_images();
	}
	$clean = array_values( array_unique( array_filter( array_map( 'lmar_clean_entry', $value ) ) ) );
	return $clean;
}

function lmar_sanitize_mode( $value ) {
	return 'local' === $value ? 'local' : 'gravatar';
}

/**
 * Checkbox: "Use these images as the site default avatar".
 * Not stored itself; it switches the core avatar_default option.
 */
function lmar_sanitize_make_default( $value ) {
	$current = get_option( 'avatar_default', 'mystery' );
	if ( '1' === $value ) {
		if ( LMAR_KEY !== $current ) {
			update_option( LMAR_OPT_PREV_DEFAULT, $current );
			update_option( 'avatar_default', LMAR_KEY );
		}
	} elseif ( LMAR_KEY === $current ) {
		update_option( 'avatar_default', get_option( LMAR_OPT_PREV_DEFAULT, 'mystery' ) );
	}
	return '';
}

/**
 * Menu item.
 */
function lmar_admin_menu() {
	add_options_page(
		__( 'Avatar Randomizer', 'custom-default-avatars' ),
		__( 'Avatar Randomizer', 'custom-default-avatars' ),
		'manage_options',
		'custom-default-avatars',
		'lmar_render_page'
	);
}
add_action( 'admin_menu', 'lmar_admin_menu' );

/**
 * Scripts and styles for our page only.
 */
function lmar_admin_assets( $hook ) {
	if ( 'settings_page_custom-default-avatars' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'lmar-admin', LMAR_URL . 'assets/admin.css', array(), LMAR_VERSION );
	wp_enqueue_script( 'lmar-admin', LMAR_URL . 'assets/admin.js', array( 'jquery' ), LMAR_VERSION, true );
	$defaults = array();
	foreach ( lmar_default_images() as $entry ) {
		$defaults[] = array(
			'id'  => $entry,
			'url' => lmar_entry_url( $entry ),
		);
	}
	wp_localize_script(
		'lmar-admin',
		'lmarData',
		array(
			'defaults'    => $defaults,
			'frameTitle'  => __( 'Choose avatar images', 'custom-default-avatars' ),
			'frameButton' => __( 'Add to randomizer', 'custom-default-avatars' ),
			'remove'      => __( 'Remove', 'custom-default-avatars' ),
			'confirmReset' => __( 'Replace the current set with the 5 starter images?', 'custom-default-avatars' ),
			'webpNote'    => __( 'WebP and other formats work, but Gravatar cannot use them as a fallback, so people with a real Gravatar will see this image instead.', 'custom-default-avatars' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'lmar_admin_assets' );

/**
 * Settings page.
 */
function lmar_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$images     = lmar_get_images();
	$mode       = get_option( LMAR_OPT_MODE, 'gravatar' );
	$is_default = ( LMAR_KEY === get_option( 'avatar_default' ) );
	?>
	<div class="wrap lmar-wrap">
		<h1><?php esc_html_e( 'Avatar Randomizer', 'custom-default-avatars' ); ?></h1>
		<p class="lmar-intro"><?php esc_html_e( 'Anyone without an avatar gets one of these images. The same person always gets the same image.', 'custom-default-avatars' ); ?></p>

		<?php if ( ! get_option( 'show_avatars' ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				printf(
					/* translators: %s: link to Discussion settings */
					esc_html__( 'Avatars are turned off for this site. Turn on "Show Avatars" in %s.', 'custom-default-avatars' ),
					'<a href="' . esc_url( admin_url( 'options-discussion.php#show_avatars' ) ) . '">' . esc_html__( 'Settings > Discussion', 'custom-default-avatars' ) . '</a>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'lmar_settings' ); ?>
			<input type="hidden" id="lmar-images" name="<?php echo esc_attr( LMAR_OPT_IMAGES ); ?>" value="<?php echo esc_attr( implode( ',', $images ) ); ?>">

			<h2><?php esc_html_e( 'Image set', 'custom-default-avatars' ); ?></h2>
			<ul id="lmar-grid" class="lmar-grid">
				<?php foreach ( $images as $entry ) : ?>
					<li class="lmar-item" data-id="<?php echo esc_attr( $entry ); ?>">
						<img src="<?php echo esc_url( lmar_entry_url( $entry ) ); ?>" alt="">
						<button type="button" class="lmar-remove" aria-label="<?php esc_attr_e( 'Remove', 'custom-default-avatars' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p id="lmar-empty" class="description" <?php echo $images ? 'hidden' : ''; ?>>
				<?php esc_html_e( 'No images yet. Until you add some, the WordPress Mystery Person is used.', 'custom-default-avatars' ); ?>
			</p>
			<p>
				<button type="button" class="button button-secondary" id="lmar-add"><?php esc_html_e( 'Add images', 'custom-default-avatars' ); ?></button>
				<button type="button" class="button-link" id="lmar-reset"><?php esc_html_e( 'Restore the 5 starter images', 'custom-default-avatars' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Tip: square PNG or JPG images, 256px or bigger, look best.', 'custom-default-avatars' ); ?></p>

			<h2><?php esc_html_e( 'Settings', 'custom-default-avatars' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Site default avatar', 'custom-default-avatars' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="lmar_make_default" value="1" <?php checked( $is_default ); ?>>
							<?php esc_html_e( 'Use this image set as the default avatar', 'custom-default-avatars' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Same as picking it under Settings > Discussion > Default Avatar.', 'custom-default-avatars' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Real Gravatars', 'custom-default-avatars' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="<?php echo esc_attr( LMAR_OPT_MODE ); ?>" value="gravatar" <?php checked( $mode, 'gravatar' ); ?>>
								<?php esc_html_e( 'Keep them. Only people without a Gravatar get an image from the set.', 'custom-default-avatars' ); ?>
							</label><br>
							<label>
								<input type="radio" name="<?php echo esc_attr( LMAR_OPT_MODE ); ?>" value="local" <?php checked( $mode, 'local' ); ?>>
								<?php esc_html_e( 'Skip Gravatar. Everyone gets an image from the set, loaded from this site (faster, no third party calls).', 'custom-default-avatars' ); ?>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Avatars uploaded with a local avatar plugin always win.', 'custom-default-avatars' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<p class="lmar-credit">
			<?php
			printf(
				/* translators: %s: Liquid Media link */
				esc_html__( 'Developed by %s', 'custom-default-avatars' ),
				'<a href="' . esc_url( LMAR_CREDIT_URL ) . '" target="_blank" rel="noopener">Liquid Media</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * "Settings" link on the Plugins screen.
 */
function lmar_action_links( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'options-general.php?page=custom-default-avatars' ) ) . '">' . esc_html__( 'Settings', 'custom-default-avatars' ) . '</a>'
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( LMAR_FILE ), 'lmar_action_links' );

