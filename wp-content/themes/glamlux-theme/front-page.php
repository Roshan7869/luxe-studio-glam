<?php
/**
 * GlamLux2Lux — Premium Front Page
 * Professional redesign — lean, synchronized, enterprise-grade
 */
get_header();

// ─── Configuration
$hero_bg = get_theme_mod('glamlux_hero_bg', 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&h=1080&fit=crop&auto=format&q=85');
$hero_headline = get_theme_mod('glamlux_hero_headline', 'The Art of Refined Beauty');
$hero_subtitle = get_theme_mod('glamlux_hero_subtitle', 'Seamless luxury beauty management and global franchise growth — powered by enterprise-grade SaaS intelligence.');
$hero_badge = get_theme_mod('glamlux_hero_badge', "India's Premier Luxury Beauty Franchise");

// ─── Fetch Services (DB → Fallback)
$cache_key = 'glamlux_fp_services_db_blog_' . get_current_blog_id();
$services_raw = get_transient($cache_key);
if (false === $services_raw) {
  global $wpdb;
  $t = $wpdb->prefix . 'gl_service_pricing';
  if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t) {
    $services_raw = $wpdb->get_results(
      "SELECT service_name as name, description, CONCAT('₹', CAST(base_price AS UNSIGNED)) as price_display, image_url FROM {$t} WHERE is_active=1 ORDER BY menu_order ASC LIMIT 6",
      ARRAY_A
    );
  }
  $services_raw = is_array($services_raw) ? $services_raw : [];
  set_transient($cache_key, $services_raw, 15 * MINUTE_IN_SECONDS);
}
$fallback_services = [
  ['name' => 'Skincare Rituals', 'description' => 'Bespoke facial treatments curated for every skin type by certified aestheticians.', 'price_display' => 'From ₹2,499', 'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=480&h=360&fit=crop&q=80', 'icon' => '✦'],
  ['name' => 'Hair Couture', 'description' => 'Editorial cuts, colour transformations, and scalp therapies using premium produce.', 'price_display' => 'From ₹1,799', 'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=480&h=360&fit=crop&q=80', 'icon' => '◈'],
  ['name' => 'Body Luxe Therapy', 'description' => 'Signature massage rituals and wraps designed to restore and rejuvenate completely.', 'price_display' => 'From ₹3,299', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=480&h=360&fit=crop&q=80', 'icon' => '❋'],
  ['name' => 'Nail Atelier', 'description' => 'Precision nail artistry with exclusive gel collections and spa manicure finishing.', 'price_display' => 'From ₹799', 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=480&h=360&fit=crop&q=80', 'icon' => '◇'],
  ['name' => 'Bridal Intelligence', 'description' => 'Full-service bridal preparation designed for the most important day of your life.', 'price_display' => 'Custom', 'image_url' => 'https://images.unsplash.com/photo-1526045612212-70caf35c14df?w=480&h=360&fit=crop&q=80', 'icon' => '✿'],
  ['name' => 'Franchise SaaS', 'description' => 'Manage multi-location operations from a single enterprise-grade beauty OS dashboard.', 'price_display' => 'Enterprise', 'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=480&h=360&fit=crop&q=80', 'icon' => '⬡'],
];
$services = !empty($services_raw) ? array_slice($services_raw, 0, 6) : $fallback_services;

// ─── Fetch Salons
$cache_key_salons = 'glamlux_fp_salons_db_blog_' . get_current_blog_id();
$salons_raw = get_transient($cache_key_salons);
if (false === $salons_raw) {
  global $wpdb;
  $t = $wpdb->prefix . 'gl_salons';
  if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t) {
    $salons_raw = $wpdb->get_results(
      "SELECT name, address, interior_image_url as image_url FROM {$t} WHERE is_active=1 ORDER BY id ASC LIMIT 6",
      ARRAY_A
    );
  }
  $salons_raw = is_array($salons_raw) ? $salons_raw : [];
  set_transient($cache_key_salons, $salons_raw, 15 * MINUTE_IN_SECONDS);
}
$salons = !empty($salons_raw) ? $salons_raw : [];

// ─── Fetch Staff
$cache_key_staff = 'glamlux_fp_staff_db_blog_' . get_current_blog_id();
$staff_raw = get_transient($cache_key_staff);
if (false === $staff_raw) {
  global $wpdb;
  $ts = $wpdb->prefix . 'gl_staff';
  $tl = $wpdb->prefix . 'gl_salons';
  if ($wpdb->get_var("SHOW TABLES LIKE '{$ts}'") === $ts) {
    $staff_raw = $wpdb->get_results(
      "SELECT u.display_name AS full_name, s.job_role AS role, s.profile_image_url as image_url, l.name as salon_name FROM {$ts} s LEFT JOIN {$wpdb->users} u ON s.wp_user_id=u.ID LEFT JOIN {$tl} l ON s.salon_id=l.id WHERE s.is_active=1 ORDER BY s.id ASC LIMIT 6",
      ARRAY_A
    );
  }
  $staff_raw = is_array($staff_raw) ? $staff_raw : [];
  set_transient($cache_key_staff, $staff_raw, 15 * MINUTE_IN_SECONDS);
}
$staff = !empty($staff_raw) ? $staff_raw : [];

// ─── Fetch Memberships
$cache_key_memberships = 'glamlux_fp_memberships_db_blog_' . get_current_blog_id();
$memberships_raw = get_transient($cache_key_memberships);
if (false === $memberships_raw) {
  global $wpdb;
  $t = $wpdb->prefix . 'gl_memberships';
  if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t) {
    $memberships_raw = $wpdb->get_results(
      "SELECT name as tier_name, tier_level, benefits, price as price_monthly, banner_image_url FROM {$t} WHERE is_active=1 ORDER BY price ASC LIMIT 3",
      ARRAY_A
    );
  }
  $memberships_raw = is_array($memberships_raw) ? $memberships_raw : [];
  set_transient($cache_key_memberships, $memberships_raw, 15 * MINUTE_IN_SECONDS);
}
$memberships = !empty($memberships_raw) ? $memberships_raw : [];

