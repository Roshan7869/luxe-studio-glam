# 🚀 Local Development Setup Guide

## System Requirements

### Minimum
- Docker 20+
- Docker Compose 2+
- 4GB RAM
- 10GB disk space

### Recommended
- Docker Desktop (Windows/Mac) or Docker Engine (Linux)
- 8GB+ RAM
- 20GB+ disk space
- Git

---

## Quick Start (5 minutes)

### Step 1: Clone & Setup
```bash
cd /path/to/luxe-studio-glam
cp .env.example .env
```

### Step 2: Configure .env
```bash
# Database
WORDPRESS_DB_NAME=glamlux_local
WORDPRESS_DB_USER=glamlux
WORDPRESS_DB_PASSWORD=secure_password_123
MYSQL_ROOT_PASSWORD=root_password_123

# WordPress
WORDPRESS_ADMIN_USER=admin
WORDPRESS_ADMIN_PASSWORD=admin123
WORDPRESS_ADMIN_EMAIL=admin@glamlux.local
WORDPRESS_SITE_URL=http://localhost
WORDPRESS_HOME=http://localhost

# Debug
WP_DEBUG=true
WP_DEBUG_LOG=true
WP_DEBUG_DISPLAY=false
```

### Step 3: Start Services
```bash
docker-compose up -d
```

### Step 4: Wait for Services
```bash
# Check status
docker-compose ps

# Wait 2-3 minutes for database to initialize
```

### Step 5: Access Application
```
Frontend:  http://localhost
Admin:     http://localhost/wp-admin
Database:  localhost:3306
Redis:     localhost:6379
```

---

## Services Breakdown

### 1. Nginx (Port 80)
- **Role**: Reverse proxy, static file serving
- **Config**: `nginx.conf`
- **Logs**: `docker logs glamlux_nginx`

### 2. WordPress (via Docker)
- **Role**: PHP application server
- **PHP Version**: 8.2
- **Extensions**: PDO MySQL, Redis, GD, OpenSSL
- **Config**: `wp-config-railway.php` (auto-loaded)

### 3. MySQL (Port 3306)
- **Role**: Data persistence
- **Version**: 8.0
- **Database**: glamlux_local
- **Volumes**: `db_data` (persistent)

### 4. Redis (Port 6379)
- **Role**: Object caching, sessions
- **Memory**: 256MB
- **Policy**: noeviction (important!)
- **Persistence**: AOF enabled

---

## Common Development Tasks

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f wordpress
docker-compose logs -f db
docker-compose logs -f redis
```

### SSH into WordPress Container
```bash
docker-compose exec wordpress bash

# Or use WP-CLI directly
docker-compose exec wordpress wp --version
```

### Run PHP Command
```bash
docker-compose exec wordpress php -v
docker-compose exec wordpress composer --version
```

### Database Operations
```bash
# Access MySQL CLI
docker-compose exec db mysql -u glamlux -p glamlux_local

# Dump database
docker-compose exec db mysqldump -u glamlux -p glamlux_local > backup.sql

# Restore database
cat backup.sql | docker-compose exec -T db mysql -u glamlux -p glamlux_local
```

### Clear Cache
```bash
# Redis
docker-compose exec redis redis-cli FLUSHALL

# WordPress transients
docker-compose exec wordpress wp transient delete-all
```

---

## Development Workflow

### Adding a New Plugin Feature
1. Edit files in `wp-content/plugins/glamlux-core/`
2. Files auto-sync to container
3. Test at `http://localhost/wp-admin`

### Database Migrations
```bash
# Create migration
docker-compose exec wordpress wp eval-file wp-content/plugins/glamlux-core/scripts/migrate-v6-indexes.php

# Seed demo data
docker-compose exec wordpress wp eval-file wp-content/plugins/glamlux-core/scripts/_dev-only/seed-enterprise-visual-dataset.php
```

### Testing REST API
```bash
# Get auth token
curl -X POST http://localhost/wp-json/glamlux/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Make authenticated request
curl -X GET http://localhost/wp-json/glamlux/v1/salons \
  -H "Authorization: Bearer {token}"
```

### Running Tests
```bash
# Unit tests
docker-compose exec wordpress composer test

# Static analysis
docker-compose exec wordpress composer analyze
```

