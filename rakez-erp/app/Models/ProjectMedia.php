<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMedia extends Model
{
    use HasFactory;

    protected $table = 'project_media';

    protected $fillable = [
        'contract_id',
        'type',
        'url',
        'department',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
