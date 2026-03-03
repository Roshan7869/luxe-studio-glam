# ⚡ Quick Reference Guide - Luxe Studio Glam v3.1

**Print this or bookmark it!**

---

## 🚀 Start Local Development (30 seconds)

```bash
cd /path/to/luxe-studio-glam
cp .env.example .env
docker-compose up -d
open http://localhost
```

✅ **Result**: Homepage loads, no errors

---

## 📱 Test Mobile Responsiveness (1 minute)

### Option 1: Chrome DevTools
```
1. Open browser
2. Press F12 (DevTools)
3. Click device icon (top-left)
4. Select device: iPhone 12, iPad, etc.
5. Resize: 375px (mobile), 768px (tablet), 1366px (desktop)
```

### Option 2: Real Device
```
1. Find your IP: ipconfig getifaddr en0
2. On phone: open http://{your-ip}:80
3. Test all features
```

---

## 📊 CSS Breakpoint Cheat Sheet

```css
/* Mobile (< 576px) - DEFAULT */
.grid { grid-template-columns: 1fr; }

/* Small (576px+) */
@media (min-width: 576px) { 
  .grid { grid-template-columns: repeat(2, 1fr); }
}

/* Tablet (768px+) */
@media (min-width: 768px) { 
  .grid { grid-template-columns: repeat(3, 1fr); }
}

/* Desktop (992px+) */
@media (min-width: 992px) { 
  .grid { grid-template-columns: repeat(4, 1fr); }
}

/* Large (1200px+) */
@media (min-width: 1200px) { 
  .container { max-width: 1140px; }
}
```

---

## 🎨 Responsive Utilities (Use Now!)

```html
<!-- Responsive containers -->
<div class="container">Your content</div>

<!-- Responsive grids -->
<div class="grid grid-4">
  <div>Item 1</div>
  <div>Item 2</div>
  <div>Item 3</div>
  <div>Item 4</div>
</div>

<!-- Responsive images -->
<img class="img-fluid" src="image.jpg" alt="" loading="lazy">

<!-- Mobile-only content -->
<div class="show-mobile">Only on mobile</div>

<!-- Desktop-only content -->
<div class="hide-mobile">Only on desktop</div>

<!-- Responsive buttons -->
<button class="btn">Touch-optimized button</button>
```

---

## 🔧 Common Tasks

### View Service Logs
```bash
docker-compose logs -f wordpress    # PHP/WordPress
docker-compose logs -f db           # MySQL
docker-compose logs -f redis        # Redis
docker-compose logs -f              # All services
```

### Access Database
```bash
# Via MySQL CLI
docker-compose exec db mysql -u glamlux -p glamlux_local

# Via WordPress CLI
docker-compose exec wordpress wp db cli
```

### Clear Cache
```bash
# Redis
docker-compose exec redis redis-cli FLUSHALL

# WordPress transients
docker-compose exec wordpress wp transient delete-all
```

### Run Tests
```bash
docker-compose exec wordpress composer test
docker-compose exec wordpress composer analyze
```

---

## 🚨 Troubleshooting Quick Fixes

| Problem | Solution |
|---|---|
| Port 80 in use | Change to 8080 in docker-compose.yml |
| Database connection error | `docker-compose restart db && sleep 30` |
| Slow performance | `docker-compose exec redis redis-cli FLUSHALL` |
| PHP errors | `docker-compose logs -f wordpress \| grep -i error` |
| Out of memory | `docker system prune -a` |

---

## 📋 File Guide

### Read These First
| File | Time | Purpose |
|---|---|---|
| `PHASE_1_COMPLETION_SUMMARY.md` | 5 min | Overview of what was done |
| `AUDIT_MODERNIZATION_REPORT.md` | 10 min | Issues found & recommendations |
| `IMPLEMENTATION_CHECKLIST.md` | 10 min | What's left to do |

### Reference During Development
| File | Purpose |
|---|---|
| `MOBILE_FIRST_FRONTEND_GUIDE.md` | How to build responsive UI |
| `API_DOCUMENTATION.md` | How to use REST API |
| `LOCAL_DEVELOPMENT_SETUP.md` | Dev environment help |
| `responsive.css` | CSS framework reference |

---

## 🎯 Performance Targets

### Load Time
- First Contentful Paint (FCP): < 1.8s
- Largest Contentful Paint (LCP): < 2.5s
- Time to Interactive (TTI): < 3.8s

### Lighthouse Score (Target)
- Performance: 90+
- Accessibility: 95+
- Best Practices: 95+
- SEO: 95+

### API Response Time
- Auth: < 100ms
- List: < 150ms
- Create/Update: < 200ms

---

## 📱 Device Sizes to Test

```
Mobile:
  iPhone SE: 375px
  iPhone 12: 390px
  iPhone Pro: 428px
  Android: 360px, 412px

Tablet:
  iPad: 768px (portrait), 1024px (landscape)
  Android: 600px (landscape)

Desktop:
  Laptop: 1366px
  Desktop: 1920px
  UltraWide: 3440px
```

