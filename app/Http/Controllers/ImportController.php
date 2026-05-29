<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessImportChunk;
use App\Models\ImportHistory;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    //  PAGE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Render the import UI page.
     */
    public function index()
    {
        $recentImports = ImportHistory::latest()->limit(10)->get();
        return view("import.index", compact("recentImports"));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STEP 1 — Upload
    //  POST /import/upload
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Accept the CSV, persist it to local storage, return a file handle
     * that subsequent steps will use (no DB write yet).
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            "file" => [
                "required",
                "file",
                "mimes:csv,txt",
                "max:40960", // 40 MB — matches php.ini upload_max_filesize
            ],
        ]);

        $storedPath = $request->file("file")->store("imports");
        $originalName = $request->file("file")->getClientOriginalName();
        $sizeKb = round($request->file("file")->getSize() / 1024, 1);

        return response()->json([
            "message" => "File uploaded successfully.",
            "file_path" => $storedPath,
            "file_name" => $originalName,
            "size_kb" => $sizeKb,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STEP 2 — Validate
    //  POST /import/validate
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Check that the CSV has at least the required column headers.
     * This is a "fast" structural check — no row-level validation.
     */
    public function validateFile(Request $request): JsonResponse
    {
        $request->validate(["file_path" => "required|string"]);

        $absolutePath = Storage::path($request->file_path);

        if (!file_exists($absolutePath)) {
            return response()->json(
                ["error" => "File not found on server."],
                404,
            );
        }

        $handle = fopen($absolutePath, "r");
        $rawRow = fgetcsv($handle);
        fclose($handle);

        if (!$rawRow) {
            return response()->json(
                [
                    "valid" => false,
                    "message" => "File appears to be empty.",
                ],
                422,
            );
        }

        $headers = array_map(fn($h) => strtolower(trim($h)), $rawRow);
        $required = ["name", "email"];
        $missing = array_values(array_diff($required, $headers));

        if (!empty($missing)) {
            return response()->json(
                [
                    "valid" => false,
                    "message" =>
                        "Missing required column(s): " .
                        implode(", ", $missing),
                    "headers_found" => $headers,
                ],
                422,
            );
        }

        // Quick peek: count total data rows without loading everything into memory
        $handle = fopen($absolutePath, "r");
        fgetcsv($handle); // skip header
        $rowCount = 0;
        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }
        fclose($handle);

        return response()->json([
            "valid" => true,
            "message" => "File structure is valid.",
            "headers" => $headers,
            "total_rows" => $rowCount,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STEP 3 — Execute
    //  POST /import/execute
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Stream-read the CSV in chunks → create one job per chunk →
     * dispatch the entire collection as a Bus::batch() →
     * persist the initial ImportHistory record →
     * return the batch UUID for polling.
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            "file_path" => "required|string",
            "file_name" => "required|string",
        ]);

        $absolutePath = Storage::path($request->file_path);

        if (!file_exists($absolutePath)) {
            return response()->json(
                ["error" => "File not found on server."],
                404,
            );
        }

        // ── Stream-read the CSV, write each chunk to disk (memory-safe) ─────
        //    Holding 800k rows in a PHP array before Bus::batch() serialises
        //    them would exhaust memory.  Instead we write each 1,000-row chunk
        //    as a small JSON file and pass only the path to the job.
        $handle = fopen($absolutePath, "r");
        $rawHeader = fgetcsv($handle);
        $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeader);

        $chunkSize = 1000;
        $buffer = [];
        $jobs = [];
        $chunkIndex = 0;
        $chunkDir = "imports/chunks/" . uniqid("", true);
        Storage::makeDirectory($chunkDir);

        $flushChunk = function () use (
            &$buffer,
            &$jobs,
            &$chunkIndex,
            $chunkDir,
        ) {
            if (empty($buffer)) {
                return;
            }
            $path = "{$chunkDir}/chunk_{$chunkIndex}.json";
            Storage::put($path, json_encode($buffer));
            $jobs[] = new ProcessImportChunk($path);
            $buffer = [];
            $chunkIndex++;
        };

        while (($rawRow = fgetcsv($handle)) !== false) {
            if (count($rawRow) < count($headers)) {
                continue;
            }

            $buffer[] = array_combine(
                $headers,
                array_slice($rawRow, 0, count($headers)),
            );

            if (count($buffer) >= $chunkSize) {
                $flushChunk();
            }
        }
        $flushChunk(); // flush last partial chunk
        fclose($handle);

        // Delete the original uploaded file immediately — it has been fully
        // split into chunk files and is no longer needed on disk.
        Storage::delete($request->file_path);

        if (empty($jobs)) {
            Storage::deleteDirectory($chunkDir);
            return response()->json(
                ["error" => "No valid data rows found in the file."],
                422,
            );
        }

        $fileName = $request->file_name;

        // ── Dispatch the batch ───────────────────────────────────────────────
        $batch = Bus::batch($jobs)
            ->name("Appika Import — {$fileName}")

            // All jobs finished without failures
            ->then(function (Batch $batch) {
                ImportHistory::where("id", $batch->id)->update([
                    "status" => "Finished",
                ]);
            })

            // At least one job failed
            ->catch(function (Batch $batch, Throwable $e) {
                ImportHistory::where("id", $batch->id)->update([
                    "status" => "Failed",
                ]);
            })

            // Always fires (success OR partial failure) – good for cleanup
            ->finally(function (Batch $batch) {
                // Delete the uploaded file once the import is fully settled
                // Storage::delete(…);  ← uncomment in production
            })

            // Don't cancel remaining jobs just because one chunk failed
            ->allowFailures()
            ->dispatch();

        // ── Persist the history record AFTER we have the real batch UUID ─────
        //    (safe with database queue: workers only run when `queue:work` is active)
        ImportHistory::create([
            "id" => $batch->id,
            "file_name" => $fileName,
            "status" => "Processing",
            "items_added" => 0,
        ]);

        return response()->json(
            [
                "message" => "Import queued successfully.",
                "batch_id" => $batch->id,
                "total_jobs" => count($jobs),
                "total_rows" => $chunkIndex * $chunkSize, // approximate
            ],
            202,
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STEP 4 — Poll Status
    //  GET /import/status/{batchId}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Return real-time progress for the Blade polling loop.
     */
    public function status(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json(
                ["error" => "Import batch not found."],
                404,
            );
        }

        $history = ImportHistory::find($batchId);

        return response()->json([
            "batch_id" => $batch->id,
            "name" => $batch->name,
            "status" => $history?->status ?? "Processing",
            "progress" => $batch->progress(), // 0-100 integer
            "total_jobs" => $batch->totalJobs,
            "pending_jobs" => $batch->pendingJobs,
            "failed_jobs" => $batch->failedJobs,
            "items_added" => $history?->items_added ?? 0,
            "is_finished" => $batch->finished(),
            "is_cancelled" => $batch->cancelled(),
            "has_failures" => $batch->hasFailures(),
            "created_at" => $history?->created_at?->toDateTimeString(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  BONUS — Sample CSV Download
    //  GET /import/sample
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Stream a sample CSV of arbitrary size for load testing.
     *
     * Usage:
     *   GET /import/sample            → 20 rows (default)
     *   GET /import/sample?rows=50000 → 50,000 rows
     *
     * Streamed row-by-row so even millions of rows won't exhaust PHP memory.
     */
    public function sample(Request $request)
    {
        $total = (int) $request->query("rows", 20);
        $total = max(1, min($total, 1_000_000)); // clamp: 1 – 1 M

        $firstNames = [
            "Alice",
            "Bob",
            "Carol",
            "David",
            "Eve",
            "Frank",
            "Grace",
            "Henry",
            "Iris",
            "Jack",
            "Karen",
            "Leo",
            "Mia",
            "Noah",
            "Olivia",
            "Paul",
            "Quinn",
            "Rose",
            "Sam",
            "Tina",
        ];
        $lastNames = [
            "Smith",
            "Johnson",
            "Williams",
            "Brown",
            "Jones",
            "Garcia",
            "Miller",
            "Davis",
            "Wilson",
            "Moore",
            "Taylor",
            "Anderson",
            "Thomas",
            "Jackson",
            "White",
        ];

        $filename =
            $total >= 1000
                ? "sample_" . number_format($total, 0, ".", "_") . "_rows.csv"
                : "sample_customers.csv";

        return response()->stream(
            function () use ($total, $firstNames, $lastNames) {
                $out = fopen("php://output", "w");

                $companies = [
                    "Acme Corp",
                    "TechStart",
                    "Global Inc",
                    "FastForward",
                    "NextLevel Ltd",
                    "BrightPath",
                    "CoreSystems",
                    "AlphaWave",
                    "Zenith Co",
                    "PeakLogic",
                ];
                $streets = [
                    "Main St",
                    "Oak Ave",
                    "Maple Rd",
                    "Cedar Blvd",
                    "Pine Lane",
                    "Elm St",
                    "River Rd",
                    "Lake Ave",
                    "Hill Dr",
                    "Park Way",
                ];

                fputcsv($out, ["name", "email", "phone", "company", "address"]);

                for ($i = 1; $i <= $total; $i++) {
                    $fn = $firstNames[array_rand($firstNames)];
                    $ln = $lastNames[array_rand($lastNames)];
                    fputcsv($out, [
                        "{$fn} {$ln}",
                        strtolower("{$fn}.{$ln}.{$i}@example.com"),
                        "0" . rand(800000000, 899999999),
                        $companies[array_rand($companies)],
                        rand(1, 999) . " " . $streets[array_rand($streets)],
                    ]);

                    // Flush every 5,000 rows to keep memory flat
                    if ($i % 5000 === 0) {
                        ob_flush();
                        flush();
                    }
                }

                fclose($out);
            },
            200,
            [
                "Content-Type" => "text/csv",
                "Content-Disposition" => "attachment; filename=\"{$filename}\"",
                "X-Accel-Buffering" => "no",
            ],
        );
    }
}
