<?php

namespace App\Utils;

use PDO;
use PDOException;
use RuntimeException;

class ShortUrlService
{
    private const DEFAULT_DB_PATH = '/var/lib/moreplaylist/shorturl.sqlite';
    private const MAX_TTL_SECONDS = 31536000;
    private const CODE_LENGTH = 7;
    private const CODE_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    private string $dbPath;
    private ?PDO $pdo = null;

    public function __construct(?string $dbPath = null)
    {
        $this->dbPath = $dbPath
            ?? getenv('SHORTURL_DB_PATH')
            ?: self::DEFAULT_DB_PATH;
    }

    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    public function getBaseUrl(): string
    {
        $envBase = getenv('SHORTURL_BASE_URL');
        if ($envBase) {
            return rtrim($envBase, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    public function createShortUrl(string $targetUrl, int $ttlSeconds = self::MAX_TTL_SECONDS): array
    {
        $this->connect();

        $now = time();
        $ttl = min($ttlSeconds, self::MAX_TTL_SECONDS);
        $expiresAt = $now + $ttl;
        $targetHash = hash('sha256', $targetUrl);

        $existing = $this->findActiveByHash($targetHash, $now);
        if ($existing) {
            return $existing;
        }

        $this->deleteExpiredByHash($targetHash, $now);
        $this->cleanupExpired($now);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $this->generateCode();

            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO short_urls (code, target_url, target_hash, created_at, expires_at)
                     VALUES (:code, :target_url, :target_hash, :created_at, :expires_at)'
                );
                $stmt->execute([
                    ':code' => $code,
                    ':target_url' => $targetUrl,
                    ':target_hash' => $targetHash,
                    ':created_at' => $now,
                    ':expires_at' => $expiresAt,
                ]);

                return [
                    'code' => $code,
                    'target_url' => $targetUrl,
                    'target_hash' => $targetHash,
                    'created_at' => $now,
                    'expires_at' => $expiresAt,
                ];
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }

                $existing = $this->findActiveByHash($targetHash, $now);
                if ($existing) {
                    return $existing;
                }
            }
        }

        throw new RuntimeException('Failed to generate a unique short code.');
    }

    public function findActiveByCode(string $code, ?int $now = null): ?array
    {
        $this->connect();
        $now = $now ?? time();

        $stmt = $this->pdo->prepare(
            'SELECT code, target_url, target_hash, created_at, expires_at
             FROM short_urls
             WHERE code = :code AND expires_at > :now
             LIMIT 1'
        );
        $stmt->execute([
            ':code' => $code,
            ':now' => $now,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function cleanupExpired(?int $now = null): int
    {
        $this->connect();
        $now = $now ?? time();

        $stmt = $this->pdo->prepare('DELETE FROM short_urls WHERE expires_at <= :now');
        $stmt->execute([':now' => $now]);

        return $stmt->rowCount();
    }

    private function findActiveByHash(string $targetHash, int $now): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, target_url, target_hash, created_at, expires_at
             FROM short_urls
             WHERE target_hash = :target_hash AND expires_at > :now
             LIMIT 1'
        );
        $stmt->execute([
            ':target_hash' => $targetHash,
            ':now' => $now,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function deleteExpiredByHash(string $targetHash, int $now): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM short_urls WHERE target_hash = :target_hash AND expires_at <= :now'
        );
        $stmt->execute([
            ':target_hash' => $targetHash,
            ':now' => $now,
        ]);
    }

    private function generateCode(): string
    {
        $characters = self::CODE_CHARSET;
        $maxIndex = strlen($characters) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $characters[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private function connect(): void
    {
        if ($this->pdo) {
            return;
        }

        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS short_urls (
                code TEXT PRIMARY KEY,
                target_url TEXT NOT NULL,
                target_hash TEXT NOT NULL UNIQUE,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_short_urls_expires_at ON short_urls (expires_at)'
        );
    }
}