// ─── Fetch Franchises
$cache_key_franchises = 'glamlux_fp_franchises_db_blog_' . get_current_blog_id();
$franchises_raw = get_transient($cache_key_franchises);
if (false === $franchises_raw) {
  global $wpdb;
  $t = $wpdb->prefix . 'gl_franchises';
  if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") === $t) {
    $franchises_raw = $wpdb->get_results(
      "SELECT owner_name, email, phone, location, status FROM {$t} WHERE status IN ('active','pending') ORDER BY id DESC LIMIT 6",
      ARRAY_A
    );
  }
  $franchises_raw = is_array($franchises_raw) ? $franchises_raw : [];
  set_transient($cache_key_franchises, $franchises_raw, 15 * MINUTE_IN_SECONDS);
}
$franchises = !empty($franchises_raw) ? $franchises_raw : [];

// ─── Testimonials
$testimonials = [
  ['text' => 'Walking into GlamLux2Lux feels like stepping into a different world. The staff, the rituals, the attention to detail — nothing else comes close.', 'author' => 'Priya M.', 'location' => 'Mumbai'],
  ['text' => 'As a franchise owner, the SaaS dashboard has transformed how I run three locations. Real-time data, zero guesswork.', 'author' => 'Rahul S.', 'location' => 'Bangalore'],
  ['text' => 'My bridal experience was beyond a dream. Every detail was personalised, every moment felt like luxury perfected.', 'author' => 'Ananya D.', 'location' => 'Delhi'],
];

$stats = [
  ['num' => '500+', 'label' => 'Franchise Locations', 'icon' => '◈'],
  ['num' => '1.2M', 'label' => 'Satisfied Clients', 'icon' => '✦'],
  ['num' => '18', 'label' => 'States Covered', 'icon' => '◇'],
  ['num' => '99.9%', 'label' => 'SaaS Uptime', 'icon' => '❋'],
];
?>

