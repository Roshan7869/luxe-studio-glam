<?php

/**
 * Auto-provision public pages and lightweight frontend portals.
 *
 * This bridges backend enterprise modules with real frontend-accessible
 * pages so deployments do not look like a plain brochure site.
 */
class GlamLux_Site_Provisioner {

	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'maybe_seed_pages_and_menus' ), 20 );
	}

	public function register_shortcodes() {
		add_shortcode( 'glamlux_operations_portal', array( $this, 'render_operations_portal' ) );
		add_shortcode( 'glamlux_franchise_enquiry_form', array( $this, 'render_franchise_enquiry_form' ) );
	}

	public function maybe_seed_pages_and_menus() {
		if ( 'yes' === get_option( 'glamlux_site_seeded_v1' ) ) {
			return;
		}

		$pages = array(
			array(
				'title' => 'Enterprise Portal',
				'slug' => 'enterprise-portal',
				'content' => '<!-- wp:shortcode -->[glamlux_operations_portal]<!-- /wp:shortcode -->',
			),
			array(
				'title' => 'Franchise Enquiry',
				'slug' => 'franchise-enquiry',
				'content' => '<!-- wp:shortcode -->[glamlux_franchise_enquiry_form]<!-- /wp:shortcode -->',
			),
		);

		$page_ids = array();
		foreach ( $pages as $page ) {
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing ) {
				$page_ids[ $page['title'] ] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title' => $page['title'],
					'post_name' => $page['slug'],
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_content' => $page['content'],
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$page_ids[ $page['title'] ] = $page_id;
			}
		}

		$menu_name = 'Primary Navigation';
		$menu_obj = wp_get_nav_menu_object( $menu_name );
		$menu_id = $menu_obj ? $menu_obj->term_id : wp_create_nav_menu( $menu_name );

		if ( ! is_wp_error( $menu_id ) ) {
			$required_links = array(
				'Home' => home_url( '/' ),
				'Enterprise Portal' => isset( $page_ids['Enterprise Portal'] ) ? get_permalink( $page_ids['Enterprise Portal'] ) : '',
				'Franchise Enquiry' => isset( $page_ids['Franchise Enquiry'] ) ? get_permalink( $page_ids['Franchise Enquiry'] ) : '',
			);

			$existing_items = wp_get_nav_menu_items( $menu_id );
			$existing_titles = array();
			if ( $existing_items ) {
				foreach ( $existing_items as $item ) {
					$existing_titles[] = strtolower( trim( $item->title ) );
				}
			}

			foreach ( $required_links as $title => $url ) {
				if ( empty( $url ) || in_array( strtolower( $title ), $existing_titles, true ) ) {
					continue;
				}
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title' => $title,
						'menu-item-url' => $url,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'custom',
					)
				);
			}

			$locations = get_theme_mod( 'nav_menu_locations', array() );
			if ( ! is_array( $locations ) ) {
				$locations = array();
			}
			if ( empty( $locations['primary'] ) ) {
				$locations['primary'] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		}

		update_option( 'glamlux_site_seeded_v1', 'yes', false );
	}

	public function render_operations_portal() {
		if ( ! is_user_logged_in() ) {
			return '<div class="glamlux-portal-box"><h3>Enterprise Portal</h3><p>Please log in with admin or franchise credentials to view operations.</p></div>';
		}

		ob_start();
		?>
		<div class="glamlux-portal-box">
			<h3>Enterprise Operations Portal</h3>
			<p>Live operational snapshot connected to GlamLux enterprise APIs.</p>
			<div id="glamlux-ops-status">Loading operations summary…</div>
		</div>
		<script>
		(async function(){
			const target = document.getElementById('glamlux-ops-status');
			if (!target) return;
			try {
				const response = await fetch((window.GlamLux?.apiRoot || '/wp-json/glamlux/v1/') + 'operations/summary', {
					headers: { 'X-WP-Nonce': window.GlamLux?.nonce || '' }
				});
				if (!response.ok) {
					target.innerHTML = '<p>Unable to load operations snapshot. Please ensure your user role has platform access.</p>';
					return;
				}
				const data = await response.json();
				target.innerHTML = `
					<ul>
						<li><strong>Platform Health:</strong> ${data.health || 'unknown'}</li>
						<li><strong>Appointments Today:</strong> ${data.operations?.appointments_today ?? 0}</li>
						<li><strong>Pending Appointments:</strong> ${data.operations?.pending_appointments ?? 0}</li>
						<li><strong>Active Staff:</strong> ${data.operations?.active_staff ?? 0}</li>
						<li><strong>Active Memberships:</strong> ${data.operations?.active_memberships ?? 0}</li>
					</ul>
				`;
			} catch (e) {
				target.innerHTML = '<p>Portal error: could not contact backend APIs.</p>';
			}
		})();
		</script>
		<style>
		.glamlux-portal-box{max-width:820px;margin:24px auto;padding:24px;border:1px solid #e6e6e6;border-radius:12px;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.06)}
		.glamlux-portal-box h3{margin-top:0}
		</style>
		<?php
		return ob_get_clean();
	}

	public function render_franchise_enquiry_form() {
		ob_start();
		?>
		<div class="glamlux-portal-box">
			<h3>Franchise Enquiry</h3>
			<p>Submit a lead directly into GlamLux CRM.</p>
			<form id="glamlux-lead-form">
				<p><input type="text" name="name" placeholder="Full name" required style="width:100%;padding:10px"></p>
				<p><input type="email" name="email" placeholder="Email" required style="width:100%;padding:10px"></p>
				<p><input type="tel" name="phone" placeholder="Phone" required style="width:100%;padding:10px"></p>
				<p><input type="text" name="state" placeholder="State" required style="width:100%;padding:10px"></p>
				<p><button type="submit" style="background:#C6A75E;color:#fff;border:0;padding:10px 16px;border-radius:6px">Submit Enquiry</button></p>
			</form>
			<div id="glamlux-lead-result"></div>
		</div>
		<script>
		(function(){
			const form = document.getElementById('glamlux-lead-form');
			const result = document.getElementById('glamlux-lead-result');
			if (!form || !result) return;
			form.addEventListener('submit', async function(e){
				e.preventDefault();
				result.textContent = 'Submitting...';
				try {
					const payload = Object.fromEntries(new FormData(form).entries());
					const res = await fetch((window.GlamLux?.apiRoot || '/wp-json/glamlux/v1/') + 'leads', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(payload)
					});
					const data = await res.json();
					if (!res.ok) {
						result.textContent = data?.message || 'Unable to submit enquiry.';
						return;
					}
					result.textContent = 'Thank you. Your enquiry has been submitted.';
					form.reset();
				} catch (err) {
					result.textContent = 'Network error while submitting enquiry.';
				}
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
