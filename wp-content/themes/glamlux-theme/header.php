<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<?php wp_head(); ?>

<!-- Google Fonts — preconnect for LCP -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>

<!-- Self-hosted animation libs -->
<script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/gsap.min.js"></script>
<script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/ScrollTrigger.min.js"></script>
<script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/lenis.min.js"></script>

<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
tailwind.config = {
    theme: {
        extend: {
            colors: {
                gold: '#C6A75E',
                gldark: '#121212',
                glbg: '#F7F6F2',
            },
            fontFamily: {
                headline: ['"Playfair Display"', 'Georgia', 'serif'],
                body: ['Inter', 'system-ui', 'sans-serif'],
            },
        }
    }
};
</script>

<style>
/* ── Nav shows immediately, no FOUC ── */
#site-nav { opacity: 1 !important; }
body { background: #F7F6F2; }
</style>
</head>

<body <?php body_class('gl-page-enter'); ?>>
<?php wp_body_open(); ?>

<!-- Scroll progress bar -->
<div id="gl-scroll-progress"></div>

<!-- ── Toast Container ── -->
<div id="gl-toast-container"></div>

<!-- ── Navigation ──────────────────────────────────────────────────────────── -->
<nav id="site-nav" class="gl-glass" role="navigation" aria-label="Main navigation"
     style="position:fixed;top:0;left:0;right:0;z-index:1000;height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);background:rgba(247,246,242,0.72);border-bottom:1px solid rgba(255,255,255,0.4);transition:background 320ms cubic-bezier(0.4,0,0.2,1),box-shadow 320ms cubic-bezier(0.4,0,0.2,1),border-color 320ms cubic-bezier(0.4,0,0.2,1);">

    <!-- Logo -->
    <a href="<?php echo esc_url(home_url('/')); ?>" class="gl-nav-logo" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
        <div style="width:38px;height:38px;background:linear-gradient(135deg,#C6A75E,#D4B97A);border-radius:8px;display:grid;place-items:center;box-shadow:0 4px 12px rgba(198,167,94,0.35);">
            <span style="font-family:'Playfair Display',serif;font-weight:700;color:#fff;font-size:1rem;line-height:1;">G</span>
        </div>
        <span style="font-family:'Playfair Display',serif;font-weight:700;font-size:1.125rem;color:#121212;letter-spacing:-0.01em;">GlamLux<span style="color:#C6A75E;">2</span>Lux</span>
    </a>

    <!-- Links -->
    <ul class="gl-nav-links" id="nav-links" style="display:flex;align-items:center;gap:36px;list-style:none;margin:0;">
        <?php
if (has_nav_menu('primary')) {
    $locations = get_nav_menu_locations();
    $menu_id = $locations['primary'];
    $menu_items = wp_get_nav_menu_items($menu_id);

    if ($menu_items) {
        foreach ($menu_items as $item) {
?>
                    <li><a href="<?php echo esc_url($item->url); ?>"
                           style="position:relative;text-decoration:none;font-size:0.875rem;font-weight:500;color:#6A6A6A;letter-spacing:0.01em;padding-bottom:3px;transition:color 180ms ease;"
                           onmouseover="this.style.color='#121212';this.querySelector('.nav-line').style.right='0'"
                           onmouseout="this.style.color='#6A6A6A';this.querySelector('.nav-line').style.right='100%'">
                        <?php echo esc_html($item->title); ?>
                        <span class="nav-line" style="position:absolute;bottom:-2px;left:0;right:100%;height:1.5px;background:#C6A75E;transition:right 320ms cubic-bezier(0.4,0,0.2,1);"></span>
                    </a></li>
                    <?php
        }
    }
}
else {
    // Fallback if no menu assigned
    echo '<li><a href="#" style="font-size:0.875rem;color:#6A6A6A;text-decoration:none;">Assign Primary Menu</a></li>';
}
?>
    </ul>

    <!-- CTA -->
    <a href="<?php echo esc_url(home_url('/franchise-enquiry')); ?>"
       class="gl-btn-primary gl-btn-glow"
       style="display:inline-flex;align-items:center;gap:8px;background:#C6A75E;color:#fff;padding:12px 24px;border-radius:9999px;font-size:0.8125rem;font-weight:600;letter-spacing:0.04em;text-decoration:none;box-shadow:0 4px 16px rgba(198,167,94,0.35);transition:all 180ms ease;animation:gl-pulse-glow 4s ease-in-out infinite;"
       onmouseover="this.style.transform='translateY(-2px) scale(1.02)';this.style.boxShadow='0 8px 28px rgba(198,167,94,0.50)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(198,167,94,0.35)'">
        Join the Franchise
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>

</nav>
<!-- /nav -->

<style>
@keyframes gl-pulse-glow {
  0%,100%{ box-shadow:0 4px 16px rgba(198,167,94,0.35); }
  50%    { box-shadow:0 8px 32px rgba(198,167,94,0.60),0 0 0 6px rgba(198,167,94,0.08); }
}
</style>

<script>
// Scroll progress + nav state (rAF + Lenis-aware event bridge)
(function(){
    const bar = document.getElementById('gl-scroll-progress');
    const nav = document.getElementById('site-nav');
    let ticking = false;

    function paint(y){
        const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        const pct = Math.min(100, Math.max(0, (y / max) * 100));

        if (bar) {
            bar.style.transform = 'scaleX(' + (pct / 100) + ')';
        }

        if (nav) {
            if (y > 50) {
                nav.style.background = 'rgba(255,255,255,0.92)';
                nav.style.boxShadow = '0 1px 0 rgba(0,0,0,0.06),0 2px 8px rgba(0,0,0,0.04)';
                nav.style.borderBottomColor = 'rgba(0,0,0,0.06)';
            } else {
                nav.style.background = 'rgba(247,246,242,0.72)';
                nav.style.boxShadow = 'none';
                nav.style.borderBottomColor = 'rgba(255,255,255,0.4)';
            }
        }
    }

    function schedule(y){
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function(){
            paint(y);
            ticking = false;
        });
    }

    window.addEventListener('glamlux:scroll', function(e){
        const y = (e.detail && typeof e.detail.y === 'number') ? e.detail.y : window.scrollY;
        schedule(y);
    });

    window.addEventListener('scroll', function(){
        schedule(window.scrollY);
    }, { passive: true });

    paint(window.scrollY);
})();
</script>
