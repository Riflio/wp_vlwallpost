<?php

class SetupVKWP {
	
	function __construct($path) {
		register_activation_hook($path, array(&$this, 'install')); //-- ловушка на включение плагина
		register_deactivation_hook($path, array(&$this, 'uninstall')); //-- на выключение
		
	}
	
	public function install() {
		global $wpdb;

		$charset_collate = '';	
		if ( ! empty($wpdb->charset) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty($wpdb->collate) )
			$charset_collate .= " COLLATE $wpdb->collate";
		
		$sql = "CREATE TABLE {$wpdb->prefix}vktemp (
					  tid mediumint(9) NOT NULL AUTO_INCREMENT, 
					  vk_id mediumint(9),
					  exportToVK BOOL,
					  exportToAlbum bigint(20),
					  ID bigint(20),
					  post_title text,
					  post_content longtext,
					  UNIQUE KEY tid (tid)
		);";

		$sql2="CREATE TABLE {$wpdb->prefix}vkmeta (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				vk_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY	(meta_id),
				KEY vk_id (vk_id),
				KEY meta_key (meta_key)
		) $charset_collate;";
		
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		dbDelta($sql2);		
	}
	
	public function uninstall() {
		global $wpdb;
		$sql="DROP TABLE  IF EXISTS {$wpdb->prefix}vktemp";
		$wpdb->query($sql);		
		$sql="DROP TABLE  IF EXISTS {$wpdb->prefix}vkmeta";
		$wpdb->query($sql);		
	}

}


?>