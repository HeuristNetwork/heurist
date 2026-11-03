<?php
declare(strict_types=1);

namespace Heurist\ExternalService\TranslationProvider;

/** Google Cloud Translation Basic v2 HTTP adapter. Credentials stay on the server. */
final class GoogleTranslation
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function translate(string $text, string $target, ?string $source = null, string $format = 'html'): string
    {
        if ($this->key === '') {
            throw new \RuntimeException('Google translation is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL is required for Google translation.');
        }
        $body = ['q' => $text, 'target' => $target, 'format' => $format, 'model' => 'nmt'];
        if ($source !== null) { $body['source'] = $source; }
        $curl = curl_init('https://translation.googleapis.com/language/translate/v2');
        if ($curl === false) { throw new \RuntimeException('Cannot initialize translation request.'); }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: '.$this->key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30
        ]);
        try {
            $response = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } finally {
            curl_close($curl);
        }
        $data = is_string($response) ? json_decode($response, true) : null;
        $translated = $data['data']['translations'][0]['translatedText'] ?? null;
        if ($status !== 200 || !is_string($translated)) {
            // Upstream errors may contain request text or credentials: do not expose them.
            throw new \RuntimeException('Google translation failed (HTTP '.$status.'). Check API configuration, billing and quotas.');
        }
        return $translated;
    }
}
