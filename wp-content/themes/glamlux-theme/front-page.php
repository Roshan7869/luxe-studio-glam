<?php
/**
 * GlamLux2Lux — Premium Front Page
 * Ultra-luxury design: Hero + Stats + Services + Testimonials + CTA
 */
get_header();

// ─── Hero content — pulled from WP Customizer (Appearance → Customize → GlamLux Platform)
$hero_bg = get_theme_mod('glamlux_hero_bg', 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1600&h=900&fit=crop&auto=format&q=85');
$hero_headline = get_theme_mod('glamlux_hero_headline', 'The Art of Refined Beauty');
$hero_subtitle = get_theme_mod('glamlux_hero_subtitle', 'Seamless luxury beauty management and global franchise growth — powered by enterprise-grade SaaS intelligence.');
$hero_badge = get_theme_mod('glamlux_hero_badge', "India's Premier Luxury Beauty Franchise");
$cta_label = get_theme_mod('glamlux_cta_label', 'Book Appointment');
$cta_url = get_theme_mod('glamlux_cta_url', '#services');
$fc_label = get_theme_mod('glamlux_franchise_cta_label', 'Own a Franchise');
$fc_url = get_theme_mod('glamlux_franchise_cta_url', '/franchise');
$site_name = get_bloginfo('name');

// ─── Fetch services from REST API (with fallback static data)
$services_raw = get_transient('glamlux_fp_services');
if (false === $services_raw) {
    $response = wp_remote_get(home_url('/wp-json/glamlux/v1/services'), ['timeout' => 3]);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $body = wp_remote_retrieve_body($response);
        $services_raw = json_decode($body, true);
    }
    else {
        $services_raw = [];
    }

    if (!is_array($services_raw)) {
        $services_raw = [];
    }

    set_transient('glamlux_fp_services', $services_raw, 60);
}

$fallback_services = array(
    [
        'name' => 'Skincare Rituals',
        'description' => 'Bespoke facial treatments curated for every skin type by certified aestheticians.',
        'price_display' => 'From ₹2,499',
        'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&h=450&fit=crop&auto=format&q=80',
    ],
    [
        'name' => 'Hair Couture',
        'description' => 'Editorial cuts, colour transformations, and scalp therapies using premium produce.',
        'price_display' => 'From ₹1,799',
        'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&h=450&fit=crop&auto=format&q=80',
    ],
    [
        'name' => 'Body Luxe Therapy',
        'description' => 'Signature massage rituals and wraps designed to restore and rejuvenate completely.',
        'price_display' => 'From ₹3,299',
        'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=600&h=450&fit=crop&auto=format&q=80',
    ],
    [
        'name' => 'Nail Atelier',
        'description' => 'Precision nail artistry with exclusive gel collections and spa manicure finishing.',
        'price_display' => 'From ₹799',
        'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&h=450&fit=crop&auto=format&q=80',
    ],
    [
        'name' => 'Bridal Intelligence',
        'description' => 'Full-service bridal preparation designed for the most important day of your life.',
        'price_display' => 'Custom',
        'image_url' => 'https://images.unsplash.com/photo-1526045612212-70caf35c14df?w=600&h=450&fit=crop&auto=format&q=80',
    ],
    [
        'name' => 'Franchise SaaS',
        'description' => 'Manage multi-location operations from a single enterprise-grade beauty OS dashboard.',
        'price_display' => 'Enterprise',
        'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=600&h=450&fit=crop&auto=format&q=80',
    ],
);
$services = !empty($services_raw) ? array_slice($services_raw, 0, 6) : $fallback_services;

$testimonials = array(
    ['text' => 'Walking into GlamLux2Lux feels like stepping into a different world. The staff, the rituals, the attention to detail — nothing else comes close.', 'author' => 'Priya M., Mumbai'],
    ['text' => 'As a franchise owner, the SaaS dashboard has transformed how I run three locations. Real-time data, zero guesswork.', 'author' => 'Rahul S., Franchise Owner'],
    ['text' => 'My bridal experience was beyond a dream. Every detail was personalised, every moment felt like luxury.', 'author' => 'Ananya D., Delhi'],
);
?>

