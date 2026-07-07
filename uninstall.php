<?php
/**
 * Uninstall handler per BP Groups Map.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function bpgm_uninstall_cleanup_site() {
	global $wpdb;

	$groupmeta_table = $wpdb->base_prefix . 'bp_groups_groupmeta';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $groupmeta_table ) ) === $groupmeta_table ) {
		$meta_keys = array(
			'bpgm_nome_bene',
			'bpgm_indirizzo',
			'bpgm_lat',
			'bpgm_lng',
			'bpgm_descrizione',
		);

		$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$groupmeta_table} WHERE meta_key IN ( {$placeholders} )",
				$meta_keys
			)
		);
	}

	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bpgm\_geo\_%' OR option_name LIKE '\_transient\_timeout\_bpgm\_geo\_%'"
	);
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		bpgm_uninstall_cleanup_site();
		restore_current_blog();
	}
} else {
	bpgm_uninstall_cleanup_site();
}
