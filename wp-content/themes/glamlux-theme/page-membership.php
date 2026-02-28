<?php
/**
 * Template Name: Memberships
 * Template for /memberships — details regarding the membership tiers
 */
get_header();
global $wpdb;

// Fetch active memberships
$memberships = $wpdb->get_results(
    "SELECT id, tier_name, price_monthly, price_yearly, benefits, banner_image_url
       FROM {$wpdb->prefix}gl_memberships
      WHERE is_active = 1
      ORDER BY price_monthly ASC",
    ARRAY_A
) ?: [];
?>

<main style="padding-top:72px;background:#F7F6F2;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:80px 64px 64px;position:relative;overflow:hidden;">
    <div style="position:absolute;top:-60px;right:-60px;width:400px;height:400px;background:radial-gradient(circle,rgba(198,167,94,0.10),transparent 70%);pointer-events:none;"></div>
    <div style="max-width:1440px;margin:0 auto;text-align:center;">
        <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:12px;">Exclusive Access</p>
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;">Privilege Memberships</h1>
        <p style="font-size:1rem;color:rgba(255,255,255,0.60);max-width:540px;margin:0 auto;">Elevate your beauty ritual. Unlocks priority bookings, exclusive events, and complimentary services across our network.</p>
    </div>
</div>

<!-- Pricing Matrix -->
<div style="max-width:1440px;margin:0 auto;padding:100px 64px;">
    <?php if (empty($memberships)): ?>
        <p style="text-align:center;color:#6A6A6A;padding:60px 0;font-size:1.125rem;">Our membership programme will be unveiling soon.</p>
    <?php
else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:32px;">
            <?php foreach ($memberships as $index => $plan):
        $is_center = ($index === 1); // Highlight the middle tier
        $bg = $is_center ? '#121212' : '#fff';
        $text = $is_center ? '#fff' : '#121212';
        $btnBg = $is_center ? '#C6A75E' : 'transparent';
        $btnText = $is_center ? '#fff' : '#121212';
        $btnBorder = $is_center ? '#C6A75E' : '#121212';
        $descColor = $is_center ? 'rgba(255,255,255,0.7)' : '#6A6A6A';
        $transform = $is_center ? 'scale(1.05)' : 'scale(1)';
        $zindex = $is_center ? '2' : '1';
?>
            <article style="background:<?php echo $bg; ?>;color:<?php echo $text; ?>;border-radius:24px;padding:48px;position:relative;box-shadow:0 12px 32px rgba(0,0,0,0.06);border:1px solid rgba(198,167,94,<?php echo $is_center ? '0.2' : '0.05'; ?>);transform:<?php echo $transform; ?>;z-index:<?php echo $zindex; ?>;">
                <?php if ($is_center): ?>
                <div style="position:absolute;top:20px;right:20px;background:#C6A75E;color:#000;padding:6px 14px;border-radius:100px;font-size:0.625rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;">Most Popular</div>
                <?php
        endif; ?>
                
                <h2 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;margin-bottom:12px;"><?php echo esc_html($plan['tier_name']); ?></h2>
                <div style="font-size:3rem;font-weight:700;font-family:'Playfair Display',serif;color:#C6A75E;margin-bottom:32px;line-height:1;">
                    ₹<?php echo esc_html(number_format($plan['price_monthly'])); ?>
                    <span style="font-size:1rem;font-family:'Inter',sans-serif;font-weight:400;color:<?php echo $descColor; ?>;">/ month</span>
                </div>
                
                <div style="margin-bottom:40px;">
                    <h3 style="font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:<?php echo $is_center ? '#fff' : '#121212'; ?>;margin-bottom:16px;">Privileges</h3>
                    <?php
        $benefits = array_filter(array_map('trim', explode("\n", $plan['benefits'])));
        foreach ($benefits as $benefit):
            // Skip empty strings
            if (empty($benefit))
                continue;
?>
                    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;"><path d="M20 6L9 17L4 12" stroke="#C6A75E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span style="font-size:0.9375rem;color:<?php echo $descColor; ?>;line-height:1.5;"><?php echo esc_html($benefit); ?></span>
                    </div>
                    <?php
        endforeach; ?>
                </div>
                
                <a href="<?php echo esc_url(home_url('/contact')); ?>" style="display:block;text-align:center;width:100%;padding:16px;background:<?php echo $btnBg; ?>;color:<?php echo $btnText; ?>;border:1px solid <?php echo $btnBorder; ?>;border-radius:100px;font-size:0.9375rem;font-weight:600;text-decoration:none;transition:all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">Enquire Now</a>
            </article>
            <?php
    endforeach; ?>
        </div>
    <?php
endif; ?>
</div>

<!-- CTA Block -->
<div style="background:#121212;padding:80px 64px;text-align:center;color:#fff;">
    <div style="max-width:600px;margin:0 auto;">
        <h2 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;margin-bottom:16px;">Corporate Packages Available</h2>
        <p style="font-size:1rem;color:rgba(255,255,255,0.7);margin-bottom:32px;">We offer bespoke tiered structures for enterprise employee benefits. Discover our Corporate Wellness integration.</p>
        <a href="<?php echo esc_url(home_url('/enterprise')); ?>" style="display:inline-block;padding:12px 32px;background:transparent;color:#C6A75E;border:1px solid #C6A75E;border-radius:100px;text-decoration:none;font-weight:600;font-size:0.875rem;">View Enterprise Solutions</a>
    </div>
</div>

</main>

<?php get_footer(); ?>
