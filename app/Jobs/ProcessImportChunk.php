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
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessImportChunk implements ShouldQueue
{
    use Batchable, Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Maximum attempts before the job is considered failed.
     */
    public int $tries = 3;

    /**
     * Max execution time per chunk (seconds).
     */
    public int $timeout = 120;

    // ---------------------------------------------------------------
    // Constructor — receives a storage path to a JSON chunk file
    // ---------------------------------------------------------------

    /**
     * @param  string  $chunkPath  Storage-relative path to the JSON chunk file
     */
    public function __construct(public readonly string $chunkPath) {}

    // ---------------------------------------------------------------
    // Handler
    // ---------------------------------------------------------------

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            Storage::delete($this->chunkPath);
            return;
        }

        // ── 1. Read rows from disk (not from serialised job payload) ──────────
        $json = Storage::get($this->chunkPath);

        if (!$json) {
            Log::warning("[ProcessImportChunk] Chunk file missing, skipping.", [
                "path" => $this->chunkPath,
            ]);
            return;
        }

        $rows = json_decode($json, true);
        $batchId = $this->batch()->id;
        $now = now();

        // ── 2. Build insert payload, filtering malformed rows ─────────────────
        $insertData = [];

        foreach ($rows as $row) {
            $name = trim($row["name"] ?? "");
            $email = strtolower(trim($row["email"] ?? ""));

            if (
                $name === "" ||
                $email === "" ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                continue;
            }

            $insertData[] = [
                "name" => $name,
                "email" => $email,
                "phone" => trim($row["phone"] ?? "") ?: null,
                "company" => trim($row["company"] ?? "") ?: null,
                "address" => trim($row["address"] ?? "") ?: null,
                "import_batch_id" => $batchId,
                "created_at" => $now,
                "updated_at" => $now,
            ];
        }

        // ── 3. Bulk insert, skip duplicate emails ─────────────────────────────
        $inserted = 0;

        if (!empty($insertData)) {
            DB::transaction(function () use ($insertData, &$inserted) {
                $inserted = Customer::insertOrIgnore($insertData);
            });

            if ($inserted > 0) {
                ImportHistory::where("id", $batchId)->increment(
                    "items_added",
                    $inserted,
                );
            }
        }

        // ── 4. Clean up chunk file from disk ──────────────────────────────────
        Storage::delete($this->chunkPath);
    }

    // ---------------------------------------------------------------
    // Failure hook
    // ---------------------------------------------------------------

    public function failed(Throwable $exception): void
    {
        Log::error("[ProcessImportChunk] Job failed", [
            "batch_id" => $this->batch()?->id,
            "chunk" => $this->chunkPath,
            "exception" => $exception->getMessage(),
        ]);

        // Leave the chunk file on disk so it can be inspected / retried
    }
}
