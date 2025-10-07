<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\FlockStore;
use Predis\Client as RedisClient;

class UploadService
{
    private EntityManagerInterface $entityManager;
    private FileRepository $fileRepository;
    private StorageService $storageService;
    private AuditService $auditService;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;
    private int $chunkSize;

    public function __construct(
        EntityManagerInterface $entityManager,
        FileRepository $fileRepository,
        StorageService $storageService,
        AuditService $auditService,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->fileRepository = $fileRepository;
        $this->storageService = $storageService;
        $this->auditService = $auditService;
        $this->params = $params;
        $this->logger = $logger;
        $this->chunkSize = $params->get('app.upload.chunk_size', 5 * 1024 * 1024); // 5MB default

        // Initialize lock store (Redis if available, otherwise filesystem locks)
        try {
            $redisUrl = $params->get('app.redis.url', 'redis://localhost:6379');
            $redis = new RedisClient($redisUrl);
            // simple ping to ensure connectivity
            $redis->connect();
            $store = new RedisStore($redis);
        } catch (\Throwable $e) {
            // Fallback to FlockStore (no external service required)
            $store = new FlockStore(sys_get_temp_dir());
            $this->logger->warning('Redis unavailable for locks, using FlockStore fallback', [
                'error' => $e->getMessage(),
            ]);
        }
        $this->lockFactory = new LockFactory($store);
    }

    public function startUpload(
        User $user,
        string $filename,
        int $totalSize,
        ?string $description = null,
        ?string $projectName = null,
        ?array $recipients = null,
        ?\DateTimeImmutable $expiresAt = null
    ): File {
        // Check quota
        if (!$user->hasQuotaForFile($totalSize)) {
            throw new \RuntimeException('Insufficient quota for file upload');
        }

        // Generate unique storage path
        $storagePath = $this->generateStoragePath($user, $filename);
        
        // Create file entity
        $file = new File();
        $file->setOwner($user);
        $file->setFilename($filename);
        $file->setStoragePath($storagePath);
        $file->setSizeBytes($totalSize);
        $file->setStatus(File::STATUS_UPLOADING);
        $file->setDescription($description);
        $file->setProjectName($projectName);
        $file->setRecipients($recipients ?? []);
        $file->setExpiresAt($expiresAt);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->logger->info('Upload started', [
            'file_id' => $file->getId(),
            'filename' => $filename,
            'size' => $totalSize,
            'user' => $user->getEmail()
        ]);

        return $file;
    }

