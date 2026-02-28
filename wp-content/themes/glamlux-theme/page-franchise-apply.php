<?php
/**
 * Template Name: Franchise Apply
 * Public franchise application form → POST to /wp-json/glamlux/v1/leads
 * Stores in wp_gl_leads with interest_type = 'franchise'
 */
get_header();
?>

<main style="padding-top:72px;background:#F7F6F2;min-height:100vh;">

<!-- Hero Sub-header -->
<div style="background:linear-gradient(135deg,#121212 0%,#1e1a14 100%);padding:80px 64px 64px;position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 70% 50%,rgba(198,167,94,0.10) 0%,transparent 70%);pointer-events:none;"></div>
    <div style="max-width:1440px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;">
        <div>
            <div style="display:inline-flex;align-items:center;gap:8px;padding:5px 14px;background:rgba(198,167,94,0.15);border:1px solid rgba(198,167,94,0.30);border-radius:9999px;font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:#C6A75E;margin-bottom:24px;">
                <svg width="8" height="8" viewBox="0 0 8 8" fill="#C6A75E"><circle cx="4" cy="4" r="4"/></svg>
                Franchise Opportunity
            </div>
            <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2rem,4vw,3.25rem);font-weight:700;color:#fff;margin-bottom:16px;letter-spacing:-0.025em;line-height:1.15;">
                Own a GlamLux<span style="color:#C6A75E;">2</span>Lux Studio
            </h1>
            <p style="font-size:1rem;color:rgba(255,255,255,0.65);line-height:1.75;max-width:420px;margin-bottom:36px;">
                Join India's fastest-growing luxury beauty franchise. Enterprise SaaS, proven brand, full operations support from day one.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:380px;">
                <?php
$why = [
    ['₹25L–₹40L', 'Investment Range'],
    ['18–22%', 'Avg. Franchise ROI'],
    ['60 Days', 'Setup to Opening'],
    ['24/7', 'Platform Support'],
];
foreach ($why as $w): ?>
                <div style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.10);border-radius:16px;padding:18px;">
                    <div style="font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:#C6A75E;margin-bottom:4px;"><?php echo esc_html($w[0]); ?></div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.55);"><?php echo esc_html($w[1]); ?></div>
                </div>
                <?php
