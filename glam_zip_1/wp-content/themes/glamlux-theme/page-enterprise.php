<?php
/**
 * Template Name: Enterprise Dashboard
 * Template for /enterprise — displaying franchise locations and high-level stats
 */
get_header();
global $wpdb;

// 15-minute caching for enterprise data
$enterprise_data = get_transient('glamlux_page_enterprise');

if (false === $enterprise_data) {
    // 1. Fetch active/pending franchises
    $franchises = $wpdb->get_results(
        "SELECT id, owner_name, email, phone, location, city, state, zip_code, status, created_at
           FROM {$wpdb->prefix}gl_franchises
          WHERE status IN ('active', 'pending')
          ORDER BY city ASC",
        ARRAY_A
    ) ?: [];

    // 2. Fetch high-level corporate stats (network-wide revenue and expenses)
    $financials = $wpdb->get_row(
        "SELECT SUM(total_revenue) AS network_revenue, 
                SUM(total_expenses) AS network_expenses,
                SUM(net_profit) AS network_profit
           FROM {$wpdb->prefix}gl_financial_reports",
        ARRAY_A
    ) ?: ['network_revenue' => 0, 'network_expenses' => 0, 'network_profit' => 0];

    // Calculate locations count
    $active_locations = 0;
    foreach ($franchises as $f) {
        if ($f['status'] === 'active') {
            $active_locations++;
        }
    }

    $enterprise_data = [
        'franchises' => $franchises,
        'financials' => $financials,
        'stats' => [
            'total_locations' => $active_locations,
            'network_revenue' => $financials['network_revenue'] ?? 0,
            'network_profit' => $financials['network_profit'] ?? 0
        ]
    ];

    set_transient('glamlux_page_enterprise', $enterprise_data, 15 * MINUTE_IN_SECONDS);
}

$franchises = $enterprise_data['franchises'];
$stats = $enterprise_data['stats'];
?>

<main style="padding-top:72px;background:#F7F6F2;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:100px 64px 80px;text-align:center;position:relative;overflow:hidden;">
    <div style="position:absolute;top:-60px;right:-60px;width:400px;height:400px;background:radial-gradient(circle,rgba(198,167,94,0.10),transparent 70%);pointer-events:none;"></div>
    <div style="max-width:1440px;margin:0 auto;position:relative;z-index:2;">
        <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:12px;">Corporate Overview</p>
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;">Enterprise Hub</h1>
        <p style="font-size:1.0625rem;color:rgba(255,255,255,0.60);max-width:560px;margin:0 auto;line-height:1.6;">Global franchise metrics and network topology. Discover the power of the GlamLux ecosystem.</p>
    </div>
</div>

<!-- High-Level Stats Strip -->
<div style="background:#121212;border-bottom:1px solid rgba(255,255,255,0.05);padding:32px 64px;">
    <div style="max-width:1440px;margin:0 auto;display:flex;justify-content:space-around;flex-wrap:wrap;gap:24px;">
        <div style="text-align:center;">
            <div style="font-size:0.75rem;color:#C6A75E;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Active Locations</div>
            <div style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:#fff;"><?php echo intval($stats['total_locations']); ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:0.75rem;color:#C6A75E;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Network Volume</div>
            <div style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:#fff;">₹<?php echo number_format($stats['network_revenue']); ?></div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:0.75rem;color:#C6A75E;letter-spacing:0.1em;text-transform:uppercase;font-weight:600;margin-bottom:8px;">Network Efficiency</div>
            <div style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:#fff;">+<?php echo $stats['network_revenue'] > 0 ? round(($stats['network_profit'] / $stats['network_revenue']) * 100, 1) : 0; ?>%</div>
        </div>
    </div>
</div>

<!-- Global Network Grid -->
<div style="max-width:1440px;margin:0 auto;padding:80px 64px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:40px;">
        <h2 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:#121212;">Franchise Network</h2>
        <a href="<?php echo esc_url(home_url('/franchise')); ?>" style="padding:10px 24px;background:#121212;color:#C6A75E;text-decoration:none;border-radius:100px;font-size:0.8125rem;font-weight:600;transition:opacity 0.2s;">Apply for Franchise</a>
    </div>

    <?php if (empty($franchises)): ?>
        <p style="color:#6A6A6A;padding:40px 0;">No publicly listed franchise locations found at this time.</p>
    <?php
else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:24px;">
            <?php foreach ($franchises as $f):
        $is_active = ($f['status'] === 'active');
        $badge_bg = $is_active ? '#E8F5E9' : '#FFF8E1';
        $badge_color = $is_active ? '#2E7D32' : '#F57F17';
?>
                <div style="background:#fff;border-radius:20px;padding:32px;box-shadow:0 4px 16px rgba(0,0,0,0.04);border:1px solid #F0EFEB;transition:transform 0.2s ease;"
                     onmouseover="this.style.transform='translateY(-4px)'"
                     onmouseout="this.style.transform=''">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
                        <h3 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:#121212;margin:0;"><?php echo esc_html($f['city']); ?></h3>
                        <span style="background:<?php echo $badge_bg; ?>;color:<?php echo $badge_color; ?>;padding:4px 10px;border-radius:100px;font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                            <?php echo esc_html($f['status']); ?>
                        </span>
                    </div>
                    
                    <div style="font-size:0.875rem;color:#6A6A6A;margin-bottom:12px;display:flex;align-items:flex-start;gap:8px;">
                        <span>📍</span>
                        <span><?php echo esc_html($f['location']); ?><br><?php echo esc_html($f['state'] . ' ' . $f['zip_code']); ?></span>
                    </div>
                    <div style="font-size:0.875rem;color:#6A6A6A;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                        <span>👤</span> <strong><?php echo esc_html($f['owner_name']); ?></strong> (Owner)
                    </div>
                </div>
            <?php
    endforeach; ?>
        </div>
    <?php
endif; ?>
</div>

</main>

<?php get_footer(); ?>
