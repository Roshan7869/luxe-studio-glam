<?php
/**
 * Template Name: Franchise Apply
 * Public franchise application form → POST to /wp-json/glamlux/v1/leads
 * Stores in wp_gl_leads with interest_type = 'franchise'
 */
get_header();
?>

<style>
/* Reset and base tokens in case omitted by header */
:root {
  --gold: #C6A75E; --gold-light: #D4B97A; --gold-dark: #A8893E;
  --dark: #0F0F0F; --dark-2: #1A1A1A; 
  --off-white: #F8F7F3; --off-white-2: #EEECEA;
  --text-primary: #0F0F0F; --text-secondary: #666;
  --radius-md: 16px; --radius-xl: 32px;
  --transition-fast: 160ms ease; --transition-base: 280ms cubic-bezier(0.4,0,0.2,1);
}
</style>

<main style="padding-top:68px;background:var(--off-white);min-height:100vh;">

<!-- Hero Sub-header -->
<section style="background:linear-gradient(135deg,var(--dark) 0%,#1e1a14 100%);padding:96px 56px 80px;position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 70% 50%,rgba(198,167,94,0.12) 0%,transparent 70%);pointer-events:none;"></div>
    
    <div style="max-width:1320px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:72px;align-items:center;">
        
        <!-- Left text content -->
        <div style="position:relative;z-index:2;">
            <div style="display:inline-flex;align-items:center;gap:8px;padding:5px 14px;background:rgba(198,167,94,0.12);border:1px solid rgba(198,167,94,0.28);border-radius:9999px;font-size:0.625rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:var(--gold);margin-bottom:28px;backdrop-filter:blur(8px);">
                <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                Franchise Opportunity
            </div>
            
            <h1 style="font-family:'Playfair Display',serif;font-size:clamp(2.5rem,4vw,3.75rem);font-weight:700;color:#fff;margin-bottom:18px;letter-spacing:-0.025em;line-height:1.1;">
                Own a GlamLux<span style="color:var(--gold);">2</span>Lux Studio
            </h1>
            <p style="font-size:1.0625rem;color:rgba(255,255,255,0.65);line-height:1.75;max-width:440px;margin-bottom:44px;">
                Join India's fastest-growing luxury beauty franchise. Enterprise SaaS, proven brand appeal, and full operational support from day one.
            </p>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:400px;">
                <?php
$why = [
    ['₹25L–₹40L', 'Investment Range'],
    ['18–22%', 'Avg. Franchise ROI'],
    ['60 Days', 'Setup to Opening'],
    ['24/7', 'Platform Support'],
];
foreach ($why as $w): ?>
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-md);padding:20px;backdrop-filter:blur(10px);">
                    <div style="font-family:'Playfair Display',serif;font-size:1.375rem;font-weight:700;color:var(--gold);margin-bottom:2px;"><?php echo esc_html($w[0]); ?></div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.55);letter-spacing:0.02em;"><?php echo esc_html($w[1]); ?></div>
                </div>
                <?php
