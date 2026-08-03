<?php
 

declare(strict_types=1);

namespace App\Contracts;

/**
 * ================================================================================================
 * INTERFACE OutboxRepositoryInterface — Contrato do padrão Outbox
 * ================================================================================================
 *
 * GARANTIA QUE ESTA INTERFACE PROTEGE:
 *   "Se o upload foi aceito (HTTP 202), o evento de processamento SERÁ publicado eventualmente,
 *    mesmo que o RabbitMQ esteja fora do ar no momento do upload."
 *
 *   Isso é possível porque `recordUpload()` grava o registro de negócio (uploads) e o evento
 *   (outbox_events) NA MESMA TRANSAÇÃO LOCAL — nunca ficam inconsistentes entre si. A publicação
 *   real no broker é responsabilidade de um processo separado (o Outbox Relay), que fica livre
 *   para tentar de novo quantas vezes precisar sem nunca perder o evento.
 *
 * ================================================================================================
 */
interface OutboxRepositoryInterface
{
    public function recordUpload(string $filePath, string $receivedAt, string $eventType, array $eventPayload): int;

    public function fetchUnpublished(int $limit = 50): array;

    /** Marca um evento como publicado com sucesso no broker. */
    public function markPublished(int $eventId): void;

    /** Atualiza o status de processamento de um upload (chamado pelo worker). */
    public function updateUploadStatus(int $uploadId, string $status, ?int $processedLines = null, ?int $discardedLines = null): void;
}
