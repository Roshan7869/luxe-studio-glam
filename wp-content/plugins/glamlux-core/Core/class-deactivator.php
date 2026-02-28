<?php
/**
 * Fired during plugin deactivation.
 */
class GlamLux_Deactivator {

	/**
	 * Clean up rewrite rules and any temporary data.
	 * We do NOT drop tables or remove roles here to prevent data loss on accidental deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

}
