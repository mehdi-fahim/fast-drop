<?php

namespace App\Tests\Unit\Service;

use App\Entity\DownloadToken;
use App\Entity\File;
use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherInterface;
use Psr\Log\LoggerInterface;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;
    private EntityManagerInterface $entityManager;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->params->method('get')
            ->with('app.hmac_secret')
            ->willReturn('test-hmac-secret');

        $this->tokenService = new TokenService(
            $this->entityManager,
            $this->createMock(\App\Repository\DownloadTokenRepository::class),
            $this->passwordHasherFactory,
            $this->params,
            $this->logger
        );
    }

    public function testGenerateToken(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);

        $file = new File();
        $file->setFilename('test.txt');
        $file->setSizeBytes(1024);
        $file->setStoragePath('/test/path');

        $expiresAt = new \DateTimeImmutable('+1 hour');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $token = $this->tokenService->generateToken(
            $file,
            $user,
            $expiresAt,
            5
        );

        $this->assertInstanceOf(DownloadToken::class, $token);
        $this->assertEquals($file, $token->getFile());
        $this->assertEquals($user, $token->getCreatedBy());
        $this->assertEquals($expiresAt, $token->getExpiresAt());
        $this->assertEquals(5, $token->getMaxDownloads());
        $this->assertEquals(0, $token->getDownloadsCount());
        $this->assertFalse($token->isRevoked());
    }

    public function testGenerateTokenWithPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);

        $file = new File();
        $file->setFilename('test.txt');
        $file->setSizeBytes(1024);
        $file->setStoragePath('/test/path');

        $expiresAt = new \DateTimeImmutable('+1 hour');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects($this->once())
            ->method('hash')
            ->with('secret-password')
            ->willReturn('hashed-password');

        $this->passwordHasherFactory->expects($this->once())
            ->method('getPasswordHasher')
            ->with(DownloadToken::class)
            ->willReturn($passwordHasher);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $token = $this->tokenService->generateToken(
            $file,
            $user,
            $expiresAt,
            5,
            'secret-password'
        );

        $this->assertInstanceOf(DownloadToken::class, $token);
        $this->assertEquals('hashed-password', $token->getPasswordHash());
    }

    public function testGenerateSecureToken(): void
    {
        $token1 = $this->tokenService->generateSecureToken();
        $token2 = $this->tokenService->generateSecureToken();

        $this->assertIsString($token1);
        $this->assertIsString($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertGreaterThan(30, strlen($token1)); // Base64url encoded 32 bytes should be ~43 chars
    }

    public function testSignAndVerifyToken(): void
    {
        $token = $this->tokenService->generateSecureToken();
        $metadata = ['file_id' => 123, 'user_id' => 456];

        $signedToken = $this->tokenService->signToken($token, $metadata);
        $this->assertIsString($signedToken);
        $this->assertNotEquals($token, $signedToken);

        $verified = $this->tokenService->verifySignedToken($signedToken);
        $this->assertIsArray($verified);
        $this->assertEquals($token, $verified['token']);
        $this->assertEquals($metadata, $verified['metadata']);
        $this->assertArrayHasKey('timestamp', $verified);
    }

    public function testVerifyInvalidSignedToken(): void
    {
        $invalidToken = 'invalid-signed-token';
        
        $verified = $this->tokenService->verifySignedToken($invalidToken);
        $this->assertNull($verified);
    }
}
