<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProctoringTest extends TestCase
{
    use RefreshDatabase;

    private Attempt $attempt;

    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->questions = collect(range(1, 5))->map(fn ($i) => Question::create([
            'question' => "Q$i", 'option_a' => 'Yes', 'option_b' => 'No', 'correct_option' => 'A',
        ])->id)->all();

        $this->attempt = Attempt::create([
            'index_number' => 'IDX1', 'uin' => 'U1',
            'question_ids' => $this->questions, 'answers' => [], 'total' => 5,
            'started_at' => now(), 'ends_at' => now()->addMinutes(30),
        ]);
        $this->withSession(['attempt_id' => $this->attempt->id]);
    }

    private function report(string $type, array $answers = [])
    {
        $response = $this->postJson('/quiz/proctor', ['type' => $type, 'answers' => $answers]);
        Carbon::setTestNow(now()->addSeconds(10)); // past the cooldown

        return $response;
    }

    public function test_talking_deducts_a_mark_and_says_so(): void
    {
        $this->report('talking')
            ->assertJson(['counted' => true, 'talk_count' => 1, 'penalty' => 1])
            ->assertJsonPath('warning.title', 'Talking detected: −1 mark')
            ->assertJsonPath('warning.body', '1 mark has been deducted from your score. We have detected talking once (1 mark deducted in total). Please stay quiet until you submit. Your exam continues.');

        $this->report('talking')
            ->assertJson(['talk_count' => 2, 'penalty' => 2])
            ->assertJsonPath('warning.body', '1 mark has been deducted from your score. We have detected talking 2 times (2 marks deducted in total). Please stay quiet until you submit. Your exam continues.');
    }

    public function test_talking_never_closes_the_exam(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->report('talking')->assertJson(['counted' => true, 'talk_count' => $i, 'penalty' => $i])
                ->assertJsonMissingPath('closed');
        }

        $a = $this->attempt->fresh();
        $this->assertFalse($a->isSubmitted());
        $this->get('/quiz')->assertOk();
    }

    public function test_every_head_turn_costs_a_mark(): void
    {
        $this->report('head_turn')
            ->assertJson(['counted' => true, 'head_turn_count' => 1, 'penalty' => 1])
            ->assertJsonPath('warning.title', 'Head turn detected: −1 mark')
            ->assertJsonPath('warning.severity', 'penalty');

        $this->report('head_turn')->assertJson(['head_turn_count' => 2, 'penalty' => 2]);
        $this->report('talking')->assertJson(['head_turn_count' => 2, 'talk_count' => 1, 'penalty' => 3]);
        $this->assertFalse($this->attempt->fresh()->isSubmitted());
    }

    public function test_repeat_within_cooldown_counts_once(): void
    {
        $this->postJson('/quiz/proctor', ['type' => 'talking'])->assertJson(['counted' => true]);
        $this->postJson('/quiz/proctor', ['type' => 'talking'])->assertJson(['counted' => false, 'talk_count' => 1, 'warning' => null]);
    }

    public function test_deductions_apply_to_normal_submission(): void
    {
        $this->report('talking');
        $this->report('head_turn');
        $this->report('head_turn');

        $answers = array_fill_keys($this->questions, 'A'); // all 5 correct
        $this->post('/quiz/submit', ['answers' => $answers])->assertRedirect('/result');

        $a = $this->attempt->fresh();
        $this->assertSame([5, 3, 2], [$a->raw_score, $a->penalty, $a->score]);
        $this->get('/result')->assertSee('Congratulations')->assertSee('Talking detected 1× (−1)')->assertSee('Head turned away 2× (−2)');
    }

    public function test_nothing_counts_when_proctoring_is_off(): void
    {
        Setting::setValue('proctoring', 0);

        $this->report('talking')->assertJson(['counted' => false]);
        $this->assertSame(0, $this->attempt->fresh()->penalty);
    }

    public function test_results_and_csv_show_deductions(): void
    {
        $this->report('talking');
        $this->post('/quiz/submit', ['answers' => array_fill_keys($this->questions, 'A')]);

        $this->withSession(['is_admin' => true]);
        $this->get('/admin/results')->assertSee('Raw score')->assertSee('Net score')->assertSee('5 / 5')->assertSee('−1')->assertSee('4 / 5');
        $csv = $this->get('/admin/results/export')->streamedContent();
        $this->assertStringContainsString('"Raw Score",Deducted,"Net Score"', $csv);
        $this->assertStringContainsString('IDX1,U1,5,1,4,5,80,1,0,', $csv);
    }
}
