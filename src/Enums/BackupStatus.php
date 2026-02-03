<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Restoring = 'restoring';
    case Deleting = 'deleting';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Restoring => 'Restoring',
            self::Deleting => 'Deleting',
            self::Unknown => 'Unknown',
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Completed;
    }

    public function isRunning(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::Restoring, self::Deleting], true);
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'pending', 'queued', 'scheduled' => self::Pending,
            'in_progress', 'inprogress', 'running', 'creating', 'backing_up' => self::InProgress,
            'completed', 'complete', 'finished', 'success', 'done', 'available' => self::Completed,
            'failed', 'error', 'errored' => self::Failed,
            'restoring', 'restore_in_progress' => self::Restoring,
            'deleting', 'removing' => self::Deleting,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