<style>
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   PAGE UTILITY CLASSES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  .gl-container {
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 56px;
  }

  .gl-section {
    padding: 96px 0;
  }

  .gl-section-sm {
    padding: 72px 0;
  }

  .gl-ornament {
    display: flex;
    align-items: center;
    gap: 14px;
    justify-content: center;
    margin-bottom: 16px;
  }

  .gl-ornament-line {
    flex: 1;
    max-width: 64px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(198, 167, 94, 0.45));
  }

  .gl-ornament-line.r {
    background: linear-gradient(90deg, rgba(198, 167, 94, 0.45), transparent);
  }

  .gl-ornament-label {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    color: var(--gold);
    text-transform: uppercase;
  }

  .gl-section-title {
    font-size: clamp(1.9rem, 3.2vw, 2.75rem);
    font-weight: 700;
    color: var(--dark);
    letter-spacing: -0.022em;
    margin-bottom: 14px;
  }

  .gl-section-sub {
    font-size: 0.9375rem;
    color: var(--text-secondary);
    max-width: 460px;
    margin: 0 auto;
    line-height: 1.7;
  }

  .gl-reveal {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.55s cubic-bezier(0.4, 0, 0.2, 1), transform 0.55s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .gl-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }

  /* Cards */
  .gl-card {
    background: #fff;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: var(--shadow-card);
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
    overflow: hidden;
  }

  .gl-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lifted);
    border-color: rgba(198, 167, 94, 0.25);
  }

  /* Buttons */
  .gl-btn-gold {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 14px 28px;
    border-radius: 9999px;
    background: var(--gold);
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    border: none;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 6px 20px rgba(198, 167, 94, 0.35);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  }

  .gl-btn-gold:hover {
    background: var(--gold-dark);
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(198, 167, 94, 0.45);
  }

  .gl-btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 14px 28px;
    border-radius: 9999px;
    background: transparent;
    color: var(--dark);
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    border: 1.5px solid rgba(0, 0, 0, 0.18);
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-fast);
  }

  .gl-btn-ghost:hover {
    background: var(--dark);
    color: #fff;
    border-color: var(--dark);
  }

  .gl-btn-ghost-light {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 14px 28px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    border: 1.5px solid rgba(255, 255, 255, 0.28);
    cursor: pointer;
    text-decoration: none;
    backdrop-filter: blur(8px);
    transition: all var(--transition-fast);
  }

  .gl-btn-ghost-light:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-2px);
  }

  /* Services horizontal scroll */
  .gl-services-scroll {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 8px;
    scrollbar-width: none;
  }

  .gl-services-scroll::-webkit-scrollbar {
    display: none;
  }

  .gl-service-card {
    flex: 0 0 240px;
    padding: 28px 24px;
    border-radius: var(--radius-lg);
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: var(--shadow-soft);
    cursor: pointer;
    transition: transform var(--transition-base), box-shadow var(--transition-base), border-color var(--transition-base);
    position: relative;
    overflow: hidden;
  }

  .gl-service-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(198, 167, 94, 0.0), rgba(198, 167, 94, 0.06));
    opacity: 0;
    transition: opacity var(--transition-base);
  }

  .gl-service-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-card);
    border-color: rgba(198, 167, 94, 0.30);
  }

  .gl-service-card:hover::before {
    opacity: 1;
  }

  .gl-service-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(198, 167, 94, 0.12), rgba(198, 167, 94, 0.06));
    display: grid;
    place-items: center;
    margin-bottom: 18px;
    font-size: 1.125rem;
    color: var(--gold);
  }

  /* Salon grid */
  .gl-salons-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  @media(max-width:1024px) {
    .gl-salons-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media(max-width:640px) {
    .gl-salons-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Team */
  .gl-team-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
  }

  @media(max-width:1024px) {
    .gl-team-grid {
      grid-template-columns: repeat(3, 1fr);
    }
  }

  @media(max-width:640px) {
    .gl-team-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  /* Membership */
  .gl-membership-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    align-items: center;
  }

  @media(max-width:900px) {
    .gl-membership-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Franchise */
  .gl-franchise-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  @media(max-width:1024px) {
    .gl-franchise-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media(max-width:640px) {
    .gl-franchise-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Testimonials */
  .gl-testimonial-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  @media(max-width:900px) {
    .gl-testimonial-grid {
      grid-template-columns: 1fr;
      gap: 14px;
    }
  }

  /* Divider */
  .gl-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0, 0, 0, 0.06), transparent);
    margin: 0;
  }

  /* Status badge */
  .gl-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
  }

  .gl-badge-active {
    background: #e8f5e9;
    color: #2e7d32;
  }

  .gl-badge-pending {
    background: #fff8e1;
    color: #f57f17;
  }

  /* Skeleton loader */
  @keyframes gl-shimmer {
    0% {
      background-position: -400px 0;
    }

    100% {
      background-position: 400px 0;
    }
  }

  .gl-skeleton {
    background: linear-gradient(90deg, #f0efeb 25%, #e8e7e3 50%, #f0efeb 75%);
    background-size: 800px 100%;
    animation: gl-shimmer 1.5s infinite linear;
    border-radius: 8px;
  }

  /* Pulse glow */
  @keyframes gl-pulse-glow {

    0%,
    100% {
      box-shadow: 0 6px 20px rgba(198, 167, 94, 0.35);
    }

    50% {
      box-shadow: 0 8px 32px rgba(198, 167, 94, 0.60);
    }
  }

  /* Modal */
  .gl-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 15, 15, 0.45);
    backdrop-filter: blur(8px);
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity var(--transition-base);
  }

  .gl-modal-overlay.open {
    display: flex;
  }

  .gl-modal-inner {
    background: #fff;
    border-radius: var(--radius-xl);
    padding: 48px;
    max-width: 520px;
    width: 90%;
    box-shadow: 0 32px 80px rgba(0, 0, 0, 0.16);
    transform: translateY(20px) scale(0.96);
    transition: transform var(--transition-base);
  }

  .gl-modal-overlay.open .gl-modal-inner {
    transform: translateY(0) scale(1);
  }

  /* Form elements */
  .gl-field {
    margin-bottom: 18px;
  }

  .gl-label {
    display: block;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-secondary);
    margin-bottom: 7px;
  }

  .gl-input {
    width: 100%;
    padding: 13px 16px;
    background: var(--off-white);
    border: 1.5px solid var(--off-white-2);
    border-radius: var(--radius-md);
    font-size: 0.9375rem;
    color: var(--dark);
    font-family: 'Inter', sans-serif;
    outline: none;
    transition: border-color var(--transition-fast);
    -webkit-appearance: none;
    appearance: none;
  }

  .gl-input:focus {
    border-color: var(--gold);
  }

  @media(max-width:768px) {
    .gl-container {
      padding: 0 24px;
    }

    .gl-section {
      padding: 72px 0;
    }

    .gl-testimonial-grid,
    .gl-franchise-grid {
      grid-template-columns: 1fr;
    }

    .gl-membership-grid {
      grid-template-columns: 1fr;
    }
  }

  /* ━━ CSS-only fallback: auto-reveal if GSAP fails to load ━━ */
  @keyframes gl-hero-fadein {
    to {
      opacity: 1;
      transform: translateY(0);
      filter: blur(0px);
    }
  }

  #hero-eyebrow {
    animation: gl-hero-fadein 0.6s ease 0.3s forwards;
  }

  #hero-headline {
    animation: gl-hero-fadein 0.8s ease 0.5s forwards;
  }

  #hero-sub {
    animation: gl-hero-fadein 0.7s ease 0.7s forwards;
  }

  #hero-actions {
    animation: gl-hero-fadein 0.6s ease 0.9s forwards;
  }

  #hero-scroll-hint {
    animation: gl-hero-fadein 0.5s ease 1.2s forwards;
  }

  /* Lazy-bg fallback: apply bg immediately */
  .gl-lazy-bg[data-bg] {
    background-image: var(--fallback-bg);
  }

  /* .gl-reveal fallback: become visible after 1s if JS hasn't triggered */
  @keyframes gl-reveal-fallback {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .gl-reveal {
    animation: gl-reveal-fallback 0.5s ease 1.2s forwards;
  }
</style>

<!-- ════════════════════════════════════════════════════════════
     1. HERO SECTION
════════════════════════════════════════════════════════════ -->
<section id="gl-hero"
  style="position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;padding-top:68px;">

  <!-- Background -->
  <div id="hero-bg-el" class="gl-lazy-bg" data-bg="<?php echo esc_url($hero_bg); ?>"
    style="position:absolute;inset:0;background:#0F0F0F url('<?php echo esc_url($hero_bg); ?>');background-size:cover;background-position:center;will-change:transform;transform:scale(1.06);">
  </div>

  <!-- Overlays -->
  <div
    style="position:absolute;inset:0;background:linear-gradient(110deg,rgba(15,15,15,0.82) 0%,rgba(15,15,15,0.42) 55%,rgba(15,15,15,0.15) 100%);z-index:1;">
  </div>
  <div
    style="position:absolute;bottom:-60px;left:38%;width:560px;height:480px;background:radial-gradient(circle,rgba(198,167,94,0.10) 0%,transparent 70%);z-index:1;pointer-events:none;">
  </div>

  <!-- Content -->
  <div style="position:relative;z-index:10;padding:0 80px;max-width:800px;" id="hero-content">

    <!-- Badge -->
    <div id="hero-eyebrow"
      style="display:inline-flex;align-items:center;gap:8px;padding:6px 16px;background:rgba(198,167,94,0.14);border:1px solid rgba(198,167,94,0.32);border-radius:9999px;font-size:0.625rem;font-weight:600;letter-spacing:0.15em;text-transform:uppercase;color:var(--gold);margin-bottom:28px;backdrop-filter:blur(8px);opacity:0;transform:translateY(14px);">
      <span style="width:6px;height:6px;border-radius:50%;background:var(--gold);display:inline-block;"></span>
      <?php echo esc_html($hero_badge); ?>
    </div>

    <!-- Headline -->
    <h1 id="hero-headline"
      style="font-family:'Playfair Display',Georgia,serif;font-size:clamp(2.75rem,5.5vw,5rem);font-weight:700;line-height:1.06;letter-spacing:-0.028em;color:#fff;margin-bottom:22px;opacity:0;filter:blur(8px);transform:translateY(28px);">
      <?php echo esc_html($hero_headline); ?>
    </h1>

    <!-- Subtitle -->
    <p id="hero-sub"
      style="font-size:1.0625rem;color:rgba(255,255,255,0.68);line-height:1.75;max-width:500px;margin-bottom:48px;opacity:0;transform:translateY(20px);">
      <?php echo esc_html($hero_subtitle); ?>
    </p>

    <!-- Actions -->
    <div id="hero-actions" style="display:flex;gap:14px;flex-wrap:wrap;opacity:0;transform:translateY(20px);">
      <a href="javascript:void(0)" data-gl-modal="booking" class="gl-btn-gold"
        style="animation:gl-pulse-glow 4s ease-in-out infinite;">
        Book Appointment
        <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
          <path d="M3 7.5h9M7.5 3l4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </a>
      <a href="<?php echo esc_url(home_url('/franchise-enquiry')); ?>" class="gl-btn-ghost-light">
        Own a Franchise
      </a>
    </div>

  </div>

  <!-- Scroll hint -->
  <div
    style="position:absolute;bottom:32px;left:50%;transform:translateX(-50%);z-index:10;display:flex;flex-direction:column;align-items:center;gap:7px;opacity:0;"
    id="hero-scroll-hint">
    <span
      style="font-size:0.5625rem;letter-spacing:0.14em;color:rgba(255,255,255,0.45);text-transform:uppercase;font-weight:600;">Scroll</span>
    <div style="width:1px;height:36px;background:linear-gradient(to bottom,rgba(198,167,94,0.7),transparent);"></div>
  </div>

</section>

<style>
  @media(max-width:768px) {
    #hero-content {
      padding: 0 24px !important;
    }
  }
