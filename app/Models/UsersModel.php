<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsersModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'users_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid_user_id',
        'phone_number',
        'email',
        'password',
        'role',
        'status',
        'verification_number',
        'verification_key',

        'phone_verified_at',
        'email_verified_at',
        'update_password_at',

        'deleted_at',
        'created_at',
        'updated_at',
    ];
    protected $dates = ['deleted_at'];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }

    public function arrFieldsToUnsetNotNeeded(): array
    {
        return [
            "password",
            "status",
            "uuid_user_id",
        ];
    }

    public function arrFieldsToUnsetNotNeededUserLogs(): array
    {
        return [
            "verification_number",
        ];
    }

    public function arrFieldsToDecrypt(): array
    {
        return [
            'phone_number',
            'email',
            'password',
            'role',
        ];
    }

    public function arrFieldsToForceInt(): array
    {
        return [];
    }


    public function arrFieldsToForceFloat(): array
    {
        return [];
    }

    public function arrToConvertToReadableDateTime(): array
    {
        return [
            'phone_verified_at',
            'email_verified_at',
            'update_password_at',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    public function arrFieldsToAddValidate(): array
    {
        return ['email', 'password'];
    }

    public function arrFieldsToAddValidateLogin(): array
    {
        return ['email', 'password'];
    }


    public function arrFieldsToEncrypt(): array
    {
        return [
            'phone_number',
            'email',
            'role',
        ];
    }

    public function arrToStores(): array
    {
        return [
            'uuid_user_id',
            'phone_number',
            'email',
            'password',
            'role',
            'status',
            'verification_number',
            'phone_verified_at',
            'email_verified_at',
        ];
    }

    public function arrFieldsToHash(): array
    {
        return [
            'password',
        ];
    }

    public function arrEnvRolesValidationRegister(): array
    {
        return [
            env('ROLE_SUPER_ADMIN'),
            env('ROLE_ADMIN'),
        ];
    }

    public function logoutAllowedRole(): array
    {
        return [
            env('ROLE_SUPER_ADMIN'),
            env('ROLE_ADMIN'),
        ];
    }

    public function arrKeyFieldsToFilterLogsVerifyEmail(): array
    {
        return ['verification_number'];
    }


    public function emailRegisterLogs(): array
    {
        return [
            'function_name' => 'emailRegister',
            'indicator_catch_error' => 'tryCatchOnRegisterEmail',
            'log_users_tbl' => [
                'start' => 'start-register-email',
                'end' => 'end-register-email',
                'user_display' => 'register-email',
            ],
            'log_history_tbl' => [
                'start' => 'start-register-email-history-tbl',
                'end' => 'end-register-email-history-tbl',
            ],
            'log_users_user_id_tbl' => [
                'start' => 'start-register-email-users-user-id-tbl',
                'end' => 'end-register-email-users-user-id-tbl',
            ],
            'log_users_personal_access_token_tbl' => [
                'start' => 'start-register-email-users-personal-access-token-tbl',
                'end' => 'end-register-email-users-personal-access-token-tbl',
            ],
            'log_exist_email_not_verified_users_tbl' => [
                'start' => 'start-update-verification-number-existing-email-not-verified',
                'end' => 'end-update-verification-number-existing-email-not-verified',
                'user_display' => 'register-email-to-verified',
            ],
            'log_exist_email_not_verified_user_personal_access_token_tbl' => [
                'start' => 'start-update-inactive-status-and-expire-at-existing-email-not-verified',
                'end' => 'end-update-inactive-status-and-expire-at-existing-email-not-verified',
            ],
            'log_exist_email_not_verified_upat' => [
                'start' => 'start-create-token-for-existing-email-not-verified',
                'end' => 'end-update-inactive-status-and-expire-at-existing-email-not-verified',
            ],
        ];
    }


    public function loginEmailLogs(): array
    {
        return [
            'function_name' => 'loginEmail',
            'indicator_catch_error' => 'tryCatchOnLoginEmail',
            'log_exist_email_to_verified_users_tbl' => [
                'start' => 'start-login-existing-email-to-verified',
                'end' => 'end-login-existing-email-to-verified',
                'user_display' => 'login-existing-email-to-verified',
            ],
            'log_exist_email_to_verified_users_personal_access_token_tbl_update' => [
                'start' => 'start-update-inactive-status-and-expire-at-login-existing-email-not-verified',
                'end' => 'end-update-inactive-status-and-expire-at-login-existing-email-not-verified',
            ],
            'log_exist_email_to_verified_users_personal_access_token_tbl_create' => [
                'start' => 'start-create-token-for-login-existing-email-not-verified',
                'end' => 'end-create-token-for-login-existing-email-not-verified',
            ],
            'log_login_users_personal_access_token_tbl_update' => [
                'start' => 'start-update-inactive-status-and-expire-at-login-email-verified',
                'end' => 'end-update-inactive-status-and-expire-at-login-email-verified',
            ],
            'log_login_users_personal_access_token_tbl_create' => [
                'start' => 'start-create-token-login-email-verified',
                'end' => 'end-create-token-login-email-verified',
            ],
            'log_login_users_tbl' => [
                'user_display' => 'login-using-email',
            ],
        ];
    }

    public function logoutLogs(): array
    {
        return [
            'function_name' => 'logout',
            'indicator_catch_error' => 'tryCatchOnLogoutLogs',
            'log_users_tbl' => [
                'user_display' => 'logout',
            ],
            'users_personal_access_token_tbl' => [
                'start' => 'start-update-inactive-status-and-last-used-at-logout',
                'end' => 'end-update-inactive-status-and-last-used-at-logout',
            ],
        ];
    }

    public function indexMeLogs(): array
    {
        return [
            'function_name' => 'me',
            'indicator_catch_error' => 'tryCatchOnMe',
            'users_tbl_log' => [
                'start' => 'start-get-me',
                'end' => 'end-get-me',
                'user_display' => 'get-me',
            ],
        ];
    }

    public function viewMeAllowedRole(): array
    {
        return [
            'super_admin' => env('ROLE_SUPER_ADMIN'),
        ];
    }

    public function arrToConvertIdsToEncrypted(): array
    {
        return [
            'uuid_user_id',
        ];
    }

    public function arrFieldsToUnsetIndex(): array
    {
        return [
            'uuid_user_id',
        ];
    }
}
