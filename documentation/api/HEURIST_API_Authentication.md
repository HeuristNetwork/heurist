# Heurist API Authentication Guide

## Overview

The API supports two authentication methods:

1. Session-based authentication (cookies)
2. JWT Bearer authentication (stateless)

All API requests are routed via:

/index.php → RequestRouter → /<version>/hserv/controller/api.php

---

## 1. Session Authentication (Cookie-based)

### Login

POST /api/{db}/login

Body (JSON):
{
  "login": "username",
  "password": "password"
}

### Behavior

- Server creates a session
- Cookies are returned
- Client must reuse cookies for subsequent requests

### Example (curl)

curl -c cookies.txt -X POST https://example.net/api/mydb/login \
  -H "Content-Type: application/json" \
  -d '{"login":"user","password":"pass"}'

curl -b cookies.txt https://example.net/api/mydb/records

---

## 2. JWT Authentication (Bearer Token)

### Get Token

POST /heurist/hserv/controller/auth.php

Body (JSON):
{
  "username": "user",
  "password": "pass",
  "db": "mydb"
}

Response:
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 600
}

---

### Use Token

Send with every request:

Authorization: Bearer <access_token>

### Example (curl)

curl https://example.net/api/mydb/records \
  -H "Authorization: Bearer YOUR_TOKEN"

---

## Token Behavior

- Stateless (no server session)
- Must be included in every request
- Expires after configured TTL

---

## Configuration

Enable JWT in:

root/heuristConfigIni.php

```php
$jwt_Secret = 'your-long-random-secret'; // min 8 chars
$jwt_TTL    = 600; // seconds (10 minutes)