---

## 🔐 Important Endpoints

### Local Endpoints
```
Frontend:  http://localhost
Admin:     http://localhost/wp-admin
API:       http://localhost/wp-json/glamlux/v1/
Database:  localhost:3306
Redis:     localhost:6379
```

### Production Endpoints (Coming Soon)
```
Frontend:  https://glamlux.com
Admin:     https://glamlux.com/wp-admin
API:       https://glamlux.com/wp-json/glamlux/v1/
```

---

## 🛠️ VS Code Settings (Recommended)

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[php]": {
    "editor.defaultFormatter": "kokororin.vscode-phpfmt"
  },
  "emmet.includeLanguages": {
    "php": "html"
  }
}
```

---

## 📱 API Testing Examples

### Get Salons
```bash
curl http://localhost/wp-json/glamlux/v1/salons \
  -H "Authorization: Bearer {token}"
```

### Create Appointment
```bash
curl -X POST http://localhost/wp-json/glamlux/v1/appointments \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"service_id":101,"staff_id":5,"salon_id":1,"appointment_date":"2026-03-15","appointment_time":"09:30"}'
```

### List Appointments
```bash
curl http://localhost/wp-json/glamlux/v1/appointments/me \
  -H "Authorization: Bearer {token}"
```

---

## ✨ CSS Custom Properties

```css
/* Colors */
--gold: #C6A75E
--dark: #0F0F0F
--off-white: #F8F7F3

/* Spacing */
--spacing-mobile: 16px
--spacing-tablet: 24px
--spacing-desktop: 32px

/* Typography */
--text-sm: clamp(0.75rem, 2vw, 0.875rem)
--text-base: clamp(0.875rem, 2.5vw, 1rem)
--text-3xl: clamp(1.875rem, 6vw, 2.25rem)

/* Transitions */
--transition-fast: 160ms
--transition-base: 280ms
--transition-slow: 480ms
```

---

## 🎨 CSS Classes Cheat Sheet

```css
/* Layout */
.container          /* Responsive width */
.grid               /* Responsive grid */
.grid-4             /* 4 columns on desktop */
.flex-col           /* Column direction */
.flex-row           /* Row direction (desktop) */

/* Typography */
h1, h2, h3, h4      /* Fluid sizes */

/* Buttons */
.btn                /* Default button (touch-opt) */
.btn-gold           /* Primary button */
.btn-ghost          /* Ghost button */

/* Navigation */
.mobile-menu-toggle /* Hamburger button */
.mobile-menu        /* Mobile menu */

/* Images */
.img-fluid          /* Responsive image */
.aspect-ratio-16-9  /* Video aspect ratio */

/* Visibility */
.show-mobile        /* Mobile only */
.hide-mobile        /* Desktop only */
```

---

## 🚀 Deployment Checklist (Quick)

Before deploying:
- [ ] All tests passing
- [ ] Lighthouse score 90+
- [ ] Mobile devices tested
- [ ] No console errors
- [ ] API endpoints working
- [ ] Environment variables set
- [ ] Database migrations run
- [ ] Cache cleared

---

## 📞 Help & Support

### Common Issues
- **Homepage 500**: Fixed! ✅
- **Mobile not responsive**: Use `responsive.css` ✅
- **API errors**: Check `API_DOCUMENTATION.md`
- **Dev setup issues**: See `LOCAL_DEVELOPMENT_SETUP.md`

### Getting Help
1. Check documentation files first
2. Search error in logs: `docker-compose logs -f | grep error`
3. Review relevant guide
4. Ask team for assistance

---

## 🎓 Learning Resources

- **Responsive Design**: https://web.dev/responsive-web-design-basics/
- **CSS Media Queries**: https://developer.mozilla.org/en-US/docs/Web/CSS/Media_Queries
- **Mobile Best Practices**: https://web.dev/mobile-web-best-practices/
- **Lighthouse**: https://web.dev/lighthouse/
- **Web Vitals**: https://web.dev/vitals/

---

## 📝 Important Notes

1. **Mobile-First**: Start with mobile, scale up
2. **Touch Targets**: Min 48x48px for buttons
3. **Font Size**: Use 16px on inputs (prevent iOS zoom)
4. **Breakpoints**: Use standard sizes (576, 768, 992, 1200px)
5. **Testing**: Always test on real devices
6. **Performance**: Lazy load images, defer JS

---

## ✅ Next Steps (Quick Summary)

```
Week 1: ✅ Phase 1 Complete
  - Fixed HTTP 500
  - Added responsive framework
  - Created documentation

Week 2: Phase 2 (4-6 hours)
  - Add hamburger menu JS
  - Lazy load images
  - Run Lighthouse audit
  - Test mobile devices

Week 3: Phase 3+ (Coming)
  - API optimization
  - Performance testing
  - Production deployment
  - Monitoring setup
```

---

**Keep this handy! Copy & Paste commands frequently used.**

---

*Last Updated: 2026-03-03*  
*Version: 3.1.0*  
*Status: Ready for Use ✅*