    public function uploadChunk(File $file, int $chunkIndex, string $chunkData): bool
    {
        $lockKey = "upload_chunk_{$file->getId()}_{$chunkIndex}";
        $lock = $this->lockFactory->createLock($lockKey, 30);

        if (!$lock->acquire()) {
            throw new \RuntimeException('Could not acquire lock for chunk upload');
        }

        try {
            $chunkPath = $this->getChunkPath($file, $chunkIndex);
            
            // Write chunk to temporary storage
            if (!$this->storageService->write($chunkPath, $chunkData)) {
                throw new \RuntimeException('Failed to write chunk');
            }

            $this->logger->debug('Chunk uploaded', [
                'file_id' => $file->getId(),
                'chunk_index' => $chunkIndex,
                'chunk_size' => strlen($chunkData)
            ]);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function completeUpload(File $file, string $expectedChecksum): bool
    {
        $lockKey = "upload_complete_{$file->getId()}";
        $lock = $this->lockFactory->createLock($lockKey, 300); // 5 minutes

        if (!$lock->acquire()) {
            throw new \RuntimeException('Could not acquire lock for upload completion');
        }

        try {
            // Assemble chunks
            $tempFilePath = $this->assembleChunks($file);
            
            if (!$tempFilePath) {
                throw new \RuntimeException('Failed to assemble chunks');
            }

            // Verify checksum
            $actualChecksum = $this->storageService->getChecksum($tempFilePath);
            // expectedChecksum envoyé par le client est un SHA-256 hex
            if ($actualChecksum !== $expectedChecksum) {
                $this->storageService->delete($tempFilePath);
                throw new \RuntimeException('Checksum verification failed');
            }

            // Move to final storage location
            $tempContent = $this->storageService->read($tempFilePath);
            if (!$this->storageService->write($file->getStoragePath(), $tempContent)) {
                $this->storageService->delete($tempFilePath);
                throw new \RuntimeException('Failed to move file to final location');
            }

            // Clean up temporary file
            $this->storageService->delete($tempFilePath);
            $this->cleanupChunks($file);

            // Update file status
            $file->setStatus(File::STATUS_OK);
            $file->setChecksum($actualChecksum);
            $this->entityManager->flush();

            // Update user quota
            $file->getOwner()->setQuotaUsedBytes(
                $file->getOwner()->getQuotaUsedBytes() + $file->getSizeBytes()
            );
            $this->entityManager->flush();

            // Log upload completion
            $this->auditService->logUpload($file->getOwner(), $file, [
                'checksum' => $actualChecksum,
                'upload_method' => 'chunked'
            ]);

            $this->logger->info('Upload completed', [
                'file_id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSizeBytes(),
                'checksum' => $actualChecksum
            ]);

            return true;
        } finally {
            $lock->release();
        }
    }

    public function cancelUpload(File $file): bool
    {
        // Clean up chunks
        $this->cleanupChunks($file);
        
        // Delete file entity
        $this->entityManager->remove($file);
        $this->entityManager->flush();

        $this->logger->info('Upload cancelled', [
            'file_id' => $file->getId(),
            'filename' => $file->getFilename()
        ]);

        return true;
    }

    public function uploadFile(
        User $user,
        UploadedFile $uploadedFile,
        ?string $description = null,
        ?string $projectName = null,
        ?array $recipients = null,
        ?\DateTimeImmutable $expiresAt = null
    ): File {
        // Check quota
        if (!$user->hasQuotaForFile($uploadedFile->getSize())) {
            throw new \RuntimeException('Insufficient quota for file upload');
        }

        // Generate unique storage path
        $storagePath = $this->generateStoragePath($user, $uploadedFile->getClientOriginalName());
        
        // Create file entity
        $file = new File();
        $file->setOwner($user);
        $file->setFilename($uploadedFile->getClientOriginalName());
        $file->setStoragePath($storagePath);
        $file->setSizeBytes($uploadedFile->getSize());
        $file->setStatus(File::STATUS_UPLOADING);
        $file->setDescription($description);
        $file->setProjectName($projectName);
        $file->setRecipients($recipients ?? []);
        $file->setExpiresAt($expiresAt);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        try {
            // Read uploaded file content
            $fileContent = file_get_contents($uploadedFile->getPathname());
            
            // Write to storage using StorageService
            if (!$this->storageService->write($storagePath, $fileContent)) {
                throw new \RuntimeException('Failed to write file to storage');
            }

            // Calculate checksum
            $checksum = $this->storageService->getChecksum($storagePath);
            
            // Update file status
            $file->setStatus(File::STATUS_OK);
            $file->setChecksum($checksum);
            $this->entityManager->flush();

            // Update user quota
            $file->getOwner()->setQuotaUsedBytes(
                $file->getOwner()->getQuotaUsedBytes() + $file->getSizeBytes()
            );
            $this->entityManager->flush();

            // Log upload
            $this->auditService->logUpload($user, $file, [
                'checksum' => $checksum,
                'upload_method' => 'direct'
            ]);

            $this->logger->info('File uploaded', [
                'file_id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSizeBytes(),
                'checksum' => $checksum
            ]);

            return $file;
        } catch (\Exception $e) {
            // Clean up on failure
            $this->entityManager->remove($file);
            $this->entityManager->flush();
            
            if ($this->storageService->exists($storagePath)) {
                $this->storageService->delete($storagePath);
            }
            
            throw $e;
        }
    }

    private function generateStoragePath(User $user, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $safeBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        
        $date = date('Y/m/d');
        $uuid = uniqid();
        
        return "files/{$user->getId()}/{$date}/{$safeBasename}_{$uuid}.{$extension}";
    }

    private function getChunkPath(File $file, int $chunkIndex): string
    {
        return "chunks/{$file->getId()}/chunk_{$chunkIndex}";
    }

    private function assembleChunks(File $file): ?string
    {
        $tempFilePath = "temp/{$file->getId()}_" . uniqid();
        
        // Check if chunks directory exists
        $chunksDir = "chunks/{$file->getId()}";
        $chunks = $this->storageService->listContents($chunksDir);
        
        if (empty($chunks)) {
            return null;
        }

        // Sort chunks by index
        $chunkFiles = [];
        foreach ($chunks as $chunk) {
            if (preg_match('/chunk_(\d+)/', $chunk['path'], $matches)) {
                $chunkFiles[(int)$matches[1]] = $chunk['path'];
            }
        }
        
        ksort($chunkFiles);

        // Assemble chunks
        $handle = fopen('php://temp', 'r+');
        foreach ($chunkFiles as $chunkPath) {
            $chunkContent = $this->storageService->read($chunkPath);
            fwrite($handle, $chunkContent);
        }
        rewind($handle);

        // Write assembled file
        $assembledContent = stream_get_contents($handle);
        fclose($handle);

        if (!$this->storageService->write($tempFilePath, $assembledContent)) {
            return null;
        }

        return $tempFilePath;
    }

    private function cleanupChunks(File $file): void
    {
        $chunksDir = "chunks/{$file->getId()}";
        $chunks = $this->storageService->listContents($chunksDir);
        
        foreach ($chunks as $chunk) {
            $this->storageService->delete($chunk['path']);
        }
    }

    public function getUploadProgress(File $file): array
    {
        $chunksDir = "chunks/{$file->getId()}";
        $chunks = $this->storageService->listContents($chunksDir);
        
        $uploadedChunks = 0;
        $totalChunks = (int)ceil($file->getSizeBytes() / $this->chunkSize);
        
        foreach ($chunks as $chunk) {
            if (str_contains($chunk['path'], 'chunk_')) {
                $uploadedChunks++;
            }
        }

        $progress = $totalChunks > 0 ? ($uploadedChunks / $totalChunks) * 100 : 0;

        return [
            'uploaded_chunks' => $uploadedChunks,
            'total_chunks' => $totalChunks,
            'progress' => round($progress, 2),
            'status' => $file->getStatus()
        ];
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }
}
