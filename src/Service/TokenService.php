<?php

namespace App\Service;

use App\Entity\DownloadToken;
use App\Entity\File;
use App\Entity\User;
use App\Repository\DownloadTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

class TokenService
{
    private EntityManagerInterface $entityManager;
    private DownloadTokenRepository $tokenRepository;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private string $hmacSecret;

    public function __construct(
        EntityManagerInterface $entityManager,
        DownloadTokenRepository $tokenRepository,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->tokenRepository = $tokenRepository;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->params = $params;
        $this->logger = $logger;
        $this->hmacSecret = $params->get('app.hmac_secret');
    }

    public function generateToken(
        File $file,
        User $createdBy,
        \DateTimeImmutable $expiresAt,
        int $maxDownloads = 1,
        ?string $password = null,
        ?array $ipWhitelist = null
    ): DownloadToken {
        // Generate cryptographically secure token
        $tokenBytes = random_bytes(32);
        $token = base64url_encode($tokenBytes);
        
        // Hash the token for storage
        $tokenHash = hash('sha256', $token);
        
        // Create the download token entity
        $downloadToken = new DownloadToken();
        $downloadToken->setFile($file);
        $downloadToken->setCreatedBy($createdBy);
        $downloadToken->setTokenHash($tokenHash);
        $downloadToken->setExpiresAt($expiresAt);
        $downloadToken->setMaxDownloads($maxDownloads);
        $downloadToken->setIpWhitelist($ipWhitelist ?? []);
        
        // Hash password if provided
        if ($password !== null) {
            $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(DownloadToken::class);
            $downloadToken->setPasswordHash($passwordHasher->hash($password));
        }

        $this->entityManager->persist($downloadToken);
        $this->entityManager->flush();

        $this->logger->info('Download token generated', [
            'file_id' => $file->getId(),
            'token_id' => $downloadToken->getId(),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'max_downloads' => $maxDownloads,
            'created_by' => $createdBy->getEmail()
        ]);

        // Return the token with the plain token value for sharing
        return $downloadToken;
    }

    /**
     * Generate a download token and also return the plain token value
     * so it can be used in a shareable URL. The plain value is NOT stored
     * in the database; only its hash is stored for verification.
     */
    public function generateTokenWithPlain(
        File $file,
        User $createdBy,
        \DateTimeImmutable $expiresAt,
        int $maxDownloads = 1,
        ?string $password = null,
        ?array $ipWhitelist = null
    ): array {
        // Generate cryptographically secure token
        $tokenBytes = random_bytes(32);
        $plainToken = base64url_encode($tokenBytes);

        // Hash the token for storage
        $tokenHash = hash('sha256', $plainToken);

        // Create the download token entity
        $downloadToken = new DownloadToken();
        $downloadToken->setFile($file);
        $downloadToken->setCreatedBy($createdBy);
        $downloadToken->setTokenHash($tokenHash);
        $downloadToken->setExpiresAt($expiresAt);
        $downloadToken->setMaxDownloads($maxDownloads);
        $downloadToken->setIpWhitelist($ipWhitelist ?? []);

        if ($password !== null) {
            $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(DownloadToken::class);
            $downloadToken->setPasswordHash($passwordHasher->hash($password));
        }

        $this->entityManager->persist($downloadToken);
        $this->entityManager->flush();

        $this->logger->info('Download token generated (with plain return)', [
            'file_id' => $file->getId(),
            'token_id' => $downloadToken->getId(),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'max_downloads' => $maxDownloads,
            'created_by' => $createdBy->getEmail()
        ]);

        return [
            'entity' => $downloadToken,
            'token' => $plainToken,
        ];
    }

