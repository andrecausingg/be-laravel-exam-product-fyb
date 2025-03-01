<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsersPersonalAccessTokenModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'users_personal_access_token_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid_users_personal_access_token_id',
        'number_user_id',
        'uuid_user_id',
        'tokenable_type',
        'name',
        'token',
        'abilities',
        'status',
        'last_used_at',
        'expires_at',
        'deleted_at',
        'created_at',
        'updated_at',
    ];
    protected $dates = ['last_used_at', 'expires_at', 'deleted_at'];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }
}
