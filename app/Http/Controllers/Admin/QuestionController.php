<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\QuestionCsvImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function template()
    {
        return response()->download(
            resource_path('templates/questions_template.csv'),
            'questions_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function upload(Request $request, QuestionCsvImporter $importer)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ], [
            'csv_file.extensions' => 'Please upload a .csv file (in Excel: File > Save As > CSV UTF-8).',
        ]);

        $result = $importer->parse($request->file('csv_file')->getRealPath());

        // All-or-nothing: if any row has a problem, nothing is imported.
        if ($result['errors']) {
            return back()
                ->with('import_errors', array_slice($result['errors'], 0, 50))
                ->with('import_error_count', count($result['errors']));
        }

        $replace = $request->boolean('replace_existing');
        DB::transaction(function () use ($result, $replace) {
            if ($replace) {
                Question::query()->delete();
            }
            $now = now();
            foreach (array_chunk($result['rows'], 200) as $chunk) {
                Question::insert(array_map(
                    fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now],
                    $chunk
                ));
            }
        });

        $count = count($result['rows']);

        return back()->with('status', "$count question(s) imported".($replace ? ' (previous questions replaced).' : '.'));
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return back()->with('status', 'Question deleted.');
    }

    public function destroyAll()
    {
        Question::query()->delete();

        return back()->with('status', 'All questions deleted.');
    }
}
