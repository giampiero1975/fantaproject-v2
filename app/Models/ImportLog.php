<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'original_file_name',
        'file_path',
        'import_type',
        'season_id',
        'status',
        'details',
        'rows_processed',
        'rows_created',
        'rows_updated',
        'rows_ceduti',
    ];
    
    protected $guarded = [];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}