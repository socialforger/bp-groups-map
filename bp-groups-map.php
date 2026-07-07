<?php
/**
 * Plugin Name: BP Groups Map
 * Description: Georeferenziazione gruppi Buddypress/Buddyboss.
 * Author: Socialforger
 * Version: 2.1.2
 * Text Domain: bp-groups-map
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BPGM_VERSION' ) ) {
	define( 'BPGM_VERSION', '2.1.2' );
}
if ( ! defined( 'BPGM_PATH' ) ) {
	define( 'BPGM_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'BPGM_URL' ) ) {
	define( 'BPGM_URL', plugin_dir_url( __FILE__ ) );
}

// Meta keys geografiche sul gruppo.
if ( ! defined( 'BPGM_META_INDIRIZZO' ) ) {
	define( 'BPGM_META_INDIRIZZO', 'bpgm_indirizzo' );
}
if ( ! defined( 'BPGM_META_LAT' ) ) {
	define( 'BPGM_META_LAT', 'bpgm_lat' );
}
if ( ! defined( 'BPGM_META_LNG' ) ) {
	define( 'BPGM_META_LNG', 'bpgm_lng' );
}

if ( ! function_exists( 'bpgm_load_textdomain' ) ) {
	function bpgm_load_textdomain() {
		load_plugin_textdomain( 'bp-groups-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	add_action( 'init', 'bpgm_load_textdomain' );
}

if ( ! function_exists( 'bpgm_check_dependencies' ) ) {
	function bpgm_check_dependencies() {
		if ( ! function_exists( 'buddypress' ) || ! bp_is_active( 'groups' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'BP Groups Map richiede BuddyPress o BuddyBoss Platform con il componente Gruppi attivo.', 'bp-groups-map' );
				echo '</p></div>';
			} );
			return false;
		}
		return true;
	}
	add_action( 'bp_init', 'bpgm_check_dependencies' );
}

if ( ! function_exists( 'bpgm_enqueue_leaflet' ) ) {
	function bpgm_enqueue_leaflet() {
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	}
}

if ( ! function_exists( 'bpgm_register_taxonomies' ) ) {
	function bpgm_register_taxonomies() {
		register_taxonomy( 'group_cat', array(), array(
			'labels'       => array(
				'name'          => __( 'Categorie Gruppo', 'bp-groups-map' ),
				'singular_name' => __( 'Categoria Gruppo', 'bp-groups-map' ),
			),
			'hierarchical' => true,
			'public'       => false,
			'show_ui'      => false,
		) );

		register_taxonomy( 'group_tag', array(), array(
			'labels'       => array(
				'name'          => __( 'Tag Gruppo', 'bp-groups-map' ),
				'singular_name' => __( 'Tag Gruppo', 'bp-groups-map' ),
			),
			'hierarchical' => false,
			'public'       => false,
			'show_ui'      => false,
		) );
	}
	add_action( 'init', 'bpgm_register_taxonomies' );
}

if ( ! function_exists( 'bpgm_register_settings' ) ) {
	function bpgm_register_settings() {
		register_setting( 'bpgm_settings_group', 'bpgm_nominatim_ua' );
		register_setting( 'bpgm_settings_group', 'bpgm_default_lat' );
		register_setting( 'bpgm_settings_group', 'bpgm_default_lng' );
		register_setting( 'bpgm_settings_group', 'bpgm_default_zoom' );
	}
	add_action( 'admin_init', 'bpgm_register_settings' );
}

if ( ! function_exists( 'bpgm_admin_menu' ) ) {
	function bpgm_admin_menu() {
		add_menu_page('BP Groups Map', 'BP Groups Map', 'manage_options', 'bp-groups-map', 'bpgm_render_settings_page', 'dashicons-location-alt', 80);
		add_submenu_page('bp-groups-map', __('Impostazioni', 'bp-groups-map'), __('Impostazioni', 'bp-groups-map'), 'manage_options', 'bp-groups-map', 'bpgm_render_settings_page');
		add_submenu_page('bp-groups-map', __('Categorie Gruppo', 'bp-groups-map'), __('Categorie Gruppo', 'bp-groups-map'), 'manage_options', 'bpgm-categorie', 'bpgm_render_admin_page');
		add_submenu_page('bp-groups-map', __('Tag Gruppo', 'bp-groups-map'), __('Tag Gruppo', 'bp-groups-map'), 'manage_options', 'edit-tags.php?taxonomy=group_tag', null);
	}
	add_action( 'admin_menu', 'bpgm_admin_menu' );
}

if ( ! function_exists( 'bpgm_render_settings_page' ) ) {
	function bpgm_render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$ua   = get_option( 'bpgm_nominatim_ua', 'ABC-Gruppi-Map/2.0 (contatto: info@abcbenicomuni.it)' );
		$lat  = get_option( 'bpgm_default_lat', '41.9028' );
		$lng  = get_option( 'bpgm_default_lng', '12.4964' );
		$zoom = get_option( 'bpgm_default_zoom', '6' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Impostazioni BP Groups Map', 'bp-groups-map' ); ?></h1>
			<hr>
			<form method="post" action="options.php">
				<?php settings_fields( 'bpgm_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="bpgm_nominatim_ua"><?php esc_html_e( 'User-Agent Nominatim (OSM)', 'bp-groups-map' ); ?></label></th>
						<td><input type="text" id="bpgm_nominatim_ua" name="bpgm_nominatim_ua" value="<?php echo esc_attr( $ua ); ?>" class="large-text" /></td>
					</tr>
					<tr>
						<th scope="row"><strong><?php esc_html_e( 'Centro Mappa Iniziale', 'bp-groups-map' ); ?></strong></th>
						<td>
							<label for="bpgm_default_lat">Latitudine:</label>
							<input type="text" id="bpgm_default_lat" name="bpgm_default_lat" value="<?php echo esc_attr( $lat ); ?>" class="small-text" />
							&nbsp;&nbsp;&nbsp;
							<label for="bpgm_default_lng">Longitudine:</label>
							<input type="text" id="bpgm_default_lng" name="bpgm_default_lng" value="<?php echo esc_attr( $lng ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bpgm_default_zoom"><?php esc_html_e( 'Livello di Zoom Iniziale', 'bp-groups-map' ); ?></label></th>
						<td><input type="number" id="bpgm_default_zoom" name="bpgm_default_zoom" value="<?php echo esc_attr( $zoom ); ?>" min="1" max="18" class="small-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bpgm_render_admin_page' ) ) {
	function bpgm_render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_POST['bpgm_new_categoria'] ) && check_admin_referer( 'bpgm_add_categoria' ) ) {
			$nome = sanitize_text_field( wp_unslash( $_POST['bpgm_new_categoria'] ) );
			if ( $nome ) { wp_insert_term( $nome, 'group_cat' ); }
		}
		if ( isset( $_GET['bpgm_delete_term'] ) && check_admin_referer( 'bpgm_delete_categoria' ) ) {
			wp_delete_term( intval( $_GET['bpgm_delete_term'] ), 'group_cat' );
		}
		$categorie = get_terms( array( 'taxonomy' => 'group_cat', 'hide_empty' => false ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Categorie Gruppo', 'bp-groups-map' ); ?></h1>
			<p><?php esc_html_e( 'Queste categorie saranno selezionabili nei dettagli nativi del gruppo.', 'bp-groups-map' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'bpgm_add_categoria' ); ?>
				<input type="text" name="bpgm_new_categoria" placeholder="<?php esc_attr_e( 'Nome nuova categoria', 'bp-groups-map' ); ?>" />
				<?php submit_button( __( 'Aggiungi categoria', 'bp-groups-map' ), 'secondary', 'submit', false ); ?>
			</form>
			<table class="widefat" style="margin-top:20px; max-width:500px;">
				<thead><tr><th><?php esc_html_e( 'Categoria', 'bp-groups-map' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $categorie as $cat ) :
					$delete_url = wp_nonce_url( add_query_arg( 'bpgm_delete_term', $cat->term_id ), 'bpgm_delete_categoria' ); ?>
					<tr>
						<td><?php echo esc_html( $cat->name ); ?></td>
						<td><a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Eliminare questa categoria?', 'bp-groups-map' ); ?>');"><?php esc_html_e( 'Elimina', 'bp-groups-map' ); ?></a></td>
					</tr>
				<?php endforeach; if ( ! $categorie ) : ?>
					<tr><td colspan="2"><em><?php esc_html_e( 'Nessuna categoria ancora creata.', 'bp-groups-map' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'edit-tags.php' === $hook || 'term.php' === $hook ) { wp_enqueue_media(); }
} );

if ( ! function_exists( 'bpgm_taxonomy_add_meta_fields' ) ) {
	function bpgm_taxonomy_add_meta_fields() {
		?>
		<div class="form-field term-group">
			<label for="bpgm_marker_icon"><?php esc_html_e( 'Icona Marker Custom', 'bp-groups-map' ); ?></label>
			<input type="text" id="bpgm_marker_icon" name="bpgm_marker_icon" value="" style="width:70%;" readonly />
			<button type="button" class="button bpgm-upload-marker-btn"><?php esc_html_e( 'Carica/Scegli Icona', 'bp-groups-map' ); ?></button>
			<button type="button" class="button bpgm-clear-marker-btn" style="color:#c0392b;"><?php esc_html_e( 'Rimuovi', 'bp-groups-map' ); ?></button>
		</div>
		<?php bpgm_taxonomy_media_js();
	}
	add_action( 'group_cat_add_form_fields', 'bpgm_taxonomy_add_meta_fields' );
	add_action( 'group_tag_add_form_fields', 'bpgm_taxonomy_add_meta_fields' );
}

if ( ! function_exists( 'bpgm_taxonomy_edit_meta_fields' ) ) {
	function bpgm_taxonomy_edit_meta_fields( $term ) {
		$marker_icon = get_term_meta( $term->term_id, 'bpgm_marker_icon', true );
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row"><label for="bpgm_marker_icon"><?php esc_html_e( 'Icona Marker Custom', 'bp-groups-map' ); ?></label></th>
			<td>
				<input type="text" id="bpgm_marker_icon" name="bpgm_marker_icon" value="<?php echo esc_url( $marker_icon ); ?>" style="width:70%;" readonly />
				<button type="button" class="button bpgm-upload-marker-btn"><?php esc_html_e( 'Carica/Scegli Icona', 'bp-groups-map' ); ?></button>
				<button type="button" class="button bpgm-clear-marker-btn" style="color:#c0392b;"><?php esc_html_e( 'Rimuovi', 'bp-groups-map' ); ?></button>
				<?php if ( $marker_icon ) : ?>
					<img src="<?php echo esc_url( $marker_icon ); ?>" style="max-height:40px; margin-top:10px; display:block;" class="bpgm-preview-marker">
				<?php endif; ?>
			</td>
		</tr>
		<?php bpgm_taxonomy_media_js();
	}
	add_action( 'group_cat_edit_form_fields', 'bpgm_taxonomy_edit_meta_fields' );
	add_action( 'group_tag_edit_form_fields', 'bpgm_taxonomy_edit_meta_fields' );
}

if ( ! function_exists( 'bpgm_save_taxonomy_meta' ) ) {
	function bpgm_save_taxonomy_meta( $term_id ) {
		if ( isset( $_POST['bpgm_marker_icon'] ) ) {
			update_term_meta( $term_id, 'bpgm_marker_icon', esc_url_raw( wp_unslash( $_POST['bpgm_marker_icon'] ) ) );
		}
	}
	add_action( 'created_group_cat', 'bpgm_save_taxonomy_meta' );
	add_action( 'edited_group_cat', 'bpgm_save_taxonomy_meta' );
	add_action( 'created_group_tag', 'bpgm_save_taxonomy_meta' );
	add_action( 'edited_group_tag', 'bpgm_save_taxonomy_meta' );
}

if ( ! function_exists( 'bpgm_taxonomy_media_js' ) ) {
	function bpgm_taxonomy_media_js() {
		static $printed = false; if ( $printed ) return; $printed = true; ?>
		<script>
		jQuery(document).ready(function($){
			var frame;
			$(document).on('click', '.bpgm-upload-marker-btn', function(e) {
				e.preventDefault(); var $input = $(this).siblings('#bpgm_marker_icon');
				if (frame) { frame.open(); return; }
				frame = wp.media({ title: 'Scegli Icona', button: { text: 'Usa icona' }, multiple: false });
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON(); $input.val(attachment.url);
					if($('.bpgm-preview-marker').length) { $('.bpgm-preview-marker').attr('src', attachment.url); }
				});
				frame.open();
			});
			$(document).on('click', '.bpgm-clear-marker-btn', function(e) { e.preventDefault(); $(this).siblings('#bpgm_marker_icon').val(''); $('.bpgm-preview-marker').remove(); });
		});
		</script>
		<?php
	}
}

if ( ! function_exists( 'bpgm_geocode_address' ) ) {
	function bpgm_geocode_address( $address ) {
		$address = trim( $address );
		if ( '' === $address ) { return new WP_Error( 'bpgm_empty_address', __( 'Indirizzo vuoto.', 'bp-groups-map' ) ); }
		$cache_key = 'bpgm_geo_' . md5( strtolower( $address ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) { return $cached; }

		$url = add_query_arg( array( 'q' => $address, 'format' => 'json', 'limit' => 1 ), 'https://nominatim.openstreetmap.org/search' );
		$ua_dinamico = get_option( 'bpgm_nominatim_ua', 'ABC-Gruppi-Map/2.0 (contatto: info@abcbenicomuni.it)' );
		$response = wp_remote_get( $url, array( 'headers' => array( 'User-Agent' => $ua_dinamico ), 'timeout' => 10 ) );
		if ( is_wp_error( $response ) ) { return $response; }

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body ) || ! isset( $body[0]['lat'], $body[0]['lon'] ) ) {
			return new WP_Error( 'bpgm_geocode_not_found', __( 'Indirizzo non trovato.', 'bp-groups-map' ) );
		}
		$result = array( 'lat' => floatval( $body[0]['lat'] ), 'lng' => floatval( $body[0]['lon'] ) );
		set_transient( $cache_key, $result, 30 * DAY_IN_SECONDS );
		return $result;
	}
}

/* -----------------------------------------------------------------------
 * APPEND DI INDIRIZZO, CATEGORIE E TAG NELLA TAB DETTAGLI DI DEFAULT
 * ---------------------------------------------------------------------*/

