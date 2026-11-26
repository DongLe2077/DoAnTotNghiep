<?php

namespace App\Services\Tenancy;

final class PropertyContext
{
    private ?int $propertyId = null;

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled', false);
    }

    public function id(): ?int
    {
        return $this->propertyId;
    }

    public function requireId(): int
    {
        abort_unless($this->propertyId !== null, 409, 'Hãy chọn khu trọ trước khi tiếp tục.');

        return $this->propertyId;
    }

    public function run(int $propertyId, callable $operation): mixed
    {
        $previous = $this->propertyId;
        $this->propertyId = $propertyId;
        try {
            return $operation();
        } finally {
            $this->propertyId = $previous;
        }
    }
}
