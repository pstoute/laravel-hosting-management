<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum BackupType: string
{
    case Full = 'full';
    case Database = 'database';
    case Files = 'files';
    case Incremental = 'incremental';
    case Snapshot = 'snapshot';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Backup',
            self::Database => 'Database Only',
            self::Files => 'Files Only',
            self::Incremental => 'Incremental',
            self::Snapshot => 'Snapshot',
            self::Unknown => 'Unknown',
        };
    }

    public function includesDatabase(): bool
    {
        return in_array($this, [self::Full, self::Database, self::Snapshot], true);
    }

    public function includesFiles(): bool
    {
        return in_array($this, [self::Full, self::Files, self::Snapshot, self::Incremental], true);
    }

    public static function fromString(?string $type): self
    {
        if ($type === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'full', 'complete', 'all' => self::Full,
            'database', 'db', 'sql', 'mysql', 'db_only', 'database_only' => self::Database,
            'files', 'file', 'filesystem', 'files_only' => self::Files,
            'incremental', 'differential', 'delta' => self::Incremental,
            'snapshot', 'image' => self::Snapshot,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
