<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class UsersUserIdModel extends Authenticatable implements JWTSubject, MustVerifyEmail

{
    use HasFactory, SoftDeletes;

    protected $table = 'users_user_id_tbl';

    protected $primaryKey = 'id';

    protected $fillable = [
        'uuid_users_user_id_id',
        'number_user_id',
        'uuid_user_id',
    ];

    protected $dates = ['deleted_at'];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getAttribute('uuid_user_id');
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'uuid_user_id';
    }
}
