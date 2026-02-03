<?php

declare(strict_types=1);

namespace Pstoute\LaravelHosting\Enums;

enum DeploymentStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Unknown => 'Unknown',
        };
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }

    public function isRunning(): bool
    {
        return in_array($this, [self::Pending, self::Queued, self::Running], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }

    public static function fromString(?string $status): self
    {
        if ($status === null) {
            return self::Unknown;
        }

        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'pending', 'waiting' => self::Pending,
            'queued', 'scheduled' => self::Queued,
            'running', 'deploying', 'in_progress', 'inprogress', 'started' => self::Running,
            'succeeded', 'success', 'finished', 'completed', 'done' => self::Succeeded,
            'failed', 'error', 'errored' => self::Failed,
            'cancelled', 'canceled', 'aborted', 'stopped' => self::Cancelled,
            default => self::tryFrom($normalized) ?? self::Unknown,
        };
    }
}
