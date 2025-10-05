<?php

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
#[ORM\HasLifecycleCallbacks]
class File
{
    public const STATUS_OK = 'ok';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_UPLOADING = 'uploading';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 500)]
    private ?string $storagePath = null;

    #[ORM\Column]
    private ?int $sizeBytes = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_UPLOADING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $projectName = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: DownloadToken::class, mappedBy: 'file', orphanRemoval: true)]
    private Collection $downloadTokens;

    #[ORM\OneToMany(targetEntity: AuditLog::class, mappedBy: 'file', orphanRemoval: true)]
    private Collection $auditLogs;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recipients = [];

    public function __construct()
    {
        $this->downloadTokens = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->metadata = [];
        $this->recipients = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): static
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function setProjectName(?string $projectName): static
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, DownloadToken>
     */
    public function getDownloadTokens(): Collection
    {
        return $this->downloadTokens;
    }

    public function addDownloadToken(DownloadToken $downloadToken): static
    {
        if (!$this->downloadTokens->contains($downloadToken)) {
            $this->downloadTokens->add($downloadToken);
            $downloadToken->setFile($this);
        }

        return $this;
    }

    public function removeDownloadToken(DownloadToken $downloadToken): static
    {
        if ($this->downloadTokens->removeElement($downloadToken)) {
            // set the owning side to null (unless already changed)
            if ($downloadToken->getFile() === $this) {
                $downloadToken->setFile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(AuditLog $auditLog): static
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
            $auditLog->setFile($this);
        }

        return $this;
    }

    public function removeAuditLog(AuditLog $auditLog): static
    {
        if ($this->auditLogs->removeElement($auditLog)) {
            // set the owning side to null (unless already changed)
            if ($auditLog->getFile() === $this) {
                $auditLog->setFile(null);
            }
        }

        return $this;
    }

    public function getRecipients(): ?array
    {
        return $this->recipients;
    }

    public function setRecipients(?array $recipients): static
    {
        $this->recipients = $recipients;

        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAccessible(): bool
    {
        return $this->status === self::STATUS_OK && !$this->isExpired();
    }

    public function getFormattedSize(): string
    {
        if ($this->sizeBytes === null) {
            return '0 B';
        }

        $bytes = $this->sizeBytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
