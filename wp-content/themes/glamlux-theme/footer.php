<!-- ══ FOOTER — GlamLux2Lux Premium ══════════════════════════════════════════ -->
<footer style="background:#0F0F0F;padding:80px 56px 48px;border-top:1px solid rgba(255,255,255,0.06);">
    <div style="max-width:1320px;margin:0 auto;">

        <div
            style="display:flex;justify-content:space-between;align-items:flex-start;gap:64px;margin-bottom:64px;flex-wrap:wrap;">

            <!-- Brand column -->
            <div style="max-width:300px;">
                <div style="display:flex;align-items:center;gap:11px;margin-bottom:20px;">
                    <div
                        style="width:36px;height:36px;background:linear-gradient(135deg,#C6A75E,#D4B97A);border-radius:8px;display:grid;place-items:center;">
                        <span
                            style="font-family:'Playfair Display',serif;font-weight:700;color:#fff;font-size:0.9375rem;">G</span>
                    </div>
                    <span
                        style="font-family:'Playfair Display',serif;font-weight:700;font-size:1.0625rem;color:#fff;letter-spacing:-0.01em;">GlamLux<span
                            style="color:#C6A75E;">2</span>Lux</span>
                </div>
                <p style="font-size:0.875rem;color:rgba(255,255,255,0.42);line-height:1.75;margin-bottom:24px;">The
                    intersection of luxury beauty and enterprise technology. Elevating every salon. Empowering every
                    franchise.</p>
                <!-- Social -->
                <div style="display:flex;gap:12px;">
                    <?php foreach (['instagram', 'linkedin', 'twitter'] as $net): ?>
                        <a href="#" aria-label="<?php echo esc_attr($net); ?>"
                            style="width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,0.12);display:grid;place-items:center;color:rgba(255,255,255,0.45);text-decoration:none;font-size:0.75rem;letter-spacing:0.04em;transition:all 200ms ease;"
                            onmouseover="this.style.borderColor='#C6A75E';this.style.color='#C6A75E';this.style.transform='translateY(-2px)'"
                            onmouseout="this.style.borderColor='rgba(255,255,255,0.12)';this.style.color='rgba(255,255,255,0.45)';this.style.transform=''">
                            <?php echo strtoupper(substr($net, 0, 2)); ?>
                        </a>
                        <?php
                    endforeach; ?>
                </div>
            </div>

            <!-- Links -->
            <?php
            $footer_menus = array(
                'Company' => 'footer_company',
                'Services' => 'footer_services',
                'Franchise' => 'footer_franchise',
            );
            $locations = get_nav_menu_locations();

            foreach ($footer_menus as $group_title => $location):
                ?>
                <div>
                    <h4
                        style="font-size:0.6875rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-bottom:20px;">
                        <?php echo esc_html($group_title); ?>
                    </h4>
                    <ul style="list-style:none;">
                        <?php
                        if (has_nav_menu($location) && isset($locations[$location])) {
                            $menu_items = wp_get_nav_menu_items($locations[$location]);
                            if ($menu_items) {
                                foreach ($menu_items as $item) {
                                    ?>
                                    <li style="margin-bottom:12px;">
                                        <a href="<?php echo esc_url($item->url); ?>"
                                            style="text-decoration:none;font-size:0.875rem;color:rgba(255,255,255,0.50);transition:color 180ms ease;"
                                            onmouseover="this.style.color='#C6A75E'"
                                            onmouseout="this.style.color='rgba(255,255,255,0.50)'"><?php echo esc_html($item->title); ?></a>
                                    </li>
                                    <?php
                                }
                            }
                        } else {
                            echo '<li style="margin-bottom:12px;"><span style="font-size:0.875rem;color:rgba(255,255,255,0.25);">No menu assigned</span></li>';
                        }
                        ?>
                    </ul>
                </div>
                <?php
            endforeach; ?>

        </div>

        <!-- Bottom -->
        <div
            style="border-top:1px solid rgba(255,255,255,0.06);padding-top:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <span style="font-size:0.8125rem;color:rgba(255,255,255,0.25);">&copy; <?php echo date('Y'); ?> GlamLux2Lux.
                All rights reserved.</span>
            <div style="display:flex;gap:24px;">
                <?php foreach (array('Privacy Policy', 'Terms', 'GDPR') as $p): ?>
                    <a href="#"
                        style="font-size:0.8125rem;color:rgba(255,255,255,0.25);text-decoration:none;transition:color 180ms ease;"
                        onmouseover="this.style.color='rgba(255,255,255,0.55)'"
                        onmouseout="this.style.color='rgba(255,255,255,0.25)'"><?php echo esc_html($p); ?></a>
                    <?php
                endforeach; ?>
            </div>
        </div>

    </div>
