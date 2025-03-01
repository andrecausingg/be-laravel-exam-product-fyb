<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products_tbl';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid_product_id',
        'image',
        'name',
        'price'
    ];

    public function getFillableAttributes(): array
    {
        return array_merge($this->fillable, ['id']);
    }

    public function indexProductAllowedRole(): array
    {
        return [
            'super_admin' => env('ROLE_SUPER_ADMIN'),
        ];
    }

    public function viewProductAllowedRole(): array
    {
        return [
            'super_admin' => env('ROLE_SUPER_ADMIN'),
        ];
    }

    public function storeProductAllowedRole(): array
    {
        return [
            'super_admin' => env('ROLE_SUPER_ADMIN'),
        ];
    }

    public function updateProductAllowedRole(): array
    {
        return [
            'super_admin' => env('ROLE_SUPER_ADMIN'),
        ];
    }

    public function indexProductLogs(): array
    {
        return [
            'function_name' => 'indexProduct',
            'indicator_catch_error' => 'tryCatchOnIndexProduct',
            'products_tbl_log' => [
                'start' => 'start-get-index-product',
                'end' => 'end-get-index-product',
                'user_display' => 'get-index-product',
            ],
        ];
    }

    public function viewProductLogs(): array
    {
        return [
            'function_name' => 'viewProduct',
            'indicator_catch_error' => 'tryCatchOnViewProduct',
            'products_tbl_log' => [
                'start' => 'start-get-view-product',
                'end' => 'end-get-view-product',
                'user_display' => 'get-view-product',
            ],
        ];
    }

    public function storeProductLogs(): array
    {
        return [
            'function_name' => 'storeProduct',
            'indicator_catch_error' => 'tryCatchOnStoreProduct',
            'products_tbl_log' => [
                'start' => 'start-store-product',
                'end' => 'end-store-product',
                'user_display' => 'store-product',
            ],
        ];
    }

    public function updateProductLogs(): array
    {
        return [
            'function_name' => 'updateProduct',
            'indicator_catch_error' => 'tryCatchOnUpdateProduct',
            'products_tbl_log' => [
                'start' => 'start-update-product',
                'end' => 'end-update-product',
                'user_display' => 'update-product',
            ],
        ];
    }

    public function destroyProductLogs(): array
    {
        return [
            'function_name' => 'destroyProductLogs',
            'indicator_catch_error' => 'tryCatchOnDestroyProductLogs',
            'products_tbl_log' => [
                'start' => 'start-destroy-product',
                'end' => 'end-destroy-product',
                'user_display' => 'destroy-product',
            ],
        ];
    }

    public function arrToStores(): array
    {
        return [
            'uuid_product_id',
            'image',
            'name',
            'price',
        ];
    }

    public function arrToUpdates(): array
    {
        return [
            'image',
            'name',
            'price',
        ];
    }

    public function getApiRelativeSettings()
    {
        $prefix = 'product/';

        $payload = [
            "store" => [
                'image',
                'name',
                'price'
            ]
        ];
        $method = [
            'store' => 'POST',
        ];

        $button_name = [
            'store' => "Create product",
        ];

        $icon = [
            'store' => null,
        ];

        $container = [
            'store' => 'page',
        ];

        $details = [
            'store' => $this->arrFormFieldsStoreProduct(),
        ];

        return compact('prefix', 'payload', 'method', 'button_name', 'icon', 'container', 'details');
    }

    public function getApiCrudSettings()
    {
        $prefix = 'product/';

        $payload = [
            "update" => [
                'image',
                'name',
                'price'
            ]
        ];

        $method = [
            'update' => "PUT",
        ];
        $button_name = [
            'update' =>  "Update product",
        ];
        $icon = [
            'update' => "IconCashRegister",
        ];
        $container = [
            'update' => "page",
        ];
        $details = [
            'update' => $this->arrFormFieldsUpdateProduct(),
        ];

        return compact('prefix', 'payload',  'method', 'button_name', 'icon', 'container', 'details');
    }

    public function arrFormFieldsStoreProduct(): array
    {
        $product = [
            [
                "key" => "image",
                'label' => "Image",
                'type' => "file",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
            [
                "key" => "name",
                'label' => "Name",
                'type' => "text",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
            [
                "key" => "price",
                'label' => "Price",
                'type' => "number",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
        ];

        return  $product;
    }

    public function arrFormFieldsUpdateProduct(): array
    {
        $product = [
            [
                "key" => "image",
                'label' => "Image",
                'type' => "file",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
            [
                "key" => "name",
                'label' => "Name",
                'type' => "text",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
            [
                "key" => "price",
                'label' => "Price",
                'type' => "number",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
            ],
        ];

        return  $product;
    }

    public function getApiFilterSettings()
    {
        $prefix = 'product/';

        $payload = [
            "index" => [
                'limit',
                'page',
                'search',
                'start_created_at',
                'end_created_at',
                'role',
                'status'
            ],
        ];

        $method = [
            'index' => 'GET',
        ];

        $button_name = [
            'index' => 'Query param',
        ];

        $icon = [
            'index' => null,
        ];

        $container = [
            'index' => null,
        ];

        $details = [
            'index' => $this->arrFormFieldsQueryParam(),
        ];

        return compact('prefix', 'payload',  'method', 'button_name', 'icon', 'container', 'details');
    }

    public function arrFormFieldsQueryParam(): array
    {
        return [
            [
                'key' => 'search',
                'label' => "Search",
                'type' => "autocomplete",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
                'placeholder' => 'Search'
            ],
            [
                'key' => 'start_created_at',
                'label' => "Start created at",
                'type' => "date",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
                'placeholder' => '- Select start created at -'
            ],
            [
                'key' => 'end_created_at',
                'label' => "End created at",
                'type' => "date",
                'value' =>  null,
                'is_hidden' => false,
                'is_required' => false,
                'placeholder' => '- Select end created at -'
            ],

        ];
    }

    public function arrButtonNameIndexProduct(): array
    {
        return [
            'Update product',
            'Delete product'
        ];
    }

    public function arrFieldsToUnsetIndex(): array
    {
        return [
            'uuid_product_id',
        ];
    }

    public function arrToConvertIdsToEncrypted(): array
    {
        return [
            'uuid_product_id',
        ];
    }

    public function arrFieldsToDecrypt(): array
    {
        return [];
    }

    public function arrFieldsToForceInt(): array
    {
        return [
            'id',
        ];
    }

    public function arrFieldsToForceFloat(): array
    {
        return ['price'];
    }

    public function arrToConvertToReadableDateTime(): array
    {
        return [
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }


    public function arrButtonNameIndex(): array
    {
        return [
            'Update product',
            'Delete product'
        ];
    }
}
