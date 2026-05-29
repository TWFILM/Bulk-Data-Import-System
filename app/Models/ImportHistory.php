<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportHistory extends Model
{
    /**
     * The primary key is the Laravel Job Batch UUID (string).
     */
    protected $primaryKey = "id";
    protected $keyType = "string";
    public $incrementing = false;

    protected $fillable = ["id", "file_name", "status", "items_added"];

    protected $casts = [
        "items_added" => "integer",
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, "import_batch_id");
    }
}
