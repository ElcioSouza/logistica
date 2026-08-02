<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases;

use App\UseCases\ProcessFileUseCase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProcessFileUseCaseTest extends TestCase
{
    private string $tempFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFilePath = sys_get_temp_dir() . '/test_logistics_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
        parent::tearDown();
    }

    private function buildLine(
        int $userId,
        string $name,
        int $orderId,
        int $productId,
        float $value,
        string $date
    ): string {
        return str_pad((string) $userId, 10, '0', STR_PAD_LEFT)
            . str_pad($name, 45, ' ', STR_PAD_LEFT)
            . str_pad((string) $orderId, 10, '0', STR_PAD_LEFT)
            . str_pad((string) $productId, 10, '0', STR_PAD_LEFT)
            . str_pad(number_format($value, 2, '.', ''), 12, '0', STR_PAD_LEFT)
            . $date;
    }

    public function testShouldThrowExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $useCase = new ProcessFileUseCase();
        iterator_to_array($useCase->execute('/path/to/nonexistent/file.txt'));
    }

    public function testShouldParseValidPositionalLinesCorrectly(): void
    {
        $lines = [
            $this->buildLine(2, 'Miller', 12345, 111, 256.24, '20201201'),
            $this->buildLine(1, 'Zarelli', 123, 111, 512.24, '20211201'),
            $this->buildLine(1, 'Zarelli', 123, 122, 512.24, '20211201'),
            $this->buildLine(2, 'Miller', 12345, 122, 256.24, '20201201'),
        ];
        file_put_contents($this->tempFilePath, implode("\n", $lines) . "\n");

        $useCase = new ProcessFileUseCase();
        $rows = iterator_to_array($useCase->execute($this->tempFilePath));

        $this->assertCount(4, $rows);

        $this->assertSame(2, $rows[0]['user_id']);
        $this->assertSame('Miller', $rows[0]['name']);
        $this->assertSame(12345, $rows[0]['order_id']);
        $this->assertSame(111, $rows[0]['product_id']);
        $this->assertSame(256.24, $rows[0]['product_value']);
        $this->assertSame('2020-12-01', $rows[0]['purchase_date']);

        $this->assertSame(1, $rows[1]['user_id']);
        $this->assertSame('Zarelli', $rows[1]['name']);
    }

    public function testShouldDiscardCorruptedLinesAndKeepProcessingTheRest(): void
    {
        $validLine = $this->buildLine(1, 'Zarelli', 123, 111, 512.24, '20211201');

        $corruptedLine = str_pad('0000000001', 10, '0', STR_PAD_LEFT)
            . str_pad('Zarelli', 45, ' ', STR_PAD_LEFT)
            . str_pad('123', 10, '0', STR_PAD_LEFT)
            . str_pad('122', 10, '0', STR_PAD_LEFT)
            . str_pad('ABCDEFGH.XY', 12, '0', STR_PAD_LEFT)
            . '20211201';

        $wrongLengthLine = 'line_too_short';

        file_put_contents(
            $this->tempFilePath,
            implode("\n", [$validLine, $corruptedLine, $wrongLengthLine, $validLine]) . "\n"
        );

        $errors = [];
        $onError = function (int $lineNumber, string $line, string $reason) use (&$errors) {
            $errors[] = ['line' => $lineNumber, 'reason' => $reason];
        };

        $useCase = new ProcessFileUseCase();
        $rows = iterator_to_array($useCase->execute($this->tempFilePath, $onError));

        $this->assertCount(2, $rows);
        $this->assertCount(2, $errors);
    }

    public function testShouldSkipEmptyLines(): void
    {
        $validLine = $this->buildLine(1, 'Zarelli', 123, 111, 512.24, '20211201');
        file_put_contents($this->tempFilePath, $validLine . "\n\n\n" . $validLine . "\n");

        $useCase = new ProcessFileUseCase();
        $rows = iterator_to_array($useCase->execute($this->tempFilePath));

        $this->assertCount(2, $rows);
    }
}
