<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\ProctorEvent;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Receives violations detected on the candidate's device, deducts marks,
 * and tells the page what warning to show. The exam is never closed.
 */
class ProctorController extends Controller
{
    public function report(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', [ProctorEvent::TALKING, ProctorEvent::HEAD_TURN, ProctorEvent::DETECTOR_UNAVAILABLE])],
        ]);

        $id = $request->session()->get('attempt_id');
        $attempt = $id ? Attempt::find($id) : null;
        if (! $attempt || $attempt->isSubmitted() || $attempt->isPastGrace()) {
            return response()->json(['ok' => false, 'redirect' => route('quiz.result')], 409);
        }
        if (! Setting::proctoring()) {
            return response()->json(['ok' => true, 'counted' => false]);
        }

        if ($data['type'] === ProctorEvent::DETECTOR_UNAVAILABLE) {
            $attempt->proctorEvents()->firstOrCreate(['type' => $data['type']]); // note for the lecturer

            return response()->json(['ok' => true, 'counted' => false]);
        }

        $result = $attempt->recordViolation($data['type']);

        return response()->json([
            'ok' => true,
            'counted' => $result['counted'],
            'talk_count' => $attempt->talk_count,
            'head_turn_count' => $attempt->head_turn_count,
            'penalty' => $attempt->penalty,
            'warning' => $result['counted'] ? $this->warning($data['type'], $result, $attempt) : null,
        ]);
    }

    /** The wording students see. Kept here so the rules and text never disagree. */
    private function warning(string $type, array $result, Attempt $attempt): array
    {
        $marks = fn (int $n) => $n.' mark'.($n === 1 ? '' : 's');
        $times = fn (int $n) => $n === 1 ? 'once' : $n.' times';
        [$title, $what, $count, $advice] = $type === ProctorEvent::TALKING
            ? ['Talking', 'talking', $attempt->talk_count, 'Please stay quiet until you submit.']
            : ['Head turn', 'a head turn', $attempt->head_turn_count, 'Keep your head facing your screen.'];

        return [
            'title' => $title.' detected: −'.$marks($result['penalty']),
            'body' => $marks($result['penalty']).' has been deducted from your score. We have detected '.$what.' '
                .$times($count).' ('.$marks($attempt->penalty).' deducted in total). '.$advice.' Your exam continues.',
            'severity' => 'penalty',
        ];
    }
}