if ( ! function_exists( 'bpgm_edit_screen_native_fields' ) ) {
	function bpgm_edit_screen_native_fields() {
		$group_id = bp_get_current_group_id();
		if ( ! $group_id ) return;

		$indirizzo = groups_get_groupmeta( $group_id, BPGM_META_INDIRIZZO );
		$lat       = groups_get_groupmeta( $group_id, BPGM_META_LAT );
		$lng       = groups_get_groupmeta( $group_id, BPGM_META_LNG );

		wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
		wp_nonce_field( 'bpgm_save_scheda', 'bpgm_nonce' );
		?>
		<div class="bpgm-native-edit-fields" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
			<p>
				<label for="bpgm_indirizzo"><strong><?php esc_html_e( 'Indirizzo geografico / Sede', 'bp-groups-map' ); ?></strong></label><br/>
				<input type="text" id="bpgm_indirizzo" name="bpgm_indirizzo" class="widefat" value="<?php echo esc_attr( $indirizzo ); ?>" placeholder="es. Via dei Mercati Generali, Roma" style="width:100%;" />
				<span class="description"><?php esc_html_e( 'Inserendo la posizione, la tab "Mappa" comparirà sul profilo pubblico di questo gruppo.', 'bp-groups-map' ); ?></span>
				<?php if ( $lat && $lng ) : ?>
					<br/><span class="bpgm-geo-status">✅ <?php printf( esc_html__( 'Geolocalizzato: %s, %s', 'bp-groups-map' ), esc_html( $lat ), esc_html( $lng ) ); ?></span>
				<?php endif; ?>
			</p>
			<?php
			$tutte_categorie   = get_terms( array( 'taxonomy' => 'group_cat', 'hide_empty' => false ) );
			$categorie_gruppo  = wp_get_object_terms( $group_id, 'group_cat', array( 'fields' => 'ids' ) );
			$tag_gruppo        = wp_get_object_terms( $group_id, 'group_tag', array( 'fields' => 'names' ) );
			?>
			<p>
				<strong><?php esc_html_e( 'Categorie', 'bp-groups-map' ); ?></strong><br/>
				<?php if ( $tutte_categorie ) : ?>
					<?php foreach ( $tutte_categorie as $cat ) : ?>
						<label style="display:inline-block; margin-right:12px; margin-top:5px;">
							<input type="checkbox" name="bpgm_categorie[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, $categorie_gruppo, true ) ); ?> />
							<?php echo esc_html( $cat->name ); ?>
						</label>
					<?php endnav_item_position; foreach; ?>
				<?php endif; ?>
			</p>
			<p>
				<label for="bpgm_tags"><strong><?php esc_html_e( 'Tag (separati da virgola)', 'bp-groups-map' ); ?></strong></label><br/>
				<input type="text" id="bpgm_tags" name="bpgm_tags" class="widefat" value="<?php echo esc_attr( implode( ', ', $tag_gruppo ) ); ?>" style="width:100%;" />
			</p>
		</div>
		<?php
	}
	// Usiamo hook multipli per assicurarci che appaia sia in modifica frontend (Manage) che durante la creazione del gruppo
	add_action( 'bp_after_group_details_admin_form', 'bpgm_edit_screen_native_fields' );
	add_action( 'bp_after_group_details_fields', 'bpgm_edit_screen_native_fields' );
	add_action( 'bp_after_group_details_creation_step', 'bpgm_edit_screen_native_fields' );
}

