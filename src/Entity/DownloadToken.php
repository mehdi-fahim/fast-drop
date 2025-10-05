<?php

namespace App\Entity;

use App\Repository\DownloadTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DownloadTokenRepository::class)]
#[ORM\Table(name: 'download_tokens')]
class DownloadToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: File::class, inversedBy: 'downloadTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?File $file = null;

    #[ORM\Column(length: 64)]
    private ?string $tokenHash = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private ?int $maxDownloads = null;

    #[ORM\Column]
    private ?int $downloadsCount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordHash = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ipWhitelist = [];

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'downloadTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?bool $revoked = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->downloadsCount = 0;
        $this->revoked = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->ipWhitelist = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getMaxDownloads(): ?int
    {
        return $this->maxDownloads;
    }

    public function setMaxDownloads(int $maxDownloads): static
    {
        $this->maxDownloads = $maxDownloads;

        return $this;
    }

    public function getDownloadsCount(): ?int
    {
        return $this->downloadsCount;
    }

    public function setDownloadsCount(int $downloadsCount): static
    {
        $this->downloadsCount = $downloadsCount;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getIpWhitelist(): ?array
    {
        return $this->ipWhitelist;
    }

    public function setIpWhitelist(?array $ipWhitelist): static
    {
        $this->ipWhitelist = $ipWhitelist;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function isRevoked(): ?bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): static
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isExhausted(): bool
    {
        return $this->downloadsCount >= $this->maxDownloads;
    }

    public function isActive(): bool
    {
        return !$this->revoked && !$this->isExpired() && !$this->isExhausted();
    }

    public function canDownload(string $clientIp): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        // Check IP whitelist if configured
        if (!empty($this->ipWhitelist)) {
            return $this->isIpAllowed($clientIp);
        }

        return true;
    }

    public function isIpAllowed(string $clientIp): bool
    {
        if (empty($this->ipWhitelist)) {
            return true;
        }

        foreach ($this->ipWhitelist as $allowedIp) {
            if ($this->matchesIpPattern($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function matchesIpPattern(string $ip, string $pattern): bool
    {
        // Simple CIDR and wildcard support
        if (str_contains($pattern, '/')) {
            // CIDR notation
            [$subnet, $mask] = explode('/', $pattern);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - (int)$mask);
            $ip = ip2long($ip);
            
            return ($ip & $mask) === ($subnet & $mask);
        }

        if (str_contains($pattern, '*')) {
            // Wildcard pattern
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            return preg_match('/^' . $pattern . '$/', $ip);
        }

        // Exact match
        return $ip === $pattern;
    }

    public function incrementDownloadsCount(): void
    {
        $this->downloadsCount++;
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getRemainingDownloads(): int
    {
        return max(0, $this->maxDownloads - $this->downloadsCount);
    }

    public function getUsagePercentage(): float
    {
        if ($this->maxDownloads === 0) {
            return 0.0;
        }

        return ($this->downloadsCount / $this->maxDownloads) * 100;
    }
}