    public function verifyToken(string $token, string $clientIp, ?string $password = null): ?DownloadToken
    {
        $tokenHash = hash('sha256', $token);
        $downloadToken = $this->tokenRepository->findByTokenHash($tokenHash);

        if (!$downloadToken) {
            $this->logger->warning('Token not found', ['token_hash' => substr($tokenHash, 0, 8) . '...']);
            return null;
        }

        // Check if token is active
        if (!$downloadToken->isActive()) {
            $this->logger->warning('Token is not active', [
                'token_id' => $downloadToken->getId(),
                'revoked' => $downloadToken->isRevoked(),
                'expired' => $downloadToken->isExpired(),
                'exhausted' => $downloadToken->isExhausted()
            ]);
            return null;
        }

        // Check IP whitelist
        if (!$downloadToken->canDownload($clientIp)) {
            $this->logger->warning('IP not allowed for token', [
                'token_id' => $downloadToken->getId(),
                'client_ip' => $clientIp,
                'whitelist' => $downloadToken->getIpWhitelist()
            ]);
            return null;
        }

        // Check password if required
        if ($downloadToken->getPasswordHash() !== null) {
            if ($password === null) {
                $this->logger->warning('Password required for token', ['token_id' => $downloadToken->getId()]);
                return null;
            }

            $passwordHasher = $this->passwordHasherFactory->getPasswordHasher(DownloadToken::class);
            if (!$passwordHasher->verify($downloadToken->getPasswordHash(), $password)) {
                $this->logger->warning('Invalid password for token', ['token_id' => $downloadToken->getId()]);
                return null;
            }
        }

        return $downloadToken;
    }

    public function useToken(DownloadToken $token): bool
    {
        if (!$token->isActive()) {
            return false;
        }

        $token->incrementDownloadsCount();
        $this->entityManager->flush();

        $this->logger->info('Token used', [
            'token_id' => $token->getId(),
            'downloads_count' => $token->getDownloadsCount(),
            'max_downloads' => $token->getMaxDownloads()
        ]);

        return true;
    }

    public function revokeToken(DownloadToken $token): void
    {
        $token->setRevoked(true);
        $this->entityManager->flush();

        $this->logger->info('Token revoked', [
            'token_id' => $token->getId(),
            'file_id' => $token->getFile()->getId()
        ]);
    }

    public function revokeTokenByHash(string $tokenHash): bool
    {
        $token = $this->tokenRepository->findByTokenHash($tokenHash);
        if (!$token) {
            return false;
        }

        $this->revokeToken($token);
        return true;
    }

    public function cleanupExpiredTokens(): int
    {
        $expiredTokens = $this->tokenRepository->findExpiredTokens();
        $count = 0;

        foreach ($expiredTokens as $token) {
            if (!$token->isRevoked()) {
                $token->setRevoked(true);
                $count++;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->logger->info('Expired tokens cleaned up', ['count' => $count]);
        }

        return $count;
    }

    public function getTokenStats(): array
    {
        return $this->tokenRepository->getTokenStats();
    }

    public function getTokenStatsByStatus(): array
    {
        return $this->tokenRepository->getTokenStatsByStatus();
    }

    public function findTokensByFile(File $file): array
    {
        return $this->tokenRepository->findTokensByFile($file);
    }

    public function findActiveTokens(): array
    {
        return $this->tokenRepository->findActiveTokens();
    }

    public function generateSecureToken(): string
    {
        $tokenBytes = random_bytes(32);
        return base64url_encode($tokenBytes);
    }

    public function signToken(string $token, array $metadata = []): string
    {
        $payload = [
            'token' => $token,
            'metadata' => $metadata,
            'timestamp' => time()
        ];

        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, $this->hmacSecret);
        
        return base64url_encode($payloadJson . '.' . $signature);
    }

    public function verifySignedToken(string $signedToken): ?array
    {
        try {
            $decoded = base64url_decode($signedToken);
            [$payloadJson, $signature] = explode('.', $decoded, 2);
            
            $expectedSignature = hash_hmac('sha256', $payloadJson, $this->hmacSecret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }
            
            return json_decode($payloadJson, true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify signed token', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

/**
 * Base64 URL-safe encoding
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL-safe decoding
 */
function base64url_decode(string $data): string
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
