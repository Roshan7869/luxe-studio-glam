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
                        <?php echo esc_html($group_title); ?></h4>
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

<!-- ══ BOOKING MODAL ════════════════════════════════════════════════════════ -->
<div id="gl-booking-modal"
    style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(10,8,5,0.80);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:24px;"
    onclick="if(event.target===this)this.style.display='none';">
    <div
        style="background:#fff;border-radius:28px;max-width:520px;width:100%;overflow:hidden;box-shadow:0 32px 96px rgba(0,0,0,0.32);position:relative;">
        <!-- Modal header -->
        <div
            style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:36px 40px 28px;position:relative;">
            <div
                style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;background:radial-gradient(circle,rgba(198,167,94,0.15),transparent 70%);pointer-events:none;">
            </div>
            <p
                style="font-size:0.625rem;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#C6A75E;margin-bottom:10px;">
                Reserve Your Session</p>
            <h2
                style="font-family:'Playfair Display',Georgia,serif;font-size:1.75rem;font-weight:700;color:#fff;letter-spacing:-0.02em;margin:0 0 6px;">
                Book an Appointment</h2>
            <p style="font-size:0.875rem;color:rgba(255,255,255,0.52);margin:0;">Experience the GlamLux2Lux difference.
            </p>
            <button onclick="document.getElementById('gl-booking-modal').style.display='none'"
                style="position:absolute;top:20px;right:20px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.6);border-radius:50%;width:36px;height:36px;cursor:pointer;display:grid;place-items:center;font-size:1.2rem;line-height:1;transition:all 200ms;"
                onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                onmouseout="this.style.background='rgba(255,255,255,0.08)'">&times;</button>
        </div>
        <!-- Modal form body -->
        <div style="padding:36px 40px 40px;">
            <form id="gl-booking-form" onsubmit="glSubmitBooking(event)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;color:#6A6A6A;margin-bottom:6px;text-transform:uppercase;">Full
                            Name</label>
                        <input type="text" name="name" required placeholder="Your name"
                            style="width:100%;padding:13px 16px;border:1.5px solid rgba(0,0,0,0.10);border-radius:12px;font-family:'Inter',sans-serif;font-size:0.9375rem;background:#F7F6F2;color:#121212;outline:none;box-sizing:border-box;transition:border-color 200ms;"
                            onfocus="this.style.borderColor='#C6A75E';this.style.background='#fff'"
                            onblur="this.style.borderColor='rgba(0,0,0,0.10)';this.style.background='#F7F6F2'">
                    </div>
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;color:#6A6A6A;margin-bottom:6px;text-transform:uppercase;">Phone</label>
                        <input type="tel" name="phone" required placeholder="+91 98765 43210"
                            style="width:100%;padding:13px 16px;border:1.5px solid rgba(0,0,0,0.10);border-radius:12px;font-family:'Inter',sans-serif;font-size:0.9375rem;background:#F7F6F2;color:#121212;outline:none;box-sizing:border-box;transition:border-color 200ms;"
                            onfocus="this.style.borderColor='#C6A75E';this.style.background='#fff'"
                            onblur="this.style.borderColor='rgba(0,0,0,0.10)';this.style.background='#F7F6F2'">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label
                        style="display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;color:#6A6A6A;margin-bottom:6px;text-transform:uppercase;">Service</label>
                    <select name="service"
                        style="width:100%;padding:13px 16px;border:1.5px solid rgba(0,0,0,0.10);border-radius:12px;font-family:'Inter',sans-serif;font-size:0.9375rem;background:#F7F6F2;color:#121212;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color 200ms;"
                        onfocus="this.style.borderColor='#C6A75E';this.style.background='#fff'"
                        onblur="this.style.borderColor='rgba(0,0,0,0.10)';this.style.background='#F7F6F2'">
                        <option value="">Select a service...</option>
                        <option value="skincare">Skincare Rituals</option>
                        <option value="hair">Hair Couture</option>
                        <option value="body">Body Luxe Therapy</option>
                        <option value="nail">Nail Atelier</option>
                        <option value="bridal">Bridal Intelligence</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;color:#6A6A6A;margin-bottom:6px;text-transform:uppercase;">Preferred
                            Date</label>
                        <input type="date" name="date"
                            style="width:100%;padding:13px 16px;border:1.5px solid rgba(0,0,0,0.10);border-radius:12px;font-family:'Inter',sans-serif;font-size:0.9375rem;background:#F7F6F2;color:#121212;outline:none;box-sizing:border-box;transition:border-color 200ms;"
                            onfocus="this.style.borderColor='#C6A75E';this.style.background='#fff'"
                            onblur="this.style.borderColor='rgba(0,0,0,0.10)';this.style.background='#F7F6F2'">
                    </div>
                    <div>
                        <label
                            style="display:block;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;color:#6A6A6A;margin-bottom:6px;text-transform:uppercase;">Preferred
                            Time</label>
                        <select name="time"
                            style="width:100%;padding:13px 16px;border:1.5px solid rgba(0,0,0,0.10);border-radius:12px;font-family:'Inter',sans-serif;font-size:0.9375rem;background:#F7F6F2;color:#121212;outline:none;box-sizing:border-box;appearance:none;cursor:pointer;transition:border-color 200ms;"
                            onfocus="this.style.borderColor='#C6A75E';this.style.background='#fff'"
                            onblur="this.style.borderColor='rgba(0,0,0,0.10)';this.style.background='#F7F6F2'">
                            <option value="">Select time...</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                            <option value="18:00">6:00 PM</option>
                        </select>
                    </div>
                </div>
                <button type="submit" id="gl-booking-btn"
                    style="width:100%;padding:16px;background:linear-gradient(135deg,#C6A75E,#D4B97A);color:#fff;border:none;border-radius:100px;font-family:'Inter',sans-serif;font-size:0.9375rem;font-weight:600;cursor:pointer;letter-spacing:0.03em;transition:all 300ms;display:flex;align-items:center;justify-content:center;gap:8px;"
                    onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 32px rgba(198,167,94,0.35)'"
                    onmouseout="this.style.transform='';this.style.boxShadow=''">
                    Reserve My Session
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
                <p id="gl-booking-msg"
                    style="text-align:center;margin-top:14px;font-size:0.8125rem;min-height:18px;color:#C6A75E;"></p>
            </form>
        </div>
    </div>
