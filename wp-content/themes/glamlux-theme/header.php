<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title><?php wp_title('|', true, 'right');
bloginfo('name'); ?></title>
  <meta name="description"
    content="GlamLux2Lux — India's premier luxury beauty franchise. Book appointments, explore services, and own a franchise location today.">
    
  <!-- PWA & Mobile Enhancements (Sprint D2) -->
  <link rel="manifest" href="<?php echo esc_url(get_template_directory_uri()); ?>/manifest.json">
  <meta name="theme-color" content="#C6A75E">
  <link rel="apple-touch-icon" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/icon-192.png">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
          .catch(err => console.log('SW registration failed: ', err));
      });
    }
  </script>

  <?php wp_head(); ?>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap"
    rel="stylesheet" />

  <!-- Animation Libraries -->
  <script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/gsap.min.js"></script>
  <script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/ScrollTrigger.min.js"></script>
  <!-- Lenis disabled — using native scroll for reliability -->
  <!-- <script src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/js/lenis.min.js"></script> -->

  <style>
    /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   GLOBAL DESIGN TOKENS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --gold: #C6A75E;
      --gold-light: #D4B97A;
      --gold-dark: #A8893E;
      --dark: #0F0F0F;
      --dark-2: #1A1A1A;
      --dark-3: #242424;
      --off-white: #F8F7F3;
      --off-white-2: #EEECEA;
      --text-primary: #0F0F0F;
      --text-secondary: #666;
      --text-muted: #999;
      --radius-sm: 8px;
      --radius-md: 16px;
      --radius-lg: 24px;
      --radius-xl: 32px;
      --shadow-soft: 0 2px 8px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
      --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
      --shadow-lifted: 0 20px 60px rgba(0, 0, 0, 0.12), 0 8px 24px rgba(0, 0, 0, 0.06);
      --transition-fast: 160ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-base: 280ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-slow: 480ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--off-white);
      color: var(--text-primary);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
    }

    h1,
    h2,
    h3,
    h4 {
      font-family: 'Playfair Display', Georgia, serif;
      line-height: 1.15;
      letter-spacing: -0.02em;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    img {
      max-width: 100%;
      display: block;
    }

    button {
      cursor: pointer;
      font-family: inherit;
    }

    /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   SCROLL PROGRESS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
    #gl-scroll-progress {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      z-index: 9999;
      background: linear-gradient(90deg, var(--gold), var(--gold-light));
      transform-origin: left;
      transform: scaleX(0);
      transition: transform 60ms linear;
    }

    /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   NAVIGATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
    #site-nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      height: 68px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 56px;
      background: rgba(248, 247, 243, 0.85);
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-bottom: 1px solid rgba(198, 167, 94, 0.12);
      transition: background var(--transition-base), box-shadow var(--transition-base);
    }

    #site-nav.scrolled {
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 1px 0 rgba(0, 0, 0, 0.06), 0 4px 16px rgba(0, 0, 0, 0.04);
    }

    /* Logo */
    .gl-logo {
      display: flex;
      align-items: center;
      gap: 11px;
      text-decoration: none;
    }

    .gl-logo-mark {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
      display: grid;
      place-items: center;
      box-shadow: 0 4px 12px rgba(198, 167, 94, 0.30);
      flex-shrink: 0;
    }

    .gl-logo-mark span {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      color: #fff;
      font-size: 0.9375rem;
      line-height: 1;
    }

    .gl-logo-text {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: 1.0625rem;
      color: var(--dark);
      letter-spacing: -0.01em;
    }

    .gl-logo-text em {
      color: var(--gold);
      font-style: normal;
    }

    /* Nav links */
    .gl-nav-links {
      display: flex;
      align-items: center;
      gap: 4px;
      list-style: none;
    }

    .gl-nav-links a {
      position: relative;
      padding: 6px 14px;
      font-size: 0.8375rem;
      font-weight: 500;
      color: var(--text-secondary);
      letter-spacing: 0.01em;
      border-radius: 9999px;
      transition: color var(--transition-fast), background var(--transition-fast);
    }

    .gl-nav-links a:hover {
      color: var(--dark);
      background: rgba(0, 0, 0, 0.04);
    }

    .gl-nav-links a.active {
      color: var(--dark);
      font-weight: 600;
    }

    /* CTA button */
    .gl-nav-cta {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 22px;
      border-radius: 9999px;
      background: var(--gold);
      color: #fff;
      font-size: 0.8125rem;
      font-weight: 600;
      letter-spacing: 0.035em;
      box-shadow: 0 4px 16px rgba(198, 167, 94, 0.30);
      transition: transform var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
    }

    .gl-nav-cta:hover {
      background: var(--gold-dark);
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(198, 167, 94, 0.40);
    }

    .gl-nav-cta svg {
      transition: transform var(--transition-fast);
    }

    .gl-nav-cta:hover svg {
      transform: translateX(3px);
    }

    /* Mobile toggle */
    .gl-nav-toggle {
      display: none;
      background: none;
      border: none;
      padding: 6px;
      border-radius: var(--radius-sm);
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
    }

    .gl-nav-toggle span {
      display: block;
      width: 22px;
      height: 1.5px;
      background: var(--dark);
      border-radius: 2px;
      transition: all var(--transition-base);
    }

    /* Toast container */
    #gl-toast-container {
      position: fixed;
      bottom: 28px;
      right: 28px;
      z-index: 9000;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    @media(max-width:860px) {
      #site-nav {
        padding: 0 24px;
      }

      .gl-nav-links {
        display: none;
      }

      .gl-nav-links.open {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        position: absolute;
        top: 68px;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        padding: 20px 24px 28px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
      }

      .gl-nav-links.open a {
        padding: 10px 14px;
        width: 100%;
      }

      .gl-nav-toggle {
        display: flex;
      }
    }
  </style>
