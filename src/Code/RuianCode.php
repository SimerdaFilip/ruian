<?php

declare(strict_types=1);

namespace Simerda\Ruian\Code;

use InvalidArgumentException;

/**
 * Shared validation for the numeric RÚIAN identifiers.
 *
 * RÚIAN codes are plain numeric keys assigned by ČÚZK. They carry no public
 * check digit, so the only thing that can be verified offline is the format:
 * a positive integer of a plausible length. Each element type embeds this
 * trait to gain `fromInt()`/`fromString()` factories without repeating the
 * guard, while staying a distinct, non-interchangeable type.
 */
trait RuianCode
{
    private function __construct(
        public readonly int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0 || $value > self::maxValue()) {
            throw new InvalidArgumentException(self::invalidMessage((string) $value));
        }

        return new self($value);
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        // Reject leading zeros, signs and anything non-numeric before casting,
        // so "007", "-5" or "12a" never slip through as a valid code.
        if (preg_match('/^[1-9][0-9]{0,' . (self::maxDigits() - 1) . '}$/', $trimmed) !== 1) {
            throw new InvalidArgumentException(self::invalidMessage($value));
        }

        return new self((int) $trimmed);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * RÚIAN keys comfortably fit into nine digits; anything longer is a typo.
     */
    private static function maxDigits(): int
    {
        return 9;
    }

    private static function maxValue(): int
    {
        return (10 ** self::maxDigits()) - 1;
    }

    private static function invalidMessage(string $value): string
    {
        $class = static::class;
        $short = substr($class, (int) strrpos($class, '\\') + 1);

        return sprintf(
            'Invalid %s "%s": expected a positive integer of at most %d digits.',
            $short,
            $value,
            self::maxDigits(),
        );
    }
}