<!-- ══ 1. HERO SECTION ════════════════════════════════════════════════════════ -->
<section id="gl-hero" style="position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;padding-top:72px;">

    <!-- Background -->
    <div id="hero-bg-el" class="gl-lazy-bg"
         data-bg="<?php echo esc_url($hero_bg); ?>"
         style="position:absolute;inset:0;background:#1a1212;background-size:cover;background-position:center;will-change:transform;transform:scale(1.08);transition:transform 0.1s linear;">
    </div>

    <!-- Gradient overlay -->
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(18,18,18,0.78) 0%,rgba(18,18,18,0.35) 55%,rgba(18,18,18,0.12) 100%);z-index:1;"></div>

    <!-- Ambient gold glow -->
    <div style="position:absolute;bottom:-80px;left:40%;width:600px;height:600px;background:radial-gradient(circle,rgba(198,167,94,0.12) 0%,transparent 70%);z-index:1;pointer-events:none;"></div>

    <!-- Content -->
    <div style="position:relative;z-index:10;padding:0 80px;max-width:780px;" id="hero-content">

        <!-- Eyebrow -->
        <div id="hero-eyebrow" style="display:inline-flex;align-items:center;gap:8px;padding:6px 18px;background:rgba(198,167,94,0.15);border:1px solid rgba(198,167,94,0.35);border-radius:9999px;font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:28px;backdrop-filter:blur(8px);opacity:0;transform:translateY(16px);">
            <svg width="10" height="10" viewBox="0 0 10 10" fill="#C6A75E"><circle cx="5" cy="5" r="5"/></svg>
            <?php echo esc_html($hero_badge); ?>
        </div>

        <!-- Headline -->
        <h1 id="hero-headline" style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(2.5rem,5vw,4.75rem);font-weight:700;line-height:1.08;letter-spacing:-0.025em;color:#fff;margin-bottom:20px;opacity:0;filter:blur(6px);transform:translateY(24px);">
            <?php echo esc_html($hero_headline); ?>
        </h1>

        <!-- Sub -->
        <p id="hero-sub" style="font-size:1.0625rem;color:rgba(255,255,255,0.70);line-height:1.7;max-width:480px;margin-bottom:44px;opacity:0;transform:translateY(20px);">
            <?php echo esc_html($hero_subtitle); ?>
        </p>

        <!-- Actions -->
        <div id="hero-actions" style="display:flex;gap:16px;flex-wrap:wrap;opacity:0;transform:translateY(20px);">
            <a href="<?php echo esc_url($cta_url); ?>"
               id="hero-cta-main"
               style="display:inline-flex;align-items:center;gap:10px;background:#C6A75E;color:#fff;padding:15px 32px;border-radius:9999px;font-size:0.875rem;font-weight:600;letter-spacing:0.04em;text-decoration:none;box-shadow:0 6px 20px rgba(198,167,94,0.40);transition:all 200ms ease;animation:gl-pulse-glow 4s ease-in-out infinite;"
               onmouseover="this.style.transform='translateY(-3px) scale(1.02)';this.style.boxShadow='0 12px 32px rgba(198,167,94,0.55)'"
               onmouseout="this.style.transform='';this.style.boxShadow='0 6px 20px rgba(198,167,94,0.40)'">
                <?php echo esc_html($cta_label); ?>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <a href="<?php echo esc_url($fc_url); ?>"
                    style="display:inline-flex;align-items:center;gap:10px;background:rgba(255,255,255,0.12);color:#fff;padding:15px 32px;border-radius:9999px;font-size:0.875rem;font-weight:600;letter-spacing:0.04em;border:1px solid rgba(255,255,255,0.30);cursor:pointer;backdrop-filter:blur(8px);transition:all 200ms ease;text-decoration:none;"
                    onmouseover="this.style.background='rgba(255,255,255,0.20)';this.style.transform='translateY(-2px)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.12)';this.style.transform=''">
                <?php echo esc_html($fc_label); ?>
            </a>
        </div>

    </div>

    <!-- Scroll hint -->
    <div style="position:absolute;bottom:36px;left:50%;transform:translateX(-50%);z-index:10;display:flex;flex-direction:column;align-items:center;gap:8px;opacity:0.55;" id="hero-scroll-hint">
        <span style="font-size:0.625rem;letter-spacing:0.12em;color:#fff;text-transform:uppercase;">Scroll</span>
        <div style="width:1px;height:40px;background:linear-gradient(to bottom,rgba(198,167,94,0.8),transparent);animation:gl-scroll-line 2s ease-in-out infinite;"></div>
    </div>
</section>

