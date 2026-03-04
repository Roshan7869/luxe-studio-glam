# Incident Response Procedures

## Overview

This document outlines the procedures for responding to system incidents based on severity level.

---

## Severity Levels

### P0 - Critical (System Down)
- **SLA**: 5 minutes
- **Impact**: Complete service outage or data loss risk
- **Escalation**: Immediate

### P1 - High (Degraded Performance)  
- **SLA**: 15 minutes
- **Impact**: Significant performance degradation affecting users
- **Escalation**: High priority

### P2 - Medium (Minor Issues)
- **SLA**: 1 hour
- **Impact**: Non-critical functionality affected
- **Escalation**: Standard

### P3 - Low (Feature Requests)
- **SLA**: Next sprint
- **Impact**: No immediate impact
- **Escalation**: Backlog

---

## P0 - Critical Incident Response

### Detection Triggers
- Health endpoint returns 500+ status
- Multiple critical errors in logs (>10 in 5 min)
- Database connection failures
- Application crash/restart loop
- Data corruption detected

### Immediate Response (0-2 min)

1. **Declare Incident**
   - Notify on-call team via Slack #alerts channel
   - Create incident ticket
   - Start incident timer

2. **Assess Impact**
   - Check health dashboard: `/wp-json/glamlux/v1/health`
   - Review error logs
   - Identify affected users/systems

3. **Communication**
   - Notify incident commander
   - Update status page to "Investigating"
   - Prepare user communication

### Investigation (2-5 min)

1. **Check Dashboard**
   ```bash
   curl https://yourdomain.com/wp-json/glamlux/v1/health
   ```

2. **Review Logs**
   ```bash
   # Check recent errors
   wp glamlux show-logs --level=error --limit=50
   
   # Check database logs
   tail -f /var/log/mysql/error.log
   ```

3. **Identify Root Cause**
   - Database connection issue?
   - Memory leak / resource exhaustion?
   - Deployment issue?
   - External service failure?
   - Malicious activity?

### Remediation (5-15 min)

#### Database Connection Failure
```bash
# Check database status
wp db check

# Restart database service
systemctl restart mysql

# Verify connection
wp user list
```

#### Memory Exhaustion
```bash
# Check memory usage
free -h

# Restart PHP-FPM
systemctl restart php8.1-fpm

# Check for memory leaks
top -p $(pgrep -f php-fpm | tr '\n' ',')
```

#### Deployment Issue
```bash
# Rollback to previous version
git revert HEAD

# Restart services
systemctl restart nginx php8.1-fpm

# Verify health
curl https://yourdomain.com/wp-json/glamlux/v1/health
```

#### External Service Failure (Redis/SQS)
```bash
# Check Redis
redis-cli ping

# Check SQS credentials
aws sqs list-queues --region us-east-1

# Fall back to database queue
wp option update glamlux_queue_mode database
```

### Verification (After Fix)

1. **Health Check**
   ```bash
   curl https://yourdomain.com/wp-json/glamlux/v1/health
   ```

2. **API Test**
   ```bash
   curl https://yourdomain.com/wp-json/glamlux/v1/salons
   ```

3. **Performance Verification**
   ```bash
   # Check response time
   curl -w "@curl-format.txt" https://yourdomain.com/wp-json/glamlux/v1/salons
   ```

### Resolution & Notification

1. **Mark Resolved**
   - Update ticket status to "Resolved"
   - Document solution
   - Time to resolution (TTR)

2. **User Notification**
   - Update status page to "Operational"
   - Send user notification email
   - Post-incident update in Slack

3. **Post-Incident Review** (Within 24 hours)
   - Root cause analysis
   - Preventive measures
   - Process improvements

---

## P1 - High Performance Degradation

### Detection Triggers
- Average response time > 2 seconds
- Error rate > 5% (last 5 min)
- Cache hit rate < 50%
- High database query time
- Memory usage > 80%

### Response Procedure

1. **Identify Bottleneck** (5 min)
   ```bash
   # Check performance metrics
   curl https://yourdomain.com/wp-json/glamlux/v1/health
   
   # Analyze slow queries
   wp profile stage --benchmark
   ```

