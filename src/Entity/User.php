<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_UPLOADER = 'ROLE_UPLOADER';
    public const ROLE_VIEWER = 'ROLE_VIEWER';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?int $quotaTotalBytes = null;

    #[ORM\Column]
    private ?int $quotaUsedBytes = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $files;

    #[ORM\OneToMany(targetEntity: DownloadToken::class, mappedBy: 'createdBy', orphanRemoval: true)]
    private Collection $downloadTokens;

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->downloadTokens = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getQuotaTotalBytes(): ?int
    {
        return $this->quotaTotalBytes;
    }

    public function setQuotaTotalBytes(?int $quotaTotalBytes): static
    {
        $this->quotaTotalBytes = $quotaTotalBytes;

        return $this;
    }

    public function getQuotaUsedBytes(): ?int
    {
        return $this->quotaUsedBytes;
    }

    public function setQuotaUsedBytes(int $quotaUsedBytes): static
    {
        $this->quotaUsedBytes = $quotaUsedBytes;

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

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setOwner($this);
        }

        return $this;
    }

    public function removeFile(File $file): static
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getOwner() === $this) {
                $file->setOwner(null);
            }
        }

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
            $downloadToken->setCreatedBy($this);
        }

        return $this;
    }

    public function removeDownloadToken(DownloadToken $downloadToken): static
    {
        if ($this->downloadTokens->removeElement($downloadToken)) {
            // set the owning side to null (unless already changed)
            if ($downloadToken->getCreatedBy() === $this) {
                $downloadToken->setCreatedBy(null);
            }
        }

        return $this;
    }

    public function hasQuotaForFile(int $fileSize): bool
    {
        if ($this->quotaTotalBytes === null) {
            return true; // No quota limit
        }

        return ($this->quotaUsedBytes + $fileSize) <= $this->quotaTotalBytes;
    }

    public function getQuotaUsagePercentage(): float
    {
        if ($this->quotaTotalBytes === null || $this->quotaTotalBytes === 0) {
            return 0.0;
        }

        return ($this->quotaUsedBytes / $this->quotaTotalBytes) * 100;
    }

    public function isAdmin(): bool
    {
        return in_array(self::ROLE_ADMIN, $this->getRoles());
    }

    public function canUpload(): bool
    {
        return in_array(self::ROLE_UPLOADER, $this->getRoles()) || $this->isAdmin();
    }

    public function canView(): bool
    {
        return in_array(self::ROLE_VIEWER, $this->getRoles()) || $this->isAdmin();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $this->expiresAt->diff($now);

        if ($this->expiresAt < $now) {
            return -$diff->days;
        }

        return $diff->days;
    }
}
