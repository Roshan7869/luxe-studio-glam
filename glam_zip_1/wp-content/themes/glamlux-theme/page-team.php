<?php
/**
 * Template Name: Our Team
 * Template for /team — full staff directory with profile images and specializations
 */
get_header();
global $wpdb;

$staff_list = get_transient('glamlux_page_team');
if (false === $staff_list) {
    $staff_list = $wpdb->get_results(
        "SELECT st.id, st.job_role, st.specializations, st.profile_image_url, st.is_active,
                u.display_name AS name,
                sl.name AS salon_name, sl.city
           FROM {$wpdb->prefix}gl_staff st
           LEFT JOIN {$wpdb->users} u ON st.wp_user_id = u.ID
           LEFT JOIN {$wpdb->prefix}gl_salons sl ON st.salon_id = sl.id
          WHERE st.is_active = 1
          ORDER BY sl.name ASC, u.display_name ASC",
        ARRAY_A
    ) ?: [];
    set_transient('glamlux_page_team', $staff_list, 15 * MINUTE_IN_SECONDS);
}

// Group by salon
$by_salon = [];
foreach ($staff_list as $m) {
    $key = $m['salon_name'] ?: 'Other';
    $by_salon[$key][] = $m;
}
?>

<main style="padding-top:72px;background:#F7F6F2;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:80px 64px 64px;position:relative;overflow:hidden;">
    <div style="position:absolute;top:-60px;right:-60px;width:400px;height:400px;background:radial-gradient(circle,rgba(198,167,94,0.10),transparent 70%);pointer-events:none;"></div>
    <div style="max-width:1440px;margin:0 auto;">
        <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:12px;">The Experts</p>
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;">Meet Our Team</h1>
        <p style="font-size:1rem;color:rgba(255,255,255,0.60);max-width:480px;"><?php echo count($staff_list); ?> world-class beauty professionals — passionate, precise, premium.</p>
    </div>
</div>

<!-- Staff by Salon -->
<div style="max-width:1440px;margin:0 auto;padding:72px 64px;">
<?php foreach ($by_salon as $salon_name => $members): ?>
    <div style="margin-bottom:72px;">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:36px;">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:#121212;"><?php echo esc_html($salon_name); ?></h2>
            <div style="flex:1;height:1px;background:linear-gradient(90deg,rgba(198,167,94,0.4),transparent);"></div>
            <span style="font-size:0.75rem;color:#6A6A6A;white-space:nowrap;"><?php echo count($members); ?> Stylists</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;">
        <?php foreach ($members as $m):
        $avatar = !empty($m['profile_image_url']) ? $m['profile_image_url'] : 'https://randomuser.me/api/portraits/women/1.jpg';
        $specs = !empty($m['specializations']) ? explode(',', $m['specializations']) : [];
?>
            <div class="gl-card" style="background:#fff;border-radius:24px;overflow:hidden;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,0.06);transition:transform 300ms ease,box-shadow 300ms ease;"
                 onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 20px 40px rgba(0,0,0,0.11)'"
                 onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.06)'">
                <div style="height:200px;overflow:hidden;">
                    <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($m['name'] ?? 'Team Member'); ?>"
                         loading="lazy" decoding="async"
                         style="width:100%;height:100%;object-fit:cover;transition:transform 400ms ease;"
                         onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"
                         onerror="this.style.display='none'">
                </div>
                <div style="padding:20px;">
                    <h3 style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:#121212;margin-bottom:4px;"><?php echo esc_html($m['name'] ?? 'Team Member'); ?></h3>
                    <p style="font-size:0.75rem;color:#C6A75E;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:10px;"><?php echo esc_html($m['job_role'] ?? ''); ?></p>
                    <?php if (!empty($specs)): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;justify-content:center;">
                    <?php foreach (array_slice($specs, 0, 2) as $spec): ?>
                        <span style="background:#F7F6F2;color:#6A6A6A;font-size:0.625rem;font-weight:500;padding:3px 8px;border-radius:9999px;"><?php echo esc_html(trim($spec)); ?></span>
                    <?php
            endforeach; ?>
                    </div>
                    <?php
        endif; ?>
                </div>
            </div>
        <?php
    endforeach; ?>
        </div>
    </div>
<?php
endforeach; ?>
</div>
</main>

<?php get_footer(); ?>
