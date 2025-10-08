<?php

namespace App\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StorageService
{
    private FilesystemOperator $filesystem;
    private string $storageType;
    private LoggerInterface $logger;

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->storageType = $params->get('app.storage.type', 'local');
        
        $this->initializeFilesystem($params);
    }

    private function initializeFilesystem(ParameterBagInterface $params): void
    {
        switch ($this->storageType) {
            case 's3':
                $this->filesystem = $this->createS3Filesystem($params);
                break;
            case 'local':
            default:
                $this->filesystem = $this->createLocalFilesystem($params);
                break;
        }
    }

    private function createLocalFilesystem(ParameterBagInterface $params): FilesystemOperator
    {
        $localPath = $params->get('app.storage.local_path');
        
        if (!is_dir($localPath)) {
            mkdir($localPath, 0755, true);
        }

        $adapter = new LocalFilesystemAdapter($localPath);
        return new Filesystem($adapter);
    }

    private function createS3Filesystem(ParameterBagInterface $params): FilesystemOperator
    {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $params->get('app.storage.s3.region'),
            'endpoint' => $params->get('app.storage.s3.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $params->get('app.storage.s3.key'),
                'secret' => $params->get('app.storage.s3.secret'),
            ],
        ]);

        $adapter = new AwsS3V3Adapter($s3Client, $params->get('app.storage.s3.bucket'));
        return new Filesystem($adapter);
    }

    public function write(string $path, string $contents): bool
    {
        try {
            $this->filesystem->write($path, $contents);
            $this->logger->info('File written successfully', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to write file', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function writeStream(string $path, $stream): bool
    {
        try {
            $this->filesystem->writeStream($path, $stream);
            $this->logger->info('File stream written successfully', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to write file stream', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function read(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read file', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function readStream(string $path)
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read file stream', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->filesystem->delete($path);
            $this->logger->info('File deleted successfully', ['path' => $path]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete file', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function move(string $source, string $destination): bool
    {
        try {
            $this->filesystem->move($source, $destination);
            $this->logger->info('File moved successfully', [
                'source' => $source,
                'destination' => $destination
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to move file', [
                'source' => $source,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    public function getSize(string $path): int
    {
        try {
            return $this->filesystem->fileSize($path);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get file size', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getMimeType(string $path): string
    {
        try {
            return $this->filesystem->mimeType($path);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get file mime type', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getChecksum(string $path): string
    {
        try {
            // Use SHA-256 for consistency with client-side calculation
            $stream = $this->filesystem->readStream($path);
            $context = hash_init('sha256');
            
            while (!feof($stream)) {
                $data = fread($stream, 8192); // Read in 8KB chunks
                if ($data !== false) {
                    hash_update($context, $data);
                }
            }
            
            fclose($stream);
            return hash_final($context);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get file checksum', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createDirectory(string $path): bool
    {
        try {
            $this->filesystem->createDirectory($path);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create directory', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function listContents(string $path = ''): array
    {
        try {
            return $this->filesystem->listContents($path)->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to list contents', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getStorageType(): string
    {
        return $this->storageType;
    }

    public function isS3(): bool
    {
        return $this->storageType === 's3';
    }

    public function generateSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        if (!$this->isS3()) {
            throw new \RuntimeException('Signed URLs are only available for S3 storage');
        }

        // This would need to be implemented with the S3 client
        // For now, return the path
        return $path;
    }
}