</style>

<!-- ════════════════════════════════════════════════════════════
     2. STATS STRIP
════════════════════════════════════════════════════════════ -->
<section class="gl-reveal"
  style="background:#fff;border-top:1px solid rgba(0,0,0,0.05);border-bottom:1px solid rgba(0,0,0,0.05);">
  <div class="gl-container" style="padding-top:0;padding-bottom:0;">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);">
      <?php foreach ($stats as $i => $s): ?>
        <div
          style="padding:40px 24px;text-align:center;<?php echo $i < count($stats) - 1 ? 'border-right:1px solid rgba(0,0,0,0.06);' : ''; ?>">
          <div
            style="font-family:'Playfair Display',serif;font-size:2.25rem;font-weight:700;color:var(--gold);line-height:1;margin-bottom:6px;">
            <?php echo esc_html($s['num']); ?>
          </div>
          <div style="font-size:0.8125rem;color:var(--text-secondary);letter-spacing:0.03em;">
            <?php echo esc_html($s['label']); ?>
          </div>
        </div>
        <?php
      endforeach; ?>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     3. SERVICES — LEAN SINGLE ROW
════════════════════════════════════════════════════════════ -->
<section id="services" class="gl-reveal gl-section" style="background:var(--off-white);">
  <div class="gl-container">

    <!-- Header row -->
    <div
      style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:40px;flex-wrap:wrap;gap:16px;">
      <div>
        <div class="gl-ornament" style="justify-content:flex-start;margin-bottom:12px;">
          <div class="gl-ornament-line" style="max-width:40px;"></div>
          <span class="gl-ornament-label">What We Offer</span>
        </div>
        <h2 class="gl-section-title" style="margin-bottom:0;">Luxury Services</h2>
        <p style="font-size:0.9375rem;color:var(--text-secondary);margin-top:10px;max-width:400px;line-height:1.65;">
          Curated beauty rituals delivered with surgical precision and artistic soul.</p>
      </div>
      <a href="<?php echo esc_url(home_url('/#services')); ?>"
        style="display:inline-flex;align-items:center;gap:8px;font-size:0.8125rem;font-weight:600;color:var(--gold);white-space:nowrap;letter-spacing:0.02em;">
        View all services
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </a>
    </div>

    <!-- Lean horizontal row -->
    <div class="gl-services-scroll" id="gl-services-row">
      <?php foreach ($services as $svc):
        $name = $svc['name'] ?? $svc['post_title'] ?? 'Service';
        $desc = $svc['description'] ?? $svc['post_excerpt'] ?? '';
        $price = $svc['price_display'] ?? (isset($svc['price']) ? '₹' . number_format($svc['price']) : '');
        $icon = $svc['icon'] ?? '✦';
        ?>
        <div class="gl-service-card" onclick="glamluxOpenModal('booking')">
          <div class="gl-service-icon"><?php echo $icon; ?></div>
          <h3
            style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:8px;line-height:1.3;">
            <?php echo esc_html($name); ?>
          </h3>
          <p style="font-size:0.8125rem;color:var(--text-secondary);line-height:1.65;margin-bottom:16px;">
            <?php echo esc_html(wp_trim_words($desc, 12, '…')); ?>
          </p>
          <?php if ($price): ?>
            <div style="font-size:0.8125rem;font-weight:600;color:var(--gold);"><?php echo esc_html($price); ?></div>
            <?php
          endif; ?>
        </div>
        <?php
      endforeach; ?>
    </div>

    <!-- Scroll indicator dots -->
    <div style="display:flex;justify-content:center;gap:6px;margin-top:24px;" id="services-dots">
      <?php for ($i = 0; $i < count($services); $i++): ?>
        <div class="svc-dot" data-idx="<?php echo $i; ?>"
          style="width:<?php echo $i === 0 ? '20' : '6'; ?>px;height:6px;border-radius:9999px;background:<?php echo $i === 0 ? 'var(--gold)' : 'rgba(0,0,0,0.12)'; ?>;transition:all 0.3s ease;cursor:pointer;">
        </div>
        <?php
      endfor; ?>
    </div>

  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     4. SALONS SHOWCASE
