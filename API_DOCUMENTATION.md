# 📱 REST API Documentation - Mobile & Desktop Ready

**Base URL**: `/wp-json/glamlux/v1/`  
**Authentication**: JWT Token (via Authorization header)  
**Response Format**: JSON  
**Status**: Production Ready v3.1

---

## Table of Contents
1. [Authentication](#authentication)
2. [Response Format](#response-format)
3. [Endpoints](#endpoints)
4. [Mobile Optimization](#mobile-optimization)
5. [Error Handling](#error-handling)

---

## Authentication

### 1. Obtain JWT Token

**Endpoint**: `POST /auth/login`

**Request** (Mobile-safe)
```json
{
  "username": "user@example.com",
  "password": "password123",
  "remember_me": true
}
```

**Response** (200 OK)
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 86400,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "User Name",
    "role": "glamlux_client"
  }
}
```

### 2. Use Token in Requests

**Headers**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json
User-Agent: GlamLux-Mobile/3.1 (iOS 15.0)
```

### 3. Token Refresh

**Endpoint**: `POST /auth/refresh`

**Headers**
```
Authorization: Bearer {expired_token}
```

**Response** (200 OK)
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "expires_in": 86400
}
```

### 4. Logout

**Endpoint**: `POST /auth/logout`

**Headers**
```
Authorization: Bearer {token}
```

---

## Response Format

### Success Response (200, 201)
```json
{
  "success": true,
  "data": {
    /* Actual data */
  },
  "message": "Operation successful",
  "timestamp": "2026-03-03T13:43:00Z"
}
```

### Error Response (4xx, 5xx)
```json
{
  "success": false,
  "error": "error_code",
  "message": "Human-readable error message",
  "errors": [
    {
      "field": "email",
      "message": "Invalid email format"
    }
  ],
  "timestamp": "2026-03-03T13:43:00Z"
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ /* items */ ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "pages": 8
  }
}
```

---

## Endpoints

### 📍 LOCATIONS & SALONS

#### List All Salons
**Endpoint**: `GET /salons`

**Query Parameters**
```
?page=1&per_page=20&sort=name&filter=active
```

**Response**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "GlamLux Mumbai",
      "location": "Mumbai, Maharashtra",
      "address": "123 Main St, Mumbai 400001",
      "phone": "+91-22-XXXX-XXXX",
      "email": "mumbai@glamlux.com",
      "latitude": 19.0760,
      "longitude": 72.8777,
      "is_active": true,
      "distance_km": 2.5
    }
  ],
  "pagination": { "page": 1, "per_page": 20, "total": 12 }
}
```

#### Get Salon Details
**Endpoint**: `GET /salons/{id}`

**Response**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "GlamLux Mumbai",
    "address": "123 Main St, Mumbai 400001",
    "phone": "+91-22-XXXX-XXXX",
    "hours": [
      { "day": "Monday", "open": "09:00", "close": "21:00" },
      { "day": "Tuesday", "open": "09:00", "close": "21:00" }
    ],
    "services": [ /* service IDs */ ],
    "staff": [ /* staff IDs */ ]
  }
}
```

---

### 💇 SERVICES

#### List Services
**Endpoint**: `GET /services`

**Query Parameters**
```
?salon_id=1&category=haircare&price_min=0&price_max=5000&sort=popular
```

**Response**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "name": "Hair Couture Cut & Style",
      "description": "Professional haircut with premium styling",
      "category": "haircare",
      "base_price": 1799,
      "duration_minutes": 45,
      "image_url": "https://cdn.glamlux.com/service-101.jpg",
      "rating": 4.8,
      "reviews": 234
    }
  ]
}
```

#### Service Details
**Endpoint**: `GET /services/{id}`

**Response** (includes staff availability, images, reviews)

---

### 📅 APPOINTMENTS & BOOKING

#### Check Availability
**Endpoint**: `GET /appointments/availability`

**Query Parameters**
```
?service_id=101&staff_id=5&salon_id=1&date=2026-03-15&timezone=Asia/Kolkata
```

