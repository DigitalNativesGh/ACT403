<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /** Page 3: the questions, with webcam and timer. */
    public function show(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt) {
            return redirect()->route('student.create');
        }
        if ($attempt->isSubmitted()) {
            return redirect()->route('quiz.result');
        }
        if ($attempt->isPastGrace()) {
            // Time ran out while away: grade whatever was saved.
            $attempt->finalize($attempt->answers ?? []);

            return redirect()->route('quiz.result');
        }

        $questions = Question::query()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $ordered = collect($attempt->question_ids)
            ->map(fn ($id) => $questions->get($id))
            ->filter()
            ->values();

        return view('quiz.show', [
            'title' => Setting::quizTitle(),
            'attempt' => $attempt,
            'questions' => $ordered,
            'saved' => $attempt->answers ?? [],
            'secondsLeft' => $attempt->secondsLeft(),
            'iceServers' => config('quiz.ice_servers'),
            'proctoring' => Setting::proctoring(),
            'rules' => config('quiz.proctoring'),
        ]);
    }

    /** Autosave (AJAX) so answers survive a refresh or crash. */
    public function save(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt || $attempt->isSubmitted() || $attempt->isPastGrace()) {
            return response()->json(['ok' => false], 409);
        }

        $attempt->forceFill(['answers' => $attempt->cleanAnswers($request->input('answers', []))])->save();

        return response()->json(['ok' => true, 'secondsLeft' => $attempt->secondsLeft()]);
    }

    public function submit(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt) {
            return redirect()->route('student.create');
        }
        if (! $attempt->isSubmitted()) {
            // After the grace period, only answers autosaved in time count.
            $answers = $attempt->isPastGrace()
                ? ($attempt->answers ?? [])
                : $request->input('answers', []);
            $attempt->finalize($answers);
        }

        return redirect()->route('quiz.result');
    }

    /** Page 4: congratulations + score. */
    public function result(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt) {
            return redirect()->route('student.create');
        }
        if (! $attempt->isSubmitted()) {
            return redirect()->route('quiz.show');
        }

        return view('quiz.result', [
            'title' => Setting::quizTitle(),
            'attempt' => $attempt,
        ]);
    }

    public function finish(Request $request)
    {
        $request->session()->forget('attempt_id');
        $request->session()->regenerate();

        return redirect()->route('student.create');
    }

    private function attempt(Request $request): ?Attempt
    {
        $id = $request->session()->get('attempt_id');

        return $id ? Attempt::find($id) : null;
    }
}
