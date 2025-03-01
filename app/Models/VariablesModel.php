<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VariablesModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'variables_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'deleted_at',
        'created_at',
        'updated_at',
    ];
    protected $dates = ['deleted_at'];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }
}