════════════════════════════════════════════════════════════ -->
<?php if (!empty($salons)): ?>
  <div class="gl-divider"></div>
  <section id="salons" class="gl-reveal gl-section" style="background:#fff;">
    <div class="gl-container">

      <div style="text-align:center;margin-bottom:52px;">
        <div class="gl-ornament">
          <div class="gl-ornament-line"></div><span class="gl-ornament-label">Our Locations</span>
          <div class="gl-ornament-line r"></div>
        </div>
        <h2 class="gl-section-title">Luxury Salons</h2>
        <p class="gl-section-sub">Flagship destinations across India's most prestigious neighbourhoods.</p>
      </div>

      <div class="gl-salons-grid">
        <?php foreach (array_slice($salons, 0, 6) as $salon): ?>
          <article class="gl-card">
            <div style="overflow:hidden;height:220px;position:relative;">
              <img
                src="<?php echo esc_url($salon['image_url'] ?? 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=600&h=400&fit=crop&q=80'); ?>"
                alt="<?php echo esc_attr($salon['name']); ?>" loading="lazy" decoding="async"
                style="width:100%;height:100%;object-fit:cover;transition:transform 0.5s cubic-bezier(0.4,0,0.2,1);"
                onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'"
                onerror="this.src='https://images.unsplash.com/photo-1560066984-138dadb4c035?w=600&h=400&fit=crop&q=80'">
              <div
                style="position:absolute;inset:0;background:linear-gradient(to top,rgba(15,15,15,0.35),transparent);pointer-events:none;">
              </div>
            </div>
            <div style="padding:24px 26px;">
              <h3
                style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:700;color:var(--dark);margin-bottom:6px;">
                <?php echo esc_html($salon['name']); ?>
              </h3>
              <p style="font-size:0.8375rem;color:var(--text-secondary);display:flex;align-items:center;gap:6px;">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="flex-shrink:0;">
                  <path
                    d="M6 1C4.07 1 2.5 2.57 2.5 4.5c0 2.8 3.5 6.5 3.5 6.5s3.5-3.7 3.5-6.5C9.5 2.57 7.93 1 6 1zm0 4.75a1.25 1.25 0 110-2.5 1.25 1.25 0 010 2.5z"
                    fill="currentColor" />
                </svg>
                <?php echo esc_html($salon['address'] ?? ''); ?>
              </p>
            </div>
          </article>
          <?php
        endforeach; ?>
      </div>

      <div style="text-align:center;margin-top:44px;">
        <a href="<?php echo esc_url(home_url('/salons')); ?>" class="gl-btn-ghost">
          Explore All Salons
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
        </a>
      </div>

    </div>
  </section>
  <?php
