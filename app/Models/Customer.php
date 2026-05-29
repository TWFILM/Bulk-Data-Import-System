<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = ["name", "email", "import_batch_id"];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function importHistory(): BelongsTo
    {
        return $this->belongsTo(ImportHistory::class, "import_batch_id");
    }
}
