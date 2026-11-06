<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? config("quiz.defaults.$key", $default);
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function quizTitle(): string
    {
        return (string) static::getValue('quiz_title');
    }

    public static function durationMinutes(): int
    {
        return max(1, (int) static::getValue('duration_minutes'));
    }

    public static function questionsPerStudent(): int
    {
        return max(0, (int) static::getValue('questions_per_student'));
    }

    public static function quizOpen(): bool
    {
        return (bool) (int) static::getValue('quiz_open');
    }

    public static function proctoring(): bool
    {
        return (bool) (int) static::getValue('proctoring');
    }

    /** Whether candidates' cameras are streamed to the live monitor. */
    public static function liveVideo(): bool
    {
        return (bool) (int) static::getValue('live_video');
    }
}