---

## Troubleshooting

### Port 80 Already in Use
```bash
# Find process using port 80
lsof -i :80

# Kill process
kill -9 <PID>

# Or use different port in docker-compose.yml
# Change: "80:80" to "8080:80"
# Then access: http://localhost:8080
```

### Database Connection Failed
```bash
# Check if DB container is running
docker-compose ps

# Restart database
docker-compose restart db

# Wait 30 seconds, then retry
```

### Redis Connection Issues
```bash
# Test Redis connection
docker-compose exec redis redis-cli ping
# Should return: PONG

# Check memory
docker-compose exec redis redis-cli INFO memory
```

### PHP Memory Limit
```bash
# In WordPress container, create memory limit override
docker-compose exec wordpress php -i | grep memory_limit

# Edit: wp-config.php
# Add: define('WP_MEMORY_LIMIT', '256M');
```

### Slow Database Queries
```bash
# Enable query logging
docker-compose exec db mysql -u glamlux -p glamlux_local

# In MySQL:
# SET GLOBAL slow_query_log = 'ON';
# SET GLOBAL long_query_time = 2;
# SELECT * FROM mysql.slow_log;
```

---

## Performance Optimization (Local)

### Enable Query Caching
```bash
# Check Redis connection
docker-compose exec wordpress wp plugin list

# Verify Redis Object Cache plugin active
```

### Database Indexing
```bash
# Check indexes
docker-compose exec db mysql -u glamlux -p glamlux_local \
  -e "SELECT * FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = 'glamlux_local';"
```

### Lighthouse Performance Test
```bash
# Inside container
docker-compose exec wordpress npm install -g lighthouse

# Or use locally (requires Node.js)
npm install -g @lhci/cli@latest
lhci autorun --config=.lighthouserc.json
```

---

## Stop & Clean Up

### Stop All Services (keeps data)
```bash
docker-compose down
```

### Full Reset (removes all data)
```bash
docker-compose down -v
# Then: docker-compose up -d
```

### Prune Docker Resources
```bash
docker system prune -a
```

---

## Environment Variables Reference

| Variable | Default | Usage |
|---|---|---|
| `WORDPRESS_DB_NAME` | glamlux_local | Database name |
| `WORDPRESS_DB_USER` | glamlux | MySQL user |
| `WORDPRESS_DB_PASSWORD` | (required) | MySQL password |
| `MYSQL_ROOT_PASSWORD` | (required) | MySQL root password |
| `WP_DEBUG` | true | Enable debug mode |
| `WP_REDIS_HOST` | redis | Redis host |
| `WP_REDIS_PORT` | 6379 | Redis port |
| `DISABLE_WP_CRON` | true | Disable WordPress cron |

---

## Mobile Device Testing

### Test from Real Device
1. Find your machine's IP: `ipconfig getifaddr en0` (Mac) or `hostname -I` (Linux)
2. Update Nginx config to serve on your IP
3. On mobile: `http://{your-ip}:80`

### Using Device Emulation
```bash
# Chrome DevTools (F12)
# Click device icon (top-left)
# Select device: iPhone 12, iPad, etc.
# Test responsive design
```

---

## Monitoring & Debugging

### Real-time Error Monitoring
```bash
docker-compose logs -f wordpress | grep -i "error"
```

### Check Plugin Health
```bash
docker-compose exec wordpress wp plugin list --status=active
```

### Verify Database Schema
```bash
docker-compose exec db mysql -u glamlux -p glamlux_local \
  -e "SHOW TABLES;"
```

### Monitor Docker Resources
```bash
docker stats
```

---

## Next Steps

1. **Start Dev Server**: `docker-compose up -d`
2. **Access Admin**: http://localhost/wp-admin
3. **Create Test Data**: Run seed scripts
4. **Test Mobile**: Use Chrome DevTools or real device
5. **Monitor**: Watch logs while making changes

---

## Support

- **Logs**: `docker-compose logs -f [service]`
- **Database Issues**: Check MySQL logs
- **PHP Errors**: Check `wp-content/debug.log`
- **API Issues**: Check REST endpoint in browser
- **Performance**: Use Lighthouse CI

---

*Last Updated: 2026-03-03*  
*Version: 3.1.0*
