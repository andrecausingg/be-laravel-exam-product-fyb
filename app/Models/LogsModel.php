<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogsModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'logs_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid_logs_id',

        'number_user_id',
        'uuid_user_id',

        'is_log_user',

        'controller_class_name',
        'function_name',
        'model_class_name',
        'activity',
        'payload',
        'success_result_details',
        'error_result_details',
        'user_device',

        'deleted_at',
        'created_at',
        'updated_at',
    ];
    protected $dates = ['deleted_at'];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }
    public function arrToConvertIdsToEncrypted(): array
    {
        return [
            "id"
        ];
    }

    public function arrFieldsToUnset(): array
    {
        return [
            "deleted_at",
        ];
    }

    public function arrToConvertToReadableDateTime(): array
    {
        return [
            'created_at',
            'updated_at',
        ];
    }

    public function getApiCrudSettings()
    {
        $prefix = 'log/';

        $payload = [
            'show' => null,
        ];
        $method = [
            'show' => 'GET',
        ];
        $button_name = [
            'show' => 'View',
        ];
        $icon = [
            'show' => null,
        ];
        $container = [
            'show' => 'modal',
        ];

        return compact('prefix', 'payload', 'method', 'button_name', 'icon', 'container');
    }

    public function getApiRelativeSettings()
    {
        $prefix = 'log/';

        $payload = [];

        $method = [];

        $button_name = [];

        $icon = [];

        $container = [];

        return compact('prefix', 'payload', 'method', 'button_name', 'icon', 'container');
    }
}
