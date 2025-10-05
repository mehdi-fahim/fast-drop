<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    public const ACTION_UPLOAD = 'upload';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_DELETE = 'delete';
    public const ACTION_GENERATE_TOKEN = 'generate_token';
    public const ACTION_REVOKE_TOKEN = 'revoke_token';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_CREATE_USER = 'create_user';
    public const ACTION_UPDATE_USER = 'update_user';
    public const ACTION_DELETE_USER = 'delete_user';
    public const ACTION_QUARANTINE = 'quarantine';
    public const ACTION_RELEASE_QUARANTINE = 'release_quarantine';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: File::class, inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?File $file = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $timestamp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resourceId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resourceType = null;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
        $this->metadata = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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

    public function getTimestamp(): ?\DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setResourceId(?string $resourceId): static
    {
        $this->resourceId = $resourceId;

        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    public function setResourceType(?string $resourceType): static
    {
        $this->resourceType = $resourceType;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }

        $this->metadata[$key] = $value;

        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function getFormattedTimestamp(): string
    {
        return $this->timestamp->format('Y-m-d H:i:s');
    }

    public function getActionDescription(): string
    {
        return match ($this->action) {
            self::ACTION_UPLOAD => 'File uploaded',
            self::ACTION_DOWNLOAD => 'File downloaded',
            self::ACTION_DELETE => 'File deleted',
            self::ACTION_GENERATE_TOKEN => 'Download token generated',
            self::ACTION_REVOKE_TOKEN => 'Download token revoked',
            self::ACTION_LOGIN => 'User logged in',
            self::ACTION_LOGOUT => 'User logged out',
            self::ACTION_CREATE_USER => 'User created',
            self::ACTION_UPDATE_USER => 'User updated',
            self::ACTION_DELETE_USER => 'User deleted',
            self::ACTION_QUARANTINE => 'File quarantined',
            self::ACTION_RELEASE_QUARANTINE => 'File released from quarantine',
            default => 'Unknown action'
        };
    }
}
