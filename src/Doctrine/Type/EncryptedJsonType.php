<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class EncryptedJsonType extends Type
{
    public const NAME = 'encrypted_json';
    private const CIPHER = 'aes-256-gcm';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'LONGTEXT';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        if (is_array($decoded) && ($decoded['_encrypted'] ?? false) === true) {
            return $this->decrypt($decoded);
        }

        if (is_array($decoded)) {
            return $decoded;
        }

        throw ConversionException::conversionFailed($value, self::NAME);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        return json_encode($this->encrypt($value), JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    private function encrypt(array $payload): array
    {
        $iv = random_bytes(12);
        $tag = '';
        $plain = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new \RuntimeException('No se pudo cifrar el JSON.');
        }

        return [
            '_encrypted' => true,
            'cipher' => self::CIPHER,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'value' => base64_encode($ciphertext),
        ];
    }

    private function decrypt(array $payload): array
    {
        $plain = openssl_decrypt(
            base64_decode((string) $payload['value'], true),
            self::CIPHER,
            $this->key(),
            OPENSSL_RAW_DATA,
            base64_decode((string) $payload['iv'], true),
            base64_decode((string) $payload['tag'], true)
        );

        if ($plain === false) {
            throw new \RuntimeException('No se pudo descifrar el JSON. Revisa DATA_ENCRYPTION_KEY.');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    private function key(): string
    {
        $raw = $_ENV['DATA_ENCRYPTION_KEY'] ?? $_SERVER['DATA_ENCRYPTION_KEY'] ?? '';
        if ($raw === '') {
            throw new \RuntimeException('Falta DATA_ENCRYPTION_KEY en .env.local.');
        }

        $decoded = base64_decode($raw, true);
        $key = $decoded !== false ? $decoded : $raw;

        return hash('sha256', $key, true);
    }
}

