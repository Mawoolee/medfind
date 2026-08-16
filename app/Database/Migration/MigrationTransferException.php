<?php

namespace App\Database\Migration;

use RuntimeException;
use Throwable;

final class MigrationTransferException extends RuntimeException
{
    public function __construct(
        string $reason,
        public readonly ?string $table = null,
        public readonly int|string|null $primaryKey = null,
        public readonly ?string $column = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($reason, 0, $previous);
    }

    /**
     * Return actionable context without including row values or exception details.
     *
     * @return array<string, int|string|null>
     */
    public function safeContext(): array
    {
        return [
            'reason' => $this->getMessage(),
            'table' => $this->table,
            'primary_key' => $this->table === 'sessions' && $this->primaryKey !== null
                ? '[REDACTED]'
                : $this->primaryKey,
            'column' => $this->column,
        ];
    }
}
