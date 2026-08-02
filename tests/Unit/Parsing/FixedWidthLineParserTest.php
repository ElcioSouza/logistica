<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing;

use App\Parsing\FixedWidthLineParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FixedWidthLineParserTest extends TestCase
{
    private FixedWidthLineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FixedWidthLineParser();
    }

    private function buildValidLine(): string
    {
        return str_pad('2', 10, '0', STR_PAD_LEFT)
            . str_pad('Miller', 45, ' ', STR_PAD_LEFT)
            . str_pad('12345', 10, '0', STR_PAD_LEFT)
            . str_pad('111', 10, '0', STR_PAD_LEFT)
            . str_pad('256.24', 12, '0', STR_PAD_LEFT)
            . '20201201';
    }

    public function testParsesAValidLineIntoTypedFields(): void
    {
        $result = $this->parser->parse($this->buildValidLine());

        $this->assertSame(2, $result['user_id']);
        $this->assertSame('Miller', $result['name']);
        $this->assertSame(12345, $result['order_id']);
        $this->assertSame(111, $result['product_id']);
        $this->assertSame(256.24, $result['product_value']);
        $this->assertSame('2020-12-01', $result['purchase_date']);
    }

    public function testThrowsWhenLineLengthIsWrong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('line_too_short');
    }

    public function testThrowsWhenNumericFieldContainsLetters(): void
    {
        $line = str_replace(
            str_pad('12345', 10, '0', STR_PAD_LEFT),
            str_pad('ABCDE', 10, '0', STR_PAD_LEFT),
            $this->buildValidLine()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($line);
    }

    public function testThrowsWhenDecimalFieldIsCorrupted(): void
    {
        $line = str_replace(
            str_pad('256.24', 12, '0', STR_PAD_LEFT),
            str_pad('AB.XYZW', 12, '0', STR_PAD_LEFT),
            $this->buildValidLine()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($line);
    }

    public function testThrowsWhenDateIsNotARealCalendarDate(): void
    {
        $line = str_replace('20201201', '20201332', $this->buildValidLine());

        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($line);
    }
}