**Response**
```json
{
  "success": true,
  "data": {
    "date": "2026-03-15",
    "available_slots": [
      { "time": "09:00", "available": true },
      { "time": "09:30", "available": true },
      { "time": "10:00", "available": false },
      { "time": "10:30", "available": true }
    ]
  }
}
```

#### Create Appointment
**Endpoint**: `POST /appointments`

**Request**
```json
{
  "service_id": 101,
  "staff_id": 5,
  "salon_id": 1,
  "appointment_date": "2026-03-15",
  "appointment_time": "09:30",
  "notes": "Allergic to ammonia",
  "client_email": "user@example.com",
  "client_phone": "+91-98XXXX-XXXX"
}
```

**Response** (201 Created)
```json
{
  "success": true,
  "data": {
    "id": 5001,
    "status": "confirmed",
    "appointment_time": "2026-03-15T09:30:00Z",
    "service_name": "Hair Couture Cut & Style",
    "staff_name": "Priya Sharma",
    "salon_name": "GlamLux Mumbai",
    "confirmation_token": "abc123def456"
  }
}
```

#### List My Appointments
**Endpoint**: `GET /appointments/me`

**Query Parameters**
```
?status=upcoming&sort=date
```

**Response**
```json
{
  "success": true,
  "data": [
    {
      "id": 5001,
      "service_name": "Hair Couture",
      "appointment_time": "2026-03-15T09:30:00Z",
      "status": "confirmed",
      "salon_name": "GlamLux Mumbai",
      "staff_name": "Priya Sharma",
      "reminder_sent": false
    }
  ]
}
```

#### Update Appointment
**Endpoint**: `PUT /appointments/{id}`

**Request**
```json
{
  "appointment_time": "2026-03-15T10:00:00Z",
  "notes": "Updated notes"
}
```

#### Cancel Appointment
**Endpoint**: `DELETE /appointments/{id}`

**Response**
```json
{
  "success": true,
  "message": "Appointment cancelled successfully"
}
```

---

### 👥 STAFF

#### List Staff
**Endpoint**: `GET /staff`

**Query Parameters**
```
?salon_id=1&specialty=hair&sort=rating&filter=active
```

**Response**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "Priya Sharma",
      "specialty": "Hair Styling",
      "rating": 4.9,
      "reviews": 156,
      "bio": "12+ years of experience",
      "avatar_url": "https://cdn.glamlux.com/staff-5.jpg",
      "available_today": true,
      "badges": ["Certified", "Premium"]
    }
  ]
}
```

#### Staff Availability
**Endpoint**: `GET /staff/{id}/availability`

**Response**
```json
{
  "success": true,
  "data": {
    "today": [ /* slots */ ],
    "tomorrow": [ /* slots */ ],
    "week": [ /* slots */ ]
  }
}
```

---

### 💳 MEMBERSHIPS

#### List Memberships
**Endpoint**: `GET /memberships`

**Response**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "tier_name": "Silver",
      "tier_level": 1,
      "price_monthly": 999,
      "benefits": [
        "10% discount on all services",
        "Priority booking",
        "Monthly gift"
      ],
      "duration_months": 1,
      "auto_renew": true
    }
  ]
}
```

#### Get My Membership
**Endpoint**: `GET /memberships/me`

