<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\RtcSignal;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Candidate side of live monitoring: heartbeat, and the WebRTC handshake
 * with any lecturer currently watching. Video goes peer-to-peer.
 */
class StreamController extends Controller
{
    /**
     * Heartbeat; returns offers from monitor tabs that want this camera.
     * With live video off, `video` is false and the page drops its streams.
     */
    public function poll(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt) {
            return response()->json(['ok' => false], 409);
        }

        $attempt->forceFill([
            'last_seen_at' => now(),
            'camera_on' => $request->boolean('camera'),
        ])->saveQuietly();

        $video = Setting::liveVideo();
        $offers = $video ? RtcSignal::take(
            RtcSignal::query()->where('attempt_id', $attempt->id)->where('type', 'offer')
        ) : [];

        return response()->json([
            'ok' => true,
            'video' => $video,
            'offers' => array_map(fn ($s) => ['viewer' => $s->viewer, 'ref' => $s->ref, 'sdp' => $s->sdp], $offers),
        ]);
    }

    public function answer(Request $request)
    {
        $attempt = $this->attempt($request);
        if (! $attempt) {
            return response()->json(['ok' => false], 409);
        }

        $data = $request->validate([
            'viewer' => ['required', 'string', 'max:40'],
            'ref' => ['required', 'string', 'max:40'],
            'sdp' => ['required', 'string', 'max:60000'],
        ]);

        if (! Setting::liveVideo()) {
            return response()->json(['ok' => true, 'video' => false]);
        }

        RtcSignal::create($data + ['attempt_id' => $attempt->id, 'type' => 'answer']);

        return response()->json(['ok' => true]);
    }

    private function attempt(Request $request): ?Attempt
    {
        $id = $request->session()->get('attempt_id');
        $attempt = $id ? Attempt::find($id) : null;

        return $attempt && ! $attempt->isSubmitted() && ! $attempt->isPastGrace() ? $attempt : null;
    }
}
