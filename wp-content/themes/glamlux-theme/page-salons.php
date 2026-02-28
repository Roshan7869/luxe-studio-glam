<?php
/**
 * Template Name: Salons Directory
 * Template for /salons — full directory of all salon locations with real DB data
 */
get_header();
global $wpdb;

$salons = $wpdb->get_results(
    "SELECT s.id, s.name, s.address, s.city, s.phone, s.email, s.interior_image_url, s.is_active,
            COUNT(DISTINCT st.id) AS staff_count
       FROM {$wpdb->prefix}gl_salons s
       LEFT JOIN {$wpdb->prefix}gl_staff st ON st.salon_id = s.id AND st.is_active = 1
      GROUP BY s.id
      ORDER BY s.name ASC",
    ARRAY_A
) ?: [];
?>

<main style="padding-top:72px;background:#F7F6F2;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:80px 64px 64px;position:relative;overflow:hidden;">
    <div style="position:absolute;top:-60px;right:-60px;width:400px;height:400px;background:radial-gradient(circle,rgba(198,167,94,0.10),transparent 70%);pointer-events:none;"></div>
    <div style="max-width:1440px;margin:0 auto;">
        <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:12px;">Our Locations</p>
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;">Flagship Studios</h1>
        <p style="font-size:1rem;color:rgba(255,255,255,0.60);max-width:480px;">Premium salon spaces crafted for an unmatched luxury experience across India.</p>
    </div>
</div>

<!-- Salons Grid -->
<div style="max-width:1440px;margin:0 auto;padding:72px 64px;">
    <?php if (empty($salons)): ?>
        <p style="text-align:center;color:#6A6A6A;padding:60px 0;">No salons found.</p>
    <?php
else: ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:28px;">
    <?php foreach ($salons as $salon):
        $interior = !empty($salon['interior_image_url']) ? $salon['interior_image_url'] : 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=600&h=400&fit=crop';
?>
        <article class="gl-card" style="background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.07);border:1px solid transparent;transition:transform 300ms ease,box-shadow 300ms ease,border-color 300ms ease;"
             onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 24px 56px rgba(0,0,0,0.12)';this.style.borderColor='rgba(198,167,94,0.25)'"
             onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.07)';this.style.borderColor='transparent'">
            <div style="height:240px;overflow:hidden;position:relative;">
                <img src="<?php echo esc_url($interior); ?>" alt="<?php echo esc_attr($salon['name']); ?>"
                     loading="lazy" decoding="async"
                     style="width:100%;height:100%;object-fit:cover;transition:transform 500ms ease;"
                     onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"
                     onerror="this.src='https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=400&h=280&fit=crop'">
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.40),transparent 60%);"></div>
                <?php if (!empty($salon['city'])): ?>
                <div style="position:absolute;bottom:16px;left:20px;">
                    <span style="background:rgba(198,167,94,0.90);color:#fff;font-size:0.7rem;font-weight:700;letter-spacing:0.08em;padding:4px 12px;border-radius:9999px;text-transform:uppercase;"><?php echo esc_html($salon['city']); ?></span>
                </div>
                <?php
        endif; ?>
            </div>
            <div style="padding:28px;">
                <h2 style="font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:#121212;margin-bottom:12px;"><?php echo esc_html($salon['name']); ?></h2>
                <?php if (!empty($salon['address'])): ?>
                <p style="font-size:0.875rem;color:#6A6A6A;line-height:1.55;margin-bottom:12px;">📍 <?php echo esc_html($salon['address']); ?></p>
                <?php
        endif; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;border-top:1px solid #F0EFEB;">
                    <?php if (!empty($salon['phone'])): ?>
                    <a href="tel:<?php echo esc_attr($salon['phone']); ?>" style="font-size:0.8125rem;color:#121212;font-weight:500;text-decoration:none;">📞 <?php echo esc_html($salon['phone']); ?></a>
                    <?php
        endif; ?>
                    <span style="font-size:0.75rem;color:#C6A75E;font-weight:600;"><?php echo intval($salon['staff_count']); ?> Stylists</span>
                </div>
            </div>
        </article>
    <?php
    endforeach; ?>
    </div>
    <?php
endif; ?>
</div>
</main>

<?php get_footer(); ?>
