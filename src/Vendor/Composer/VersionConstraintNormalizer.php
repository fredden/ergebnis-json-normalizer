<?php

declare(strict_types=1);

/**
 * Copyright (c) 2018-2023 Andreas Möller
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/ergebnis/json-normalizer
 */

namespace Ergebnis\Json\Normalizer\Vendor\Composer;

use Composer\Semver;
use Ergebnis\Json\Json;
use Ergebnis\Json\Normalizer\Format;
use Ergebnis\Json\Normalizer\Normalizer;

final class VersionConstraintNormalizer implements Normalizer
{
    private const PROPERTIES_THAT_SHOULD_BE_NORMALIZED = [
        'conflict',
        'provide',
        'replace',
        'require',
        'require-dev',
    ];

    public function __construct(private Semver\VersionParser $versionParser)
    {
    }

    public function normalize(Json $json): Json
    {
        $decoded = $json->decoded();

        if (!\is_object($decoded)) {
            return $json;
        }

        $objectPropertiesThatShouldBeNormalized = \array_intersect_key(
            \get_object_vars($decoded),
            \array_flip(self::PROPERTIES_THAT_SHOULD_BE_NORMALIZED),
        );

        if ([] === $objectPropertiesThatShouldBeNormalized) {
            return $json;
        }

        foreach ($objectPropertiesThatShouldBeNormalized as $name => $value) {
            $packages = (array) $decoded->{$name};

            if ([] === $packages) {
                continue;
            }

            $decoded->{$name} = \array_map(function (string $versionConstraint): string {
                return $this->normalizeVersionConstraint($versionConstraint);
            }, $packages);
        }

        /** @var string $encoded */
        $encoded = \json_encode(
            $decoded,
            Format\JsonEncodeOptions::default()->toInt(),
        );

        return Json::fromString($encoded);
    }

    private function normalizeVersionConstraint(string $versionConstraint): string
    {
        $normalized = self::trim($versionConstraint);
        $normalized = self::removeExtraSpaces($normalized);

        try {
            $this->versionParser->parseConstraints($normalized);
        } catch (\UnexpectedValueException) {
            return $normalized;
        }

        $normalized = self::normalizeVersionConstraintSeparators($normalized);
        $normalized = self::removeDuplicateVersionConstraints($normalized);
        $normalized = self::removeOverlappingVersionConstraints($normalized);

        return self::sortVersionConstraints($normalized);
    }

    private static function trim(string $versionConstraint): string
    {
        return \trim($versionConstraint);
    }

    private static function removeExtraSpaces(string $versionConstraint): string
    {
        return \str_replace(
            '  ',
            ' ',
            $versionConstraint,
        );
    }

    private static function normalizeVersionConstraintSeparators(string $versionConstraint): string
    {
        $orConstraints = self::splitIntoOrConstraints($versionConstraint);

        return self::joinOrConstraints(...\array_map(static function (string $orConstraint): string {
            $andConstraints = self::splitIntoAndConstraints($orConstraint);

            return self::joinAndConstraints(...$andConstraints);
        }, $orConstraints));
    }

    private static function removeDuplicateVersionConstraints(string $versionConstraint): string
    {
        $orConstraints = self::splitIntoOrConstraints($versionConstraint);

        return self::joinOrConstraints(...\array_unique(\array_map(static function (string $orConstraint): string {
            $andConstraints = self::splitIntoAndConstraints($orConstraint);

            return self::joinAndConstraints(...\array_unique($andConstraints));
        }, $orConstraints)));
    }

    private static function removeOverlappingVersionConstraints(string $versionConstraint): string
    {
        $orConstraints = self::splitIntoOrConstraints($versionConstraint);

        $regex = '{^[~^]\d+(?:\.\d+)*$}';

        $count = \count($orConstraints);

        for ($i = 0; $i < $count; ++$i) {
            $a = $orConstraints[$i];

            if (!\is_string($a)) {
                continue;
            }

            if (1 !== \preg_match($regex, $a)) {
                continue;
            }

            for ($j = $i + 1; $j < $count; ++$j) {
                $b = $orConstraints[$j];

                if (!\is_string($b)) {
                    continue;
                }

                if (1 !== \preg_match($regex, $b)) {
                    continue;
                }

                if (Semver\Semver::satisfies(\ltrim($a, '^~'), $b)) {
                    $orConstraints[$i] = null;
                } elseif (Semver\Semver::satisfies(\ltrim($b, '^~'), $a)) {
                    $orConstraints[$j] = null;
                }
            }
        }

        return self::joinOrConstraints(...\array_filter($orConstraints, static function (?string $orConstraint): bool {
            return \is_string($orConstraint);
        }));
    }

    private static function sortVersionConstraints(string $versionConstraint): string
    {
        $normalize = static function (string $versionConstraint): string {
            return \trim($versionConstraint, '<>=!~^');
        };

        $sort = static function (string $a, string $b) use ($normalize): int {
            return \strnatcmp(
                $normalize($a),
                $normalize($b),
            );
        };

        $orConstraints = self::splitIntoOrConstraints($versionConstraint);

        $orConstraints = \array_map(static function (string $orConstraint) use ($sort): string {
            $andConstraints = self::splitIntoAndConstraints($orConstraint);

            \usort($andConstraints, $sort);

            return self::joinAndConstraints(...$andConstraints);
        }, $orConstraints);

        \usort($orConstraints, $sort);

        return self::joinOrConstraints(...$orConstraints);
    }

    /**
     * @see https://github.com/composer/semver/blob/3.3.2/src/VersionParser.php#L257
     *
     * @return array<int, string>
     */
    private static function splitIntoOrConstraints(string $versionConstraint): array
    {
        return \preg_split(
            '{\s*\|\|?\s*}',
            $versionConstraint,
        );
    }

    private static function joinOrConstraints(string ...$orConstraints): string
    {
        return \implode(
            ' || ',
            $orConstraints,
        );
    }

    /**
     * @see https://github.com/composer/semver/blob/3.3.2/src/VersionParser.php#L264
     *
     * @return array<int, string>
     */
    private static function splitIntoAndConstraints(string $orConstraint): array
    {
        return \preg_split(
            '{(?<!^|as|[=>< ,]) *(?<!-)[, ](?!-) *(?!,|as|$)}',
            $orConstraint,
        );
    }

    private static function joinAndConstraints(string ...$andConstraints): string
    {
        return \implode(
            ' ',
            $andConstraints,
        );
    }
}