**Response**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "tier_name": "Gold",
    "status": "active",
    "renews_at": "2026-04-03",
    "discount_percentage": 15
  }
}
```

---

### 💰 PAYMENT

#### Create Payment Intent
**Endpoint**: `POST /payments/create-intent`

**Request**
```json
{
  "amount": 2999,
  "currency": "INR",
  "description": "Hair Couture Service",
  "metadata": {
    "appointment_id": 5001,
    "salon_id": 1
  }
}
```

**Response**
```json
{
  "success": true,
  "data": {
    "payment_id": "pay_123abc",
    "client_secret": "pi_123abc_secret_xyz",
    "amount": 2999,
    "currency": "INR",
    "status": "requires_payment_method"
  }
}
```

#### Process Payment
**Endpoint**: `POST /payments/process`

**Request**
```json
{
  "payment_id": "pay_123abc",
  "payment_method": "pm_123abc"
}
```

**Response**
```json
{
  "success": true,
  "data": {
    "transaction_id": "txn_123abc",
    "status": "succeeded",
    "receipt_url": "https://..."
  }
}
```

---

### 👤 USER PROFILE

#### Get Profile
**Endpoint**: `GET /users/me`

**Response**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "email": "user@example.com",
    "phone": "+91-98XXXX-XXXX",
    "name": "User Name",
    "avatar_url": "https://...",
    "appointments_count": 5,
    "membership_active": true,
    "settings": {
      "notifications_sms": true,
      "notifications_email": true
    }
  }
}
```

#### Update Profile
**Endpoint**: `PUT /users/me`

**Request**
```json
{
  "name": "New Name",
  "phone": "+91-98XXXX-XXXX",
  "preferences": {
    "preferred_staff_id": 5,
    "preferred_salon_id": 1
  }
}
```

---

## Mobile Optimization

### Recommended Headers
```
User-Agent: GlamLux-Mobile/3.1 (iOS 15.0) | GlamLux-Mobile/3.1 (Android 12)
Accept-Encoding: gzip, deflate
Cache-Control: max-age=300
```

### Mobile-Specific Endpoints

#### Lightweight Salons (reduced payload)
**Endpoint**: `GET /salons?format=lite`

**Response** (minimal data for maps, lists)
```json
{
  "data": [
    { "id": 1, "name": "Mumbai", "lat": 19.07, "lon": 72.87, "distance": 2.5 }
  ]
}
```

### Pagination Best Practices
```
?page=1&per_page=10  /* Mobile friendly */
?page=1&per_page=50  /* Desktop friendly */
```

---

## Error Handling

### Common Error Codes

| Code | Status | Message | Solution |
|---|---|---|---|
| `AUTH_REQUIRED` | 401 | Authentication required | Include valid JWT token |
| `INVALID_TOKEN` | 401 | Token expired or invalid | Refresh token or login again |
| `NOT_FOUND` | 404 | Resource not found | Check ID or URL |
| `VALIDATION_ERROR` | 422 | Field validation failed | Fix validation errors |
| `CONFLICT` | 409 | Time slot already booked | Choose different time |
| `RATE_LIMIT` | 429 | Too many requests | Wait 60 seconds |
| `SERVER_ERROR` | 500 | Server error | Retry after 30 seconds |

### Rate Limiting

**Headers**
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1646313000
```

**Error Response** (429)
```json
{
  "success": false,
  "error": "RATE_LIMIT",
  "message": "Too many requests. Try again in 60 seconds.",
  "retry_after": 60
}
```

---

## Testing API

### cURL Examples

**Login**
```bash
curl -X POST http://localhost/wp-json/glamlux/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

**Get Salons**
```bash
curl http://localhost/wp-json/glamlux/v1/salons \
  -H "Authorization: Bearer {token}"
```

**Create Appointment**
```bash
curl -X POST http://localhost/wp-json/glamlux/v1/appointments \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 101,
    "staff_id": 5,
    "salon_id": 1,
    "appointment_date": "2026-03-15",
    "appointment_time": "09:30"
  }'
```

---

## SDK & Libraries

### Official SDKs Coming Soon
- **Mobile**: GlamLux-SDK-iOS (Swift)
- **Mobile**: GlamLux-SDK-Android (Kotlin)
- **Web**: GlamLux-SDK-JS (TypeScript)

---

## Support

- **API Status**: https://status.glamlux.com
- **Documentation**: https://docs.glamlux.com
- **Issues**: https://github.com/luxe-studio-glam/api-issues

---

*Last Updated: 2026-03-03*  
*API Version: 3.1*  
*Status: Production Ready*
