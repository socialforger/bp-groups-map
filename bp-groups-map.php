<?php
/**
 * Plugin Name: BP Groups Map
 * Description: Il plugin aggiunge una sezione "Dettagli" ai gruppi Buddypress/Buddyboss, con indirizzo geolocalizzato che appare come marker su mappa OSM.
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

// Meta keys sul gruppo.
define( 'BPGM_META_NOME_BENE', 'bpgm_nome_bene' );
define( 'BPGM_META_INDIRIZZO', 'bpgm_indirizzo' );
define( 'BPGM_META_LAT', 'bpgm_lat' );
define( 'BPGM_META_LNG', 'bpgm_lng' );
define( 'BPGM_META_DESCRIZIONE', 'bpgm_descrizione' );

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
		<p><?php esc_html_e( 'Queste categorie saranno selezionabili nella scheda del gruppo.', 'bp-groups-map' ); ?></p>
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
 * TAB PUBBLICO SPOSTATO SU "DETTAGLI" (DETAILS)
 * ---------------------------------------------------------------------*/

function bpgm_register_group_extension() {
	if ( ! class_exists( 'BP_Group_Extension' ) ) { return; }

	class BPGM_Group_Extension extends BP_Group_Extension {
		public function __construct() {
			$args = array(
				'slug'              => 'dettagli', // URL: /groups/nome-gruppo/dettagli/
				'name'              => __( 'Dettagli', 'bp-groups-map' ), // Nome Tab
				'nav_item_position' => 20, // Posizionato dopo la Home/Attività di default
				'access'            => 'anyone',
				'show_tab'          => 'anyone',
				'admin_name'        => __( 'Dettagli', 'bp-groups-map' ),
				'admin_access'      => 'admins',
			);
			parent::init( $args );
		}

		public function display( $group_id = null ) {
			$group_id = $group_id ?: bp_get_current_group_id();
			$this->render_scheda_readonly( $group_id );
		}

		private function render_scheda_readonly( $group_id ) {
			$nome_bene   = groups_get_groupmeta( $group_id, BPGM_META_NOME_BENE );
			$indirizzo   = groups_get_groupmeta( $group_id, BPGM_META_INDIRIZZO );
			$lat         = groups_get_groupmeta( $group_id, BPGM_META_LAT );
			$lng         = groups_get_groupmeta( $group_id, BPGM_META_LNG );
			$descrizione = groups_get_groupmeta( $group_id, BPGM_META_DESCRIZIONE );
			$categorie   = wp_get_object_terms( $group_id, 'group_cat' );
			$tags        = wp_get_object_terms( $group_id, 'group_tag' );

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
			?>
			<div class="bpgm-scheda-view">
				<?php if ( $nome_bene ) : ?><h3><?php echo esc_html( $nome_bene ); ?></h3><?php endif; ?>
				<?php if ( $indirizzo ) : ?><p class="bpgm-indirizzo">📍 <?php echo esc_html( $indirizzo ); ?></p><?php endif; ?>

				<?php if ( $lat && $lng ) :
					if ( class_exists( 'Leaflet_Map' ) ) {
						echo do_shortcode( '[leaflet-map height="300px" lat="' . esc_attr( $lat ) . '" lng="' . esc_attr( $lng ) . '" zoom="15" scrollwheel="false"]' );
						if ( $custom_marker_url ) { echo do_shortcode( '[leaflet-marker iconUrl="' . esc_url( $custom_marker_url ) . '"]' . esc_html( $nome_bene ?: bp_get_current_group_name() ) . '[/leaflet-marker]' ); }
						else { echo do_shortcode( '[leaflet-marker]' . esc_html( $nome_bene ?: bp_get_current_group_name() ) . '[/leaflet-marker]' ); }
					} else {
						bpgm_enqueue_leaflet();
						wp_enqueue_script( 'bpgm-scheda-map', BPGM_URL . 'assets/js/scheda-map.js', array( 'leaflet' ), BPGM_VERSION, true );
						wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
						wp_localize_script( 'bpgm-scheda-map', 'bpgmScheda', array( 'lat' => floatval( $lat ), 'lng' => floatval( $lng ), 'name' => $nome_bene ?: bp_get_current_group_name(), 'markerIcon' => esc_url_raw( $custom_marker_url ) ) );
						?><div id="bpgm-scheda-map" style="height:300px;"></div><?php
					}
				endif; ?>

				<?php if ( $descrizione ) : ?>
					<h4><?php esc_html_e( 'Descrizione del gruppo', 'bp-groups-map' ); ?></h4>
					<p><?php echo wp_kses_post( wpautop( $descrizione ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! is_wp_error( $categorie ) && $categorie ) : ?>
					<div class="bpgm-categorie">
						<?php foreach ( $categorie as $cat ) : $icon_url = get_term_meta( $cat->term_id, 'bpgm_marker_icon', true ); ?>
							<span class="bpgm-badge bpgm-badge-categoria" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;">
								<?php if ( $icon_url ) : ?><img src="<?php echo esc_url( $icon_url ); ?>" style="width: 16px; height: 16px; object-fit: contain;" alt="" /><?php endif; ?>
								<?php echo esc_html( $cat->name ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! is_wp_error( $tags ) && $tags ) : ?>
					<div class="bpgm-tags">
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

		public function edit_screen( $group_id = null ) {
			if ( ! bp_is_item_admin() ) { return; }
			$group_id = $group_id ?: bp_get_current_group_id();
			$nome_bene   = groups_get_groupmeta( $group_id, BPGM_META_NOME_BENE );
			$indirizzo   = groups_get_groupmeta( $group_id, BPGM_META_INDIRIZZO );
			$descrizione = groups_get_groupmeta( $group_id, BPGM_META_DESCRIZIONE );
			$lat         = groups_get_groupmeta( $group_id, BPGM_META_LAT );
			$lng         = groups_get_groupmeta( $group_id, BPGM_META_LNG );

			wp_enqueue_style( 'bpgm-style', BPGM_URL . 'assets/css/map.css', array(), BPGM_VERSION );
			wp_nonce_field( 'bpgm_save_scheda', 'bpgm_nonce' );
			?>
			<h4><?php esc_html_e( 'Modifica Dettagli Gruppo', 'bp-groups-map' ); ?></h4>
			<p>
				<label for="bpgm_nome_bene"><strong>Nome del bene</strong></label><br/>
				<input type="text" id="bpgm_nome_bene" name="bpgm_nome_bene" class="widefat" value="<?php echo esc_attr( $nome_bene ); ?>" />
			</p>
			<p>
				<label for="bpgm_indirizzo"><strong>Indirizzo del bene</strong></label><br/>
				<input type="text" id="bpgm_indirizzo" name="bpgm_indirizzo" class="widefat" value="<?php echo esc_attr( $indirizzo ); ?>" />
				<?php if ( $lat && $lng ) : ?><br/><span class="bpgm-geo-status">✅ Geolocalizzato: <?php echo esc_html($lat).', '.esc_html($lng); ?></span><?php endif; ?>
			</p>
			<p>
				<label for="bpgm_descrizione"><strong>Descrizione del gruppo</strong></label><br/>
				<textarea id="bpgm_descrizione" name="bpgm_descrizione" class="widefat" rows="4"><?php echo esc_textarea( $descrizione ); ?></textarea>
			</p>
			<?php
			$tutte_categorie   = get_terms( array( 'taxonomy' => 'group_cat', 'hide_empty' => false ) );
			$categorie_gruppo  = wp_get_object_terms( $group_id, 'group_cat', array( 'fields' => 'ids' ) );
			$tag_gruppo        = wp_get_object_terms( $group_id, 'group_tag', array( 'fields' => 'names' ) );
			?>
			<p>
				<strong>Categorie</strong><br/>
				<?php foreach ( $tutte_categorie as $cat ) : ?>
					<label style="display:inline-block; margin-right:12px;">
						<input type="checkbox" name="bpgm_categorie[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, $categorie_gruppo, true ) ); ?> />
						<?php echo esc_html( $cat->name ); ?>
					</label>
				<?php endforeach; ?>
			</p>
			<p>
				<label for="bpgm_tags"><strong>Tag</strong></label><br/>
				<input type="text" id="bpgm_tags" name="bpgm_tags" class="widefat" value="<?php echo esc_attr( implode( ', ', $tag_gruppo ) ); ?>" />
			</p>
			<?php
		}

		public function edit_screen_save( $group_id = null ) {
			if ( ! isset( $_POST['bpgm_nonce'] ) || ! wp_verify_nonce( $_POST['bpgm_nonce'], 'bpgm_save_scheda' ) ) { return; }
			$group_id = $group_id ?: bp_get_current_group_id();
			$nome_bene   = isset( $_POST['bpgm_nome_bene'] ) ? sanitize_text_field( wp_unslash( $_POST['bpgm_nome_bene'] ) ) : '';
			$descrizione = isset( $_POST['bpgm_descrizione'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bpgm_descrizione'] ) ) : '';
			$indirizzo   = isset( $_POST['bpgm_indirizzo'] ) ? sanitize_text_field( wp_unslash( $_POST['bpgm_indirizzo'] ) ) : '';

			groups_update_groupmeta( $group_id, BPGM_META_NOME_BENE, $nome_bene );
			groups_update_groupmeta( $group_id, BPGM_META_DESCRIZIONE, $descrizione );

			wp_set_object_terms( $group_id, isset( $_POST['bpgm_categorie'] ) ? array_map( 'intval', (array) $_POST['bpgm_categorie'] ) : array(), 'group_cat' );
			$tags_raw = isset( $_POST['bpgm_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['bpgm_tags'] ) ) : '';
			wp_set_object_terms( $group_id, array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ), 'group_tag' );

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
	}
	bp_register_group_extension( 'BPGM_Group_Extension' );
}
add_action( 'bp_init', 'bpgm_register_group_extension' );

/* -----------------------------------------------------------------------
 * REST: GRUPPI GEOLOCALIZZATI IN GEOJSON
 * ---------------------------------------------------------------------*/

function bpgm_register_rest_route() {
	register_rest_route( 'abc/v1', '/groups-map', array(
		'methods'             => 'GET',
		'callback'            => 'bpgm_rest_get_groups',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'bpgm_register_rest_route' );

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
		$nome_bene = groups_get_groupmeta( $group->id, BPGM_META_NOME_BENE );
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
				'id' => $group->id, 'name' => $nome_bene ?: $group->name, 'indirizzo' => groups_get_groupmeta( $group->id, BPGM_META_INDIRIZZO ),
				'description' => wp_trim_words( groups_get_groupmeta( $group->id, BPGM_META_DESCRIZIONE ) ?: $group->description, 20 ),
				'categorie' => $categorie_names, 'tags' => $tag_names, 'marker_icon' => esc_url_raw( $marker_custom ),
				'permalink' => bp_get_group_permalink( $group ),
				'avatar' => bp_core_fetch_avatar( array( 'item_id' => $group->id, 'object' => 'group', 'type' => 'thumb', 'html' => false ) ),
				'members' => groups_get_totalmembercount( $group->id ),
			),
		);
	}
	return new WP_REST_Response( array( 'type' => 'FeatureCollection', 'features' => $features, 'term_icons' => $term_icons ), 200 );
}

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
		wp_enqueue_style( 'leaflet-markercluster-default', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', array('leaflet-markercluster'), '1.5.3' );
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

// --- PROTEZIONE CONFLITTI SHORTCODE ---
add_shortcode( 'gruppi_map', 'bpgm_shortcode_map' );
if ( ! shortcode_exists( 'osm_map' ) ) {
	add_shortcode( 'osm_map', 'bpgm_shortcode_map' );
}