endif; ?>

<!-- ════════════════════════════════════════════════════════════
     5. MEET THE TEAM
════════════════════════════════════════════════════════════ -->
<?php if (!empty($staff)): ?>
  <div class="gl-divider"></div>
  <section id="team" class="gl-reveal gl-section" style="background:var(--off-white);">
    <div class="gl-container">

      <div style="text-align:center;margin-bottom:52px;">
        <div class="gl-ornament">
          <div class="gl-ornament-line"></div><span class="gl-ornament-label">The Artisans</span>
          <div class="gl-ornament-line r"></div>
        </div>
        <h2 class="gl-section-title">Meet Our Experts</h2>
        <p class="gl-section-sub">Hand-picked master artists trained in luxury beauty techniques from around the world.
        </p>
      </div>

      <div class="gl-team-grid">
        <?php foreach (array_slice($staff, 0, 6) as $person): ?>
          <article style="text-align:center;">
            <div
              style="width:108px;height:108px;border-radius:50%;overflow:hidden;margin:0 auto 14px;border:2px solid rgba(198,167,94,0.20);box-shadow:0 8px 24px rgba(0,0,0,0.08);">
              <img
                src="<?php echo esc_url($person['image_url'] ?? 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=200&h=200&fit=crop&q=80'); ?>"
                alt="<?php echo esc_attr($person['full_name'] ?? ''); ?>" loading="lazy" decoding="async"
                style="width:100%;height:100%;object-fit:cover;"
                onerror="this.src='https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200&h=200&fit=crop&q=80'">
            </div>
            <h3
              style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--dark);margin-bottom:3px;">
              <?php echo esc_html($person['full_name'] ?? ''); ?>
            </h3>
            <p
              style="font-size:0.6875rem;color:var(--gold);text-transform:uppercase;letter-spacing:0.1em;font-weight:600;margin-bottom:3px;">
              <?php echo esc_html($person['role'] ?? ''); ?>
            </p>
            <p style="font-size:0.75rem;color:var(--text-muted);"><?php echo esc_html($person['salon_name'] ?? ''); ?></p>
          </article>
          <?php
        endforeach; ?>
      </div>

      <div style="text-align:center;margin-top:44px;">
        <a href="<?php echo esc_url(home_url('/team')); ?>" class="gl-btn-ghost">
          Meet the Full Team
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
        </a>
      </div>

    </div>
  </section>
  <?php
endif; ?>

<!-- ════════════════════════════════════════════════════════════
     6. MEMBERSHIP PLANS
════════════════════════════════════════════════════════════ -->
<?php if (!empty($memberships)): ?>
  <div class="gl-divider"></div>
  <section id="memberships" class="gl-reveal gl-section" style="background:#fff;">
    <div class="gl-container">

      <div style="text-align:center;margin-bottom:52px;">
        <div class="gl-ornament">
          <div class="gl-ornament-line"></div><span class="gl-ornament-label">Exclusive Access</span>
          <div class="gl-ornament-line r"></div>
        </div>
        <h2 class="gl-section-title">Privilege Memberships</h2>
        <p class="gl-section-sub">Unlock premium benefits, priority booking, and personalised luxury experiences.</p>
      </div>

      <div class="gl-membership-grid">
        <?php foreach ($memberships as $idx => $plan):
          $featured = ($idx === 1);
          ?>
          <article style="
        background:<?php echo $featured ? 'var(--dark)' : '#fff'; ?>;
        color:<?php echo $featured ? '#fff' : 'var(--dark)'; ?>;
        border-radius:var(--radius-xl);padding:40px 36px;
        position:relative;overflow:hidden;
        border:1px solid <?php echo $featured ? 'rgba(198,167,94,0.28)' : 'rgba(0,0,0,0.06)'; ?>;
        box-shadow:<?php echo $featured ? '0 24px 64px rgba(198,167,94,0.18),0 8px 32px rgba(0,0,0,0.16)' : 'var(--shadow-card)'; ?>;
        transform:<?php echo $featured ? 'scale(1.04)' : 'scale(1)'; ?>;
        z-index:<?php echo $featured ? '2' : '1'; ?>;
        transition:transform var(--transition-base),box-shadow var(--transition-base);
      ">
            <?php if ($featured): ?>
              <div
                style="position:absolute;top:18px;right:18px;background:var(--gold);color:#fff;font-size:0.5625rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:5px 12px;border-radius:9999px;">
                Most Popular</div>
              <?php
            endif; ?>
            <!-- Gold accent line -->
            <div style="width:32px;height:2px;background:var(--gold);border-radius:2px;margin-bottom:24px;"></div>
            <h3 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;margin-bottom:14px;">
              <?php echo esc_html($plan['tier_name']); ?>
            </h3>
            <div
              style="font-family:'Playfair Display',serif;font-size:2.5rem;font-weight:700;color:var(--gold);margin-bottom:8px;line-height:1;">
              ₹<?php echo esc_html(number_format($plan['price_monthly'])); ?>
              <span
                style="font-size:0.9375rem;color:<?php echo $featured ? 'rgba(255,255,255,0.45)' : 'var(--text-muted)'; ?>;font-weight:400;font-family:'Inter',sans-serif;">/month</span>
            </div>
            <p
              style="font-size:0.875rem;line-height:1.7;color:<?php echo $featured ? 'rgba(255,255,255,0.65)' : 'var(--text-secondary)'; ?>;margin-bottom:32px;">
              <?php echo wp_kses_post(nl2br($plan['benefits'])); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/memberships')); ?>" style="
          display:block;text-align:center;padding:14px;
          background:<?php echo $featured ? 'var(--gold)' : 'transparent'; ?>;
          color:<?php echo $featured ? '#fff' : 'var(--dark)'; ?>;
          border:1.5px solid <?php echo $featured ? 'var(--gold)' : 'rgba(0,0,0,0.16)'; ?>;
          border-radius:9999px;font-size:0.875rem;font-weight:600;letter-spacing:0.03em;
          text-decoration:none;transition:all var(--transition-fast);
        " onmouseover="this.style.background='<?php echo $featured ? '#A8893E' : 'var(--dark)'; ?>';this.style.color='#fff';this.style.borderColor='<?php echo $featured ? '#A8893E' : 'var(--dark)'; ?>'"
              onmouseout="this.style.background='<?php echo $featured ? 'var(--gold)' : 'transparent'; ?>';this.style.color='<?php echo $featured ? '#fff' : 'var(--dark)'; ?>';this.style.borderColor='<?php echo $featured ? 'var(--gold)' : 'rgba(0,0,0,0.16)'; ?>'">
              Join Now
            </a>
          </article>
          <?php
        endforeach; ?>
      </div>

    </div>
  </section>
  <?php
