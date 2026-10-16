# HEURIST Website Domain Configuration

## 1. Purpose

This document describes how to publish a Heurist project website under a
dedicated subdomain or a user-owned domain using the centralized routing
introduced in Heurist 7.

Unlike previous releases, Apache is now responsible only for serving
static files and forwarding all other requests to the root `index.php`.
URL interpretation, domain mapping and database selection are handled by
the PHP routing layer (`RequestRouter`).

This document explains the server-side configuration required to make a
project available under its own public domain.

------------------------------------------------------------------------

## 2. Choosing the publication URL

A project may be published in one of three ways.

### Standard Heurist URL

    https://server.example.org/database/web

No additional configuration is required.

### Server subdomain

    https://mbh.heuristref.net

The host is mapped to a database (and optionally a default website) in
`domainWebsites.json`.

### User-owned domain

    https://parramattafoodcultures.net

The browser continues to display the user's domain while Heurist
internally resolves it to the appropriate database and website.

------------------------------------------------------------------------

## 3. DNS Configuration

### Cloudflare-managed servers

Recommended for Heurist servers managed by the project.

-   Create an **A** record pointing to the server IP, or a **CNAME**
    pointing to the primary host.
-   Enable the Cloudflare proxy.
-   Cloudflare terminates HTTPS and provides certificates for proxied
    domains.

No per-domain origin certificate is normally required.

### Direct DNS

If Cloudflare is not used, create A (and optionally AAAA) records
pointing directly to the server.

------------------------------------------------------------------------

## 4. Apache Configuration

### HTTP (port 80)

Use a single VirtualHost redirecting all requests to HTTPS while
preserving the requested host.

### HTTPS (port 443)

#### Wildcard certificate available

If a wildcard certificate (for example `*.huma-num.fr`) is available, a
single HTTPS VirtualHost can serve every project subdomain.

#### Individual certificates

If wildcard certificates are unavailable, create one HTTPS VirtualHost
for each published hostname and obtain a certificate using Certbot.

### Rewrite rules

The recommended configuration is:

``` apache
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^.*$ /index.php [L,QSA]
```

All routing decisions are then made by PHP.

------------------------------------------------------------------------

## 5. Configure Heurist

Edit `HEURIST/domainWebsites.json`.

### Domain mappings

``` json
{
  "domains": {
    "parramattafoodcultures.net": {
      "db": "parramatta_food_cultures",
      "website": 68,
      "version": "heurist"
    }
  }
}
```

Fields:

-   **db** -- target database.
-   **website** -- optional default website record.
-   **version** -- optional code version (`heurist`, `h7-alpha`, ...).

### DBREF mappings

Database aliases may also be defined.

``` json
{
  "dbref": {
    "MBH": {
      "db": "MBH_Manuscripta_Bibliae_Hebraicae",
      "website": 123
    }
  }
}
```

These aliases allow short database names while still selecting a default
website.

------------------------------------------------------------------------

## 6. URL Substitutions

Each database may define optional substitutions in

    <database>/settings/URLSubstitutions.txt

These provide project-specific aliases and redirects before normal route
processing.

------------------------------------------------------------------------

## 7. Deployment Examples

### Project on heuristref.net

    https://parramattafoodcultures.net

Cloudflare proxy → centralized PHP routing → `parramatta_food_cultures`.

### Project subdomain

    https://mbh.heuristref.net

Mapped via `domainWebsites.json`.

### Huma-Num

If a wildcard certificate for `*.huma-num.fr` is available, a single
HTTPS VirtualHost may be used.

Otherwise create one HTTPS VirtualHost and certificate per published
hostname.

### User-owned domain

    https://www.mapoftheabsentees.net

Configure DNS, Apache, SSL and add a corresponding entry to
`domainWebsites.json`.
