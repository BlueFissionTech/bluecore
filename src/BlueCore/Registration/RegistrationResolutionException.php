<?php

namespace BlueFission\BlueCore\Registration;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

final class RegistrationResolutionException extends \RuntimeException
{
    private array $diagnostic;

    public static function forBinding(
        string $contract,
        string $phase,
        mixed $implementation,
        ?\Throwable $previous = null
    ): self {
        $implementationName = Str::is($implementation) && Val::isNotEmpty($implementation)
            ? $implementation
            : null;
        $target = Val::isNotNull($implementationName)
            ? " using '{$implementationName}'"
            : '';

        return new self(
            "Unable to resolve '{$contract}' during '{$phase}'{$target}.",
            [
                'contract' => $contract,
                'phase' => $phase,
                'implementation' => $implementationName,
                'exception_class' => Val::isNotNull($previous) ? $previous::class : null,
                'exception' => $previous?->getMessage(),
            ],
            $previous
        );
    }

    public function diagnostic(): array
    {
        return Arr::make($this->diagnostic)->toArray();
    }

    private function __construct(string $message, array $diagnostic, ?\Throwable $previous)
    {
        $this->diagnostic = $diagnostic;

        parent::__construct($message, 0, $previous);
    }
}
