<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HistoryModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'history_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid_history_id',

        'number_tbl_id',
        'uuid_tbl_id',

        'tbl_name',
        'column_name',
        'value',

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