<style>
@keyframes gl-scroll-line {
  0%,100%{transform:scaleY(1);opacity:0.55}
  50%     {transform:scaleY(0.6);opacity:0.2}
}
</style>

<!-- ══ 2. STATS STRIP ═════════════════════════════════════════════════════════ -->
<section class="gl-reveal" style="background:#fff;padding:0;">
<div style="display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(0,0,0,0.06);border-bottom:1px solid rgba(0,0,0,0.06);">
    <?php
$stats = array(
    ['num' => '500+', 'label' => 'Franchise Locations'],
    ['num' => '1.2M', 'label' => 'Satisfied Clients'],
    ['num' => '18', 'label' => 'States Covered'],
    ['num' => '99.9%', 'label' => 'Uptime SaaS'],
);
foreach ($stats as $i => $s):
?>
    <div style="padding:44px 32px;text-align:center;<?php echo $i < count($stats) - 1 ? 'border-right:1px solid rgba(0,0,0,0.06);' : ''; ?>transition:background 180ms ease;" onmouseover="this.style.background='#FDFCF9'" onmouseout="this.style.background=''">
        <span class="gl-counter" data-target="<?php echo esc_attr($s['num']); ?>" style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:#C6A75E;display:block;line-height:1;"><?php echo esc_html($s['num']); ?></span>
        <span style="font-size:0.8125rem;color:#6A6A6A;margin-top:8px;display:block;letter-spacing:0.03em;"><?php echo esc_html($s['label']); ?></span>
    </div>
    <?php
endforeach; ?>
</div>
</section>

<!-- ══ 3. SERVICES GRID ═══════════════════════════════════════════════════════ -->
<section id="services" class="gl-reveal" style="padding:100px 0;background:#F7F6F2;">
<div class="gl-container" style="max-width:1440px;margin:0 auto;padding:0 64px;">

    <!-- Section header -->
    <div style="text-align:center;margin-bottom:64px;">
        <div class="gl-ornament" style="display:flex;align-items:center;gap:16px;margin-bottom:20px;justify-content:center;">
            <div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,transparent,rgba(198,167,94,0.5));"></div>
            <span style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;color:#C6A75E;text-transform:uppercase;">What We Offer</span>
            <div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,rgba(198,167,94,0.5),transparent);"></div>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,3.5vw,3rem);font-weight:700;color:#121212;letter-spacing:-0.02em;margin-bottom:16px;">Luxury Services</h2>
        <p style="font-size:1rem;color:#6A6A6A;max-width:480px;margin:0 auto;">Curated beauty rituals delivered with surgical precision and artistic soul.</p>
    </div>

    <!-- Skeleton placeholders (shown until services load) -->
    <div id="services-skeleton" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
        <?php for ($s = 0; $s < 6; $s++): ?>
        <div class="gl-skeleton-card" style="background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.05);">
            <div class="gl-skeleton" style="background:#EAEAEA;height:220px;width:100%;position:relative;overflow:hidden;border-radius:0;"></div>
            <div style="padding:24px;">
                <div class="gl-skeleton" style="height:14px;width:70%;margin-bottom:10px;border-radius:6px;"></div>
                <div class="gl-skeleton" style="height:12px;width:90%;margin-bottom:6px;border-radius:6px;"></div>
                <div class="gl-skeleton" style="height:12px;width:65%;margin-bottom:20px;border-radius:6px;"></div>
                <div class="gl-skeleton" style="height:14px;width:35%;border-radius:6px;"></div>
            </div>
        </div>
        <?php
endfor; ?>
    </div>

    <!-- Real services grid -->
    <div id="services-grid" style="display:none;grid-template-columns:repeat(3,1fr);gap:24px;">
        <?php foreach ($services as $svc):
    $name = $svc['name'] ?? $svc['post_title'] ?? 'Service';
    $desc = $svc['description'] ?? $svc['post_excerpt'] ?? '';
    $price = $svc['price_display'] ?? (isset($svc['price']) ? '₹' . number_format($svc['price']) : '');
    $img = $svc['image_url'] ?? get_template_directory_uri() . '/assets/images/service-placeholder.jpg';
