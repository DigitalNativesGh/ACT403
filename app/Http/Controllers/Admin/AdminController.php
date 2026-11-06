<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->get('is_admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login', ['configured' => config('quiz.admin_password') !== '']);
    }

    public function login(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);
        $expected = (string) config('quiz.admin_password');

        if ($expected === '' || ! hash_equals($expected, (string) $request->input('password'))) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('is_admin');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }

    /** Page 1: lecturer dashboard (upload, settings, results). */
    public function dashboard()
    {
        return view('admin.dashboard', [
            'settings' => [
                'quiz_title' => Setting::quizTitle(),
                'duration_minutes' => Setting::durationMinutes(),
                'questions_per_student' => Setting::questionsPerStudent(),
                'quiz_open' => Setting::quizOpen(),
                'proctoring' => Setting::proctoring(),
            ],
            'questions' => Question::query()->orderBy('id')->get(),
            'stats' => $this->stats(Attempt::query()->get()),
        ]);
    }

    /** All candidates and their scores, with CSV download. */
    public function results()
    {
        $attempts = Attempt::query()->orderBy('index_number')->get();

        return view('admin.results', [
            'title' => Setting::quizTitle(),
            'attempts' => $attempts,
            'stats' => $this->stats($attempts),
        ]);
    }

    private function stats($attempts): array
    {
        $submitted = $attempts->whereNotNull('submitted_at');

        return [
            'candidates' => $attempts->count(),
            'submitted' => $submitted->count(),
            'in_progress' => $attempts->count() - $submitted->count(),
            'average' => $submitted->count() ? round($submitted->avg(fn ($a) => $a->percentage()), 1) : null,
            'highest' => $submitted->count() ? $submitted->max(fn ($a) => $a->percentage()) : null,
        ];
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'quiz_title' => ['required', 'string', 'max:120'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'questions_per_student' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value);
        }
        Setting::setValue('quiz_open', $request->boolean('quiz_open') ? 1 : 0);
        Setting::setValue('proctoring', $request->boolean('proctoring') ? 1 : 0);

        return back()->with('status', 'Settings saved.');
    }

    public function export()
    {
        $filename = 'quiz-results-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly
            fputcsv($out, ['Index Number', 'UIN', 'Raw Score', 'Deducted', 'Net Score', 'Total', 'Net Percentage', 'Talking Detected', 'Head Turns Detected', 'Closed By Proctor', 'Started', 'Submitted', 'Time Taken'], ',', '"', '');
            Attempt::query()->orderBy('index_number')->each(function (Attempt $a) use ($out) {
                fputcsv($out, [
                    $a->index_number,
                    $a->uin,
                    $a->raw_score ?? '',
                    $a->penalty,
                    $a->score ?? '',
                    $a->total,
                    $a->isSubmitted() ? $a->percentage() : '',
                    $a->talk_count,
                    $a->head_turn_count,
                    $a->closed_reason === Attempt::CLOSED_FOR_TALKING ? 'Yes - talking' : '',
                    $a->started_at?->format('Y-m-d H:i:s'),
                    $a->submitted_at?->format('Y-m-d H:i:s') ?? 'Not submitted',
                    $a->timeTaken() ?? '',
                ], ',', '"', '');
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Remove one attempt so the student can retake (e.g. after a technical failure). */
    public function destroyAttempt(Attempt $attempt)
    {
        $attempt->delete();

        return back()->with('status', "Attempt for {$attempt->index_number} removed. They can now retake the quiz.");
    }

    public function destroyAllAttempts()
    {
        Attempt::query()->delete();

        return back()->with('status', 'All results cleared.');
    }
}
