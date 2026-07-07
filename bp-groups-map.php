<?php
/**
 * Plugin Name: BP Groups Map
 * Description: Integra indirizzo, categorie e tag nella tab Dettagli nativa dei gruppi e aggiunge una tab "Mappa" condizionale.
 * Author: Socialforger
 * Version: 2.1.2
 * Text Domain: bp-groups-map
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BPGM_VERSION', '2.1.2' );
define( 'BPGM_PATH', plugin_dir_path( __FILE__ ) );
define( 'BPGM_URL', plugin_dir_url( __FILE__ ) );

function bpgm_load_textdomain() {
	load_plugin_textdomain( 'bp-groups-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'bpgm_load_textdomain' );

// Meta keys geografiche sul gruppo.
define( 'BPGM_META_INDIRIZZO', 'bpgm_indirizzo' );
define( 'BPGM_META_LAT', 'bpgm_lat' );
define( 'BPGM_META_LNG', 'bpgm_lng' );

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

function bpgm_enqueue_leaflet() {
	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
}

/* -----------------------------------------------------------------------
 * TASSONOMIE: Categorie e Tag per i gruppi.
 * ---------------------------------------------------------------------*/

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

/* -----------------------------------------------------------------------
 * BACKEND: Menu Principale e Impostazioni del Plugin
 * ---------------------------------------------------------------------*/

function bpgm_register_settings() {
	register_setting( 'bpgm_settings_group', 'bpgm_nominatim_ua' );
	register_setting( 'bpgm_settings_group', 'bpgm_default_lat' );
	register_setting( 'bpgm_settings_group', 'bpgm_default_lng' );
	register_setting( 'bpgm_settings_group', 'bpgm_default_zoom' );
}
add_action( 'admin_init', 'bpgm_register_settings' );

function bpgm_admin_menu() {
	add_menu_page('BP Groups Map', 'BP Groups Map', 'manage_options', 'bp-groups-map', 'bpgm_render_settings_page', 'dashicons-location-alt', 80);
	add_submenu_page('bp-groups-map', __('Impostazioni', 'bp-groups-map'), __('Impostazioni', 'bp-groups-map'), 'manage_options', 'bp-groups-map', 'bpgm_render_settings_page');
	add_submenu_page('bp-groups-map', __('Categorie Gruppo', 'bp-groups-map'), __('Categorie Gruppo', 'bp-groups-map'), 'manage_options', 'bpgm-categorie', 'bpgm_render_admin_page');
	add_submenu_page('bp-groups-map', __('Tag Gruppo', 'bp-groups-map'), __('Tag Gruppo', 'bp-groups-map'), 'manage_options', 'edit-tags.php?taxonomy=group_tag', null);
}
add_action( 'admin_menu', 'bpgm_admin_menu' );

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

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'edit-tags.php' === $hook || 'term.php' === $hook ) { wp_enqueue_media(); }
} );

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

function bpgm_save_taxonomy_meta( $term_id ) {
	if ( isset( $_POST['bpgm_marker_icon'] ) ) {
		update_term_meta( $term_id, 'bpgm_marker_icon', esc_url_raw( wp_unslash( $_POST['bpgm_marker_icon'] ) ) );
	}
}
add_action( 'created_group_cat', 'bpgm_save_taxonomy_meta' );
add_action( 'edited_group_cat', 'bpgm_save_taxonomy_meta' );
add_action( 'created_group_tag', 'bpgm_save_taxonomy_meta' );
add_action( 'edited_group_tag', 'bpgm_save_taxonomy_meta' );

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

/* -----------------------------------------------------------------------
 * APPEND DI INDIRIZZO, CATEGORIE E TAG NELLA TAB DETTAGLI DI DEFAULT
 * ---------------------------------------------------------------------*/

// 1. Aggiunta dei soli 3 campi mancanti nel form di modifica nativo (Nome e Descrizione sono già gestiti da BP)
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
		$categorie_gruppo  = wp_get_object_terms( $group_id, 'group_cat', array( 'fields'