2. **Root Cause Analysis** (10 min)
   - Database query issue?
   - Cache not working?
   - External API slow?
   - Resource constraint?

3. **Apply Optimization** (10 min)
   - Clear cache: `wp cache flush`
   - Restart services: `systemctl restart nginx php8.1-fpm`
   - Scale horizontally if needed
   - Disable non-essential plugins

4. **Monitor Improvement** (5 min)
   - Verify response time improved
   - Check error rate normalized
   - Monitor for 15 minutes

5. **Document & Close**
   - Log optimization applied
   - Set follow-up task for root cause fix

---

## P2 - Medium Issues

### Detection Triggers
- Non-critical functionality broken
- Minor performance issues
- Feature conflicts
- Data inconsistency (non-critical)

### Response Procedure

1. **Assess Impact** (30 min)
   - Identify affected feature
   - Check if users are blocked
   - Determine workaround

2. **Plan Fix** (30 min)
   - Design solution
   - Estimate effort
   - Schedule maintenance window

3. **Implement Fix** (Variable)
   - Develop solution
   - Test thoroughly
   - Deploy to staging first

4. **Monitor** (30 min)
   - Watch error logs
   - Verify fix is effective
   - Check for side effects

---

## Escalation Matrix

### P0 Escalation Path
1. On-call Engineer (0-5 min)
2. Team Lead (if not resolved, 5-15 min)
3. Engineering Manager (if not resolved, 15+ min)
4. CTO (if enterprise SLA at risk)

### P1 Escalation Path
1. On-call Engineer (0-15 min)
2. Team Lead (if not resolved, 15-30 min)
3. Engineering Manager (if not resolved, 30+ min)

---

## Communication Templates

### Incident Declared
```
⚠️ INCIDENT DECLARED - P[0/1/2]
Service: GlamLux
Issue: [BRIEF DESCRIPTION]
Impact: [WHAT'S AFFECTED]
Status: Investigating
ETA: [ESTIMATED FIX TIME]
```

### Incident Investigating
```
🔍 INVESTIGATING
We're actively working on the issue.
Current Status: [STATUS]
ETA: [TIME]
```

### Incident Resolved
```
✅ RESOLVED
Issue: [BRIEF DESCRIPTION]
Duration: [TIME]
Root Cause: [BRIEF EXPLANATION]
Resolution: [WHAT WAS DONE]
Post-Incident Review: [LINK]
```

---

## Useful Commands

### Health Checks
```bash
# Full health check
curl https://yourdomain.com/wp-json/glamlux/v1/health

# Database check
wp db check

# Event queue status
wp glamlux show-queue

# Cache stats
curl https://yourdomain.com/wp-json/glamlux/v1/cache/stats
```

### Performance Diagnostics
```bash
# Show slow queries
wp profile stage --benchmark

# Check memory usage
wp cli --info | grep memory

# List active crons
wp cron event list
```

### Remediation
```bash
# Restart services
systemctl restart nginx
systemctl restart php8.1-fpm
systemctl restart mysql

# Clear all caches
wp cache flush
wp wp-cli cache flush

# Force event processing
wp glamlux process-events --force
```

---

## Prevention Best Practices

1. **Monitoring**
   - Set up automated health checks
   - Configure alerts for thresholds
   - Review logs daily

2. **Maintenance**
   - Regular database optimization
   - Cache warmup schedules
   - Plugin/theme updates

3. **Testing**
   - Load testing before releases
   - Staging environment validation
   - Smoke tests after deployments

4. **Documentation**
   - Keep runbooks updated
   - Document known issues
   - Maintain architectural diagrams

---

## References

- [Health Check Endpoint](../README.md#health-check)
- [Monitoring Guide](./monitoring.md)
- [Performance Tuning](./performance-tuning.md)
- [Backup & Recovery](./backup-recovery.md)

---

**Last Updated**: 2026-03-04  
**Next Review**: 2026-04-04  
**Version**: 1.0
