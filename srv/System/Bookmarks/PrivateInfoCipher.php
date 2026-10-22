<?php
/**
* PrivateInfoCipher.php - Encryption of the private note of a bookmark (PrivateInfo 2-1192)
*
* Plan 20, §10.9: the note (legacy "password reminder", bkm_PwdReminder) is stored
* encrypted and shown to the owner of the bookmark only. One key per installation:
* the global $privateInfo_Secret of heuristConfigIni.php (configIni.php gives the
* empty default). AES-256-GCM through openssl; a stored value is
* "enc1:" + base64(iv, tag, cipher text). A value without the prefix is legacy
* text and is given as it is. Without a key a note is stored as text (as legacy).
* The legacy bookmark editor uses this class too (hserv DbUsrBookmarks).
*
* @project     Heurist academic knowledge management system
* @package     System\Bookmarks
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\System\Bookmarks;

/** Encrypts and decrypts private notes. */
final class PrivateInfoCipher
{
    /** Prefix of an encrypted value. */
    public const PREFIX = 'enc1:';

    /** Characters of a note (the legacy column holds 250: the encrypted value is longer, plan 20 §10.3). */
    public const MAX_TEXT = 140;

    /** Length of the legacy column bkm_PwdReminder. */
    public const MAX_STORED = 250;

    private const CIPHER = 'aes-256-gcm';

    private string $key;

    /** @param string $secret The installation secret ('' : no encryption). */
    public function __construct(string $secret)
    {
        $secret = trim($secret);
        $this->key = $secret === '' ? '' : hash('sha256', 'heurist-private-info|'.$secret, true);
    }

    /** The cipher of the installation: the global $privateInfo_Secret (heuristConfigIni.php). */
    public static function fromConfiguration(): self
    {
        $secret = $GLOBALS['privateInfo_Secret'] ?? '';
        return new self(is_string($secret) ? $secret : '');
    }

    /** Whether the installation has a key (notes are encrypted). */
    public function hasKey(): bool
    {
        return $this->key !== '' && function_exists('openssl_encrypt');
    }

    /**
     * The stored form of a note: encrypted with a key, else the text.
     *
     * @throws \InvalidArgumentException When the note does not fit the column.
     */
    public function encrypt(string $text): string
    {
        if($text === '' || !$this->hasKey()){ return $this->assertLength($text); }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($text, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if($cipher === false){ throw new \RuntimeException('The private note could not be encrypted'); }
        return $this->assertLength(self::PREFIX.base64_encode($iv.$tag.$cipher));
    }

    /**
     * The text of a stored note: legacy text as it is; null for an encrypted note that cannot
     * be read (no key, another key, a changed value).
     */
    public function decrypt(?string $stored): ?string
    {
        $stored = (string)$stored;
        if(strpos($stored, self::PREFIX) !== 0){ return $stored; }
        if(!$this->hasKey()){ return null; }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if($raw === false || strlen($raw) < 29){ return null; }
        $text = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $text === false ? null : $text;
    }

    /** @throws \InvalidArgumentException */
    private function assertLength(string $stored): string
    {
        if(strlen($stored) > self::MAX_STORED){
            throw new \InvalidArgumentException('The private note is too long (at most '.self::MAX_TEXT.' characters)');
        }
        return $stored;
    }
}
