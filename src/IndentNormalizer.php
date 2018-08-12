<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018 Andreas Möller.
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/localheinz/json-normalizer
 */

namespace Localheinz\Json\Normalizer;

use Localheinz\Json\Normalizer\Format\IndentInterface;
use Localheinz\Json\Printer\Printer;
use Localheinz\Json\Printer\PrinterInterface;

final class IndentNormalizer implements NormalizerInterface
{
    /**
     * @var IndentInterface
     */
    private $indent;

    /**
     * @var PrinterInterface
     */
    private $printer;

    public function __construct(IndentInterface $indent, PrinterInterface $printer = null)
    {
        $this->indent = $indent;
        $this->printer = $printer ?: new Printer();
    }

    public function normalize(string $json): string
    {
        if (null === \json_decode($json) && \JSON_ERROR_NONE !== \json_last_error()) {
            throw new \InvalidArgumentException(\sprintf(
                '"%s" is not valid JSON.',
                $json
            ));
        }

        return $this->printer->print(
            $json,
            $this->indent->__toString()
        );
    }
}
