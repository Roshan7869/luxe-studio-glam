<?php
/**
 * Inventory Admin UI
 * RULE: Separate class, non-blocking UI (loads via REST API).
 */
class GlamLux_Inventory_Admin
{
    public function render_admin_page()
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Inventory Management</h1>';
        echo '<hr class="wp-header-end">';
        echo '<p>Live inventory from the Service Layer (loaded asynchronously via REST APIs).</p>';
        echo '<div id="glamlux-inventory-root"><div class="notice notice-info"><p>Loading inventory data...</p></div></div>';
?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if (!window.GlamLux) return;
			// Requesting items for salon ID 1 as standard for admin view
			fetch(window.GlamLux.apiRoot + 'inventory?salon_id=1', {
				headers: { 'X-WP-Nonce': window.GlamLux.nonce }
			})
			.then(r => r.json())
			.then(res => {
				const root = document.getElementById('glamlux-inventory-root');
				if (res.success && res.data && res.data.length > 0) {
					let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Item Name</th><th>SKU</th><th>Stock</th><th>Reorder Level</th><th>Unit Price</th></tr></thead><tbody>';
					res.data.forEach(item => {
						html += `<tr>
							<td><strong>${item.name}</strong></td>
							<td>${item.sku}</td>
							<td>${item.current_stock}</td>
							<td>${item.reorder_level}</td>
							<td>$${item.price_per_unit}</td>
						</tr>`;
					});
					html += '</tbody></table>';
					root.innerHTML = html;
				} else {
					root.innerHTML = '<div class="notice notice-warning"><p>No inventory items found.</p></div>';
				}
			})
			.catch(e => {
				document.getElementById('glamlux-inventory-root').innerHTML = '<div class="notice notice-error"><p>Failed to load inventory.</p></div>';
			});
		});
		</script>
		<?php
        echo '</div>';
    }
}
