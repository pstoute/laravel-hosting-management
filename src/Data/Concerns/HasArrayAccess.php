<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Data\Concerns;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

trait HasArrayAccess
{
    /**
     * Convert the DTO to an array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $this->{$name};
            $result[$name] = $this->serializeValue($value);
        }

        return $result;
    }

    /**
     * Convert the DTO to JSON
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize a value for array/JSON output
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->serializeValue($item), $value);
        }

        return $value;
    }
}
