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
            return new Response(['error' => 'Nenhum arquivo válido foi enviado na requisição.'], 400);
        }
        
        $originalName = $file['name'] ?? '';
        if (!str_ends_with(strtolower($originalName), '.txt')) {
            return new Response(['error' => 'Formato inválido. O sistema aceita apenas arquivos .txt.'], 400);
        }

        try {

            $result = $this->uploadFileUseCase->execute($file['tmp_name']);

            return new Response([
                'success' => true,
                'message' => 'Arquivo recebido com sucesso e registrado para processamento assíncrono.',
                'upload_id' => $result['upload_id'],
            ], 202);

        } catch (InvalidArgumentException $e) {
            return new Response(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            return new Response(['error' => 'Erro interno ao processar o arquivo.'], 500);
        }
    }
}