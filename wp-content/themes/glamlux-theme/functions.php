<?php
/**
 * GlamLux Theme Functions and Definitions
 *
 * Phase 15 Additions:
 * - WebP conversion filter for uploaded images
 * - Native lazy-loading on all <img> tags
 * - Tailwind CDN enqueue (development only)
 */

// ─── Section Header Helper ──────────────────────────────────────────────────

if (!function_exists('glamlux_section_header')) {
	function glamlux_section_header(string $eyebrow, string $title, string $subtitle = ''): string
	{
		$out = '<div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;justify-content:center;">';
		$out .= '<div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,transparent,rgba(198,167,94,0.5));"></div>';
		$out .= '<span style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;color:#C6A75E;text-transform:uppercase;">' . esc_html($eyebrow) . '</span>';
		$out .= '<div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,rgba(198,167,94,0.5),transparent);"></div>';
		$out .= '</div>';
		$out .= '<h2 style="font-family:\'Playfair Display\',serif;font-size:clamp(2rem,3.5vw,3rem);font-weight:700;color:#121212;letter-spacing:-0.02em;margin-bottom:16px;">' . esc_html($title) . '</h2>';
		if ($subtitle) {
			$out .= '<p style="font-size:1rem;color:#6A6A6A;max-width:480px;margin:0 auto;">' . esc_html($subtitle) . '</p>';
		}
		return $out;
	}
}


if (!function_exists('glamlux_theme_setup')) {
	function glamlux_theme_setup()
	{
		add_theme_support('automatic-feed-links');
		add_theme_support('title-tag');
		add_theme_support('post-thumbnails');

		// Register menus for dynamic editing from WP Admin
		register_nav_menus(array(
			'primary' => 'Primary Header Menu',
			'footer_company' => 'Footer - Company',
			'footer_services' => 'Footer - Services',
			'footer_franchise' => 'Footer - Franchise',
		));

	// WooCommerce integrations (Commented out because plugin is not active)
	// add_theme_support('woocommerce');
	// add_theme_support('wc-product-gallery-zoom');
	// add_theme_support('wc-product-gallery-lightbox');
	// add_theme_support('wc-product-gallery-slider');
	}
}
add_action('after_setup_theme', 'glamlux_theme_setup');


// ─── Page Template Registration ─────────────────────────────────────────────

add_filter('theme_page_templates', function ($templates) {
	$templates['page-salons.php'] = 'Salons Directory';
	$templates['page-team.php'] = 'Our Team';
	$templates['page-membership.php'] = 'Membership Plans';
	$templates['page-portfolio.php'] = 'Portfolio Gallery';
	$templates['page-franchise-apply.php'] = 'Franchise Apply';
	return $templates;
});

// ─── Asset Enqueueing ────────────────────────────────────────────────────────


function glamlux_enqueue_scripts()
{
	wp_enqueue_style('glamlux-style', get_stylesheet_uri(), array(), '1.1.0');

	// Tailwind (CDN for dev; swap for compiled build on production)
	if (defined('WP_DEBUG') && WP_DEBUG) {
		wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com?plugins=forms,container-queries', array(), null, false);
	}
}
add_action('wp_enqueue_scripts', 'glamlux_enqueue_scripts');

// ─── WebP Conversion on Image Upload ────────────────────────────────────────

/**
 * Convert uploaded images to WebP format on the server.
 * Requires PHP Imagick or GD extension.
 * Fires on 'wp_handle_upload' — after WordPress saves the file.
 *
 * @param array $upload  Array with 'file', 'url', 'type'.
 * @return array         Modified upload array pointing to the .webp file.
 */
function glamlux_convert_upload_to_webp($upload)
{
	$image_types = array('image/jpeg', 'image/png', 'image/gif');

	if (!in_array($upload['type'], $image_types, true)) {
		return $upload;
	}

	$source = $upload['file'];
	$destination = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $source);

	$converted = false;

	// Try Imagick first
	if (class_exists('Imagick')) {
		try {
			$imagick = new Imagick($source);
			$imagick->setImageFormat('webp');
			$imagick->setOption('webp:lossless', 'false');
			$imagick->setImageCompressionQuality(82);
			$imagick->writeImage($destination);
			$imagick->clear();
			$converted = true;
		}
		catch (Exception $e) {
			glamlux_log_error('WebP Imagick conversion failed: ' . $e->getMessage());
		}
	}

	// Fallback: GD
	if (!$converted && function_exists('imagewebp')) {
		switch ($upload['type']) {
			case 'image/jpeg':
				$img = imagecreatefromjpeg($source);
				break;
			case 'image/png':
				$img = imagecreatefrompng($source);
				break;
			default:
				$img = null;
		}
		if ($img) {
			imagewebp($img, $destination, 82);
			imagedestroy($img);
			$converted = true;
		}
	}

	if ($converted) {
		// Update the upload metadata to point to the .webp version
		$upload['file'] = $destination;
		$upload['url'] = str_replace(basename($source), basename($destination), $upload['url']);
		$upload['type'] = 'image/webp';

		// Remove the original non-WebP file to save disk space
		wp_delete_file($source);
	}

	return $upload;
}
add_filter('wp_handle_upload', 'glamlux_convert_upload_to_webp');

// ─── Lazy Loading ──────────────────────────────────────────────────────────

/**
 * Add native lazy-loading to all images output through the_content, widgets,
 * and WooCommerce product images.
 *
 * @param array $attr   Image attribute array.
 * @return array        Modified attribute array.
 */
function glamlux_add_lazy_loading($attr)
{
	// WordPress already adds loading="lazy" since 5.5 for attachments.
	// This ensures it for ALL wp_get_attachment_image() calls.
	if (!isset($attr['loading'])) {
		$attr['loading'] = 'lazy';
	}
	// Add decoding async for off-thread image decode
	if (!isset($attr['decoding'])) {
		$attr['decoding'] = 'async';
	}
	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'glamlux_add_lazy_loading');

/**
 * Inject loading="lazy" and decoding="async" into all <img> tags rendered
 * in post content and theme templates via output buffering.
 *
 * @param string $content  Post or widget content.
 * @return string          Modified content string.
 */
function glamlux_lazy_load_content_images($content)
{
	if (is_admin()) {
		return $content;
	}

	// Add lazy/decoding to any <img> missing it
	return preg_replace_callback(
		'/<img\b([^>]*)>/i',
		function ($matches) {
		$attrs = $matches[1];
		if (false === stripos($attrs, 'loading=')) {
			$attrs .= ' loading="lazy"';
		}
		if (false === stripos($attrs, 'decoding=')) {
			$attrs .= ' decoding="async"';
		}
		return '<img' . $attrs . '>';
	},
		$content
	);
}
add_filter('the_content', 'glamlux_lazy_load_content_images');
add_filter('widget_text', 'glamlux_lazy_load_content_images');

// ─── SEO: Document Title Separator ──────────────────────────────────────────

add_filter('document_title_separator', function () {
	return '·';
});

// ─── Security: Remove WP version from head ───────────────────────────────────

remove_action('wp_head', 'wp_generator');