</footer>

<!-- Old duplicate booking modal removed — single modal now in front-page.php (#gl-modal-booking) -->


<?php wp_footer(); ?>

<!-- ══ ANIMATION ENGINE ═══════════════════════════════════════════════════════ -->
<script>
    (function () {
        'use strict';

        // ── 1. GSAP ScrollTrigger (native scroll — Lenis disabled for reliability) ──
        if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
            gsap.registerPlugin(ScrollTrigger);
            gsap.ticker.lagSmoothing(0);
        }

        // ── Scroll event for custom listeners ───────────────────────────────────────
        window.addEventListener('scroll', function () {
            var y = window.scrollY || document.documentElement.scrollTop;
            var h = document.documentElement.scrollHeight - window.innerHeight;
            window.dispatchEvent(new CustomEvent('glamlux:scroll', {
                detail: { y: y, progress: h > 0 ? y / h : 0 }
            }));
        }, { passive: true });

        // ── 2b. GSAP Hero Entrance (guarded) ────────────────────────────────────────
        if (typeof gsap !== 'undefined') {
            var tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

            // Eyebrow
            var eyebrow = document.getElementById('hero-eyebrow');
            if (eyebrow) tl.to(eyebrow, { opacity: 1, y: 0, duration: 0.6 }, 0.1);

            // Headline — blur + fade
            var headline = document.getElementById('hero-headline');
            if (headline) tl.to(headline, { opacity: 1, y: 0, filter: 'blur(0px)', duration: 0.8 }, 0.3);

            // Sub
            var sub = document.getElementById('hero-sub');
            if (sub) tl.to(sub, { opacity: 1, y: 0, duration: 0.7 }, 0.55);

            // CTA
            var actions = document.getElementById('hero-actions');
            if (actions) tl.to(actions, { opacity: 1, y: 0, duration: 0.6 }, 0.75);

            // Scroll hint
            var hint = document.getElementById('hero-scroll-hint');
            if (hint) gsap.to(hint, { opacity: 0.55, duration: 0.8, delay: 1.4 });

            // Hero BG parallax on scroll
            var heroBg = document.getElementById('hero-bg-el');
            if (heroBg && typeof ScrollTrigger !== 'undefined') {
                ScrollTrigger.create({
                    trigger: '#gl-hero',
                    start: 'top top',
                    end: 'bottom top',
                    onUpdate: function (self) {
                        var v = 1 + self.progress * 0.05;
                        heroBg.style.transform = 'scale(' + v + ') translateY(' + (self.progress * 5) + '%)';
                    }
                });
            }
        } else {
            // CSS fallback: show hero content immediately if GSAP is unavailable
            ['hero-eyebrow', 'hero-headline', 'hero-sub', 'hero-actions', 'hero-scroll-hint'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.style.opacity = '1'; el.style.transform = 'none'; el.style.filter = 'none'; }
            });
        }

        // ── 3. Section reveal with IntersectionObserver ──────────────────────────────
        if ('IntersectionObserver' in window) {
            var revealObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.style.opacity = '1';
                        e.target.style.transform = 'translateY(0)';
                        revealObs.unobserve(e.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

            document.querySelectorAll('.gl-reveal').forEach(function (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(28px)';
                el.style.transition = 'opacity 0.55s cubic-bezier(0.4,0,0.2,1), transform 0.55s cubic-bezier(0.4,0,0.2,1)';
                revealObs.observe(el);
            });
        }

        // ── 4. Lazy background images ────────────────────────────────────────────────
        if ('IntersectionObserver' in window) {
            var bgObs = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        var el = e.target, bg = el.getAttribute('data-bg');
                        if (bg) { el.style.backgroundImage = 'url("' + bg + '")'; el.removeAttribute('data-bg'); }
                        obs.unobserve(el);
                    }
                });
            }, { rootMargin: '200px 0px' });

            document.querySelectorAll('.gl-lazy-bg[data-bg]').forEach(function (el) { bgObs.observe(el); });
        } else {
            document.querySelectorAll('.gl-lazy-bg[data-bg]').forEach(function (el) {
                el.style.backgroundImage = 'url("' + el.getAttribute('data-bg') + '")';
            });
        }

        // ── 5. Skeleton → Real Services swap ────────────────────────────────────────
        (function swapServices() {
            var skeleton = document.getElementById('services-skeleton');
            var grid = document.getElementById('services-grid');
            if (!skeleton || !grid) return;

            setTimeout(function () {
                skeleton.style.transition = 'opacity 400ms ease';
                skeleton.style.opacity = '0';
                setTimeout(function () {
                    skeleton.style.display = 'none';
                    grid.style.display = 'grid';
                    grid.style.opacity = '0';
                    setTimeout(function () {
                        grid.style.transition = 'opacity 400ms ease';
                        grid.style.opacity = '1';
                    }, 20);
                }, 400);
            }, 900); // simulate 900ms load, then reveal
        })();

        // ── 6. Modal Open / Close ────────────────────────────────────────────────────
        window.glamluxOpenModal = function (id) {
            var overlay = document.getElementById('gl-modal-' + id);
            if (!overlay) return;
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'auto';
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(function () {
                overlay.style.opacity = '1';
                var inner = overlay.querySelector('.gl-modal-inner') || overlay.querySelector('.gl-modal');
                if (inner) inner.style.transform = 'scale(1) translateY(0)';
            }, 10);
        };

        window.glamluxCloseModal = function (id) {
            var overlay = document.getElementById('gl-modal-' + id);
            if (!overlay) return;
            // CRITICAL: restore body overflow FIRST, before anything that might error
            document.body.style.overflow = '';
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'none';
            var inner = overlay.querySelector('.gl-modal-inner') || overlay.querySelector('.gl-modal');
            if (inner) inner.style.transform = 'scale(0.93) translateY(20px)';
            setTimeout(function () { overlay.style.display = 'none'; }, 320);
        };

        // Wire "Book an Appointment" buttons to modal
        document.querySelectorAll('[data-gl-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () { glamluxOpenModal(btn.getAttribute('data-gl-modal')); });
        });

        // Close on overlay click
        document.querySelectorAll('.gl-modal-overlay').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target === el) glamluxCloseModal(el.id.replace('gl-modal-', ''));
            });
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.gl-modal-overlay[style*="flex"]').forEach(function (el) {
                    glamluxCloseModal(el.id.replace('gl-modal-', ''));
                });
            }
        });

        // ── 7. Booking form submission ───────────────────────────────────────────────
        var bookingForm = document.getElementById('gl-booking-form');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = document.getElementById('gl-book-submit');
                var origText = btn.textContent;
                btn.textContent = 'Confirming…';
                btn.disabled = true;
                btn.style.opacity = '0.7';

                var fd = new FormData(bookingForm);
                var date = fd.get('appointment_date');
                var time = fd.get('appointment_time');
                var payload = JSON.stringify({
                    service_id: fd.get('service_id'),
                    appointment_time: date + ' ' + time + ':00',
                    notes: fd.get('client_name') + ' | ' + fd.get('phone'),
                });

                fetch('/wp-json/glamlux/v1/book', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (window.GlamLux && window.GlamLux.nonce) || '',
                    },
                    body: payload,
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        glamluxCloseModal('booking');
                        glamluxToast('✓ Appointment confirmed! We\'ll reach out shortly.', 'success');
                        bookingForm.reset();
                    })
                    .catch(function () {
                        glamluxToast('Appointment request sent. We\'ll confirm via call.', '');
                    })
                    .finally(function () {
                        btn.textContent = origText;
                        btn.disabled = false;
                        btn.style.opacity = '1';
                    });
            });
        }

        // ── 8. Toast notifications ───────────────────────────────────────────────────
        window.glamluxToast = function (message, type) {
            var container = document.getElementById('gl-toast-container');
            if (!container) return;
            var toast = document.createElement('div');
            toast.className = 'gl-toast' + (type ? ' ' + type : '');
            toast.style.cssText = 'padding:14px 20px;background:#fff;border-radius:12px;box-shadow:0 20px 48px rgba(0,0,0,0.12);font-size:0.875rem;font-weight:500;color:#121212;border-left:3px solid #C6A75E;transform:translateX(120%);transition:transform 320ms cubic-bezier(0,0,0.2,1);min-width:260px;max-width:380px;';
            if (type === 'success') toast.style.borderColor = '#4CAF50';
            if (type === 'error') toast.style.borderColor = '#EF5350';
            toast.textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    toast.style.transform = 'translateX(0)';
             setTimeout(function () {
                        toast.style.transform = 'translateX(120%)';
                        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 320);
                    }, 4000);
                });
            });
        };