endif; ?>

<!-- ════════════════════════════════════════════════════════════
     7. TESTIMONIALS
════════════════════════════════════════════════════════════ -->
<div class="gl-divider"></div>
<section class="gl-reveal gl-section" style="background:var(--off-white);">
  <div class="gl-container">

    <div style="text-align:center;margin-bottom:52px;">
      <div class="gl-ornament">
        <div class="gl-ornament-line"></div><span class="gl-ornament-label">Client Voices</span>
        <div class="gl-ornament-line r"></div>
      </div>
      <h2 class="gl-section-title">Trusted by Thousands</h2>
    </div>

    <div class="gl-testimonial-grid">
      <?php foreach ($testimonials as $t): ?>
        <div
          style="background:#fff;border-radius:var(--radius-lg);padding:32px;border:1px solid rgba(0,0,0,0.05);box-shadow:var(--shadow-soft);position:relative;">
          <div
            style="position:absolute;top:-12px;left:28px;font-family:'Cormorant Garamond',serif;font-size:3.5rem;color:var(--gold);line-height:1;opacity:0.4;">
            "</div>
          <p
            style="font-family:'Cormorant Garamond',serif;font-size:1.0625rem;font-style:italic;line-height:1.8;color:var(--dark);margin-bottom:22px;padding-top:14px;">
            <?php echo esc_html($t['text']); ?>
          </p>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:1px;background:var(--gold);"></div>
            <div>
              <div style="font-size:0.8125rem;font-weight:600;color:var(--dark);"><?php echo esc_html($t['author']); ?>
              </div>
              <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo esc_html($t['location']); ?></div>
            </div>
          </div>
        </div>
        <?php
      endforeach; ?>
    </div>

  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     8. FRANCHISE NETWORK
════════════════════════════════════════════════════════════ -->
<?php if (!empty($franchises)): ?>
  <div class="gl-divider"></div>
  <section id="franchise-network" class="gl-reveal gl-section" style="background:#fff;">
    <div class="gl-container">

      <div style="text-align:center;margin-bottom:52px;">
        <div class="gl-ornament">
          <div class="gl-ornament-line"></div><span class="gl-ornament-label">Our Network</span>
          <div class="gl-ornament-line r"></div>
        </div>
        <h2 class="gl-section-title">Expanding Everywhere</h2>
        <p class="gl-section-sub">Join a thriving ecosystem of luxury beauty destinations across India's most vibrant
          cities.</p>
      </div>

      <div class="gl-franchise-grid">
        <?php foreach (array_slice($franchises, 0, 6) as $fr): ?>
          <article
            style="background:var(--off-white);border-radius:var(--radius-lg);padding:28px;border:1px solid rgba(0,0,0,0.05);position:relative;transition:transform var(--transition-base),box-shadow var(--transition-base);"
            onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='var(--shadow-card)'"
            onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="position:absolute;top:20px;right:20px;">
              <span
                class="gl-badge <?php echo $fr['status'] === 'active' ? 'gl-badge-active' : 'gl-badge-pending'; ?>"><?php echo esc_html($fr['status']); ?></span>
            </div>
            <h3
              style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:700;color:var(--dark);margin-bottom:14px;padding-right:64px;">
              <?php echo esc_html($fr['location']); ?>
            </h3>
            <div style="display:flex;flex-direction:column;gap:7px;">
              <span style="font-size:0.8125rem;color:var(--text-secondary);">👤
                <?php echo esc_html($fr['owner_name']); ?></span>
              <span style="font-size:0.8125rem;color:var(--text-secondary);">✉ <?php echo esc_html($fr['email']); ?></span>
              <?php if (!empty($fr['phone'])): ?>
                <span style="font-size:0.8125rem;color:var(--text-secondary);">📞 <?php echo esc_html($fr['phone']); ?></span>
                <?php
              endif; ?>
            </div>
          </article>
          <?php
        endforeach; ?>
      </div>

    </div>
  </section>
  <?php
