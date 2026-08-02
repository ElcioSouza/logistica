<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\UploadController;
use App\Http\Request;
use App\UseCases\UploadFileUseCase;
use PHPUnit\Framework\TestCase;

/**
 * Classe de teste unitário para o UploadController.
 * Valida o comportamento da camada HTTP antes de acionar o caso de uso.
 */
class UploadControllerTest extends TestCase
{
    private UploadFileUseCase $uploadFileUseCaseMock;
    private UploadController $controller;

    protected function setUp(): void
    {
        $this->uploadFileUseCaseMock = $this->createMock(UploadFileUseCase::class);
        $this->controller = new UploadController($this->uploadFileUseCaseMock);
    }

    /**
     * Valida o comportamento de Fail-Fast:
     * Garante que a API retorna HTTP 400 (Bad Request)
     * caso a requisição não contenha nenhum arquivo válido.
     */
    public function testShouldReturn400WhenNoFileIsProvided(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/upload',
            queryParams: [],
            body: [],
            files: []
        );

        $response = $this->controller->handle($request);

        $this->assertEquals(400, $response->statusCode);
        $this->assertArrayHasKey('error', $response->payload);
    }

    /**
     * Valida a restrição de formato:
     * Garante que a API rejeita arquivos cujo nome não termine em .txt,
     * retornando HTTP 400 com mensagem informativa.
     */
    public function testShouldReturn400WhenFileIsNotTxt(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/upload',
            queryParams: [],
            body: [],
            files: [
                'file' => [
                    'name' => 'dados.pdf',
                    'type' => 'application/pdf',
                    'tmp_name' => '/tmp/php_fake',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 1024,
                ],
            ]
        );

        $response = $this->controller->handle($request);

        $this->assertEquals(400, $response->statusCode);
        $this->assertStringContainsString('apenas arquivos .txt', $response->payload['error']);
    }

    /**
     * Valida o fluxo de sucesso assíncrono:
     * Garante que um arquivo .txt válido é aceito, o UseCase é executado via mock,
     * e a API responde imediatamente com HTTP 202 (Accepted) sem esperar o processamento.
     */
    public function testShouldReturn202WhenValidFileIsAccepted(): void
    {
        $this->uploadFileUseCaseMock
            ->expects($this->once())
            ->method('execute')
            ->with('/tmp/php_fake')
            ->willReturn(['success' => true, 'message' => 'ok', 'upload_id' => 7]);

        $request = new Request(
            method: 'POST',
            uri: '/upload',
            queryParams: [],
            body: [],
            files: [
                'file' => [
                    'name' => 'legado_2gb.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php_fake',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 2097152000,
                ],
            ]
        );

        $response = $this->controller->handle($request);

        $this->assertEquals(202, $response->statusCode);
        $this->assertTrue($response->payload['success']);
        $this->assertSame(7, $response->payload['upload_id']);
    }

    /**
     * Ferramentas comuns (curl/Postman) enviam Content-Type genérico (application/octet-stream)
     * para arquivos .txt. A validação por extensão não pode rejeitar esse caso.
     */
    public function testShouldAcceptTxtFileEvenWithGenericContentType(): void
    {
        $this->uploadFileUseCaseMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(['success' => true, 'message' => 'ok', 'upload_id' => 1]);

        $request = new Request(
            method: 'POST',
            uri: '/upload',
            queryParams: [],
            body: [],
            files: [
                'file' => [
                    'name' => 'pedidos.txt',
                    'type' => 'application/octet-stream', // Content-Type genérico — não deve ser motivo de rejeição
                    'tmp_name' => '/tmp/php_fake',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 1024,
                ],
            ]
        );

        $response = $this->controller->handle($request);

        $this->assertEquals(202, $response->statusCode);
    }
}