</head>

<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>

  <!-- Scroll progress -->
  <div id="gl-scroll-progress"></div>
  <!-- Toast notifications -->
  <div id="gl-toast-container"></div>

  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     NAVIGATION BAR
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <nav id="site-nav" role="navigation" aria-label="Main navigation">

    <!-- Logo -->
    <a href="<?php echo esc_url(home_url('/')); ?>" class="gl-logo" aria-label="GlamLux2Lux Home">
      <div class="gl-logo-mark"><span>G</span></div>
      <span class="gl-logo-text">GlamLux<em>2</em>Lux</span>
    </a>

    <!-- Nav links (hardcoded lean ops navigation) -->
    <ul class="gl-nav-links" id="gl-nav-links">
      <li><a href="<?php echo esc_url(home_url('/')); ?>" <?php echo is_front_page() ? 'class="active"' : ''; ?>>Home</a></li>
      <li><a href="<?php echo esc_url(home_url('/enterprise-portal')); ?>">Enterprise Portal</a></li>
      <li><a href="<?php echo esc_url(home_url('/franchise-enquiry')); ?>">Franchise Enquiry</a></li>
    </ul>

    <div style="display:flex;align-items:center;gap:12px;">
      <a href="<?php echo esc_url(home_url('/franchise-enquiry')); ?>" class="gl-nav-cta" id="nav-cta">
        Join the Franchise
        <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
          <path d="M2.5 6.5h8M6.5 2.5l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </a>
      <button class="gl-nav-toggle" id="gl-nav-toggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>

  </nav>

  <script>
    (function () {
      var nav = document.getElementById('site-nav');
      var bar = document.getElementById('gl-scroll-progress');
      var toggle = document.getElementById('gl-nav-toggle');
      var links = document.getElementById('gl-nav-links');

      // Scroll progress + nav state
      function paint(y) {
        var max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        var pct = Math.min(1, y / max);
        if (bar) bar.style.transform = 'scaleX(' + pct + ')';
        if (nav) {
          if (y > 40) { nav.classList.add('scrolled'); }
          else { nav.classList.remove('scrolled'); }
        }
      }
      var ticking = false;
      window.addEventListener('scroll', function () { if (!ticking) { requestAnimationFrame(function () { paint(window.scrollY); ticking = false; }); ticking = true; } }, { passive: true });
      window.addEventListener('glamlux:scroll', function (e) { paint(e.detail && typeof e.detail.y === 'number' ? e.detail.y : window.scrollY); });
      paint(0);

      // Mobile toggle
      if (toggle && links) {
        toggle.addEventListener('click', function () {
          var open = links.classList.toggle('open');
          var spans = toggle.querySelectorAll('span');
          if (open) {
            spans[0].style.transform = 'rotate(45deg) translate(4px,4px)';
            spans[1].style.opacity = '0';
            spans[2].style.transform = 'rotate(-45deg) translate(4px,-4px)';
          } else {
            spans[0].style.transform = ''; spans[1].style.opacity = ''; spans[2].style.transform = '';
          }
        });
      }
    })();
  </script>