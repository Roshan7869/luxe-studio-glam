<?php
/**
 * Template Name: Transformation Portfolio
 * Template for /portfolio — displaying all service logs / before & after gallery
 */
get_header();
global $wpdb;

// Fetch all service logs with an after image
$logs = $wpdb->get_results(
    "SELECT l.id, l.client_name, l.before_image_url, l.after_image_url, l.notes, l.created_at,
            s.service_name 
       FROM {$wpdb->prefix}gl_service_logs l
       LEFT JOIN {$wpdb->prefix}gl_service_pricing s ON l.service_id = s.id
      WHERE l.after_image_url IS NOT NULL
      ORDER BY l.created_at DESC",
    ARRAY_A
) ?: [];
?>

<main style="padding-top:72px;background:#121212;color:#fff;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#000 100%);padding:100px 64px 80px;text-align:center;">
    <div style="max-width:1440px;margin:0 auto;">
        <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:12px;">The GlamLux2Lux Signature</p>
        <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;">Transformation Portfolio</h1>
        <p style="font-size:1.0625rem;color:rgba(255,255,255,0.60);max-width:560px;margin:0 auto;line-height:1.6;">Witness the artistry. Real client transformations driven by our luxury experts.</p>
    </div>
</div>

<!-- Portfolio Grid -->
<div style="max-width:1440px;margin:0 auto;padding:60px 64px 100px;">
    <?php if (empty($logs)): ?>
        <p style="text-align:center;color:rgba(255,255,255,0.4);padding:80px 0;">Our portfolio is currently being curated. Check back soon.</p>
    <?php
else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(400px, 1fr));gap:32px;">
            <?php foreach ($logs as $log): ?>
                <article style="background:#1e1a14;border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.05);transition:transform 0.3s ease,box-shadow 0.3s ease;"
                         onmouseover="this.style.transform='translateY(-8px)';this.style.boxShadow='0 20px 48px rgba(0,0,0,0.4)'"
                         onmouseout="this.style.transform='';this.style.boxShadow='none'">
                    
                    <div style="display:flex;height:300px;">
                        <?php if (!empty($log['before_image_url'])): ?>
                            <div style="flex:1;position:relative;border-right:1px solid rgba(255,255,255,0.1);">
                                <img src="<?php echo esc_url($log['before_image_url']); ?>" alt="Before" loading="lazy" style="width:100%;height:100%;object-fit:cover;opacity:0.65;filter:grayscale(30%);">
                                <div style="position:absolute;bottom:16px;left:16px;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);padding:4px 10px;border-radius:4px;font-size:0.625rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:#fff;">Before</div>
                            </div>
                        <?php
        endif; ?>
                        
                        <div style="flex:1;position:relative;">
                            <img src="<?php echo esc_url($log['after_image_url']); ?>" alt="After" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                            <div style="position:absolute;bottom:16px;right:16px;background:#C6A75E;color:#000;padding:4px 10px;border-radius:4px;font-size:0.625rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;">After</div>
                        </div>
                    </div>
                    
                    <div style="padding:28px;">
                        <h2 style="font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:#C6A75E;margin-bottom:8px;"><?php echo esc_html($log['service_name'] ?? 'Custom Treatment'); ?></h2>
                        <?php if (!empty($log['client_name'])): ?>
                            <p style="font-size:0.875rem;color:rgba(255,255,255,0.8);margin-bottom:12px;">Client: <?php echo esc_html($log['client_name']); ?></p>
                        <?php
        endif; ?>
                        
                        <?php if (!empty($log['notes'])): ?>
                            <p style="font-size:0.875rem;line-height:1.6;color:rgba(255,255,255,0.5);font-style:italic;">"<?php echo esc_html($log['notes']); ?>"</p>
                        <?php
        endif; ?>
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
