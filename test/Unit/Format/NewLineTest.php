<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2022 Andreas Möller
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/ergebnis/json-normalizer
 */

namespace Ergebnis\Json\Normalizer\Test\Unit\Format;

use Ergebnis\Json\Json;
use Ergebnis\Json\Normalizer\Exception;
use Ergebnis\Json\Normalizer\Format;
use PHPUnit\Framework;

/**
 * @internal
 *
 * @covers \Ergebnis\Json\Normalizer\Format\NewLine
 *
 * @uses \Ergebnis\Json\Normalizer\Exception\InvalidNewLineString
 */
final class NewLineTest extends Framework\TestCase
{
    /**
     * @dataProvider provideInvalidNewLineString
     */
    public function testFromStringRejectsInvalidNewLineString(string $string): void
    {
        $this->expectException(Exception\InvalidNewLineString::class);

        Format\NewLine::fromString($string);
    }

    /**
     * @return \Generator<array<string>>
     */
    public static function provideInvalidNewLineString(): \Generator
    {
        $strings = [
            "\t",
            " \r ",
            " \r\n ",
            " \n ",
            ' ',
            "\f",
            "\x0b",
            "\x85",
        ];

        foreach ($strings as $string) {
            yield [
                $string,
            ];
        }
    }

    /**
     * @dataProvider provideValidNewLineString
     */
    public function testFromStringReturnsNewLine(string $string): void
    {
        $newLine = Format\NewLine::fromString($string);

        self::assertSame($string, $newLine->toString());
    }

    /**
     * @return \Generator<array<string>>
     */
    public static function provideValidNewLineString(): \Generator
    {
        $strings = [
            "\n",
            "\r",
            "\r\n",
        ];

        foreach ($strings as $string) {
            yield [
                $string,
            ];
        }
    }

    public function testFromJsonReturnsFormatWithDefaultNewLineIfNoneFound(): void
    {
        $encoded = '{"foo": "bar"}';

        $json = Json::fromString($encoded);

        $newLine = Format\NewLine::fromJson($json);

        self::assertSame(\PHP_EOL, $newLine->toString());
    }

    /**
     * @dataProvider provideNewLine
     */
    public function testFromFormatReturnsFormatWithNewLineSniffedFromArray(string $newLineString): void
    {
        $json = Json::fromString(
            <<<JSON
["foo",{$newLineString}"bar"]
JSON
        );

        $newLine = Format\NewLine::fromJson($json);

        self::assertSame($newLineString, $newLine->toString());
    }

    /**
     * @dataProvider provideNewLine
     */
    public function testFromFormatReturnsFormatWithNewLineNewLineSniffedFromObject(string $newLineString): void
    {
        $json = Json::fromString(
            <<<JSON
{"foo": 9000,{$newLineString}"bar": 123}
JSON
        );

        $newLine = Format\NewLine::fromJson($json);

        self::assertSame($newLineString, $newLine->toString());
    }

    /**
     * @return \Generator<array<string>>
     */
    public static function provideNewLine(): \Generator
    {
        $values = [
            "\r\n",
            "\n",
            "\r",
        ];

        foreach ($values as $newLine) {
            yield [
                $newLine,
            ];
        }
    }
}
