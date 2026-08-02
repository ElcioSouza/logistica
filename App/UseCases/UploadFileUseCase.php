<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Contracts\OutboxRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class UploadFileUseCase
{
    public function __construct(
        private readonly OutboxRepositoryInterface $outboxRepository,
        private readonly string $storageDirectory = __DIR__ . '/../../storage/uploads'
    ) {}

    public function execute(string $tmpFilePath): array
    {
        if (!file_exists($tmpFilePath)) {
            throw new InvalidArgumentException('The provided file does not exist.');
        }

        if (!is_dir($this->storageDirectory) && !mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)) {
            throw new RuntimeException('Unable to create the storage directory.');
        }

        $storageDirectory = realpath($this->storageDirectory) ?: $this->storageDirectory;
        $storedPath = rtrim($storageDirectory, '/') . '/' . uniqid('upload_', true) . '.txt';

        $moved = is_uploaded_file($tmpFilePath)
            ? move_uploaded_file($tmpFilePath, $storedPath)
            : @rename($tmpFilePath, $storedPath);

        if (!$moved) {
            $moved = @copy($tmpFilePath, $storedPath) && @unlink($tmpFilePath);
        }

        if (!$moved) {
            throw new RuntimeException('Unable to move the file to persistent storage.');
        }

        $receivedAt = date('Y-m-d H:i:s');
        $uploadId = $this->outboxRepository->recordUpload(
            filePath: $storedPath,
            receivedAt: $receivedAt,
            eventType: 'file.uploaded',
            eventPayload: ['file_path' => $storedPath, 'received_at' => $receivedAt]
        );

        return [
            'success' => true,
            'message' => 'File received successfully and registered for asynchronous processing.',
            'upload_id' => $uploadId,
        ];
    }
}