</div>
<script>
    function glSubmitBooking(e) {
        e.preventDefault();
        var btn = document.getElementById('gl-booking-btn');
        var msg = document.getElementById('gl-booking-msg');
        var fd = new FormData(e.target);
        btn.disabled = true; btn.textContent = 'Sending…';
        fetch('<?php echo esc_url(rest_url('glamlux/v1/leads')); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' },
            body: JSON.stringify({ lead_type: 'appointment', name: fd.get('name'), phone: fd.get('phone'), notes: 'Service: ' + fd.get('service') + ' | Date: ' + fd.get('date') + ' | Time: ' + fd.get('time') })
        })
            .then(r => r.json())
            .then(d => {
                btn.style.display = 'none';
                msg.style.color = '#2ecc71';
                msg.textContent = '✓ Appointment request sent! We will confirm your slot shortly.';
            })
            .catch(() => {
                btn.disabled = false; btn.textContent = 'Reserve My Session';
                msg.style.color = '#e74c3c';
                msg.textContent = 'Something went wrong. Please call us directly.';
            });
    }
</script>
<!-- ══════════════════════════════════════════════════════════════════════════ -->

<?php wp_footer(); ?>

<!-- ══ ANIMATION ENGINE ═══════════════════════════════════════════════════════ -->
<script>
    (function () {
        'use strict';

        // ── 1. Lenis smooth scroll (single source of scroll truth) ─────────────────
        var lenis;
        if (typeof Lenis !== 'undefined') {
            lenis = new Lenis({
                duration: 1.15,
                smoothWheel: true,
                smoothTouch: false,
                touchMultiplier: 1.2,
            });

            // ── 2. GSAP hero entrance & Ticker Sync ─────────────────────────────────────
            if (typeof gsap !== 'undefined') {
                if (typeof ScrollTrigger !== 'undefined') {
                    gsap.registerPlugin(ScrollTrigger);
                    lenis.on('scroll', ScrollTrigger.update);
                }

                gsap.ticker.add(function (time) {
                    lenis.raf(time * 1000);
                });
                gsap.ticker.lagSmoothing(0);
            } else {
                function raf(time) {
                    lenis.raf(time);
                    requestAnimationFrame(raf);
                }
                requestAnimationFrame(raf);
            }

            lenis.on('scroll', function (e) {
                window.dispatchEvent(new CustomEvent('glamlux:scroll', {
                    detail: {
                        y: e && typeof e.scroll === 'number' ? e.scroll : window.scrollY,
                        progress: e && typeof e.progress === 'number' ? e.progress : 0,
                    }
                }));
            });
        }

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
                overlay.querySelector('.gl-modal').style.transform = 'scale(1) translateY(0)';
            }, 10);
        };

        window.glamluxCloseModal = function (id) {
            var overlay = document.getElementById('gl-modal-' + id);
            if (!overlay) return;
            overlay.style.opacity = '0';
            overlay.querySelector('.gl-modal').style.transform = 'scale(0.93) translateY(20px)';
            document.body.style.overflow = '';
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

    })();
</script>

</body>

</html>