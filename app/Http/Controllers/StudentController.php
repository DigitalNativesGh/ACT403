<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /** Page 2: student enters index number and UIN. */
    public function create(Request $request)
    {
        $attempt = $this->currentAttempt($request);
        if ($attempt && ! $attempt->isSubmitted()) {
            return redirect()->route('quiz.show');
        }

        $perStudent = Setting::questionsPerStudent();
        $available = Question::count();

        return view('student.details', [
            'title' => Setting::quizTitle(),
            'open' => Setting::quizOpen(),
            'duration' => Setting::durationMinutes(),
            'questionCount' => $perStudent > 0 ? min($perStudent, $available) : $available,
            'proctoring' => Setting::proctoring(),
            'rules' => config('quiz.proctoring'),
        ]);
    }

    public function store(Request $request)
    {
        $id = ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-\.]+$/'];
        $data = $request->validate([
            'index_number' => $id,
            'uin' => $id,
        ], [
            'index_number.regex' => 'Index number may only contain letters, numbers, "/", "-" and ".".',
            'uin.regex' => 'UIN may only contain letters, numbers, "/", "-" and ".".',
        ], [
            'uin' => 'UIN',
        ]);

        $index = strtoupper(trim($data['index_number']));
        $uin = strtoupper(trim($data['uin']));

        $existing = Attempt::query()->where('index_number', $index)->first();
        if ($existing) {
            if ($existing->isSubmitted()) {
                return back()->withInput()->withErrors([
                    'index_number' => 'This index number has already submitted the quiz.',
                ]);
            }
            if ($existing->uin !== $uin) {
                return back()->withInput()->withErrors([
                    'uin' => 'This index number already started the quiz with a different UIN.',
                ]);
            }

            return $this->enter($request, $existing); // resume (e.g. after a browser crash)
        }

        if (! Setting::quizOpen()) {
            return back()->withInput()->withErrors(['index_number' => 'The quiz is not open yet. Please wait for your lecturer.']);
        }

        $questionIds = Question::query()->pluck('id')->shuffle();
        if ($questionIds->isEmpty()) {
            return back()->withInput()->withErrors(['index_number' => 'No questions have been uploaded yet.']);
        }
        $perStudent = Setting::questionsPerStudent();
        if ($perStudent > 0) {
            $questionIds = $questionIds->take($perStudent);
        }

        try {
            $attempt = Attempt::create([
                'index_number' => $index,
                'uin' => $uin,
                'question_ids' => $questionIds->values()->all(),
                'answers' => [],
                'total' => $questionIds->count(),
                'started_at' => now(),
                'ends_at' => now()->addMinutes(Setting::durationMinutes()),
            ]);
        } catch (QueryException) {
            return back()->withInput()->withErrors(['index_number' => 'This index number has already started the quiz.']);
        }

        return $this->enter($request, $attempt);
    }

    private function enter(Request $request, Attempt $attempt)
    {
        $request->session()->regenerate();
        $request->session()->put('attempt_id', $attempt->id);

        return redirect()->route('quiz.show');
    }

    private function currentAttempt(Request $request): ?Attempt
    {
        $id = $request->session()->get('attempt_id');

        return $id ? Attempt::find($id) : null;
    }
}