// ── 5. Skeleton → Real Services swap ────────────────────────────────────────
(function swapServices(){
    var skeleton = document.getElementById('services-skeleton');
    var grid     = document.getElementById('services-grid');
    if (!skeleton || !grid) return;

    setTimeout(function(){
        skeleton.style.transition = 'opacity 400ms ease';
        skeleton.style.opacity    = '0';
        setTimeout(function(){
            skeleton.style.display = 'none';
            grid.style.display     = 'grid';
            grid.style.opacity     = '0';
            setTimeout(function(){
                grid.style.transition = 'opacity 400ms ease';
                grid.style.opacity    = '1';
            }, 20);
        }, 400);
    }, 900); // simulate 900ms load, then reveal
})();

// ── 6. Modal Open / Close ────────────────────────────────────────────────────
window.glamluxOpenModal = function(id) {
    var overlay = document.getElementById('gl-modal-' + id);
    if (!overlay) return;
    overlay.style.opacity = '0';
    overlay.style.pointerEvents = 'auto';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function(){
        overlay.style.opacity = '1';
        overlay.querySelector('.gl-modal').style.transform = 'scale(1) translateY(0)';
    }, 10);
};

window.glamluxCloseModal = function(id) {
    var overlay = document.getElementById('gl-modal-' + id);
    if (!overlay) return;
    overlay.style.opacity = '0';
    overlay.querySelector('.gl-modal').style.transform = 'scale(0.93) translateY(20px)';
    document.body.style.overflow = '';
    setTimeout(function(){ overlay.style.display = 'none'; }, 320);
};