?>
        <article class="gl-card" style="background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.05);border:1px solid rgba(0,0,0,0.05);transition:transform 320ms cubic-bezier(0.4,0,0.2,1),box-shadow 320ms cubic-bezier(0.4,0,0.2,1),border-color 320ms cubic-bezier(0.4,0,0.2,1);"
                 onmouseover="this.style.transform='translateY(-8px)';this.style.boxShadow='0 20px 48px rgba(0,0,0,0.10),0 0 0 1px rgba(198,167,94,0.25)';this.style.borderColor='rgba(198,167,94,0.30)';this.querySelector('.svc-img').style.transform='scale(1.06)'"
                 onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.05)';this.style.borderColor='rgba(0,0,0,0.05)';this.querySelector('.svc-img').style.transform='scale(1)'">
            <div style="overflow:hidden;height:220px;">
                <img class="svc-img" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($name); ?>"
                     loading="lazy" decoding="async"
                     style="width:100%;height:220px;object-fit:cover;transition:transform 500ms cubic-bezier(0.4,0,0.2,1);"
                     onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?w=400&h=300&fit=crop'">
            </div>
            <div style="padding:28px;">
                <h3 style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:700;color:#121212;margin-bottom:8px;"><?php echo esc_html($name); ?></h3>
                <p style="font-size:0.875rem;color:#6A6A6A;line-height:1.65;margin-bottom:16px;"><?php echo esc_html(wp_trim_words($desc, 14, '…')); ?></p>
                <?php if ($price): ?>
                <span style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:600;color:#C6A75E;"><?php echo esc_html($price); ?></span>
                <?php
    endif; ?>
            </div>
        </article>
        <?php
endforeach; ?>
    </div>

</div>
</section>

<!-- ══ 4. TESTIMONIALS ════════════════════════════════════════════════════════ -->
<section class="gl-reveal" style="background:#fff;padding:96px 0;">
<div style="max-width:1440px;margin:0 auto;padding:0 64px;">

    <div style="text-align:center;margin-bottom:56px;">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;justify-content:center;">
            <div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,transparent,rgba(198,167,94,0.5));"></div>
            <span style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;color:#C6A75E;text-transform:uppercase;">Client Voices</span>
            <div style="flex:1;max-width:80px;height:1px;background:linear-gradient(90deg,rgba(198,167,94,0.5),transparent);"></div>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:clamp(1.75rem,3vw,2.5rem);font-weight:700;color:#121212;letter-spacing:-0.02em;">Trusted by Thousands</h2>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
        <?php foreach ($testimonials as $t): ?>
        <div class="gl-reveal" style="background:#F7F6F2;border-radius:24px;padding:36px;border-left:3px solid #C6A75E;">
            <p style="font-family:'Cormorant Garamond',serif;font-size:1.125rem;font-style:italic;line-height:1.75;color:#121212;margin-bottom:24px;">"<?php echo esc_html($t['text']); ?>"</p>
            <span style="font-size:0.75rem;font-weight:600;color:#6A6A6A;letter-spacing:0.08em;text-transform:uppercase;">— <?php echo esc_html($t['author']); ?></span>
        </div>
        <?php
endforeach; ?>
    </div>

</div>
</section>

<!-- ══ 5. FRANCHISE CTA SECTION ══════════════════════════════════════════════ -->
<section class="gl-reveal" style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:120px 0;overflow:hidden;position:relative;">
    <!-- Gold glow -->
    <div style="position:absolute;top:-100px;left:50%;transform:translateX(-50%);width:800px;height:500px;background:radial-gradient(ellipse,rgba(198,167,94,0.12) 0%,transparent 70%);pointer-events:none;"></div>

    <div style="max-width:700px;margin:0 auto;text-align:center;position:relative;z-index:1;padding:0 32px;">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;justify-content:center;">
            <div style="flex:1;max-width:60px;height:1px;background:rgba(198,167,94,0.35);"></div>
            <span style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;color:#C6A75E;text-transform:uppercase;">Your Opportunity</span>
            <div style="flex:1;max-width:60px;height:1px;background:rgba(198,167,94,0.35);"></div>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.25rem);font-weight:700;color:#fff;letter-spacing:-0.025em;margin-bottom:20px;line-height:1.15;">
            Own a <span style="color:#C6A75E;">GlamLux2Lux</span><br>Franchise
        </h2>
        <p style="font-size:1.0625rem;color:rgba(255,255,255,0.65);line-height:1.7;margin-bottom:44px;max-width:500px;margin-left:auto;margin-right:auto;">
            Join India's fastest-growing luxury beauty franchise network. Enterprise SaaS tools, proven brand,  and full operational support from day one.
        </p>
        <a href="<?php echo esc_url(home_url('/franchise/apply')); ?>"
           style="display:inline-flex;align-items:center;gap:12px;background:#C6A75E;color:#fff;padding:18px 40px;border-radius:9999px;font-size:0.9375rem;font-weight:600;letter-spacing:0.04em;text-decoration:none;box-shadow:0 8px 32px rgba(198,167,94,0.40);animation:gl-pulse-glow 4s ease-in-out infinite;transition:all 200ms ease;"
           onmouseover="this.style.transform='translateY(-4px) scale(1.02)';this.style.boxShadow='0 16px 48px rgba(198,167,94,0.55)'"
           onmouseout="this.style.transform='';this.style.boxShadow='0 8px 32px rgba(198,167,94,0.40)'">
            Apply for Franchise
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3.5 9h11M9 3.5l5.5 5.5-5.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
</section>

