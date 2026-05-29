<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Usage:
 *   php artisan import:generate-sample              # 100 rows  (default)
 *   php artisan import:generate-sample --rows=10000 # 10 k rows
 *   php artisan import:generate-sample --rows=50000 # stress test
 */
class GenerateSampleCsv extends Command
{
    protected $signature = 'import:generate-sample
                              {--rows=100 : Number of customer rows to generate}
                              {--out= : Output file path (default: storage/app/sample_customers.csv)}';

    protected $description = "Generate a sample customers CSV file for testing bulk import";

    // ── Name pools ───────────────────────────────────────────────────
    private array $firstNames = [
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
        "Liam",
        "Mia",
        "Noah",
        "Olivia",
        "Paul",
        "Quinn",
        "Rose",
        "Sam",
        "Tina",
        "Uma",
        "Victor",
        "Wendy",
        "Xander",
        "Yara",
        "Zoe",
    ];

    private array $lastNames = [
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
        "Harris",
        "Martin",
        "Thompson",
        "Robinson",
        "Clark",
    ];

    // ────────────────────────────────────────────────────────────────
    public function handle(): int
    {
        $rows = max(1, (int) $this->option("rows"));
        $outPath =
            $this->option("out") ?? storage_path("app/sample_customers.csv");

        $this->info(
            "Generating <comment>{$rows}</comment> rows → <comment>{$outPath}</comment>",
        );

        $handle = fopen($outPath, "w");

        if ($handle === false) {
            $this->error("Cannot open file for writing: {$outPath}");
            return self::FAILURE;
        }

        // ── Header ──────────────────────────────────────────────────
        fputcsv($handle, ["name", "email"]);

        // ── Progress bar ────────────────────────────────────────────
        $bar = $this->output->createProgressBar($rows);
        $bar->setFormat(
            " %current%/%max% [%bar%] %percent:3s%% — %elapsed:6s% elapsed",
        );
        $bar->start();

        for ($i = 1; $i <= $rows; $i++) {
            $fn = $this->firstNames[array_rand($this->firstNames)];
            $ln = $this->lastNames[array_rand($this->lastNames)];
            $email = strtolower("{$fn}.{$ln}.{$i}@example.com");

            fputcsv($handle, ["{$fn} {$ln}", $email]);
            $bar->advance();
        }

        $bar->finish();
        fclose($handle);

        $kb = round(filesize($outPath) / 1024, 1);

        $this->newLine(2);
        $this->info("✓ Done!  File size: <comment>{$kb} KB</comment>");
        $this->line("  Path: <comment>{$outPath}</comment>");
        $this->line(
            "  Upload this file at: <comment>http://localhost:8000</comment>",
        );

        return self::SUCCESS;
    }
}