endforeach; ?>
            </div>
        </div>
        <!-- Application Form -->
        <div style="background:#fff;border-radius:28px;padding:40px;box-shadow:0 24px 64px rgba(0,0,0,0.20);">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:#121212;margin-bottom:6px;">Apply Now</h2>
            <p style="font-size:0.875rem;color:#6A6A6A;margin-bottom:28px;">Fill in your details and our franchise team will contact you within 24 hours.</p>

            <div id="gl-franchise-success" style="display:none;background:linear-gradient(135deg,#E8F5E9,#F1F8E9);border:1px solid #81C784;border-radius:16px;padding:20px 24px;margin-bottom:24px;">
                <div style="font-size:1.5rem;margin-bottom:8px;">🎉</div>
                <div style="font-weight:600;color:#2E7D32;margin-bottom:4px;">Application Received!</div>
                <div style="font-size:0.875rem;color:#388E3C;">Our franchise team will contact you within 24 hours. Check your email for a confirmation.</div>
            </div>

            <div id="gl-franchise-error" style="display:none;background:#FFEBEE;border:1px solid #EF9A9A;border-radius:12px;padding:16px 20px;margin-bottom:20px;font-size:0.875rem;color:#C62828;"></div>

            <form id="gl-franchise-form" novalidate>
                <?php wp_nonce_field('glamlux_franchise_apply', 'gl_nonce', true, true); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Full Name *</label>
                        <input type="text" name="name" id="fr-name" required placeholder="Your full name"
                               style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border-color 200ms ease;"
                               onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Email Address *</label>
                        <input type="email" name="email" id="fr-email" required placeholder="you@email.com"
                               style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border-color 200ms ease;"
                               onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Phone Number *</label>
                        <input type="tel" name="phone" id="fr-phone" required placeholder="+91 XXXXX XXXXX"
                               style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border-color 200ms ease;"
                               onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">State / City *</label>
                        <input type="text" name="state" id="fr-state" required placeholder="e.g. Maharashtra"
                               style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;transition:border-color 200ms ease;"
                               onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Investment Budget</label>
                    <select name="budget" style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;appearance:none;box-sizing:border-box;transition:border-color 200ms ease;" onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'">
                        <option value="">Select your investment range…</option>
                        <option value="25-30L">₹25L – ₹30L</option>
                        <option value="30-40L">₹30L – ₹40L</option>
                        <option value="40L+">₹40L+</option>
                    </select>
                </div>
                <div style="margin-bottom:24px;">
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#6A6A6A;margin-bottom:8px;">Tell us about yourself</label>
                    <textarea name="message" rows="3" placeholder="Brief background — business experience, location interest, timeline…"
                              style="width:100%;background:#F7F6F2;border:1.5px solid #EAEAEA;border-radius:12px;padding:12px 16px;font-size:0.9375rem;color:#121212;font-family:'Inter',sans-serif;outline:none;resize:vertical;box-sizing:border-box;transition:border-color 200ms ease;"
                              onfocus="this.style.borderColor='#C6A75E'" onblur="this.style.borderColor='#EAEAEA'"></textarea>
                </div>
                <button type="submit" id="gl-fr-submit"
                        style="width:100%;background:#C6A75E;color:#fff;padding:16px 24px;border-radius:9999px;font-size:0.9375rem;font-weight:600;letter-spacing:0.04em;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(198,167,94,0.35);transition:all 200ms ease;display:flex;align-items:center;justify-content:center;gap:10px;"
                        onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 32px rgba(198,167,94,0.50)'"
                        onmouseout="this.style.transform='';this.style.boxShadow='0 6px 20px rgba(198,167,94,0.35)'">
                    Submit Application
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <p style="font-size:0.75rem;color:#9A9A9A;text-align:center;margin-top:12px;margin-bottom:0;">By submitting, you agree to our privacy policy. No spam — ever.</p>
            </form>
        </div>
    </div>
</div>

</main>

<script>
(function () {
    var form   = document.getElementById('gl-franchise-form');
    var submit = document.getElementById('gl-fr-submit');
    var errBox = document.getElementById('gl-franchise-error');
    var sucBox = document.getElementById('gl-franchise-success');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.style.display = 'none';

        var name    = document.getElementById('fr-name').value.trim();
        var email   = document.getElementById('fr-email').value.trim();
        var phone   = document.getElementById('fr-phone').value.trim();
        var state   = document.getElementById('fr-state').value.trim();
        var message = form.querySelector('[name="message"]').value.trim();
        var budget  = form.querySelector('[name="budget"]').value;

        if (!name || !email || !phone || !state) {
            errBox.textContent = 'Please fill in all required fields (Name, Email, Phone, State).';
            errBox.style.display = 'block';
            return;
        }

        var origHTML = submit.innerHTML;
        submit.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="animation:gl-spin 1s linear infinite"><path d="M12 2a10 10 0 1 0 10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Submitting…';
        submit.disabled = true;
        submit.style.opacity = '0.8';

        fetch('<?php echo esc_url(rest_url("glamlux/v1/leads")); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name:          name,
                email:         email,
                phone:         phone,
                state:         state,
                message:       message + (budget ? '\n\nBudget: ' + budget : ''),
                interest_type: 'franchise',
                source:        'website_apply_form',
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                form.style.display = 'none';
                sucBox.style.display = 'block';
            } else {
                var msg = (data && data.message) ? data.message : 'Submission failed. Please try again.';
                errBox.textContent = msg;
                errBox.style.display = 'block';
                submit.innerHTML = origHTML;
                submit.disabled = false;
                submit.style.opacity = '1';
            }
        })
        .catch(function () {
            errBox.textContent = 'Network error. Please check your connection and try again.';
            errBox.style.display = 'block';
            submit.innerHTML = origHTML;
            submit.disabled = false;
            submit.style.opacity = '1';
        });
    });
})();
</script>
<style>
@keyframes gl-spin { to { transform: rotate(360deg); } }
</style>

<?php get_footer(); ?>
