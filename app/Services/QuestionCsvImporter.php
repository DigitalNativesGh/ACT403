<?php

namespace App\Services;

/**
 * Parses and validates a question CSV. Framework-free so it can be tested alone.
 *
 * Expected columns (header row, any order, case-insensitive):
 *   question, option_a, option_b, option_c, option_d, option_e, correct_answer
 * option_c..option_e may be left blank. correct_answer is a letter (A-E);
 * "Option B", "b" or the exact text of the correct option are also accepted.
 */
class QuestionCsvImporter
{
    public const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    private const ALIASES = [
        'question' => 'question', 'questions' => 'question', 'question_text' => 'question',
        'option_a' => 'option_a', 'a' => 'option_a', 'optiona' => 'option_a',
        'option_b' => 'option_b', 'b' => 'option_b', 'optionb' => 'option_b',
        'option_c' => 'option_c', 'c' => 'option_c', 'optionc' => 'option_c',
        'option_d' => 'option_d', 'd' => 'option_d', 'optiond' => 'option_d',
        'option_e' => 'option_e', 'e' => 'option_e', 'optione' => 'option_e',
        'correct_answer' => 'correct_answer', 'answer' => 'correct_answer',
        'correct' => 'correct_answer', 'correct_option' => 'correct_answer', 'key' => 'correct_answer',
    ];

    /**
     * @return array{rows: array<int, array<string, string|null>>, errors: array<int, string>}
     */
    public function parse(string $path): array
    {
        $rows = [];
        $errors = [];

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ['rows' => [], 'errors' => ['The file could not be opened.']];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return ['rows' => [], 'errors' => ['The file is empty.']];
        }
        $firstLine = $this->toUtf8($this->stripBom($firstLine));
        // Excel in some regions saves "CSV" with semicolons.
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv(trim($firstLine), $delimiter, '"', '');
        $map = []; // column index => field name
        foreach ($header as $i => $name) {
            $key = strtolower(preg_replace('/[\s\-]+/', '_', trim((string) $name)));
            if (isset(self::ALIASES[$key])) {
                $map[$i] = self::ALIASES[$key];
            }
        }

        $missing = array_diff(['question', 'option_a', 'option_b', 'correct_answer'], array_values($map));
        if ($missing) {
            fclose($handle);

            return ['rows' => [], 'errors' => [
                'Header row is missing column(s): '.implode(', ', $missing)
                .'. Download the template and keep its first row unchanged.',
            ]];
        }

        $line = 1;
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map(fn ($c) => trim((string) $c), $cells)) === '') {
                continue; // blank line
            }

            $record = array_fill_keys(['question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer'], '');
            foreach ($map as $i => $field) {
                $record[$field] = trim($this->toUtf8((string) ($cells[$i] ?? '')));
            }

            [$row, $rowErrors] = $this->validateRecord($record);
            foreach ($rowErrors as $message) {
                $errors[] = "Row $line: $message";
            }
            if (! $rowErrors) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        if (! $rows && ! $errors) {
            $errors[] = 'No questions were found below the header row.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array{0: array<string, string|null>, 1: array<int, string>}
     */
    private function validateRecord(array $r): array
    {
        $errors = [];

        if ($r['question'] === '') {
            $errors[] = 'the question text is empty.';
        }

        $options = [];
        $seenGap = false;
        foreach (self::LETTERS as $letter) {
            $value = $r['option_'.strtolower($letter)];
            if ($value === '') {
                $seenGap = true;
            } else {
                if ($seenGap) {
                    $errors[] = "option $letter is filled but an earlier option is blank. Fill options in order (A, B, C...).";
                    break;
                }
                $options[$letter] = $value;
            }
        }
        if (count($options) < 2) {
            $errors[] = 'at least options A and B are required.';
        }

        $correct = $this->resolveCorrect($r['correct_answer'], $options);
        if ($r['correct_answer'] === '') {
            $errors[] = 'correct_answer is empty. Put the letter of the right option (e.g. B).';
        } elseif ($correct === null) {
            $errors[] = "correct_answer \"{$r['correct_answer']}\" does not match a filled option (use A-".array_key_last($options ?: ['A' => 1]).').';
        }

        return [[
            'question' => $r['question'],
            'option_a' => $options['A'] ?? '',
            'option_b' => $options['B'] ?? '',
            'option_c' => $options['C'] ?? null,
            'option_d' => $options['D'] ?? null,
            'option_e' => $options['E'] ?? null,
            'correct_option' => $correct,
        ], $errors];
    }

    private function resolveCorrect(string $value, array $options): ?string
    {
        $v = strtoupper(trim($value));
        $v = preg_replace('/^(OPTION|OPT)[\s_\.\-]*/', '', $v);
        $v = rtrim($v, '.)');
        if (in_array($v, self::LETTERS, true)) {
            return isset($options[$v]) ? $v : null;
        }
        // Allow the full text of the correct option.
        foreach ($options as $letter => $text) {
            if (strcasecmp(trim($text), trim($value)) === 0) {
                return $letter;
            }
        }

        return null;
    }

    private function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    private function toUtf8(string $s): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }

        return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
}
