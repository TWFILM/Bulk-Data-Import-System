<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

// ── Landing page ─────────────────────────────────────────────────────────────
Route::get("/", [ImportController::class, "index"])->name("import.index");

// ── Import API surface (called via AJAX from Blade) ───────────────────────────
Route::prefix("import")
    ->name("import.")
    ->group(function () {
        // Step 1 — Upload the CSV to local storage
        Route::post("/upload", [ImportController::class, "upload"])->name(
            "upload",
        );

        // Step 2 — Validate CSV headers (mock structural check)
        Route::post("/validate", [
            ImportController::class,
            "validateFile",
        ])->name("validate");

        // Step 3 — Chunk + dispatch Bus::batch(), return batch UUID
        Route::post("/execute", [ImportController::class, "execute"])->name(
            "execute",
        );

        // Step 4 — Poll progress (called every 2 s by JavaScript)
        Route::get("/status/{batchId}", [
            ImportController::class,
            "status",
        ])->name("status");

        // Bonus — Download a ready-made sample CSV for quick testing
        Route::get("/sample", [ImportController::class, "sample"])->name(
            "sample",
        );
    });
