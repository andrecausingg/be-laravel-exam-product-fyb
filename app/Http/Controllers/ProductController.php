<?php

namespace App\Http\Controllers;

use App\Helper\Helper;
use Illuminate\Support\Str;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use App\Models\VariablesModel;
use Illuminate\Support\Carbon;
use App\Models\GlobalArrFieldsModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    protected $helper,
        $fill_attr_gaf_model,
        $fill_attr_product_models;

    public function __construct(
        Helper $helper,
        GlobalArrFieldsModel $fill_attr_gaf_model,
        ProductModel $fill_attr_product_models
    ) {
        $this->helper = $helper;
        $this->fill_attr_gaf_model = $fill_attr_gaf_model;
        $this->fill_attr_product_models = $fill_attr_product_models;
    }

    public function indexProduct(Request $request)
    {
        $global_payload_error = null;
        $arr_container_datas = [];
        $arr_all_data = [];
        $number_user_id = null;
        $uuid_user_id = null;

        $logs_details_function = $this->fill_attr_product_models->indexProductLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_product_models->indexProductAllowedRole()
        );
        if (empty($auth->uuid_user_id)) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'User is not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $number_user_id = $auth->number_user_id;
        $uuid_user_id = $auth->uuid_user_id;

        // Product model
        $relative_settings = $this->fill_attr_product_models->getApiRelativeSettings();
        $crud_settings = $this->fill_attr_product_models->getApiCrudSettings();
        $filter_settings = $this->fill_attr_product_models->getApiFilterSettings();

        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        // Validation rules for each item in the array
        $validator = Validator::make($request_data, []);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'title_message' => 'Validation failed',
                'message' => 'There was an error processing the inputs.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $product_models = new ProductModel();
            $product_models = $product_models
                ->orderBy('id', 'desc');
            // Get query parameters
            $search = $request->query('search');
            $start_created_at = $request->query('start_created_at');
            $end_created_at = $request->query('end_created_at');

            $product_models->where(function ($query) use ($start_created_at, $end_created_at, $search) {
                // Apply date range conditions as a block
                if ($start_created_at && $end_created_at) {
                    $query->where(function ($q) use ($start_created_at, $end_created_at) {
                        $q->where('created_at', '>=', $start_created_at)
                            ->where('created_at', '<=', $end_created_at);
                    });
                } else {
                    if ($start_created_at) {
                        $query->where('created_at', '>=', $start_created_at);
                    }

                    if ($end_created_at) {
                        $query->where('created_at', '<=', $end_created_at);
                    }
                }

                if ($search) {
                    $query->where('id', $search)
                        ->orWhere('name', 'LIKE', "%{$search}%");
                }
            });

            // Apply pagination
            $product_models = $product_models->paginate(
                $request->query('limit', 12), // Items per page
                ['*'], // Select all columns
                'page', // Pagination parameter name
                $request->query('page', 1) // Current page
            );

            // Get all data
            foreach ($product_models as $product_model) {

                // Store on array the specific data
                foreach ($this->fill_attr_product_models->getFillableAttributes() as $getFillableAttribute) {
                    // fields to encrypt
                    if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrToConvertIdsToEncrypted())) {
                        $arr_container_datas[$getFillableAttribute] = Crypt::encrypt($product_model->$getFillableAttribute);
                    }
                    // fields to decrypt
                    else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToDecrypt())) {
                        $arr_container_datas[$getFillableAttribute] = $this->helper->isEncrypted($product_model->$getFillableAttribute) ? Crypt::decrypt($product_model->$getFillableAttribute) ?? null : $product_model->$getFillableAttribute ?? null;
                    }
                    // Fields to force int
                    else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToForceInt())) {
                        $arr_container_datas[$getFillableAttribute] =
                            !is_int($product_model->$getFillableAttribute)
                            ? $this->helper->forceConvertToInt($product_model->$getFillableAttribute)
                            : $product_model->$getFillableAttribute;
                    }
                    // Fields to force float
                    else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToForceFloat())) {
                        $arr_container_datas[$getFillableAttribute] =
                            !is_float($product_model->$getFillableAttribute)
                            ? $this->helper->forceConvertToFloat($product_model->$getFillableAttribute)
                            : $product_model->$getFillableAttribute;
                    }
                    // fields to convert date and time
                    else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrToConvertToReadableDateTime())) {
                        $arr_container_datas[$getFillableAttribute] = $this->helper->convertReadableTimeDate($product_model->$getFillableAttribute);
                    }
                    // just declare
                    else {
                        $arr_container_datas[$getFillableAttribute] = $product_model->$getFillableAttribute;
                    }
                }

                // Start Format the action details
                $crud_action = $this->helper->formatApi(
                    $crud_settings['prefix'],
                    $crud_settings['payload'],
                    $crud_settings['method'],
                    $crud_settings['button_name'],
                    $crud_settings['icon'],
                    $crud_settings['container'],
                    $crud_settings['details']
                );
                // Format index
                $this->helper->detailsFormatIndex(
                    $arr_container_datas,
                    null, // make it null if no action key
                    'uuid_product_id', // make it null if no target id
                    $this->fill_attr_product_models->arrButtonNameIndex(),
                    $crud_action,
                    [],
                );
                // Unset key not needed
                $this->helper->unsetKeyOnArray(
                    $this->fill_attr_product_models->arrFieldsToUnsetIndex(),
                    $arr_container_datas,
                    null // make it null if no exact key
                );
                // End Format the action details

                // Data
                $arr_all_data[] = $arr_container_datas;
            }
            // dd($arr_all_data);


            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully retrieve product',
                'data' => $arr_all_data,
                'buttons' => $this->helper->formatApi(
                    $relative_settings['prefix'],
                    $relative_settings['payload'],
                    $relative_settings['method'],
                    $relative_settings['button_name'],
                    $relative_settings['icon'],
                    $relative_settings['container'],
                    $relative_settings['details']
                ),
                'filters' => $this->helper->formatApi(
                    $filter_settings['prefix'],
                    $filter_settings['payload'],
                    $filter_settings['method'],
                    $filter_settings['button_name'],
                    $filter_settings['icon'],
                    $filter_settings['container'],
                    $filter_settings['details']
                ),
                'pagination' => [
                    'count' => $product_models->count(),
                    'has_page' => $product_models->hasPages(),
                    'has_more_pages' => $product_models->hasMorePages(),
                    'current_page' => $product_models->currentPage(),
                    'last_page' => $product_models->lastPage(),
                    'per_page' => $product_models->perPage(),
                    'next_page_url' => $product_models->nextPageUrl(),
                    'previous_page_url' => $product_models->previousPageUrl(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function_name'], // Get function name
                    'indicator' => $logs_details_function['indicator_catch_error'],
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->helper->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    public function viewProduct(Request $request, $id)
    {
        $global_payload_error = null;
        $arr_container_datas = [];
        $arr_all_data = [];
        $number_user_id = null;
        $uuid_user_id = null;

        $logs_details_function = $this->fill_attr_product_models->indexProductLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_product_models->viewProductAllowedRole()
        );
        if (empty($auth->uuid_user_id)) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'User is not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $number_user_id = $auth->number_user_id;
        $uuid_user_id = $auth->uuid_user_id;

        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        // Validation rules for each item in the array
        $validator = Validator::make($request_data, []);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'title_message' => 'Validation failed',
                'message' => 'There was an error processing the inputs.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $product_model = new ProductModel();

            // Check if encrypted id
            if (!$this->helper->isEncrypted($id)) {
                return response()->json([
                    'title_message' => 'Unprocessed',
                    'message' => 'Sorry your id is not on valid format.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $decrypted_uuid_product_id = Crypt::decrypt($id);
            $product_model = $product_model
                ->where('uuid_product_id', $decrypted_uuid_product_id)
                ->first();
            if (!$product_model) {
                return response()->json([
                    'title_message' => 'Not found',
                    'message' => 'Product id not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Store on array the specific data
            foreach ($this->fill_attr_product_models->getFillableAttributes() as $getFillableAttribute) {
                // fields to encrypt
                if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrToConvertIdsToEncrypted())) {
                    $arr_container_datas[$getFillableAttribute] = Crypt::encrypt($product_model->$getFillableAttribute);
                }
                // fields to decrypt
                else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToDecrypt())) {
                    $arr_container_datas[$getFillableAttribute] = $this->helper->isEncrypted($product_model->$getFillableAttribute) ? Crypt::decrypt($product_model->$getFillableAttribute) ?? null : $product_model->$getFillableAttribute ?? null;
                }
                // Fields to force int
                else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToForceInt())) {
                    $arr_container_datas[$getFillableAttribute] =
                        !is_int($product_model->$getFillableAttribute)
                        ? $this->helper->forceConvertToInt($product_model->$getFillableAttribute)
                        : $product_model->$getFillableAttribute;
                }
                // Fields to force float
                else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrFieldsToForceFloat())) {
                    $arr_container_datas[$getFillableAttribute] =
                        !is_float($product_model->$getFillableAttribute)
                        ? $this->helper->forceConvertToFloat($product_model->$getFillableAttribute)
                        : $product_model->$getFillableAttribute;
                }
                // fields to convert date and time
                else if (in_array($getFillableAttribute, $this->fill_attr_product_models->arrToConvertToReadableDateTime())) {
                    $arr_container_datas[$getFillableAttribute] = $this->helper->convertReadableTimeDate($product_model->$getFillableAttribute);
                }
                // just declare
                else {
                    $arr_container_datas[$getFillableAttribute] = $product_model->$getFillableAttribute;
                }
            }

            // Unset key not needed
            $this->helper->unsetKeyOnArray(
                $this->fill_attr_product_models->arrFieldsToUnsetIndex(),
                $arr_container_datas,
                null // make it null if no exact key
            );
            // End Format the action details

            // Data
            $arr_all_data[] = $arr_container_datas;




            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully view product',
                'data' => $arr_all_data,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function_name'], // Get function name
                    'indicator' => $logs_details_function['indicator_catch_error'],
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->helper->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    public function storeProduct(Request $request)
    {
        $global_payload_error = null;
        $file_name = '';

        $logs_details_function = $this->fill_attr_product_models->storeProductLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_product_models->storeProductAllowedRole()
        );
        if (empty($auth->uuid_user_id)) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'User is not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        // Validation rules for each item in the array
        $validator = Validator::make($request_data, [
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name' => 'required|string|max:255|lowercase',
            'price' => 'required|numeric|min:0',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'title_message' => 'Validation failed',
                'message' => 'There was an error processing the inputs.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $products_model = new ProductModel;
            $number_user_id = $auth->number_user_id;
            $uuid_user_id = $auth->uuid_user_id;

            // Start store product
            // Handle image upload
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file_name = $this->helper->handleUploadFile(
                    [
                        'custom_folder' => 'product',
                        'file_image' => $request->file('image'),
                        'image_actual_extension' => $request->file('image')->getClientOriginalExtension(),
                    ],
                    0, // 1 is original name | 0 is system generated name
                );
            }

            // Merge the content and file or image
            $result_merge_data = $this->helper->arrMergeContentAndFile(
                $request,
                $request->all(),
                $file_name,
                'image',
            );

            // Merge the data you want to merge
            $result_merge_data['uuid_product_id'] = Str::uuid();

            $payload_product_create = $this->helper->formatFieldsData(
                $this->fill_attr_product_models->arrToStores(), // Fields to store in database
                $result_merge_data, // Data to save on database
                [],
                [],
                [],
                [],
                [],
                [],
            );
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['start'],
                $payload_product_create,
                null,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }

            $result_created_product = $products_model->create($payload_product_create);
            if (!$result_created_product) {
                $global_payload_error = $request_data;
                throw new \Exception('Failed to create Product.');
            }
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['end'],
                $payload_product_create,
                $result_created_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }

            // User input logs
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['user_display'],
                $this->helper->filterDataBaseOnKey(
                    $payload_product_create,
                    ['image', 'name', 'price']
                ),
                $result_created_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                true,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            // End store product

            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully store product.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function'],
                    'indicator' =>  $logs_details_function['indicator_catch_error'],
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->helper->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    public function updateProduct(Request $request, $id)
    {
        $global_payload_error = null;
        $file_name = '';

        $logs_details_function = $this->fill_attr_product_models->updateProductLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_product_models->updateProductAllowedRole()
        );
        if (empty($auth->uuid_user_id)) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'User is not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        // Validation rules for each item in the array
        $validator = Validator::make($request_data, [
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name' => 'nullable|string|max:255|lowercase',
            'price' => 'nullable|numeric|min:0',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'title_message' => 'Validation failed',
                'message' => 'There was an error processing the inputs.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $products_model = new ProductModel;
            $number_user_id = $auth->number_user_id;
            $uuid_user_id = $auth->uuid_user_id;

            // Check if encrypted id
            if (!$this->helper->isEncrypted($id)) {
                return response()->json([
                    'title_message' => 'Unprocessed',
                    'message' => 'Sorry your id is not on valid format.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $decrypted_uuid_product_id = Crypt::decrypt($id);
            $products_model = $products_model->where('uuid_product_id', $decrypted_uuid_product_id)
                ->first();
            if (!$products_model) {
                return response()->json([
                    'title_message' => 'Not found',
                    'message' => 'Product id not found'
                ], Response::HTTP_NOT_FOUND);
            }


            // *********************************** //
            // Start checking changes
            // Get the changes of the fields
            $result_update_logs_old_new = $this->helper->checkIfThereChanges(
                $products_model, // the model to update
                $this->fill_attr_product_models->arrToUpdates(), // Fields to update in database
                $request_data, // the merge of file and user input
                0, // if the database value is all lower case must 1 here to match the old new
                0, // if the database value is all capital case must 1 here to match the old new
            );
            // No changes return error
            if (
                is_object($result_update_logs_old_new)
                && method_exists($result_update_logs_old_new, 'getStatusCode')
                && $result_update_logs_old_new->getStatusCode() == Response::HTTP_UNPROCESSABLE_ENTITY
            ) {
                return $result_update_logs_old_new;
            }
            // End checking changes
            // *********************************** //

            // Start update product
            // Handle image upload
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file_name = $this->helper->handleUploadFile(
                    [
                        'custom_folder' => 'product',
                        'file_image' => $request->file('image'),
                        'image_actual_extension' => $request->file('image')->getClientOriginalExtension(),
                    ],
                    0, // 1 is original name | 0 is system generated name
                );
            }

            // Merge the content and file or image
            $result_merge_data = $this->helper->arrMergeContentAndFile(
                $request,
                $request->all(),
                $file_name,
                'image',
            );

            $payload_product_update = $this->helper->formatFieldsData(
                $this->fill_attr_product_models->arrToUpdates(), // Fields to update in database
                $result_merge_data, // Data to save on database
                [],
                [],
                [],
                [],
                [],
                [],
            );
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['start'],
                $payload_product_update,
                null,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            $result_update_product = $this->helper->updateOnModel(
                $products_model,
                $payload_product_update,
                'Updating Product'
            );
            if (
                is_object($result_update_product) &&
                method_exists($result_update_product, 'getStatusCode') &&
                $result_update_product->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
            ) {
                return $result_update_product;
            }
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['end'],
                $payload_product_update,
                $result_update_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }

            // User input logs
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['user_display'],
                $this->helper->filterDataBaseOnKey(
                    $payload_product_update,
                    ['image', 'name', 'price']
                ),
                $result_update_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                true,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            // End update product

            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully update product.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function'],
                    'indicator' =>  $logs_details_function['indicator_catch_error'],
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->helper->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
    
    public function destroyProduct(Request $request, $id)
    {
        $global_payload_error = null;
        $file_name = '';

        $logs_details_function = $this->fill_attr_product_models->destroyProductLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_product_models->updateProductAllowedRole()
        );
        if (empty($auth->uuid_user_id)) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'User is not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        try {
            $products_model = new ProductModel;
            $number_user_id = $auth->number_user_id;
            $uuid_user_id = $auth->uuid_user_id;

            // Check if encrypted id
            if (!$this->helper->isEncrypted($id)) {
                return response()->json([
                    'title_message' => 'Unprocessed',
                    'message' => 'Sorry your id is not on valid format.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $decrypted_uuid_product_id = Crypt::decrypt($id);
            $products_model = $products_model->where('uuid_product_id', $decrypted_uuid_product_id)
                ->first();
            if (!$products_model) {
                return response()->json([
                    'title_message' => 'Not found',
                    'message' => 'Product id not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['start'],
                $decrypted_uuid_product_id,
                null,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            $result_delete_product = $products_model->delete();
            if (!$result_delete_product) {
                throw new \Exception('Failed to delete product.');
            }
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['end'],
                $decrypted_uuid_product_id,
                $result_delete_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }

            // User input logs
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($products_model),
                $logs_details_function['products_tbl_log']['user_display'],
                $decrypted_uuid_product_id,
                $result_delete_product,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                true,
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            // End update product

            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully delete product.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function'],
                    'indicator' =>  $logs_details_function['indicator_catch_error'],
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->helper->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }
}