<!-- ══ 6. BOOKING MODAL ═══════════════════════════════════════════════════════ -->
<div id="gl-modal-booking" class="gl-modal-overlay" role="dialog" aria-modal="true" aria-label="Book an Appointment"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.35);backdrop-filter:blur(6px);z-index:3000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity 320ms cubic-bezier(0.4,0,0.2,1);">
    <div class="gl-modal" style="background:#fff;border-radius:28px;padding:48px;max-width:520px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,0.14);transform:scale(0.93) translateY(20px);transition:transform 320ms cubic-bezier(0.4,0,0.2,1);">

        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:36px;">
            <div>
                <p style="font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:6px;">Reserve Your Session</p>
                <h2 style="font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:#121212;line-height:1.2;">Book an Appointment</h2>
            </div>
            <button onclick="glamluxCloseModal('booking')" style="width:36px;height:36px;border-radius:50%;background:#F7F6F2;border:none;cursor:pointer;display:grid;place-items:center;font-size:1.125rem;color:#6A6A6A;transition:background 200ms ease;" onmouseover="this.style.background='#EAEAEA'" onmouseout="this.style.background='#F7F6F2'">✕</button>
        </div>

        <form id="gl-booking-form" novalidate>
            <?php wp_nonce_field('glamlux_booking', 'glamlux_nonce', true, true); ?>
            <input type="hidden" name="action" value="glamlux_create_booking">

            <!-- Service selector -->
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Service</label>
                <select name="service_id" required style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;transition:border-color 200ms ease;appearance:none;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                    <option value="">Select a service…</option>
                    <?php foreach ($services as $svc):
    $id = $svc['id'] ?? $svc['ID'] ?? '';
    $name = $svc['name'] ?? $svc['post_title'] ?? '';
?>
                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                    <?php
endforeach; ?>
                </select>
            </div>

            <!-- Date / Time -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Date</label>
                    <input type="date" name="appointment_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>"
                           style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;transition:border-color 200ms ease;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                </div>
                <div>
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Time</label>
                    <select name="appointment_time" required style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;appearance:none;transition:border-color 200ms ease;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                        <?php for ($h = 9; $h <= 20; $h++): ?>
                        <option value="<?php echo sprintf('%02d:00', $h); ?>"><?php echo sprintf('%02d:00 %s', $h > 12 ? $h - 12 : $h, $h >= 12 ? 'PM' : 'AM'); ?></option>
                        <?php
endfor; ?>
                    </select>
                </div>
            </div>

            <!-- Name -->
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Full Name</label>
                <input type="text" name="client_name" placeholder="Your name" required
                       style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;transition:border-color 200ms ease;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
            </div>

            <!-- Phone -->
            <div style="margin-bottom:32px;">
                <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Phone</label>
                <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required
                       style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;transition:border-color 200ms ease;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
            </div>

            <button type="submit" id="gl-book-submit"
                    style="width:100%;background:#C6A75E;color:#fff;padding:16px 24px;border-radius:9999px;font-size:0.9375rem;font-weight:600;letter-spacing:0.04em;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(198,167,94,0.35);transition:all 200ms ease;"
                    onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 10px 28px rgba(198,167,94,0.50)'"
                    onmouseout="this.style.transform='';this.style.boxShadow='0 6px 20px rgba(198,167,94,0.35)'">
                Confirm Appointment
            </button>

        </form>
    </div>
</div>

<?php get_footer(); ?>
