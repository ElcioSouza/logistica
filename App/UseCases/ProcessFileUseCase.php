<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Parsing\FixedWidthLineParser;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final class ProcessFileUseCase
{
    public function __construct(
        private readonly FixedWidthLineParser $parser = new FixedWidthLineParser()
    ) {}

    public function execute(string $filePath, ?callable $onError = null): Generator
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("The provided file does not exist or cannot be read: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open the file for reading: {$filePath}");
        }

        try {
            $lineNumber = 0;

            while (($rawLine = fgets($handle)) !== false) {
                $lineNumber++;

                $line = rtrim($rawLine, "\r\n");

                if ($line === '') {
                    continue;
                }

                try {
                    yield $this->parser->parse($line);
                } catch (InvalidArgumentException $e) {
                    
                    if ($onError !== null) {
                        $onError($lineNumber, $line, $e->getMessage());
                    }
                    continue;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
