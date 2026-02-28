<?php
/**
 * Generator: Write class-glamlux-content-manager.php into the container.
 * Run via: docker exec <container> php /var/www/html/wp-content/plugins/glamlux-core/scripts/write-content-manager.php
 */
$target = __DIR__ . '/../includes/class-glamlux-content-manager.php';

$code = <<<'PHPEOF'
<?php
/**
 * GlamLux Content Manager
 *
 * WordPress-first content governance layer for GlamLux2Lux.
 * Connects: CPTs, Franchise scoping, Meta boxes, Permissions UI,
 *           WP Customizer, Data sync bridge, REST API content routes.
 */
class GlamLux_Content_Manager {

	public function __construct() {
		add_action( 'init',              [ $this, 'register_post_types' ] );
		add_action( 'init',              [ $this, 'register_taxonomies' ] );
		add_action( 'add_meta_boxes',    [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post',         [ $this, 'save_meta_boxes' ], 10, 2 );
		add_action( 'pre_get_posts',     [ $this, 'scope_franchise_content' ] );
		add_action( 'save_post_gl_announcement', [ $this, 'sync_announcement' ], 10, 2 );
		add_action( 'save_post_gl_offer',        [ $this, 'sync_offer' ],        10, 2 );
		add_action( 'before_delete_post',         [ $this, 'on_delete_post' ] );
		add_action( 'save_post',         [ $this, 'invalidate_content_cache' ] );
		add_action( 'admin_menu',        [ $this, 'add_permission_manager_page' ] );
		add_action( 'admin_post_gl_save_user_perms', [ $this, 'handle_save_user_perms' ] );
		add_action( 'customize_register', [ $this, 'register_customizer_settings' ] );
		add_action( 'rest_api_init',     [ $this, 'register_content_routes' ] );
		add_filter( 'manage_gl_announcement_posts_columns',       [ $this, 'announcement_columns' ] );
		add_action( 'manage_gl_announcement_posts_custom_column', [ $this, 'announcement_column_data' ], 10, 2 );
		add_filter( 'manage_gl_offer_posts_columns',              [ $this, 'offer_columns' ] );
		add_action( 'manage_gl_offer_posts_custom_column',        [ $this, 'offer_column_data' ],        10, 2 );
		add_action( 'post_submitbox_misc_actions', [ $this, 'add_content_ownership_info' ] );
	}

	/* -----------------------------------------------------------------------
	   1. Custom Post Types
	----------------------------------------------------------------------- */

	public function register_post_types() {
		register_post_type( 'gl_announcement', [
			'label'           => 'Announcements',
			'labels'          => [
				'name'               => 'Announcements',
				'singular_name'      => 'Announcement',
				'add_new'            => 'Add Announcement',
				'add_new_item'       => 'Add New Announcement',
				'edit_item'          => 'Edit Announcement',
				'search_items'       => 'Search Announcements',
				'not_found'          => 'No announcements found',
				'menu_name'          => 'Announcements',
			],
			'public'          => true,
			'show_in_rest'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ],
			'menu_icon'       => 'dashicons-megaphone',
			'menu_position'   => 25,
			'has_archive'     => 'announcements',
			'rewrite'         => [ 'slug' => 'announcements', 'with_front' => false ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		] );

		register_post_type( 'gl_offer', [
			'label'           => 'Offers & Promotions',
			'labels'          => [
				'name'          => 'Offers & Promos',
				'singular_name' => 'Offer',
				'add_new'       => 'Add Offer',
				'add_new_item'  => 'Add New Offer',
				'edit_item'     => 'Edit Offer',
				'search_items'  => 'Search Offers',
				'not_found'     => 'No offers found',
				'menu_name'     => 'Offers & Promos',
			],
			'public'          => true,
			'show_in_rest'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
			'menu_icon'       => 'dashicons-tag',
			'menu_position'   => 26,
			'has_archive'     => 'offers',
			'rewrite'         => [ 'slug' => 'offers', 'with_front' => false ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		] );

		register_post_type( 'gl_gallery', [
			'label'           => 'Gallery',
			'labels'          => [
				'name'          => 'Gallery',
				'singular_name' => 'Gallery Item',
				'add_new'       => 'Add Gallery Item',
				'add_new_item'  => 'Add New Gallery Item',
				'edit_item'     => 'Edit Gallery Item',
				'not_found'     => 'No gallery items found',
				'menu_name'     => 'Gallery',
			],
			'public'          => true,
			'show_in_rest'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail', 'author' ],
			'menu_icon'       => 'dashicons-format-gallery',
			'menu_position'   => 27,
			'has_archive'     => 'gallery',
			'rewrite'         => [ 'slug' => 'gallery', 'with_front' => false ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		] );

		if ( get_option( 'glamlux_cpt_version' ) !== '1.0' ) {
			flush_rewrite_rules();
			update_option( 'glamlux_cpt_version', '1.0' );
		}
	}

	/* -----------------------------------------------------------------------
	   2. Taxonomies
	----------------------------------------------------------------------- */

	public function register_taxonomies() {
		register_taxonomy( 'gl_service_category', [ 'gl_offer', 'gl_gallery' ], [
			'label'        => 'Service Category',
			'hierarchical' => true,
			'show_in_rest' => true,
			'rewrite'      => [ 'slug' => 'service-category' ],
		] );
		register_taxonomy( 'gl_content_scope', [ 'gl_announcement', 'gl_offer' ], [
			'label'        => 'Content Scope',
			'hierarchical' => false,
			'show_in_rest' => true,
			'rewrite'      => [ 'slug' => 'content-scope' ],
		] );
	}

	/* -----------------------------------------------------------------------
	   3. Meta Boxes
	----------------------------------------------------------------------- */

	public function add_meta_boxes() {
		add_meta_box( 'gl_franchise_meta', '🏢 Franchise Assignment',
			[ $this, 'render_franchise_meta_box' ],
			[ 'gl_announcement', 'gl_offer', 'gl_gallery', 'post', 'page' ],
			'side', 'high' );
		add_meta_box( 'gl_offer_details', '💰 Offer Details',
			[ $this, 'render_offer_meta_box' ], 'gl_offer', 'normal', 'high' );
		add_meta_box( 'gl_gallery_type', '🖼 Gallery Type',
			[ $this, 'render_gallery_meta_box' ], 'gl_gallery', 'side', 'default' );
	}

	public function render_franchise_meta_box( $post ) {
		global $wpdb;
		wp_nonce_field( 'gl_franchise_meta_nonce', 'gl_franchise_nonce' );
		$current     = get_post_meta( $post->ID, '_gl_franchise_id', true );
		$is_super    = current_user_can( 'manage_glamlux_platform' );
		$franchises  = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}gl_franchises ORDER BY name", ARRAY_A ) ?: [];
		if ( $is_super ) {
			echo '<p style="font-size:12px;color:#666;margin-bottom:8px">Assign to a franchise, or leave blank for platform-wide.</p>';
			echo '<select name="gl_franchise_id" style="width:100%"><option value="">— Platform Wide —</option>';
			foreach ( $franchises as $f ) {
				printf( '<option value="%d" %s>%s</option>', $f['id'], selected( $current, $f['id'], false ), esc_html( $f['name'] ) );
			}
			echo '</select>';
		} else {
			$my = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$wpdb->prefix}gl_franchises WHERE admin_id=%d LIMIT 1", get_current_user_id() ) );
			if ( $my ) {
				echo '<p><strong>' . esc_html( $my->name ) . '</strong></p>';
				echo '<p style="font-size:11px;color:#888">Content will publish under your franchise.</p>';
				echo '<input type="hidden" name="gl_franchise_id" value="' . esc_attr( $my->id ) . '">';
			} else {
				echo '<p style="color:orange;font-size:12px">⚠ No franchise linked. Contact platform admin.</p>';
			}
		}
	}

	public function render_offer_meta_box( $post ) {
		wp_nonce_field( 'gl_offer_meta_nonce', 'gl_offer_nonce' );
		$price  = get_post_meta( $post->ID, '_gl_offer_price',      true );
		$orig   = get_post_meta( $post->ID, '_gl_offer_orig_price', true );
		$expiry = get_post_meta( $post->ID, '_gl_offer_expiry',     true );
		$disc   = get_post_meta( $post->ID, '_gl_offer_discount',   true );
		echo '<table class="form-table" style="margin:0">';
		echo '<tr><th style="width:140px"><label>Offer Price (₹)</label></th><td><input type="number" name="gl_offer_price"      value="' . esc_attr($price)  . '" step="0.01" min="0" class="small-text"></td></tr>';
		echo '<tr><th><label>Original Price (₹)</label></th><td><input type="number" name="gl_offer_orig_price" value="' . esc_attr($orig)   . '" step="0.01" min="0" class="small-text"> <span class="description">For strikethrough display</span></td></tr>';
		echo '<tr><th><label>Discount %</label></th><td><input type="number" name="gl_offer_discount"   value="' . esc_attr($disc)   . '" step="1" min="0" max="100" class="small-text">%</td></tr>';
		echo '<tr><th><label>Expiry Date</label></th><td><input type="date"   name="gl_offer_expiry"     value="' . esc_attr($expiry) . '"> <span class="description">Leave blank = no expiry</span></td></tr>';
		echo '</table>';
	}

	public function render_gallery_meta_box( $post ) {
		wp_nonce_field( 'gl_gallery_meta_nonce', 'gl_gallery_nonce' );
		$type = get_post_meta( $post->ID, '_gl_gallery_type', true ) ?: 'salon';
		echo '<label><strong>Gallery Type</strong></label><br>';
		foreach ( [ 'salon' => '🏛 Salon Ambience', 'before_after' => '✨ Before & After', 'staff' => '👩‍💼 Staff Portfolio', 'product' => '🛍 Product Showcase' ] as $val => $label ) {
			printf( '<label style="display:block;margin-top:6px"><input type="radio" name="gl_gallery_type" value="%s" %s> %s</label>', esc_attr($val), checked($type,$val,false), esc_html($label) );
		}
	}

	public function save_meta_boxes( $post_id, $post ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( isset($_POST['gl_franchise_nonce']) && wp_verify_nonce($_POST['gl_franchise_nonce'],'gl_franchise_meta_nonce') ) {
			update_post_meta( $post_id, '_gl_franchise_id', absint($_POST['gl_franchise_id'] ?? 0) );
		}
		if ( isset($_POST['gl_offer_nonce']) && wp_verify_nonce($_POST['gl_offer_nonce'],'gl_offer_meta_nonce') ) {
			update_post_meta( $post_id, '_gl_offer_price',      (float)($_POST['gl_offer_price']      ?? 0) );
			update_post_meta( $post_id, '_gl_offer_orig_price', (float)($_POST['gl_offer_orig_price'] ?? 0) );
			update_post_meta( $post_id, '_gl_offer_discount',   (int)  ($_POST['gl_offer_discount']   ?? 0) );
			update_post_meta( $post_id, '_gl_offer_expiry',     sanitize_text_field($_POST['gl_offer_expiry'] ?? '') );
		}
		if ( isset($_POST['gl_gallery_nonce']) && wp_verify_nonce($_POST['gl_gallery_nonce'],'gl_gallery_meta_nonce') ) {
			$allowed = ['salon','before_after','staff','product'];
			$t = sanitize_text_field($_POST['gl_gallery_type'] ?? 'salon');
			update_post_meta( $post_id, '_gl_gallery_type', in_array($t,$allowed,true) ? $t : 'salon' );
		}
	}

	/* -----------------------------------------------------------------------
	   4. Franchise content scoping
	----------------------------------------------------------------------- */

	public function scope_franchise_content( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		$user = wp_get_current_user();
		if ( ! $user->ID ) return;
		if ( current_user_can('manage_glamlux_platform') || current_user_can('manage_options') ) return;
		if ( current_user_can('manage_glamlux_franchise') ) {
			if ( in_array( $query->get('post_type'), ['gl_announcement','gl_offer','gl_gallery','post'], true ) ) {
				$query->set( 'author', $user->ID );
			}
		}
	}

	/* -----------------------------------------------------------------------
	   5. Data sync bridge
	----------------------------------------------------------------------- */

	public function sync_announcement( $post_id, $post ) {
		if ( 'publish' !== $post->post_status ) return;
		delete_transient('gl_announcements_cache');
		do_action('glamlux_announcement_published', $post_id, get_post_meta($post_id,'_gl_franchise_id',true));
	}
	public function sync_offer( $post_id, $post ) {
		if ( 'publish' !== $post->post_status ) return;
		delete_transient('gl_offers_cache');
		do_action('glamlux_offer_published', $post_id, get_post_meta($post_id,'_gl_franchise_id',true));
	}
	public function on_delete_post( $post_id ) {
		if ( in_array( get_post_type($post_id), ['gl_announcement','gl_offer','gl_gallery'], true ) ) {
			delete_transient('gl_announcements_cache');
			delete_transient('gl_offers_cache');
			delete_transient('gl_gallery_cache');
		}
	}
	public function invalidate_content_cache( $post_id ) {
		$type = get_post_type($post_id);
		if ( 'gl_announcement' === $type ) delete_transient('gl_announcements_cache');
		if ( 'gl_offer'        === $type ) delete_transient('gl_offers_cache');
		if ( 'gl_gallery'      === $type ) delete_transient('gl_gallery_cache');
	}

	/* -----------------------------------------------------------------------
	   6. Permission Manager — WP Admin UI
	----------------------------------------------------------------------- */

	public function add_permission_manager_page() {
		add_submenu_page( 'glamlux-dashboard', 'User Content Permissions', '🔐 Permissions',
			'manage_glamlux_platform', 'glamlux-permissions', [ $this, 'render_permission_manager' ] );
	}

	public function render_permission_manager() {
		if ( ! current_user_can('manage_glamlux_platform') ) wp_die('Insufficient permissions.');
		$gl_roles    = ['glamlux_super_admin','glamlux_franchise_admin','glamlux_staff','glamlux_state_manager'];
		$users       = get_users(['role__in'=>$gl_roles,'number'=>200,'orderby'=>'display_name']);
		$content_caps = [
			'edit_posts'=>'Write Posts','publish_posts'=>'Publish Posts',
			'edit_pages'=>'Edit Pages','publish_pages'=>'Publish Pages',
			'upload_files'=>'Upload Media','manage_categories'=>'Manage Categories',
			'moderate_comments'=>'Moderate Comments',
		];
		if (isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>✅ Permissions saved.</p></div>';
		echo '<div class="wrap"><h1>🔐 User Content Permissions</h1>';
		echo '<p style="color:#666;max-width:680px">Control what each GlamLux user can do with WordPress content. Changes apply immediately.</p><hr class="wp-header-end">';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="gl_save_user_perms">';
		wp_nonce_field('gl_user_perms_save','gl_perms_nonce');
		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:16px"><thead><tr><th style="width:200px">User</th><th>Role</th>';
		foreach ($content_caps as $cap=>$label) echo '<th style="text-align:center;font-size:11px">' . esc_html($label) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ($users as $user) {
			echo '<tr>';
			printf('<td><strong>%s</strong><br><span style="font-size:11px;color:#888">%s</span></td><td>%s</td>',
				esc_html($user->display_name), esc_html($user->user_email),
				esc_html(implode(', ', array_map(fn($r)=>ucwords(str_replace(['glamlux_','_'],['',''],$r)),$user->roles))));
			foreach ($content_caps as $cap=>$label) {
				printf('<td style="text-align:center"><input type="checkbox" name="user_caps[%d][%s]" value="1" %s></td>',
					$user->ID, esc_attr($cap), checked(user_can($user->ID,$cap),true,false));
			}
			echo '</tr>';
		}
		echo '</tbody></table><p style="margin-top:16px"><input type="submit" value="Save All Permissions" class="button button-primary button-large"></p></form></div>';
		echo '<style>.wp-list-table th,.wp-list-table td{vertical-align:middle;}</style>';
	}

	public function handle_save_user_perms() {
		if (!isset($_POST['gl_perms_nonce'])||!wp_verify_nonce($_POST['gl_perms_nonce'],'gl_user_perms_save')) wp_die('Nonce failed.');
		if (!current_user_can('manage_glamlux_platform')) wp_die('Insufficient permissions.');
		$content_caps = ['edit_posts','publish_posts','edit_pages','publish_pages','upload_files','manage_categories','moderate_comments'];
		$gl_roles     = ['glamlux_super_admin','glamlux_franchise_admin','glamlux_staff','glamlux_state_manager'];
		$all_users    = get_users(['role__in'=>$gl_roles,'number'=>200]);
		$submitted    = $_POST['user_caps'] ?? [];
		foreach ($all_users as $user) {
			foreach ($content_caps as $cap) {
				isset($submitted[$user->ID][$cap]) ? $user->add_cap($cap) : $user->remove_cap($cap);
			}
		}
		wp_redirect(admin_url('admin.php?page=glamlux-permissions&saved=1'));
		exit;
	}

	/* -----------------------------------------------------------------------
	   7. WP Customizer
	----------------------------------------------------------------------- */

	public function register_customizer_settings( $wp_customize ) {
		$wp_customize->add_panel('glamlux_platform',['title'=>'💎 GlamLux Platform','priority'=>1]);
		foreach (['glamlux_hero'=>'🖼 Hero','glamlux_brand'=>'🎨 Brand','glamlux_contact'=>'📞 Contact','glamlux_social'=>'📱 Social','glamlux_cta'=>'🚀 CTA'] as $id=>$title) {
			$wp_customize->add_section($id,['title'=>$title,'panel'=>'glamlux_platform','priority'=>10]);
		}
		// Hero
		$this->cz_text($wp_customize,'glamlux_hero_headline','glamlux_hero','Hero Headline','The Art of Refined Beauty');
		$this->cz_text($wp_customize,'glamlux_hero_subtitle','glamlux_hero','Hero Subtitle','Seamless luxury beauty management and global franchise growth — powered by enterprise-grade SaaS intelligence.');
		$this->cz_text($wp_customize,'glamlux_hero_badge','glamlux_hero','Hero Badge',"India's Premier Luxury Beauty Franchise");
		$wp_customize->add_setting('glamlux_hero_bg',['default'=>'','transport'=>'refresh','sanitize_callback'=>'esc_url_raw']);
		$wp_customize->add_control(new WP_Customize_Image_Control($wp_customize,'glamlux_hero_bg',['label'=>'Hero Background Image','section'=>'glamlux_hero']));
		// Brand
		$wp_customize->add_setting('glamlux_primary_color',['default'=>'#C6A75E','transport'=>'postMessage','sanitize_callback'=>'sanitize_hex_color']);
		$wp_customize->add_control(new WP_Customize_Color_Control($wp_customize,'glamlux_primary_color',['label'=>'Primary Gold Color','section'=>'glamlux_brand']));
		$wp_customize->add_setting('glamlux_dark_bg',['default'=>'#121212','transport'=>'postMessage','sanitize_callback'=>'sanitize_hex_color']);
		$wp_customize->add_control(new WP_Customize_Color_Control($wp_customize,'glamlux_dark_bg',['label'=>'Dark Background','section'=>'glamlux_brand']));
		// Contact
		$this->cz_text($wp_customize,'glamlux_phone','glamlux_contact','Phone','+91 98765 43210');
		$this->cz_text($wp_customize,'glamlux_whatsapp','glamlux_contact','WhatsApp','+91 98765 43210');
		$this->cz_text($wp_customize,'glamlux_email','glamlux_contact','Email','info@glamlux2lux.com');
		$this->cz_text($wp_customize,'glamlux_address','glamlux_contact','Address','Mumbai, Maharashtra, India');
		// Social
		$this->cz_url($wp_customize,'glamlux_instagram','glamlux_social','Instagram','https://instagram.com/glamlux2lux');
		$this->cz_url($wp_customize,'glamlux_facebook','glamlux_social','Facebook','https://facebook.com/glamlux2lux');
		$this->cz_url($wp_customize,'glamlux_youtube','glamlux_social','YouTube','');
		$this->cz_url($wp_customize,'glamlux_linkedin','glamlux_social','LinkedIn','');
		// CTA
		$this->cz_text($wp_customize,'glamlux_cta_label','glamlux_cta','Button Label','Book Appointment');
		$this->cz_url($wp_customize,'glamlux_cta_url','glamlux_cta','Button URL','/contact');
		$this->cz_text($wp_customize,'glamlux_franchise_cta_label','glamlux_cta','Franchise CTA Label','Own a Franchise');
		$this->cz_url($wp_customize,'glamlux_franchise_cta_url','glamlux_cta','Franchise CTA URL','/franchise');
	}
	private function cz_text($c,$id,$sec,$label,$def){ $c->add_setting($id,['default'=>$def,'transport'=>'refresh','sanitize_callback'=>'sanitize_text_field']); $c->add_control($id,['label'=>$label,'section'=>$sec,'type'=>'text']); }
	private function cz_url($c,$id,$sec,$label,$def){ $c->add_setting($id,['default'=>$def,'transport'=>'refresh','sanitize_callback'=>'esc_url_raw']); $c->add_control($id,['label'=>$label,'section'=>$sec,'type'=>'url']); }

	/* -----------------------------------------------------------------------
	   8. REST API content endpoints
	----------------------------------------------------------------------- */

	public function register_content_routes() {
		register_rest_route('glamlux/v1','/announcements',[
			'methods'=>'GET','callback'=>[$this,'api_get_announcements'],'permission_callback'=>'__return_true',
			'args'=>['franchise_id'=>['sanitize_callback'=>'absint','default'=>0],'limit'=>['sanitize_callback'=>'absint','default'=>10]],
		]);
		register_rest_route('glamlux/v1','/offers',[
			'methods'=>'GET','callback'=>[$this,'api_get_offers'],'permission_callback'=>'__return_true',
			'args'=>['franchise_id'=>['sanitize_callback'=>'absint','default'=>0],'active_only'=>['sanitize_callback'=>'rest_sanitize_boolean','default'=>true],'limit'=>['sanitize_callback'=>'absint','default'=>12]],
		]);
		register_rest_route('glamlux/v1','/gallery',[
			'methods'=>'GET','callback'=>[$this,'api_get_gallery'],'permission_callback'=>'__return_true',
			'args'=>['franchise_id'=>['sanitize_callback'=>'absint','default'=>0],'type'=>['sanitize_callback'=>'sanitize_key','default'=>''],'limit'=>['sanitize_callback'=>'absint','default'=>20]],
		]);
		register_rest_route('glamlux/v1','/site-config',[
			'methods'=>'GET','callback'=>[$this,'api_get_site_config'],'permission_callback'=>'__return_true',
		]);
	}

	public function api_get_announcements( $req ) {
		$key = 'gl_announcements_cache_' . $req['franchise_id'];
		if ($c = get_transient($key)) return rest_ensure_response($c);
		$mq = $req['franchise_id'] ? [['relation'=>'OR',['key'=>'_gl_franchise_id','value'=>$req['franchise_id'],'compare'=>'='],['key'=>'_gl_franchise_id','value'=>'','compare'=>'='],['key'=>'_gl_franchise_id','compare'=>'NOT EXISTS']]] : [];
		$posts = get_posts(['post_type'=>'gl_announcement','post_status'=>'publish','posts_per_page'=>min((int)$req['limit'],50),'meta_query'=>$mq,'orderby'=>'date','order'=>'DESC']);
		$data  = array_map(fn($p)=>$this->fmt($p,'announcement'),$posts);
		set_transient($key,$data,5*MINUTE_IN_SECONDS);
		return rest_ensure_response($data);
	}

	public function api_get_offers( $req ) {
		$key = 'gl_offers_cache_' . $req['franchise_id'];
		if ($c = get_transient($key)) return rest_ensure_response($c);
		$mq  = [];
		if ($req['active_only']) $mq[] = ['relation'=>'OR',['key'=>'_gl_offer_expiry','value'=>date('Y-m-d'),'compare'=>'>=','type'=>'DATE'],['key'=>'_gl_offer_expiry','value'=>'','compare'=>'='],['key'=>'_gl_offer_expiry','compare'=>'NOT EXISTS']];
		if ($req['franchise_id']) $mq[] = ['relation'=>'OR',['key'=>'_gl_franchise_id','value'=>$req['franchise_id']],['key'=>'_gl_franchise_id','value'=>''],['key'=>'_gl_franchise_id','compare'=>'NOT EXISTS']];
		$posts = get_posts(['post_type'=>'gl_offer','post_status'=>'publish','posts_per_page'=>min((int)$req['limit'],50),'meta_query'=>$mq,'orderby'=>'date','order'=>'DESC']);
		$data  = array_map(fn($p)=>$this->fmt($p,'offer'),$posts);
		set_transient($key,$data,5*MINUTE_IN_SECONDS);
		return rest_ensure_response($data);
	}

	public function api_get_gallery( $req ) {
		$key = 'gl_gallery_cache_' . $req['franchise_id'] . '_' . $req['type'];
		if ($c = get_transient($key)) return rest_ensure_response($c);
		$mq  = [];
		if ($req['type']) $mq[] = ['key'=>'_gl_gallery_type','value'=>$req['type']];
		if ($req['franchise_id']) $mq[] = ['relation'=>'OR',['key'=>'_gl_franchise_id','value'=>$req['franchise_id']],['key'=>'_gl_franchise_id','value'=>''],['key'=>'_gl_franchise_id','compare'=>'NOT EXISTS']];
		$posts = get_posts(['post_type'=>'gl_gallery','post_status'=>'publish','posts_per_page'=>min((int)$req['limit'],100),'meta_query'=>$mq]);
		$data  = array_map(fn($p)=>$this->fmt($p,'gallery'),$posts);
		set_transient($key,$data,10*MINUTE_IN_SECONDS);
		return rest_ensure_response($data);
	}

	public function api_get_site_config() {
		$defaults = ['hero_headline'=>'The Art of Refined Beauty','hero_subtitle'=>'Experience the pinnacle of luxury minimalism.','hero_badge'=>"India's Premier Luxury Beauty Franchise",'primary_color'=>'#C6A75E','dark_bg'=>'#121212','phone'=>'+91 98765 43210','whatsapp'=>'+91 98765 43210','email'=>'info@glamlux2lux.com','address'=>'Mumbai, Maharashtra, India','instagram'=>'https://instagram.com/glamlux2lux','facebook'=>'https://facebook.com/glamlux2lux','youtube'=>'','linkedin'=>'','cta_label'=>'Book Appointment','cta_url'=>'/contact','franchise_cta_label'=>'Own a Franchise','franchise_cta_url'=>'/franchise'];
		$config = [];
		foreach ($defaults as $k=>$d) $config[$k] = get_theme_mod('glamlux_'.$k,$d);
		$config['nav_menu'] = $this->get_nav_menu_items('primary');
		return rest_ensure_response($config);
	}

	private function get_nav_menu_items( $loc ) {
		$locs = get_nav_menu_locations();
		if (!isset($locs[$loc])) return [];
		$items = wp_get_nav_menu_items($locs[$loc]);
		if (!$items) return [];
		return array_map(fn($i)=>['title'=>$i->title,'url'=>$i->url,'target'=>$i->target],$items);
	}

	private function fmt( $post, $type ) {
		$meta = get_post_meta($post->ID);
		$d = ['id'=>$post->ID,'title'=>get_the_title($post),'excerpt'=>get_the_excerpt($post),'content'=>apply_filters('the_content',$post->post_content),'date'=>$post->post_date,'url'=>get_permalink($post),'image'=>get_the_post_thumbnail_url($post,'large')?:'','franchise_id'=>(int)($meta['_gl_franchise_id'][0]??0),'author'=>get_the_author_meta('display_name',$post->post_author)];
		if ('offer'   === $type) { $d['price']=(float)($meta['_gl_offer_price'][0]??0);$d['orig_price']=(float)($meta['_gl_offer_orig_price'][0]??0);$d['discount']=(int)($meta['_gl_offer_discount'][0]??0);$d['expiry']=$meta['_gl_offer_expiry'][0]??'';$d['is_active']=empty($d['expiry'])||$d['expiry']>=date('Y-m-d'); }
		if ('gallery' === $type) { $d['gallery_type']=$meta['_gl_gallery_type'][0]??'salon'; }
		return $d;
	}

	/* -----------------------------------------------------------------------
	   9. Admin column customisation
	----------------------------------------------------------------------- */

	public function announcement_columns($cols){ return array_merge(array_slice($cols,0,2),['franchise'=>'Franchise','scope'=>'Scope'],array_slice($cols,2)); }
	public function announcement_column_data($col,$id) {
		global $wpdb;
		if ('franchise'===$col){ $fid=(int)get_post_meta($id,'_gl_franchise_id',true);$n=$fid?$wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}gl_franchises WHERE id=%d",$fid)):null;echo '<span style="color:#C6A75E;font-weight:600">'.esc_html($n?:'Platform-wide').'</span>'; }
		if ('scope'===$col){ $fid=(int)get_post_meta($id,'_gl_franchise_id',true);echo $fid?'<span style="background:#FFF3CD;padding:2px 6px;border-radius:4px;font-size:11px">Franchise</span>':'<span style="background:#D1ECF1;padding:2px 6px;border-radius:4px;font-size:11px">Platform-wide</span>'; }
	}
	public function offer_columns($cols){ return array_merge(array_slice($cols,0,2),['price'=>'₹ Price','expiry'=>'Expires','franchise'=>'Franchise'],array_slice($cols,2)); }
	public function offer_column_data($col,$id) {
		global $wpdb;
		if ('price'   ===$col) echo '<strong>₹'.esc_html(number_format((float)get_post_meta($id,'_gl_offer_price',true),2)).'</strong>';
		if ('expiry'  ===$col) { $exp=get_post_meta($id,'_gl_offer_expiry',true);if(!$exp){echo '<span style="color:#888">No expiry</span>';return;}$exp<date('Y-m-d')?printf('<span style="color:#a00">⛔ Expired: %s</span>',esc_html($exp)):printf('<span style="color:#0a0">✅ %s</span>',esc_html($exp)); }
		if ('franchise'===$col) { $fid=(int)get_post_meta($id,'_gl_franchise_id',true);$n=$fid?$wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}gl_franchises WHERE id=%d",$fid)):null;echo esc_html($n?:'Platform-wide'); }
	}

	public function add_content_ownership_info( $post ) {
		if (!in_array($post->post_type,['gl_announcement','gl_offer','gl_gallery'],true)) return;
		$fid=(int)get_post_meta($post->ID,'_gl_franchise_id',true);
		echo '<div class="misc-pub-section" style="border-top:1px solid #eee;padding-top:8px;margin-top:6px;"><span style="font-size:11px;color:#888">Visible on: ';
		echo $fid?'<strong style="color:#C6A75E">Franchise #'.$fid.' only</strong>':'<strong style="color:#0a0">All franchise pages</strong>';
		echo '</span></div>';
	}
}
PHPEOF;

file_put_contents($target, $code);

if (file_exists($target) && filesize($target) > 500) {
    echo "SUCCESS: Wrote " . filesize($target) . " bytes to " . $target . "\n";
}
else {
    echo "ERROR: File is empty or missing.\n";
}
