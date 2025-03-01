<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalArrFieldsModel extends Model
{
    use HasFactory;

    public function arrToEncrypt(): array
    {
        return [
            // Register
            'email',
            'phone_number',
            'password',
            'role',

            // This is for personal update password
            'current_password',
            'password_confirmation',
        ];
    }

    public function arrToDecrypt(): array
    {
        return [
            // UsersModel
            'phone_number',
            'email',
            'password',
            'role',

            // History
            'value',
        ];
    }

    public function arrToReadableDateTime(): array
    {
        return [
            // Default all table
            'created_at',
            'updated_at',
            'deleted_at',

            // users_personal_access_token_tbl
            'last_used_at',
            'expires_at',

            // users_tbl
            'email_verified_at',
        ];
    }

    
    public function arrHeaderFields(): array
    {
        return [];
    }

}