// Wire "Book an Appointment" buttons to modal
document.querySelectorAll('[data-gl-modal]').forEach(function(btn){
    btn.addEventListener('click', function(){ glamluxOpenModal(btn.getAttribute('data-gl-modal')); });
});

// Close on overlay click
document.querySelectorAll('.gl-modal-overlay').forEach(function(el){
    el.addEventListener('click', function(e){
        if (e.target === el) glamluxCloseModal(el.id.replace('gl-modal-',''));
    });
});

// Close on Escape
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        document.querySelectorAll('.gl-modal-overlay[style*="flex"]').forEach(function(el){
            glamluxCloseModal(el.id.replace('gl-modal-',''));
        });
    }
});

// ── 7. Booking form submission ───────────────────────────────────────────────
var bookingForm = document.getElementById('gl-booking-form');
if (bookingForm) {
    bookingForm.addEventListener('submit', function(e){
        e.preventDefault();
        var btn = document.getElementById('gl-book-submit');
        var origText = btn.textContent;
        btn.textContent = 'Confirming…';
        btn.disabled = true;
        btn.style.opacity = '0.7';

        var fd   = new FormData(bookingForm);
        var date = fd.get('appointment_date');
        var time = fd.get('appointment_time');
        var payload = JSON.stringify({
            service_id:       fd.get('service_id'),
            appointment_time: date + ' ' + time + ':00',
            notes:            fd.get('client_name') + ' | ' + fd.get('phone'),
        });

        fetch('/wp-json/glamlux/v1/book', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (window.GlamLux && window.GlamLux.nonce) || '',
            },
            body: payload,
        })
        .then(function(r){ 
            // PHASE 4: Validate HTTP response status before parsing JSON
            if (!r.ok) {
                throw new Error('API request failed: ' + r.status + ' ' + r.statusText);
            }
            return r.json(); 
        })
        .then(function(data){
            glamluxCloseModal('booking');
            glamluxToast('✓ Appointment confirmed! We\'ll reach out shortly.', 'success');
            bookingForm.reset();
        })
        .catch(function(){
            glamluxToast('Appointment request sent. We\'ll confirm via call.', '');
        })
        .finally(function(){
            btn.textContent = origText;
            btn.disabled    = false;
            btn.style.opacity = '1';
        });
    });
}

// ── 8. Toast notifications ───────────────────────────────────────────────────
window.glamluxToast = function(message, type) {
    var container = document.getElementById('gl-toast-container');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'gl-toast' + (type ? ' ' + type : '');
    toast.style.cssText = 'padding:14px 20px;background:#fff;border-radius:12px;box-shadow:0 20px 48px rgba(0,0,0,0.12);font-size:0.875rem;font-weight:500;color:#121212;border-left:3px solid #C6A75E;transform:translateX(120%);transition:transform 320ms cubic-bezier(0,0,0.2,1);min-width:260px;max-width:380px;';
    if (type === 'success') toast.style.borderColor = '#4CAF50';
    if (type === 'error')   toast.style.borderColor = '#EF5350';
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(function(){ requestAnimationFrame(function(){
        toast.style.transform = 'translateX(0)';
        setTimeout(function(){
            toast.style.transform = 'translateX(120%)';
            setTimeout(function(){ if(toast.parentNode) toast.parentNode.removeChild(toast); }, 320);
        }, 4000);
    });});
};

})();
</script>

</body>

</html>