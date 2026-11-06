<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    public const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    protected $fillable = [
        'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_option',
    ];

    /**
     * Non-empty options keyed by letter, e.g. ['A' => '...', 'B' => '...'].
     */
    public function options(): array
    {
        $options = [];
        foreach (self::LETTERS as $letter) {
            $value = $this->{'option_'.strtolower($letter)};
            if ($value !== null && trim($value) !== '') {
                $options[$letter] = $value;
            }
        }

        return $options;
    }
}
