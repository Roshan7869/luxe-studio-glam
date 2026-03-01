<?php
/**
 * GlamLux2Lux — Premium Inner Page Template
 * Used for all pages: About, Contact, Franchise, Philosophy, etc.
 */
get_header();
$page_title = get_the_title();
$page_slug = basename(get_permalink());
?>

<!-- ── Page Hero Banner ────────────────────────────────────────────────────── -->
<section style="padding-top:72px;background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);min-height:280px;display:flex;align-items:center;position:relative;overflow:hidden;">
    <!-- Gold ambient glow -->
    <div style="position:absolute;top:-60px;left:40%;width:600px;height:400px;background:radial-gradient(ellipse,rgba(198,167,94,0.12) 0%,transparent 70%);pointer-events:none;"></div>

    <div style="max-width:1440px;margin:0 auto;padding:0 80px;position:relative;z-index:10;width:100%;">
        <div style="display:inline-flex;align-items:center;gap:8px;padding:5px 16px;background:rgba(198,167,94,0.12);border:1px solid rgba(198,167,94,0.28);border-radius:9999px;font-size:0.625rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#C6A75E;margin-bottom:16px;">
            <svg width="8" height="8" viewBox="0 0 8 8" fill="#C6A75E"><circle cx="4" cy="4" r="4"/></svg>
            GlamLux2Lux
        </div>
        <h1 style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(2rem,4vw,3.5rem);font-weight:700;color:#fff;line-height:1.1;letter-spacing:-0.025em;">
            <?php echo esc_html($page_title); ?>
        </h1>
    </div>
</section>

<!-- ── Page Content ──────────────────────────────────────────────────────── -->
<main style="background:#F7F6F2;min-height:60vh;padding:72px 0;">
    <div style="max-width:1440px;margin:0 auto;padding:0 80px;">

        <?php if (have_posts()):
    while (have_posts()):
        the_post(); ?>

        <div style="background:#fff;border-radius:24px;padding:56px;box-shadow:0 4px 32px rgba(0,0,0,0.06);border:1px solid rgba(0,0,0,0.05);">
            <div style="max-width:900px;font-family:'Inter',sans-serif;font-size:1rem;line-height:1.8;color:#3D3D3D;">
                <?php the_content(); ?>
            </div>

            <!-- Back link -->
            <div style="margin-top:48px;padding-top:32px;border-top:1px solid rgba(0,0,0,0.06);">
                <a href="<?php echo esc_url(home_url('/')); ?>"
                   style="display:inline-flex;align-items:center;gap:10px;color:#C6A75E;text-decoration:none;font-size:0.875rem;font-weight:600;letter-spacing:0.04em;transition:gap 200ms ease;"
                   onmouseover="this.style.gap='14px'"
                   onmouseout="this.style.gap='10px'">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13 8H3M8 3L3 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Back to Home
                </a>
            </div>
        </div>

        <?php
    endwhile;
else: ?>
        <div style="background:#fff;border-radius:24px;padding:56px;box-shadow:0 4px 32px rgba(0,0,0,0.06);text-align:center;">
            <p style="font-size:1.0625rem;color:#6A6A6A;">This page is coming soon. Please check back later.</p>
            <a href="<?php echo esc_url(home_url('/')); ?>"
               style="display:inline-flex;margin-top:24px;align-items:center;gap:10px;background:#C6A75E;color:#fff;padding:14px 32px;border-radius:9999px;font-size:0.875rem;font-weight:600;text-decoration:none;letter-spacing:0.04em;">
                Return Home
            </a>
        </div>
        <?php
endif; ?>

    </div>
</main>

<?php get_footer(); ?>
