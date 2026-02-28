<?php
/**
 * Global Admin Settings Panel for GlamLux2Lux Frontend.
 * Allows Super Admin to edit text, images, and other frontend configs.
 */
class GlamLux_Settings {

	private $options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page under the main GlamLux Platform menu
	 */
	public function add_plugin_page() {
		add_submenu_page(
			'glamlux-dashboard',
			'Frontend Settings',
			'Frontend Settings',
			'manage_glamlux_platform',
			'glamlux-frontend-settings',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		$this->options = get_option( 'glamlux_frontend_settings' );
		?>
		<div class="wrap">
			<h1>Frontend Settings</h1>
			<p>Configure the copy, images, and layout blocks for the main customer-facing website.</p>
			<form method="post" action="options.php">
			<?php
				settings_fields( 'glamlux_frontend_group' );
				do_settings_sections( 'glamlux-frontend-settings' );
				submit_button();
			?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'glamlux_frontend_group', 
			'glamlux_frontend_settings', 
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'glamlux_hero_section', 
			'Hero Section', 
			array( $this, 'print_hero_info' ), 
			'glamlux-frontend-settings'
		);

		add_settings_field(
			'hero_headline', 
			'Hero Headline', 
			array( $this, 'hero_headline_callback' ), 
			'glamlux-frontend-settings', 
			'glamlux_hero_section'
		);

		add_settings_field(
			'hero_subtitle', 
			'Hero Subtitle', 
			array( $this, 'hero_subtitle_callback' ), 
			'glamlux-frontend-settings', 
			'glamlux_hero_section'
		);
		
		add_settings_field(
			'hero_bg_image', 
			'Hero Background Image URL', 
			array( $this, 'hero_bg_image_callback' ), 
			'glamlux-frontend-settings', 
			'glamlux_hero_section'
		);

		add_settings_section(
			'glamlux_about_section',
			'Brand Story & About Section',
			array( $this, 'print_about_info' ),
			'glamlux-frontend-settings'
		);

		add_settings_field(
			'about_quote',
			'Brand Quote',
			array( $this, 'about_quote_callback' ),
			'glamlux-frontend-settings',
			'glamlux_about_section'
		);

		add_settings_field(
			'about_text',
			'Brand Story Paragraph',
			array( $this, 'about_text_callback' ),
			'glamlux-frontend-settings',
			'glamlux_about_section'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 */
	public function sanitize( $input ) {
		$sanitized_input = array();
		if( isset( $input['hero_headline'] ) )
			$sanitized_input['hero_headline'] = sanitize_text_field( $input['hero_headline'] );
			
		if( isset( $input['hero_subtitle'] ) )
			$sanitized_input['hero_subtitle'] = sanitize_text_field( $input['hero_subtitle'] );
			
		if( isset( $input['hero_bg_image'] ) )
			$sanitized_input['hero_bg_image'] = esc_url_raw( $input['hero_bg_image'] );
			
		if( isset( $input['about_quote'] ) )
			$sanitized_input['about_quote'] = sanitize_textarea_field( $input['about_quote'] );
			
		if( isset( $input['about_text'] ) )
			$sanitized_input['about_text'] = sanitize_textarea_field( $input['about_text'] );

		return $sanitized_input;
	}

	public function print_hero_info() {
		print 'Enter your settings below for the main hero section of the homepage:';
	}
	public function print_about_info() {
		print 'Configure the text for the "Redefining Luxury" brand block:';
	}

	public function hero_headline_callback() {
		printf(
			'<input type="text" id="hero_headline" name="glamlux_frontend_settings[hero_headline]" value="%s" class="regular-text" />',
			isset( $this->options['hero_headline'] ) ? esc_attr( $this->options['hero_headline']) : 'The Art of Refined Beauty'
		);
	}

	public function hero_subtitle_callback() {
		printf(
			'<input type="text" id="hero_subtitle" name="glamlux_frontend_settings[hero_subtitle]" value="%s" class="large-text" />',
			isset( $this->options['hero_subtitle'] ) ? esc_attr( $this->options['hero_subtitle']) : 'Experience the pinnacle of luxury minimalism in beauty services and franchise opportunities. Where elegance meets innovation.'
		);
	}
	
	public function hero_bg_image_callback() {
		printf(
			'<input type="url" id="hero_bg_image" name="glamlux_frontend_settings[hero_bg_image]" value="%s" class="large-text" placeholder="https://..." />',
			isset( $this->options['hero_bg_image'] ) ? esc_url( $this->options['hero_bg_image']) : ''
		);
	}

	public function about_quote_callback() {
		printf(
			'<textarea id="about_quote" name="glamlux_frontend_settings[about_quote]" class="large-text" rows="3">%s</textarea>',
			isset( $this->options['about_quote'] ) ? esc_textarea( $this->options['about_quote']) : '"Beauty is not just about appearance, it is an experience of refined elegance and timeless grace."'
		);
	}

	public function about_text_callback() {
		printf(
			'<textarea id="about_text" name="glamlux_frontend_settings[about_text]" class="large-text" rows="5">%s</textarea>',
			isset( $this->options['about_text'] ) ? esc_textarea( $this->options['about_text']) : 'At GlamLux2Lux, we believe in the power of minimalism. Our spaces are sanctuaries designed to strip away the noise of the modern world, allowing your true radiance to emerge. We merge high-tech SaaS precision with high-touch artisanal care.'
		);
	}
}
