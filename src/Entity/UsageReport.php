<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\UsageReportRepository')]
#[ORM\Table(name: 'usage_reports')]
#[ORM\Index(name: 'idx_user_date', columns: ['user_id', 'report_date'])]
#[ORM\Index(name: 'idx_report_date', columns: ['report_date'])]
class UsageReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $reportDate = null;

    #[ORM\Column(type: 'integer')]
    private int $uploadsCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $uploadsSizeBytes = 0;

    #[ORM\Column(type: 'integer')]
    private int $downloadsCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $downloadsSizeBytes = 0;

    #[ORM\Column(type: 'integer')]
    private int $filesSharedCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $filesExpiredCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $filesDeletedCount = 0;

    #[ORM\Column(type: 'json')]
    private array $fileTypes = [];

    #[ORM\Column(type: 'json')]
    private array $hourlyActivity = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->reportDate = new \DateTime();
        $this->fileTypes = [];
        $this->hourlyActivity = array_fill(0, 24, 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getReportDate(): ?\DateTimeInterface
    {
        return $this->reportDate;
    }

    public function setReportDate(\DateTimeInterface $reportDate): self
    {
        $this->reportDate = $reportDate;
        return $this;
    }

    public function getUploadsCount(): int
    {
        return $this->uploadsCount;
    }

    public function setUploadsCount(int $uploadsCount): self
    {
        $this->uploadsCount = $uploadsCount;
        return $this;
    }

    public function incrementUploadsCount(): self
    {
        $this->uploadsCount++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getUploadsSizeBytes(): int
    {
        return $this->uploadsSizeBytes;
    }

    public function setUploadsSizeBytes(int $uploadsSizeBytes): self
    {
        $this->uploadsSizeBytes = $uploadsSizeBytes;
        return $this;
    }

    public function addUploadsSize(int $sizeBytes): self
    {
        $this->uploadsSizeBytes += $sizeBytes;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getUploadsSizeFormatted(): string
    {
        return $this->formatBytes($this->uploadsSizeBytes);
    }

    public function getDownloadsCount(): int
    {
        return $this->downloadsCount;
    }

    public function setDownloadsCount(int $downloadsCount): self
    {
        $this->downloadsCount = $downloadsCount;
        return $this;
    }

    public function incrementDownloadsCount(): self
    {
        $this->downloadsCount++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDownloadsSizeBytes(): int
    {
        return $this->downloadsSizeBytes;
    }

    public function setDownloadsSizeBytes(int $downloadsSizeBytes): self
    {
        $this->downloadsSizeBytes = $downloadsSizeBytes;
        return $this;
    }

    public function addDownloadsSize(int $sizeBytes): self
    {
        $this->downloadsSizeBytes += $sizeBytes;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDownloadsSizeFormatted(): string
    {
        return $this->formatBytes($this->downloadsSizeBytes);
    }

    public function getFilesSharedCount(): int
    {
        return $this->filesSharedCount;
    }

    public function setFilesSharedCount(int $filesSharedCount): self
    {
        $this->filesSharedCount = $filesSharedCount;
        return $this;
    }

    public function incrementFilesSharedCount(): self
    {
        $this->filesSharedCount++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getFilesExpiredCount(): int
    {
        return $this->filesExpiredCount;
    }

    public function setFilesExpiredCount(int $filesExpiredCount): self
    {
        $this->filesExpiredCount = $filesExpiredCount;
        return $this;
    }

    public function incrementFilesExpiredCount(): self
    {
        $this->filesExpiredCount++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getFilesDeletedCount(): int
    {
        return $this->filesDeletedCount;
    }

    public function setFilesDeletedCount(int $filesDeletedCount): self
    {
        $this->filesDeletedCount = $filesDeletedCount;
        return $this;
    }

    public function incrementFilesDeletedCount(): self
    {
        $this->filesDeletedCount++;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    public function setFileTypes(array $fileTypes): self
    {
        $this->fileTypes = $fileTypes;
        return $this;
    }

    public function addFileType(string $extension, int $count = 1): self
    {
        if (!isset($this->fileTypes[$extension])) {
            $this->fileTypes[$extension] = 0;
        }
        $this->fileTypes[$extension] += $count;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getHourlyActivity(): array
    {
        return $this->hourlyActivity;
    }

    public function setHourlyActivity(array $hourlyActivity): self
    {
        $this->hourlyActivity = $hourlyActivity;
        return $this;
    }

    public function incrementHourlyActivity(int $hour): self
    {
        if ($hour >= 0 && $hour <= 23) {
            $this->hourlyActivity[$hour]++;
            $this->updatedAt = new \DateTime();
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getTotalActivity(): int
    {
        return $this->uploadsCount + $this->downloadsCount;
    }

    public function getTotalSizeBytes(): int
    {
        return $this->uploadsSizeBytes + $this->downloadsSizeBytes;
    }

    public function getTotalSizeFormatted(): string
    {
        return $this->formatBytes($this->getTotalSizeBytes());
    }

    public function getPeakHour(): int
    {
        return array_search(max($this->hourlyActivity), $this->hourlyActivity);
    }

    public function getTopFileType(): ?string
    {
        if (empty($this->fileTypes)) {
            return null;
        }
        
        return array_search(max($this->fileTypes), $this->fileTypes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
