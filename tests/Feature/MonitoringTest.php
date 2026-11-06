<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\RtcSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(array $overrides = []): Attempt
    {
        static $n = 0;
        $n++;

        return Attempt::create($overrides + [
            'index_number' => 'IDX'.$n,
            'uin' => 'U'.$n,
            'question_ids' => [1],
            'answers' => [],
            'total' => 1,
            'started_at' => now(),
            'ends_at' => now()->addMinutes(30),
        ]);
    }

    public function test_admin_pages_require_login(): void
    {
        $this->get('/admin/monitor')->assertRedirect('/admin/login');
        $this->get('/admin/results')->assertRedirect('/admin/login');
        $this->get('/admin/monitor/roster')->assertRedirect('/admin/login');
    }

    public function test_admin_pages_render(): void
    {
        $this->attempt();
        $done = $this->attempt(['score' => 1, 'submitted_at' => now()]);

        $this->withSession(['is_admin' => true]);
        $this->get('/admin')->assertOk()->assertSee('Live monitor');
        $this->get('/admin/monitor')->assertOk()->assertSee('stun:stun.l.google.com', false)->assertSee('Live video on');
        $this->get('/admin/results')->assertOk()->assertSee($done->index_number)->assertSee('Download CSV');
    }

    public function test_roster_pages_candidates_who_are_writing(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->attempt(['last_seen_at' => now(), 'camera_on' => true]);
        }
        $this->attempt(['submitted_at' => now(), 'score' => 0]);           // finished: hidden
        $this->attempt(['ends_at' => now()->subMinutes(10)]);              // time up: hidden

        $this->withSession(['is_admin' => true]);
        $this->getJson('/admin/monitor/roster?page=1')
            ->assertJson(['page' => 1, 'last_page' => 3, 'total' => 120, 'online' => 120])
            ->assertJsonCount(50, 'candidates');
        $this->getJson('/admin/monitor/roster?page=3')->assertJsonCount(20, 'candidates');
    }

    public function test_offer_and_answer_round_trip(): void
    {
        $attempt = $this->attempt();

        // Lecturer's monitor tab sends an offer.
        $this->withSession(['is_admin' => true])
            ->postJson('/admin/monitor/offers', [
                'viewer' => 'tab1',
                'offers' => [['attempt_id' => $attempt->id, 'ref' => 'r1', 'sdp' => "v=0\r\noffer\r\n"]],
            ])->assertJson(['queued' => 1]);

        // Candidate's heartbeat picks it up exactly once and marks them online.
        $this->withSession(['attempt_id' => $attempt->id])
            ->postJson('/quiz/stream/poll', ['camera' => true])
            ->assertJson(['offers' => [['viewer' => 'tab1', 'ref' => 'r1', 'sdp' => "v=0\r\noffer\r\n"]]]); // CRLF kept
        $this->postJson('/quiz/stream/poll', ['camera' => true])->assertJson(['offers' => []]);
        $this->assertTrue($attempt->fresh()->isOnline());
        $this->assertTrue($attempt->fresh()->camera_on);

        // Candidate answers; only that monitor tab receives it.
        $this->postJson('/quiz/stream/answer', ['viewer' => 'tab1', 'ref' => 'r1', 'sdp' => "v=0\r\nanswer\r\n"])->assertOk();
        $this->withSession(['is_admin' => true])
            ->postJson('/admin/monitor/answers', ['viewer' => 'other'])->assertJson(['answers' => []]);
        $this->postJson('/admin/monitor/answers', ['viewer' => 'tab1'])
            ->assertJson(['answers' => [['attempt_id' => $attempt->id, 'ref' => 'r1', 'sdp' => "v=0\r\nanswer\r\n"]]]);
        $this->assertSame(0, RtcSignal::count());
    }

    public function test_finished_candidates_get_no_offers(): void
    {
        $attempt = $this->attempt(['submitted_at' => now(), 'score' => 1]);

        $this->withSession(['is_admin' => true])
            ->postJson('/admin/monitor/offers', [
                'viewer' => 'tab1',
                'offers' => [['attempt_id' => $attempt->id, 'ref' => 'r1', 'sdp' => 'x']],
            ])->assertJson(['queued' => 0]);

        $this->withSession(['attempt_id' => $attempt->id])
            ->postJson('/quiz/stream/poll')->assertStatus(409);
    }

    public function test_lecturer_can_turn_live_video_off_and_on(): void
    {
        $attempt = $this->attempt();
        $offer = ['viewer' => 'tab1', 'offers' => [['attempt_id' => $attempt->id, 'ref' => 'r1', 'sdp' => 'x']]];

        $this->postJson('/admin/monitor/video', ['on' => false])->assertRedirect('/admin/login');

        // An offer is in flight when the lecturer turns video off: it is dropped.
        $this->withSession(['is_admin' => true])->postJson('/admin/monitor/offers', $offer)->assertJson(['queued' => 1]);
        $this->postJson('/admin/monitor/video', ['on' => false])->assertJson(['video' => false]);
        $this->assertSame(0, RtcSignal::count());
        $this->getJson('/admin/monitor/roster')->assertJson(['video' => false]);
        $this->postJson('/admin/monitor/offers', $offer)->assertJson(['queued' => 0]);

        // Candidate keeps their heartbeat but is told to stop streaming.
        $this->withSession(['attempt_id' => $attempt->id])
            ->postJson('/quiz/stream/poll', ['camera' => true])
            ->assertJson(['video' => false, 'offers' => []]);
        $this->assertTrue($attempt->fresh()->camera_on);
        $this->postJson('/quiz/stream/answer', ['viewer' => 'tab1', 'ref' => 'r1', 'sdp' => 'y'])->assertOk();
        $this->assertSame(0, RtcSignal::count());

        // Back on: offers flow again.
        $this->withSession(['is_admin' => true])->postJson('/admin/monitor/video', ['on' => true])->assertJson(['video' => true]);
        $this->postJson('/admin/monitor/offers', $offer)->assertJson(['queued' => 1]);
        $this->withSession(['attempt_id' => $attempt->id])
            ->postJson('/quiz/stream/poll', ['camera' => true])
            ->assertJson(['video' => true, 'offers' => [['viewer' => 'tab1', 'ref' => 'r1']]]);
    }
}