endif; ?>

<!-- ════════════════════════════════════════════════════════════
     9. FRANCHISE CTA
════════════════════════════════════════════════════════════ -->
<section class="gl-reveal" style="background:var(--dark);padding:112px 0;position:relative;overflow:hidden;">
  <div
    style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:700px;height:400px;background:radial-gradient(ellipse,rgba(198,167,94,0.10) 0%,transparent 70%);pointer-events:none;">
  </div>

  <div style="max-width:680px;margin:0 auto;text-align:center;position:relative;z-index:1;padding:0 32px;">
    <div class="gl-ornament" style="margin-bottom:22px;">
      <div style="flex:1;max-width:52px;height:1px;background:rgba(198,167,94,0.28);"></div>
      <span class="gl-ornament-label">Your Opportunity</span>
      <div style="flex:1;max-width:52px;height:1px;background:rgba(198,167,94,0.28);"></div>
    </div>
    <h2
      style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.25rem);font-weight:700;color:#fff;letter-spacing:-0.025em;margin-bottom:18px;line-height:1.12;">
      Own a <span style="color:var(--gold);">GlamLux2Lux</span><br>Franchise
    </h2>
    <p
      style="font-size:1.0625rem;color:rgba(255,255,255,0.58);line-height:1.75;margin-bottom:44px;max-width:500px;margin-left:auto;margin-right:auto;">
      Join India's fastest-growing luxury beauty franchise network. Enterprise SaaS tools, proven brand, and full
      operational support from day one.
    </p>
    <a href="<?php echo esc_url(home_url('/franchise-enquiry')); ?>" class="gl-btn-gold"
      style="font-size:0.9375rem;padding:17px 38px;animation:gl-pulse-glow 4s ease-in-out infinite;">
      Apply for Franchise
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
          stroke-linejoin="round" />
      </svg>
    </a>
  </div>
</section>

<!-- ════════════════════════════════════════════════════════════
     10. BOOKING MODAL
════════════════════════════════════════════════════════════ -->
<div id="gl-modal-booking" class="gl-modal-overlay" role="dialog" aria-modal="true"
  aria-labelledby="booking-modal-title">
  <div class="gl-modal-inner">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;">
      <div>
        <p
          style="font-size:0.625rem;font-weight:600;letter-spacing:0.15em;text-transform:uppercase;color:var(--gold);margin-bottom:6px;">
          Reserve Your Session</p>
        <h2 id="booking-modal-title"
          style="font-family:'Playfair Display',serif;font-size:1.625rem;font-weight:700;color:var(--dark);line-height:1.2;">
          Book an Appointment</h2>
      </div>
      <button onclick="glamluxCloseModal('booking')" aria-label="Close"
        style="width:34px;height:34px;border-radius:50%;background:var(--off-white);border:none;cursor:pointer;display:grid;place-items:center;color:var(--text-secondary);font-size:1rem;transition:background var(--transition-fast);"
        onmouseover="this.style.background='var(--off-white-2)'"
        onmouseout="this.style.background='var(--off-white)'">✕</button>
    </div>

    <form id="gl-booking-form" novalidate>
      <?php wp_nonce_field('glamlux_booking', 'glamlux_nonce', true, true); ?>
      <input type="hidden" name="action" value="glamlux_create_booking">

      <div class="gl-field">
        <label class="gl-label">Service</label>
        <select name="service_id" required class="gl-input">
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

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
        <div>
          <label class="gl-label">Date</label>
          <input type="date" name="appointment_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>"
            class="gl-input">
        </div>
        <div>
          <label class="gl-label">Time</label>
          <select name="appointment_time" required class="gl-input">
            <?php for ($h = 9; $h <= 20; $h++): ?>
              <option value="<?php echo sprintf('%02d:00', $h); ?>">
                <?php echo sprintf('%02d:00 %s', $h > 12 ? $h - 12 : $h, $h >= 12 ? 'PM' : 'AM'); ?>
              </option>
              <?php
            endfor; ?>
          </select>
        </div>
      </div>

      <div class="gl-field">
        <label class="gl-label">Full Name</label>
        <input type="text" name="client_name" placeholder="Your full name" required class="gl-input">
      </div>

      <div class="gl-field" style="margin-bottom:28px;">
        <label class="gl-label">Phone</label>
        <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required class="gl-input">
      </div>

      <button type="submit" id="gl-book-submit" class="gl-btn-gold"
        style="width:100%;justify-content:center;font-size:0.9375rem;padding:16px;">
        Confirm Appointment
      </button>
    </form>

  </div>
</div>

<?php get_footer(); ?>