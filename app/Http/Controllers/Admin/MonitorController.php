<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\RtcSignal;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Lecturer side of live monitoring: a paged grid of candidate cameras.
 */
class MonitorController extends Controller
{
    public function show()
    {
        return view('admin.monitor', [
            'title' => Setting::quizTitle(),
            'perPage' => (int) config('quiz.monitor_per_page'),
            'iceServers' => config('quiz.ice_servers'),
            'liveVideo' => Setting::liveVideo(),
        ]);
    }

    /** Candidates currently writing, in join order so pages stay stable. */
    public function roster(Request $request)
    {
        $page = Attempt::query()
            ->inProgress()
            ->orderBy('id')
            ->paginate((int) config('quiz.monitor_per_page'), ['id', 'index_number', 'uin', 'ends_at', 'last_seen_at', 'camera_on', 'talk_count', 'head_turn_count', 'penalty']);

        return response()->json([
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'video' => Setting::liveVideo(),
            'online' => Attempt::query()->inProgress()
                ->where('last_seen_at', '>=', now()->subSeconds(config('quiz.online_seconds')))->count(),
            'candidates' => $page->getCollection()->map(fn (Attempt $a) => [
                'id' => $a->id,
                'index_number' => $a->index_number,
                'uin' => $a->uin,
                'online' => $a->isOnline(),
                'camera_on' => $a->camera_on,
                'seconds_left' => $a->secondsLeft(),
                'talk_count' => $a->talk_count,
                'head_turn_count' => $a->head_turn_count,
                'penalty' => $a->penalty,
            ])->values(),
        ]);
    }

    /** Queue WebRTC offers (one per candidate tile) from this monitor tab. */
    public function offers(Request $request)
    {
        $data = $request->validate([
            'viewer' => ['required', 'string', 'max:40'],
            'offers' => ['required', 'array', 'max:200'],
            'offers.*.attempt_id' => ['required', 'integer'],
            'offers.*.ref' => ['required', 'string', 'max:40'],
            'offers.*.sdp' => ['required', 'string', 'max:60000'],
        ]);

        RtcSignal::purgeStale();

        if (! Setting::liveVideo()) {
            return response()->json(['ok' => true, 'queued' => 0, 'video' => false]);
        }

        $offers = collect($data['offers']);
        $valid = Attempt::query()->inProgress()->whereIn('id', $offers->pluck('attempt_id'))->pluck('id')->all();
        $offers = $offers->whereIn('attempt_id', $valid);

        // A newer offer from this tab replaces any not yet picked up.
        RtcSignal::query()->where('viewer', $data['viewer'])->where('type', 'offer')
            ->whereIn('attempt_id', $offers->pluck('attempt_id'))->delete();

        $now = now();
        RtcSignal::insert($offers->map(fn ($o) => [
            'attempt_id' => $o['attempt_id'],
            'viewer' => $data['viewer'],
            'ref' => $o['ref'],
            'type' => 'offer',
            'sdp' => $o['sdp'],
            'created_at' => $now,
        ])->values()->all());

        return response()->json(['ok' => true, 'queued' => $offers->count()]);
    }

    /**
     * Turn live video on or off for everyone. Off: candidates stop sending
     * their camera (they still see themselves) and no new connections are made.
     */
    public function video(Request $request)
    {
        $on = (bool) $request->validate(['on' => ['required', 'boolean']])['on'];

        Setting::setValue('live_video', $on ? 1 : 0);
        if (! $on) {
            RtcSignal::query()->delete(); // drop handshakes still in flight
        }

        return response()->json(['ok' => true, 'video' => $on]);
    }

    /** Collect candidates' answers addressed to this monitor tab. */
    public function answers(Request $request)
    {
        $viewer = (string) $request->validate(['viewer' => ['required', 'string', 'max:40']])['viewer'];

        $answers = RtcSignal::take(
            RtcSignal::query()->where('viewer', $viewer)->where('type', 'answer')
        );

        return response()->json([
            'answers' => array_map(fn ($s) => ['attempt_id' => $s->attempt_id, 'ref' => $s->ref, 'sdp' => $s->sdp], $answers),
        ]);
    }
}
