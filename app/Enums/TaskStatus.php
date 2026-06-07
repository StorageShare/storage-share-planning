<?php

namespace App\Enums;

enum TaskStatus: string
{
    case CONCEPT = 'concept';
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case IN_REVIEW = 'in_review';
    case REVIEW = 'review';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';
    case CLOSED = 'closed'; // When a task is reviewed and approved

    public function label(): string
    {
        return match ($this) {
            self::CONCEPT => 'Concept',
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In uitvoering',
            self::IN_REVIEW => 'In afwachting',
            self::REVIEW => 'Ter beoordeling',
            self::COMPLETED => 'Voltooid',
            self::REJECTED => 'Afgekeurd',
            self::SKIPPED => 'Overgeslagen',
            self::CLOSED => 'Gesloten',
        };
    }

    /**
     * Convenience method returning backing values only.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
