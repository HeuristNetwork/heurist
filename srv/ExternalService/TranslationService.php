<?php
declare(strict_types=1);

namespace Heurist\ExternalService;

use Heurist\ExternalService\TranslationProvider\GoogleTranslation;

/** Translation policy and input validation, independent of the legacy System. */
final class TranslationService
{
    private int $userId;
    private string $associationMembershipStatus;
    private ?GoogleTranslation $provider;
    private array $languageCodes;

    /** @param array<string,string> $languageCodes Three-letter to two-letter language codes. */
    public function __construct(int $userId, string $associationMembershipStatus,
        ?GoogleTranslation $provider = null, array $languageCodes = [])
    {
        $this->userId = $userId;
        $this->associationMembershipStatus = $associationMembershipStatus;
        $this->provider = $provider;
        $this->languageCodes = array_change_key_case($languageCodes, CASE_UPPER);
    }

    public function translate(string $text, string $target, ?string $source = null, string $format = 'html'): string
    {
        if ($this->userId < 1) {
            throw new \RuntimeException('Please sign in to translate text.', 401);
        }
        if (!in_array($this->associationMembershipStatus, ['database', 'individual'], true)) {
            throw new \RuntimeException('Association membership is required to translate text.', 403);
        }
        if (trim($text) === '' || preg_match('//u', $text) !== 1
            || preg_match_all('/./us', $text) > 5000) {
            throw new \InvalidArgumentException('Provide between 1 and 5000 UTF-8 characters for translation.');
        }
        if (!in_array($format, ['text', 'html'], true)) {
            throw new \InvalidArgumentException('Translation format must be text or html.');
        }
        $target = $this->languageCode($target);
        $source = $source === null || $source === '' ? null : $this->languageCode($source);
        if ($this->provider === null) {
            throw new \RuntimeException('No translation provider is configured.');
        }
        return $this->provider->translate($text, $target, $source, $format);
    }

    private function languageCode(string $code): string
    {
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/iD', $code)) {
            throw new \InvalidArgumentException('Invalid translation language code.');
        }
        if (strlen($code) === 3) {
            $code = $this->languageCodes[strtoupper($code)] ?? '';
            if ($code === '') { throw new \InvalidArgumentException('Unknown translation language code.'); }
        }
        return strtolower($code);
    }
}
