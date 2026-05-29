<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\ImportHistory;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Maximum attempts before the job is considered failed.
     * Useful when a DB is temporarily unavailable.
     */
    public int $tries = 3;

    /**
     * Max execution time per chunk (seconds).
     * 1,000 rows × bulk insert should never exceed 30 s.
     */
    public int $timeout = 120;

    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    /**
     * @param  array<int, array<string, string>>  $rows   Pre-parsed CSV rows
     */
    public function __construct(public readonly array $rows) {}

    // ---------------------------------------------------------------
    // Handler
    // ---------------------------------------------------------------

    public function handle(): void
    {
        // If the whole batch was cancelled mid-flight, skip silently.
        if ($this->batch()->cancelled()) {
            return;
        }

        $batchId = $this->batch()->id;
        $now = now();

        // ── 1. Build the payload, filtering out rows missing required fields ──
        $insertData = [];

        foreach ($this->rows as $row) {
            $name = trim($row["name"] ?? "");
            $email = strtolower(trim($row["email"] ?? ""));

            if (
                $name === "" ||
                $email === "" ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                continue; // skip malformed rows without failing the whole job
            }

            $insertData[] = [
                "name" => $name,
                "email" => $email,
                "import_batch_id" => $batchId,
                "created_at" => $now,
                "updated_at" => $now,
            ];
        }

        if (empty($insertData)) {
            return; // nothing valid in this chunk
        }

        // ── 2. Bulk insert inside a transaction; skip duplicate emails ─────────
        $inserted = 0;

        DB::transaction(function () use ($insertData, &$inserted) {
            // insertOrIgnore returns the number of rows actually written.
            // Duplicate e-mails (unique constraint) are silently skipped.
            $inserted = Customer::insertOrIgnore($insertData);
        });

        // ── 3. Atomically increment the counter on the history record ─────────
        if ($inserted > 0) {
            ImportHistory::where("id", $batchId)->increment(
                "items_added",
                $inserted,
            );
        }
    }

    // ---------------------------------------------------------------
    // Failure hook
    // ---------------------------------------------------------------

    public function failed(Throwable $exception): void
    {
        Log::error("[ProcessImportChunk] Job failed", [
            "batch_id" => $this->batch()?->id,
            "rows" => count($this->rows),
            "exception" => $exception->getMessage(),
        ]);
    }
}
