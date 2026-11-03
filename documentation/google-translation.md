# Google translation

## Endpoint URLs and implementation status

The translation endpoint (2026-11-02) is:

```text
POST /heurist/api/{database}/external/translate
Content-Type: application/json

{"text": "Hello world", "target": "fr", "source": "en", "format": "text"}
```

It answers `{"text": "Bonjour le monde"}`. The router `hserv/controller/api.php` (resource `external`) calls
ExternalServiceController, which calls ServiceFactory::translationService(). Errors are the srv JSON errors
(`status`, `error`, `message`): 400 invalid input, 401 not logged in, 403 not an association member or a
request from another site, 415 not JSON, 502 no provider or the provider failed. It is documented in
`documentation/api/heurist-openapi.yaml` (tag External services) and tested by `tests/ExternalServiceTest.php`.
heurist-explorer uses it (`TranslationClient`).

The existing **DeepL-only** endpoint is:

```text
POST http://localhost/heurist/hserv/controller/usr_info.php
Content-Type: application/x-www-form-urlencoded

db=osmak_mapping&a=translate_string&string=Hello%20world&target=fr&source=en
```

Replace the installation base URL and database name as needed. Its successful
response is the legacy Heurist JSON envelope with the translated string in
`data`. Configuring a Google key does not switch this legacy endpoint to Google.

## Google service configuration

Enable Cloud Translation API and billing in your Google Cloud project. In the
installation's private `heuristConfigIni.php`, set `$accessToken_GoogleTranslateAPI`
to your API key. The new translation service currently supports Google only.
Restrict the key to Cloud Translation API and your server's outgoing IP address.
Set Google API quotas to control consumption; billing budget alerts do not stop spending.

The service is available through ServiceFactory and the endpoint above. The legacy
translate_string endpoint continues using DeepL.

```php
$factory->translationService()->translate('Hello world', 'fr', 'en', 'text');
```

Omit `source` for automatic detection. Two-letter, regional and Heurist three-letter
language codes are accepted. Requests are limited to 5,000 Unicode characters.
HTML input is supported; treat translated HTML with the same sanitization as input HTML.
Requires PHP cURL. Uses Basic v2 NMT with no Composer dependency.
The API key stays on the server. Services and providers have no hserv dependency.
There are no automatic retries or automatic paid-provider fallback.

This adapter does not implement caching or per-user/monthly usage accounting.
Use Google quotas before enabling production access. LibreTranslate can later be
added behind the same endpoint; self-hosting requires server capacity and maintenance.

TranslationService requires a positive user ID and association status `database`
or `individual`. ServiceFactory obtains the membership result from USystem before
closing the session. The existing helper currently returns `database` for every
logged-in user before its real membership lookup; this behavior is unchanged.
LookupService is an explicit not-implemented stub.
