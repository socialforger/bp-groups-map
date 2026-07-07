<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function bpgm_uninstall_cleanup_site() {
	global $wpdb;

	$groupmeta_table = $wpdb->base_prefix . 'bp_groups_groupmeta';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $groupmeta_table ) ) === $groupmeta_table ) {
		$meta_keys = array( 'bpgm_nome_bene', 'bpgm_indirizzo', 'bpgm_lat', 'bpgm_lng', 'bpgm_descrizione' );
		$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$groupmeta_table} WHERE meta_key IN ( {$placeholders} )", $meta_keys ) );
	}

	$taxonomies = array( 'group_cat', 'group_tag' );
	$tax_placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
	$term_taxonomy_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ( {$tax_placeholders} )", $taxonomies ) );

	if ( ! empty( $term_taxonomy_ids ) ) {
		$tt_placeholders = implode( ', ', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );
		$term_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ( {$tt_placeholders} )", $term_taxonomy_ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( {$tt_placeholders} )", $term_taxonomy_ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ( {$tt_placeholders} )", $term_taxonomy_ids ) );

		if ( ! empty( $term_ids ) ) {
			$term_placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
			$still_used = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ( {$term_placeholders} )", $term_ids ) );
			$term_ids_to_delete = array_diff( $term_ids, $still_used );

			if ( ! empty( $term_ids_to_delete ) ) {
				$del_placeholders = implode( ', ', array_fill( 0, count( $term_ids_to_delete ), '%d' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ( {$del_placeholders} )", $term_ids_to_delete ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id IN ( {$del_placeholders} )", $term_ids_to_delete ) );
			}
		}
	}

	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bpgm\_geo\_%' OR option_name LIKE '\_transient\_timeout\_bpgm\_geo\_%'" );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id ); bpgm_uninstall_cleanup_site(); restore_current_blog();
	}
} else {
	bpgm_uninstall_cleanup_site();
}