if ( ! function_exists( 'bpgm_save_native_fields' ) ) {
	function bpgm_save_native_fields( $group_id ) {
		if ( ! isset( $_POST['bpgm_nonce'] ) || ! wp_verify_nonce( $_POST['bpgm_nonce'], 'bpgm_save_scheda' ) ) { return; }

		$indirizzo = isset( $_POST['bpgm_indirizzo'] ) ? sanitize_text_field( wp_unslash( $_POST['bpgm_indirizzo'] ) ) : '';

		$categorie_ids = isset( $_POST['bpgm_categorie'] ) ? array_map( 'intval', (array) $_POST['bpgm_categorie'] ) : array();
		wp_set_object_terms( $group_id, $categorie_ids, 'group_cat' );

		$tags_raw = isset( $_POST['bpgm_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['bpgm_tags'] ) ) : '';
		$tags     = array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) );
		wp_set_object_terms( $group_id, $tags, 'group_tag' );

		$indirizzo_precedente = groups_get_groupmeta( $group_id, BPGM_META_INDIRIZZO );
		groups_update_groupmeta( $group_id, BPGM_META_INDIRIZZO, $indirizzo );

		if ( $indirizzo && $indirizzo !== $indirizzo_precedente ) {
			$geo = bpgm_geocode_address( $indirizzo );
			if ( ! is_wp_error( $geo ) ) {
				groups_update_groupmeta( $group_id, BPGM_META_LAT, $geo['lat'] );
				groups_update_groupmeta( $group_id, BPGM_META_LNG, $geo['lng'] );
			}
		} elseif ( '' === $indirizzo ) {
			groups_delete_groupmeta( $group_id, BPGM_META_LAT );
			groups_delete_groupmeta( $group_id, BPGM_META_LNG );
		}
	}
	add_action( 'groups_group_details_edited', 'bpgm_save_native_fields' );
	add_action( 'groups_created_group', 'bpgm_save_native_fields' );
}

