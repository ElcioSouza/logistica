<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\UseCases\UploadFileUseCase;
use InvalidArgumentException;
use Throwable;

class UploadController
{

    public function __construct(
        private readonly UploadFileUseCase $uploadFileUseCase
    ) {}

    public function handle(Request $request): Response
    {
        $file = $request->files['file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return new Response(['error' => 'No valid file was provided in the request.'], 400);
        }
        
        $originalName = $file['name'] ?? '';
        if (!str_ends_with(strtolower($originalName), '.txt')) {
            return new Response(['error' => 'Invalid format. Only .txt files are accepted.'], 400);
        }

        try {

            $result = $this->uploadFileUseCase->execute($file['tmp_name']);

            return new Response([
                'success' => true,
                'message' => 'File received successfully and registered for asynchronous processing.',
                'upload_id' => $result['upload_id'],
            ], 202);

        } catch (InvalidArgumentException $e) {
            return new Response(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            return new Response(['error' => 'Internal error while processing the file.'], 500);
        }
    }
}