endforeach; ?>
            </div>
        </div>

        <!-- Application Form -->
        <div style="background:#fff;border-radius:var(--radius-xl);padding:44px;box-shadow:0 32px 80px rgba(0,0,0,0.25);position:relative;z-index:2;">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:var(--dark);margin-bottom:8px;">Apply Now</h2>
            <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:32px;">Submit your preliminary details and our franchise directors will contact you.</p>

            <div id="gl-franchise-success" style="display:none;background:#f3faeb;border:1px solid #c7e5a8;border-radius:var(--radius-md);padding:24px;margin-bottom:28px;text-align:center;">
                <div style="font-size:1.75rem;margin-bottom:10px;">✨</div>
                <div style="font-size:1.125rem;font-family:'Playfair Display',serif;font-weight:700;color:var(--dark);margin-bottom:6px;">Application Received</div>
                <div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;">Your details have been securely transmitted to our franchise board. Please check your email for confirmation.</div>
            </div>

            <div id="gl-franchise-error" style="display:none;background:#fff5f5;border:1px solid #fecaca;border-radius:var(--radius-md);padding:16px 20px;margin-bottom:24px;font-size:0.875rem;color:#b91c1c;"></div>

            <form id="gl-franchise-form" novalidate>
                <?php wp_nonce_field('glamlux_franchise_apply', 'gl_nonce', true, true); ?>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">Full Name *</label>
                        <input type="text" name="name" id="fr-name" required placeholder="Your full name"
                               style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">Email Address *</label>
                        <input type="email" name="email" id="fr-email" required placeholder="you@email.com"
                               style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">Phone Number *</label>
                        <input type="tel" name="phone" id="fr-phone" required placeholder="+91 XXXXX XXXXX"
                               style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">State / City *</label>
                        <input type="text" name="state" id="fr-state" required placeholder="e.g. Maharashtra"
                               style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">Investment Budget</label>
                    <select name="budget" style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;appearance:none;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'">
                        <option value="">Select your investment range…</option>
                        <option value="25-30L">₹25L – ₹30L</option>
                        <option value="30-40L">₹30L – ₹40L</option>
                        <option value="40L+">₹40L+</option>
                    </select>
                </div>

                <div style="margin-bottom:32px;">
                    <label style="display:block;font-size:0.6875rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:8px;">Tell us about yourself</label>
                    <textarea name="message" rows="3" placeholder="Brief background — business experience, location interest, timeline…"
                              style="width:100%;background:var(--off-white);border:1.5px solid var(--off-white-2);border-radius:12px;padding:13px 16px;font-size:0.9375rem;color:var(--dark);font-family:'Inter',sans-serif;outline:none;resize:vertical;transition:border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--gold)'" onblur="this.style.borderColor='var(--off-white-2)'"></textarea>
                </div>

                <button type="submit" id="gl-fr-submit"
                        style="width:100%;background:var(--gold);color:#fff;padding:17px 24px;border-radius:9999px;font-size:0.9375rem;font-weight:600;letter-spacing:0.04em;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(198,167,94,0.35);transition:all var(--transition-fast);display:flex;align-items:center;justify-content:center;gap:10px;"
                        onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 32px rgba(198,167,94,0.50)'"
                        onmouseout="this.style.transform='';this.style.boxShadow='0 6px 20px rgba(198,167,94,0.35)'">
                    Submit Application
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <p style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:16px;margin-bottom:0;">By submitting, you agree to our stringent privacy policy. We will never share your data.</p>
            </form>
        </div>
        
    </div>
</section>

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
            errBox.textContent = 'Please firmly verify that Name, Email, Phone, and State are filled.';
            errBox.style.display = 'block';
            return;
        }

        var origHTML = submit.innerHTML;
        submit.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="animation:gl-spin 1s linear infinite"><path d="M12 2a10 10 0 1 0 10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg> Submitting…';
        submit.disabled = true;
        submit.style.opacity = '0.8';

        fetch((window.GlamLux && window.GlamLux.apiRoot ? window.GlamLux.apiRoot : '/wp-json/glamlux/v1/') + 'leads', {
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
        .then(function (r) { 
            // PHASE 4: Validate HTTP response status before parsing JSON
            if (!r.ok) {
                throw new Error('API request failed: ' + r.status + ' ' + r.statusText);
            }
            return r.json(); 
        })
        .then(function (data) {
            if (data && data.success) {
                // Instantly swap the UI to success state
                form.style.opacity = '0';
                setTimeout(function(){
                    form.style.display = 'none';
                    sucBox.style.display = 'block';
                    sucBox.animate([{opacity:0,transform:'translateY(10px)'},{opacity:1,transform:'translateY(0)'}], {duration:400,easing:'ease-out'});
                    
                    // Show a global toast if available
                    if (window.glamluxToast) glamluxToast('Application effectively transmitted.', 'success');
                }, 300);
            } else {
                var msg = (data && data.message) ? data.message : 'Submission failed. Please check your network context.';
                errBox.textContent = msg;
                errBox.style.display = 'block';
                submit.innerHTML = origHTML;
                submit.disabled = false;
                submit.style.opacity = '1';
            }
        })
        .catch(function () {
            errBox.textContent = 'Network communication error. Please try again.';
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
@media(max-width:1024px){
  section>div{grid-template-columns:1fr !important;gap:40px !important;}
  h1{font-size:2.5rem !important;}
}
@media(max-width:768px){
  section{padding:80px 24px 60px !important;}
  #gl-franchise-form > div[style*="grid"]{grid-template-columns:1fr !important;gap:20px !important;}
}
</style>

<?php get_footer(); ?>