if ( ! function_exists( 'bpgm_display_in_native_details' ) ) {
	function bpgm_display_in_native_details() {
		$group_id = bp_get_current_group_id();
		if ( ! $group_id ) return;

		$indirizzo = groups_get_groupmeta( $group_id, BPGM_META_INDIRIZZO );
		$categorie = wp_get_object_terms( $group_id, 'group_cat' );
		$tags      = wp_get_object_terms( $group_id, 'group_tag' );

		if ( ! $indirizzo && empty( $categorie ) && empty( $tags ) ) { return; }

		wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
		?>
		<div class="bpgm-custom-details-block" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; width: 100%; display: block; clear: both;">
			<?php if ( $indirizzo ) : ?><p class="bpgm-indirizzo">📍 <strong><?php esc_html_e( 'Posizione:', 'bp-groups-map' ); ?></strong> <?php echo esc_html( $indirizzo ); ?></p><?php endif; ?>

			<?php if ( ! is_wp_error( $categorie ) && $categorie ) : ?>
				<div class="bpgm-categorie" style="margin-top:10px;">
					<?php foreach ( $categorie as $cat ) : $icon_url = get_term_meta( $cat->term_id, 'bpgm_marker_icon', true ); ?>
						<span class="bpgm-badge bpgm-badge-categoria" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;">
							<?php if ( $icon_url ) : ?><img src="<?php echo esc_url( $icon_url ); ?>" style="width: 16px; height: 16px; object-fit: contain;" alt="" /><?php endif; ?>
							<?php echo esc_html( $cat->name ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>
				<div class="bpgm-tags" style="margin-top:5px;">
					<?php foreach ( $tags as $tag ) : $icon_url = get_term_meta( $tag->term_id, 'bpgm_marker_icon', true ); ?>
						<span class="bpgm-badge bpgm-badge-tag" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;">
							<?php if ( $icon_url ) : ?><img src="<?php echo esc_url( $icon_url ); ?>" style="width: 16px; height: 16px; object-fit: contain;" alt="" /><?php endif; ?>
							#<?php echo esc_html( $tag->name ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
	// Hook estesi sia per BuddyPress Nouveau che per i tracciati BuddyBoss della scheda dettagli
	add_action( 'bp_after_group_details', 'bpgm_display_in_native_details' );
	add_action( 'bp_group_meta_section', 'bpgm_display_in_native_details' );
}

if ( ! function_exists( 'bpgm_register_mappa_extension' ) ) {
	function bpgm_register_mappa_extension() {
		if ( ! class_exists( 'BP_Group_Extension' ) ) { return; }

		class BPGM_Group_Extension_Mappa extends BP_Group_Extension {
			public function __construct() {
				$args = array(
					'slug'               => 'mappa',
					'name'               => __( 'Mappa', 'bp-groups-map' ),
					'nav_item_position'  => 25,
					'access'             => 'anyone',
					'show_tab'           => 'anyone',
					'enable_edit_item'   => false,
					'enable_create_step' => false,
				);
				parent::init( $args );
			}

			public function display( $group_id = null ) {
				$group_id = $group_id ?: bp_get_current_group_id();
				$lat       = groups_get_groupmeta( $group_id, BPGM_META_LAT );
				$lng       = groups_get_groupmeta( $group_id, BPGM_META_LNG );
				$categorie = wp_get_object_terms( $group_id, 'group_cat' );
				$tags      = wp_get_object_terms( $group_id, 'group_tag' );

				$custom_marker_url = '';
				if ( ! is_wp_error( $categorie ) && ! empty( $categorie ) ) {
					foreach ( $categorie as $cat ) {
						$img = get_term_meta( $cat->term_id, 'bpgm_marker_icon', true );
						if ( $img ) { $custom_marker_url = $img; break; }
					}
				}
				if ( ! $custom_marker_url && ! is_wp_error( $tags ) && ! empty( $tags ) ) {
					foreach ( $tags as $tag ) {
						$img = get_term_meta( $tag->term_id, 'bpgm_marker_icon', true );
						if ( $img ) { $custom_marker_url = $img; break; }
					}
				}

				if ( $lat && $lng ) :
					if ( class_exists( 'Leaflet_Map' ) ) {
						echo do_shortcode( '[leaflet-map height="450px" lat="' . esc_attr( $lat ) . '" lng="' . esc_attr( $lng ) . '" zoom="15" scrollwheel="false"]' );
						if ( $custom_marker_url ) { echo do_shortcode( '[leaflet-marker iconUrl="' . esc_url( $custom_marker_url ) . '"]' . esc_html( bp_get_group_name() ) . '[/leaflet-marker]' ); }
						else { echo do_shortcode( '[leaflet-marker]' . esc_html( bp_get_group_name() ) . '[/leaflet-marker]' ); }
					} else {
						bpgm_enqueue_leaflet();
						wp_enqueue_script( 'bpgm-scheda-map', BPGM_URL . 'assets/js/scheda-map.js', array( 'leaflet' ), BPGM_VERSION, true );
						wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
						wp_localize_script( 'bpgm-scheda-map', 'bpgmScheda', array( 'lat' => floatval( $lat ), 'lng' => floatval( $lng ), 'name' => bp_get_group_name(), 'markerIcon' => esc_url_raw( $custom_marker_url ) ) );
						?><div id="bpgm-scheda-map" style="height:450px;"></div><?php
					}
				endif;
			}
		}
		bp_register_group_extension( 'BPGM_Group_Extension_Mappa' );
	}
	add_action( 'bp_init', 'bpgm_register_mappa_extension' );
}

if ( ! function_exists( 'bpgm_manage_group_tabs' ) ) {
	function bpgm_manage_group_tabs() {
		if ( ! bp_is_group() ) { return; }
		
		$group_id = bp_get_current_group_id();
		$lat = groups_get_groupmeta( $group_id, BPGM_META_LAT );
		$lng = groups_get_groupmeta( $group_id, BPGM_META_LNG );
		
		if ( ! $lat || ! $lng ) {
			bp_core_remove_subnav_item( bp_get_current_group_slug(), 'mappa', 'groups' );
		}
	}
	add_action( 'bp_actions', 'bpgm_manage_group_tabs', 999 );
}

if ( ! function_exists( 'bpgm_register_rest_route' ) ) {
	function bpgm_register_rest_route() {
		register_rest_route( 'abc/v1', '/groups-map', array(
			'methods'             => 'GET',
			'callback'            => 'bpgm_rest_get_groups',
			'permission_callback' => '__return_true',
		) );
	}
	add_action( 'rest_api_init', 'bpgm_register_rest_route' );
}

if ( ! function_exists( 'bpgm_rest_get_groups' ) ) {
	function bpgm_rest_get_groups( WP_REST_Request $request ) {
		$group_type = $request->get_param( 'group_type' );
		$args = array(
			'per_page'   => 0, 'page' => 1, 'status' => array( 'public' ),
			'meta_query' => array( array( 'key' => BPGM_META_LAT, 'compare' => 'EXISTS' ), array( 'key' => BPGM_META_LNG, 'compare' => 'EXISTS' ) ),
		);
		if ( $group_type ) { $args['group_type'] = sanitize_key( $group_type ); }
		$result = function_exists( 'groups_get_groups' ) ? groups_get_groups( $args ) : array( 'groups' => array() );
		$features = array(); $term_icons = array();

		$all_cats = get_terms( array( 'taxonomy' => 'group_cat', 'hide_empty' => false ) );
		$all_tags = get_terms( array( 'taxonomy' => 'group_tag', 'hide_empty' => false ) );
		if ( ! is_wp_error( $all_cats ) ) { foreach ( $all_cats as $t ) { $icon = get_term_meta( $t->term_id, 'bpgm_marker_icon', true ); if ( $icon ) { $term_icons[$t->name] = esc_url_raw( $icon ); } } }
		if ( ! is_wp_error( $all_tags ) ) { foreach ( $all_tags as $t ) { $icon = get_term_meta( $t->term_id, 'bpgm_marker_icon', true ); if ( $icon ) { $term_icons[$t->name] = esc_url_raw( $icon ); } } }

		foreach ( $result['groups'] as $group ) {
			$lat = groups_get_groupmeta( $group->id, BPGM_META_LAT ); $lng = groups_get_groupmeta( $group->id, BPGM_META_LNG );
			if ( ! $lat || ! $lng ) continue;
			$categorie_terms = wp_get_object_terms( $group->id, 'group_cat' );
			$tag_terms       = wp_get_object_terms( $group->id, 'group_tag' );
			$categorie_names = array(); $tag_names = array(); $marker_custom = '';

			if ( ! is_wp_error( $categorie_terms ) && ! empty( $categorie_terms ) ) {
				foreach ( $categorie_terms as $term ) { $categorie_names[] = $term->name; }
				$marker_custom = get_term_meta( $categorie_terms[0]->term_id, 'bpgm_marker_icon', true );
			}
			if ( ! is_wp_error( $tag_terms ) && ! empty( $tag_terms ) ) {
				foreach ( $tag_terms as $term ) { $tag_names[] = $term->name; }
				if ( ! $marker_custom ) { $marker_custom = get_term_meta( $tag_terms[0]->term_id, 'bpgm_marker_icon', true ); }
			}

			$features[] = array(
				'type' => 'Feature',
				'geometry' => array( 'type' => 'Point', 'coordinates' => array( floatval( $lng ), floatval( $lat ) ) ),
				'properties' => array(
					'id' => $group->id, 'name' => $group->name, 'indirizzo' => groups_get_groupmeta( $group->id, BPGM_META_INDIRIZZO ),
					'description' => wp_trim_words( $group->description, 20 ),
					'categorie' => $categorie_names, 'tags' => $tag_names, 'marker_icon' => esc_url_raw( $marker_custom ),
					'permalink' => bp_get_group_permalink( $group ),
					'avatar' => bp_core_fetch_avatar( array( 'item_id' => $group->id, 'object' => 'group', 'type' => 'thumb', 'html' => false ) ),
					'members' => groups_get_totalmembercount( $group->id ),
				),
			);
		}
		return new WP_REST_Response( array( 'type' => 'FeatureCollection', 'features' => $features, 'term_icons' => $term_icons ), 200 );
	}
}

if ( ! function_exists( 'bpgm_shortcode_map' ) ) {
	function bpgm_shortcode_map( $atts ) {
		$atts = shortcode_atts( array( 'group_type' => '', 'height' => '600px', 'layers' => '' ), $atts, 'gruppi_map' );
		if ( class_exists( 'Leaflet_Map' ) ) {
			$rest_url = rest_url( 'abc/v1/groups-map' );
			if ( ! empty( $atts['group_type'] ) ) { $rest_url = add_query_arg( 'group_type', sanitize_key( $atts['group_type'] ), $rest_url ); }
			$output = do_shortcode( '[leaflet-map height="' . esc_attr( $atts['height'] ) . '" fitbounds="1"]' );
			$output .= do_shortcode( '[leaflet-geojson src="' . esc_url_raw( $rest_url ) . '" cluster="true"]' );
			if ( ! empty( $atts['layers'] ) ) {
				foreach ( array_map( 'trim', explode( ',', $atts['layers'] ) ) as $layer ) {
					$layer_url = esc_url_raw( $layer );
					if ( preg_match( '/\.(kml|kmz)$/i', $layer_url ) ) { $output .= do_shortcode( '[leaflet-kml src="' . $layer_url . '"]' ); }
					else { $output .= do_shortcode( '[leaflet-geojson src="' . $layer_url . '"]' ); }
				}
			}
			return $output;
		} else {
			bpgm_enqueue_leaflet();
			wp_enqueue_style( 'leaflet-markercluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', array('leaflet'), '1.5.3' );
			wp_enqueue_style( 'leaflet-markercluster-default', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.Default.css', array('leaflet-markercluster'), '1.5.3' );
			wp_enqueue_script( 'leaflet-markercluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array('leaflet'), '1.5.3', true );
			wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
			wp_enqueue_script( 'bpgm-frontend-map', BPGM_URL . 'assets/js/frontend-map.js', array( 'leaflet', 'leaflet-markercluster' ), BPGM_VERSION, true );
			wp_localize_script( 'bpgm-frontend-map', 'bpgmFrontend', array(
				'restUrl' => esc_url_raw( rest_url( 'abc/v1/groups-map' ) ), 'groupType' => sanitize_key( $atts['group_type'] ),
				'defaultLat' => floatval( get_option( 'bpgm_default_lat', '41.9028' ) ), 'defaultLng' => floatval( get_option( 'bpgm_default_lng', '12.4964' ) ),
				'defaultZoom' => intval( get_option( 'bpgm_default_zoom', '6' ) ),
			) );
			return '<div id="bpgm-map" style="height:' . esc_attr( $atts['height'] ) . ';"></div>';
		}
	}
}

if ( ! shortcode_exists( 'gruppi_map' ) ) {
	add_shortcode( 'gruppi_map', 'bpgm_shortcode_map' );
}
if ( ! shortcode_exists( 'osm_map' ) ) {
	add_shortcode( 'osm_map', 'bpgm_shortcode_map' );
}
