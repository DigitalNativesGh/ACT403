<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Attempt extends Model
{
    protected $fillable = [
        'index_number', 'uin', 'question_ids', 'answers', 'score', 'total',
        'started_at', 'ends_at', 'submitted_at', 'last_seen_at', 'camera_on',
    ];

    /** Set on older attempts that proctoring ended early (no longer happens). */
    public const CLOSED_FOR_TALKING = 'talking';

    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'answers' => 'array',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'camera_on' => 'boolean',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** Still writing: not submitted and time (plus grace) not used up. */
    public function scopeInProgress(Builder $query): void
    {
        $query->whereNull('submitted_at')
            ->where('ends_at', '>', now()->subSeconds((int) config('quiz.grace_seconds')));
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->getTimestamp() >= now()->getTimestamp() - config('quiz.online_seconds');
    }

    public function secondsLeft(): int
    {
        return max(0, $this->ends_at->getTimestamp() - now()->getTimestamp());
    }

    /** True once the timer and the grace period have both run out. */
    public function isPastGrace(): bool
    {
        return now()->getTimestamp() > $this->ends_at->getTimestamp() + config('quiz.grace_seconds');
    }

    /**
     * Keep only answers to questions on this attempt, with valid letters.
     */
    public function cleanAnswers(mixed $answers): array
    {
        if (! is_array($answers)) {
            return [];
        }

        $allowed = array_map('strval', $this->question_ids ?? []);
        $clean = [];
        foreach ($answers as $questionId => $letter) {
            $letter = strtoupper(trim((string) $letter));
            if (in_array((string) $questionId, $allowed, true) && in_array($letter, Question::LETTERS, true)) {
                $clean[(string) $questionId] = $letter;
            }
        }

        return $clean;
    }

    public function proctorEvents(): HasMany
    {
        return $this->hasMany(ProctorEvent::class);
    }

    /**
     * Apply the proctoring rules to one detected violation.
     *
     * @return array{counted: bool, penalty: int}
     */
    public function recordViolation(string $type): array
    {
        $rules = config('quiz.proctoring');

        return DB::transaction(function () use ($type, $rules) {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->id);
            $recent = $fresh->proctorEvents()->where('type', $type)
                ->where('created_at', '>', now()->subSeconds($rules['cooldown_seconds']))->exists();
            if ($fresh->isSubmitted() || $recent) {
                $this->setRawAttributes($fresh->getAttributes(), true);

                return ['counted' => false, 'penalty' => 0];
            }

            if ($type === ProctorEvent::TALKING) {
                $fresh->talk_count++;
                $penalty = $rules['talk_penalty'];
            } else {
                $fresh->head_turn_count++;
                $penalty = $rules['head_turn_penalty'];
            }
            $fresh->penalty += $penalty;
            $fresh->save();
            $fresh->proctorEvents()->create(['type' => $type, 'penalty' => $penalty]);
            $this->setRawAttributes($fresh->getAttributes(), true);

            return ['counted' => true, 'penalty' => $penalty];
        });
    }

    /**
     * Grade the attempt and lock it.
     */
    public function finalize(array $answers, ?string $closedReason = null): void
    {
        $answers = $this->cleanAnswers($answers);
        $correct = Question::query()
            ->whereIn('id', $this->question_ids)
            ->pluck('correct_option', 'id');

        $score = 0;
        foreach ($correct as $questionId => $letter) {
            if (($answers[(string) $questionId] ?? null) === $letter) {
                $score++;
            }
        }

        $this->refresh(); // pick up deductions recorded since this model was loaded
        $this->forceFill([
            'answers' => $answers,
            'raw_score' => $score,
            'score' => max(0, $score - $this->penalty),
            'submitted_at' => now(),
            'closed_reason' => $closedReason,
        ])->save();
    }

    public function percentage(): float
    {
        return $this->total > 0 ? round(($this->score ?? 0) / $this->total * 100, 1) : 0.0;
    }

    public function timeTaken(): ?string
    {
        if (! $this->submitted_at) {
            return null;
        }
        $seconds = max(0, $this->submitted_at->getTimestamp() - $this->started_at->getTimestamp());

        return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
    }
}
