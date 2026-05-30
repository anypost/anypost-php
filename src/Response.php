<?php

declare(strict_types=1);

namespace Anypost;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * An immutable view over a decoded JSON response object.
 *
 * Read fields with property or array syntax — both return the same value, and
 * nested objects come back as {@see Response} instances:
 *
 *     $email = $client->email->send([...]);
 *     $email->id;          // "email_..."
 *     $email['created_at'];
 *
 * Lists of objects (a paginated `data` array, `to`, …) come back as plain PHP
 * arrays whose object elements are themselves {@see Response} instances. Call
 * {@see toArray()} for the raw decoded structure with no wrapping.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class Response implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private readonly array $attributes)
    {
    }

    /**
     * Wrap a decoded JSON value, turning object-shaped arrays into responses.
     */
    public static function wrap(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($value === [] || array_is_list($value)) {
                return array_map([self::class, 'wrap'], $value);
            }

            /** @var array<string, mixed> $value */
            return new self($value);
        }

        return $value;
    }

    public function __get(string $name): mixed
    {
        return self::wrap($this->attributes[$name] ?? null);
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return self::wrap($this->attributes[$offset] ?? null);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Anypost\\Response is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Anypost\\Response is immutable.');
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->attributes as $key => $value) {
            yield $key => self::wrap($value);
        }
    }

    /**
     * The raw decoded response, with no {@see Response} wrapping at any depth.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
