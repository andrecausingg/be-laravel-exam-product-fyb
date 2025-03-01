<?php

namespace App\Helper;

use App\Mail\ErrorMail;
use App\Models\LogsModel;
use App\Models\UsersModel;
use Illuminate\Support\Str;
use App\Models\HistoryModel;
use App\Mail\VerificationMail;
use App\Models\UsersInfoModel;
use App\Models\VariablesModel;
use Illuminate\Support\Carbon;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Mail\ResendVerificationMail;
use App\Models\GlobalArrFieldsModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use DeviceDetector\Parser\Client\Browser;
use App\Models\UserPersonalAccessTokenModel;
use App\Models\UsersPersonalAccessTokenModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Encryption\DecryptException;


class Helper
{
    protected $fillable_attr_history, $fillable_attr_log, $fill_attr_gaf_model;

    public function __construct(
        HistoryModel $fillable_attr_history,
        LogsModel $fillable_attr_log,
        GlobalArrFieldsModel $fill_attr_gaf_model,
    ) {
        $this->fillable_attr_history = $fillable_attr_history;
        $this->fillable_attr_log = $fillable_attr_log;
        $this->fill_attr_gaf_model = $fill_attr_gaf_model;
    }

    // Authentication
    public function authorizeUser($request, $role_allowed_list = [])
    {
        try {
            // Authenticate the user with the provided token
            $user = JWTAuth::parseToken()->authenticate();
            $auth = UsersPersonalAccessTokenModel::where('uuid_user_id', $user->uuid_user_id)
                ->where('status', 'active')
                ->where('token', $request->bearerToken())
                ->whereNull('last_used_at')
                ->where('expires_at', '>', Carbon::now())
                ->where('name',  'LOGIN')
                ->latest()
                ->first();

            if (!$auth) {
                JWTAuth::invalidate(JWTAuth::getToken());
                return response()->json([
                    'title_message' => 'Unauthorized',
                    'message' => 'User is not allowed to use this api.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user_model = UsersModel::where('uuid_user_id', $auth->uuid_user_id)->first();

            $role = $this->isEncrypted($user_model->role) ? Crypt::decrypt($user_model->role) : $user_model->role;
            if (!in_array($role, $role_allowed_list)) {
                JWTAuth::invalidate(JWTAuth::getToken());
                return response()->json([
                    'title_message' => 'Unauthorized',
                    'message' => 'User is not authenticated.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            return $auth;
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'Token expired'
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'Invalid token'
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'title_message' => 'Unauthorized',
                'message' => 'Failed to authenticate.'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    // Merge headers and request body
    public function mergeHeaderAndRequestBody($request, $arr_headers_fields = [])
    {
        $arr_holder_headers = [];

        try {
            foreach ($arr_headers_fields as $arr_headers_field) {
                // Check if the header exists
                if ($request->hasHeader($arr_headers_field)) {
                    // Store the header value in the $arr_holder_headers array
                    $arr_holder_headers[$arr_headers_field] = $request->header($arr_headers_field);
                } else {
                    // If a required header is missing, return a 422 error
                    return response()->json([
                        'title_message' => 'Error',
                        'message' => "Missing required header: {$arr_headers_field}"
                    ], Response::HTTP_UNAUTHORIZED);
                }
            }

            // Merge request body with headers
            $merged_data = array_merge($request->all(), $arr_holder_headers);

            // Validate the merged data (optional)
            if (!is_array($merged_data)) {
                // If merged data is not an array, return a 422 error
                return response()->json([
                    'title_message' => 'Error',
                    'message' => 'Invalid data format after merging headers and request body.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $unset_key_data = $this->unsetFieldsIsEmpty($merged_data);

            return $unset_key_data;
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'title_message' => 'Error',
                'message' => 'An error occurred while merging headers and request body.',
                'details' => $e->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
    }


    // Log
    public function log(
        $number_user_id = null,
        $uuid_user_id = null,
        $controller_class_name = '',
        $function_name = '',
        $model_class_name = '',
        $activity = '',
        $payload = [],
        $success_result_details = null,
        $error_result_details = null,
        $arr_fields_to_encrypt = [],
        $arr_fields_to_decrypt = [],
        $arr_fields_to_path_file_key_value = [],
        $arr_fields_to_lower_case = [],
        $arr_fields_to_upper_case = [],
        $arr_fields_to_readable_date = [],
        $is_log_user = false,
    ) {
        $global_payload_error = null;

        try {
            $arr_payload_data = [];
            // $arr_data = [
            //     'user_id' => $user_id,
            //     'activity' => $activity,
            //     'payload' => $payload,
            //     'success_result_details' => $success_result_details,
            //     'error_result_details' => $error_result_details,
            //     'arr_fields_to_lower_case' => $arr_fields_to_lower_case,
            //     'arr_fields_to_upper_case' => $arr_fields_to_upper_case,
            //     'arr_fields_to_encrypt' => $arr_fields_to_encrypt,
            //     'arr_fields_to_decrypt' => $arr_fields_to_decrypt,
            //     'arr_fields_to_path_file_key_value' => $arr_fields_to_path_file_key_value,
            // ];
            // dd($arr_data);

            if (!empty($payload)) {
                // Format payload
                $return_log_format_payload = $this->logFormat(
                    $payload,
                    $arr_fields_to_lower_case,
                    $arr_fields_to_upper_case,
                    $arr_fields_to_encrypt,
                    $arr_fields_to_decrypt,
                    $arr_fields_to_path_file_key_value,
                    $arr_fields_to_readable_date,
                );
                $arr_payload_data['payload'] = $return_log_format_payload;
            }

            // Format success result if not empty
            if (!empty($success_result_details)) {
                $return_log_format_success_result = $this->logFormat(
                    $success_result_details,
                    $arr_fields_to_lower_case,
                    $arr_fields_to_upper_case,
                    $arr_fields_to_encrypt,
                    $arr_fields_to_decrypt,
                    $arr_fields_to_path_file_key_value,
                    $arr_fields_to_readable_date,
                );
            } else {
                $return_log_format_success_result = null; // Ensure it's set to null if no success result
            }

            // $json_encode = json_encode($arr_payload_data); // make it json
            // $json_decode = json_decode($json_encode, true); // make it array 

            // Data to insert
            $data = [
                'uuid_logs_id' => Str::uuid(),
                'number_user_id' => $number_user_id,
                'uuid_user_id' => $uuid_user_id,
                'controller_class_name' => $controller_class_name,
                'function_name' => $function_name,
                'model_class_name' => $model_class_name,
                'activity' => $activity,
                'payload' => !empty($arr_payload_data) ? json_encode($arr_payload_data) : null,
                'success_result_details' => $return_log_format_success_result ? json_encode($return_log_format_success_result) : null,
                'error_result_details' => $error_result_details ? json_encode($error_result_details) : null,
                'is_log_user' => $is_log_user,
            ];


            $log = LogsModel::create($data);
            if (!$log) {
                $global_payload_error = $data;
                throw new \Exception('Failed to save Log Model.');
            }

            return response()->json([
                'title_message' => 'Success',
                'message' => 'Success create log',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => "log", // Get function name
                    'indicator' => "tryCatchOnLog",
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error, // Data being used to create auth
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Error',
                    'message' => $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    // Log format
    private function logFormat(
        $payload = [],
        $arr_fields_to_lower_case = [],
        $arr_fields_to_upper_case = [],
        $arr_fields_to_encrypt = [],
        $arr_fields_to_decrypt = [],
        $arr_fields_to_path_file_key_value = [],
        $arr_fields_to_readable_date = []
    ) {
        $global_payload_error = null;

        try {
            // Convert specific fields to lowercase
            foreach ($arr_fields_to_lower_case as $field) {
                if (isset($payload[$field]) && is_string($payload[$field])) {
                    $payload[$field] = Str::lower($payload[$field]);
                }
            }

            // Convert specific fields to uppercase
            foreach ($arr_fields_to_upper_case as $field) {
                if (isset($payload[$field]) && is_string($payload[$field])) {
                    $payload[$field] = Str::upper($payload[$field]);
                }
            }

            // Encrypt specific fields
            foreach ($arr_fields_to_encrypt as $field) {
                if (isset($payload[$field])) {
                    if (!$this->isEncrypted($payload[$field])) {
                        $payload[$field] = Crypt::encrypt($payload[$field]);
                    }
                }
            }

            // Decrypt specific fields
            foreach ($arr_fields_to_decrypt as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = Crypt::decrypt($payload[$field]);
                }
            }

            // Add path URL image or file
            foreach ($arr_fields_to_path_file_key_value as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = [
                        'key' => $field,
                        'value' => $payload[$field],
                    ];
                }
            }

            // Readable date time
            foreach ($arr_fields_to_readable_date as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = $this->convertReadableTimeDate($payload[$field]);
                }
            }
        } catch (\Exception $e) {
            $global_payload_error = [
                'payload' => $payload,
                'arr_fields_to_lower_case' => $arr_fields_to_lower_case,
                'arr_fields_to_upper_case' => $arr_fields_to_upper_case,
                'arr_fields_to_encrypt' => $arr_fields_to_encrypt,
                'arr_fields_to_decrypt' => $arr_fields_to_decrypt,
                'arr_fields_to_path_file_key_value' => $arr_fields_to_path_file_key_value,
                'arr_fields_to_readable_date' => $arr_fields_to_readable_date
            ];

            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => "log", // Get function name
                    'indicator' => "tryCatchOnLogFormat",
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error, // Data being used to create auth
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->errorMail($error_mail_data);
            }

            return false;
        }

        // Return the formatted payload
        return $payload;
    }


    // Convert to readable date time January 1, 2024 6:49 pm
    public function convertReadableTimeDate($data)
    {
        // Return null if $data is empty or null
        if (empty($data)) {
            return null;
        }

        // Set the timezone for Carbon to 'Asia/Manila'
        $carbon_date = Carbon::parse($data)->setTimezone('Asia/Manila');
        $value = $carbon_date->format('F j, Y g:i:s a');

        return $value;
    }


    // Send error mail
    public function errorMail($data)
    {
        if (env("APP_ENV") == 'production') {
            $email = env("MAIL_EMAIL");
            // Mail::to($email)->send(new ErrorMail($data));
        }
    }



    // Format fields data
    public function formatFieldsData(
        $arr_fields_to_format,
        $user_input_data,
        $arr_decrypt_encrypted_ids = [],
        $arr_fields_to_encrypt = [],
        $arr_fields_to_hash = [],
        $arr_fields_make_lowercase = [],
        $arr_fields_make_uppercase = [],
        $arr_fields_make_json_encode = []
    ) {
        $global_payload_error = null;
        $arr_attributes_store = [];


        // Unset fields that don't have values
        $user_input_data = $this->unsetFieldsIsEmpty($user_input_data);

        foreach ($arr_fields_to_format as $arr_store_field) {
            if (array_key_exists($arr_store_field, $user_input_data)) {
                // Default to null for fields not in $user_input_data
                $value = array_key_exists($arr_store_field, $user_input_data)
                    ? $user_input_data[$arr_store_field]
                    : null;

                try {
                    // Check if field should be converted to lowercase
                    if (in_array($arr_store_field, $arr_fields_make_lowercase)  && $value !== null) {
                        $value = strtolower($value);
                    }

                    // Check if field should be converted to uppercase
                    if (in_array($arr_store_field, $arr_fields_make_uppercase)  && $value !== null) {
                        $value = strtoupper($value);
                    }

                    // Check if field should be JSON encoded
                    if (in_array($arr_store_field, $arr_fields_make_json_encode)  && $value !== null) {
                        $value = json_encode($value);
                    }

                    // Check if field is encrypted and then decrypt it
                    if (in_array($arr_store_field, $arr_decrypt_encrypted_ids)  && $value !== null) {
                        $value = Crypt::decrypt($value);
                    }

                    // Check if the field should be encrypted
                    if (in_array($arr_store_field, $arr_fields_to_encrypt)  && $value !== null) {
                        $value = Crypt::encrypt($value);
                    }

                    // Check if the field should be hashed
                    if (in_array($arr_store_field, $arr_fields_to_hash)  && $value !== null) {
                        $value = Hash::make($value);
                    }
                } catch (\Exception $e) {
                    $global_payload_error = [
                        'arr_fields_to_format' => $arr_fields_to_format,
                        'user_input_data' => $user_input_data,
                        'arr_decrypt_encrypted_ids' => $arr_decrypt_encrypted_ids,
                        'arr_fields_to_encrypt' => $arr_fields_to_encrypt,
                        'arr_fields_to_hash' => $arr_fields_to_hash,
                        'arr_fields_make_lowercase' => $arr_fields_make_lowercase,
                        'arr_fields_make_uppercase' => $arr_fields_make_uppercase,
                        'arr_fields_make_json_encode' => $arr_fields_make_json_encode
                    ];

                    $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
                    if ($variable_err_email && $variable_err_email->value == 1) {
                        // Capture the error message
                        $error_mail_data = [
                            'controller_class_name' => static::class,   // Get the current class name
                            'function_name' => "log", // Get function name
                            'indicator' => "tryCatchOnFormatFieldsData",
                            'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                            'payload' => $global_payload_error, // Data being used to create auth
                            'error_message' => $e->getMessage(), // Capture the exception message
                            'error_details' => $e->getTraceAsString() // Capture the exception message
                        ];

                        // Send the error mail
                        $this->errorMail($error_mail_data);
                    }
                    // Optionally, you can decide to skip this field or handle it differently
                    continue;
                }

                $arr_attributes_store[$arr_store_field] = $value;
            }
        }


        return $arr_attributes_store;
    }


    // Format fields update data
    public function updateOnModel($model, $payload, $error_message = '')
    {
        // Store the changes
        $changes = [];
        $global_payload_error = null;


        try {
            // Get original values
            $original_value = $model->getOriginal();

            // Get the array of keys that should be converted to readable date time
            $dateKeys = $this->fill_attr_gaf_model->arrToReadableDateTime();

            // Format original values for date fields
            foreach ($original_value as $key => $value) {
                if (in_array($key, $dateKeys)) {
                    $original_value[$key] = $value ? $this->convertReadableTimeDate($value) : null;
                }
            }

            $changes['original'] = $original_value; // Store the formatted original values

            // Set the new values on the model without saving it yet
            $model->fill($payload);

            // Capture the attributes that have been changed (dirty attributes)
            $changes_values = $model->getDirty();

            // Now update the model and save the changes
            if (!$model->save()) { // If the save fails
                $global_payload_error = $payload;
                throw new \Exception(
                    'Failed to save the model update.' . "Model name: " . get_class($model) . " " . $error_message
                ); // Throw an exception
            }

            // Get the array of keys that should be converted to readable date time
            $date_keys = $this->fill_attr_gaf_model->arrToReadableDateTime();

            // Track old and new values
            foreach ($changes_values as $key => $new_value) {
                // Get the old value (before update)
                $old_value = $original_value[$key] ?? null;

                // Check if the key is in the array of date keys
                if (in_array($key, $date_keys)) {
                    // Convert old and new values to readable date time
                    $old_value = $old_value ? $this->convertReadableTimeDate($old_value) : null;
                    $new_value = $new_value ? $this->convertReadableTimeDate($new_value) : null;
                }

                // Store old and new values in the changes array
                $changes['changes'][$key] = [
                    'old' => $old_value,   // Old value (before updating)
                    'new' => $new_value,   // New value (after updating)
                ];
            }
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => "updateOnModel", // Get function name
                    'indicator' => "tryCatchOnUpdateOnModel",
                    'date' => Carbon::now()->format('F j, Y g:i:s a'), // Formatted date
                    'payload' => $global_payload_error,
                    'error_message' => $e->getMessage(), // Capture the exception message
                    'error_details' => $e->getTraceAsString() // Capture the exception message
                ];

                // Send the error mail
                $this->errorMail($error_mail_data);
            }

            $variable_try_catch = VariablesModel::where('function_name', 'isTryCatch500')->first();
            if ($variable_try_catch && $variable_try_catch->value == 1) {
                return response()->json([
                    'title_message' => 'Internal Server error',
                    'message' => $e->getMessage(),
                    'error_details' => $e->getTraceAsString(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Return changes if no exception occurred
        return $changes;
    }



    // Unset the fields is empty or null
    public function unsetFieldsIsEmpty($data)
    {
        foreach ($data as $key => $value) {
            if ($value === "") {
                unset($data[$key]);
            }
        }

        return $data;
    }

    // Checking if value is incrypted
    public function isEncrypted($value)
    {
        try {
            Crypt::decrypt($value);
            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }



    // Format the Api return
    public function formatApi($prefix = '', $payloads = [], $method = '', $button_name = '', $icon = null, $container = '', $details = []): array
    {

        $arr_action = [];

        foreach ($payloads as $key => $payload) {
            $action = [
                'url' => $prefix . $key,
                'payload' => $payload,
                'method' => $method[$key] ?? null,
                'icon' => $icon[$key] ?? null,
                'button_name' => $this->upperCase($button_name[$key] ?? null),
                'container' => $container[$key] ?? null,
                'details' => $details[$key] ?? null,
            ];

            $arr_action[] = $action;
        }

        return $arr_action;
    }

    // Uppercase the Word with dash -
    public function upperCase($button_name)
    {
        if ($button_name == '' || $button_name == null) {
            return null;
        }
        // Remove hyphens and uppercase the string
        $upper_case_string = str_replace('-', ' ', ucfirst($button_name));

        return $upper_case_string;
    }

    // Unset key on array 
    public function unsetKeyOnArray(
        $arr_fields,
        &$data,
        $exact_key = null
    ) {
        if ($exact_key !== null) {
            foreach ($arr_fields as $key) {
                unset($data[$exact_key][$key]);
            }
        } else {
            foreach ($arr_fields as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key]);
                }
            }
        }
    }

    public function unsetKeyOnArrayRecursive(
        array $arr_fields,
        array &$data,
        ?string $exact_key = null
    ): void {
        if ($exact_key !== null && isset($data[$exact_key]) && is_array($data[$exact_key])) {
            // If the key points to a nested array, loop through each item
            foreach ($data[$exact_key] as &$nestedItem) {
                if (is_array($nestedItem)) {
                    foreach ($arr_fields as $key) {
                        if (array_key_exists($key, $nestedItem)) {
                            unset($nestedItem[$key]);
                        }
                    }
                }
            }
        } else {
            // If not nested, just remove the keys from the top level
            foreach ($arr_fields as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key]);
                }
            }
        }
    }


    // Handle Upload file or image store on specific storage
    public function handleUploadFile($arr_data_file, $is_original_name = 0)
    {
        $file_name = '';

        if ($is_original_name == 1) {
            $image_actual_name_without_extension = preg_replace('/[^a-zA-Z0-9]/', '_', $arr_data_file['image_actual_name_without_extension']);

            // Generate File Name
            $file_name =  $image_actual_name_without_extension . "_" . Carbon::now()->timestamp . "." . $arr_data_file['image_actual_extension'];

            // Generate the file path within the custom folder
            $file_path = $arr_data_file['custom_folder'] . '/' . $file_name;

            // Save on Storage
            Storage::disk('public')->put($file_path, file_get_contents($arr_data_file['file_image']));
        } else {
            // Generate File Name
            $file_name = Str::uuid() . "_" . Str::uuid() . "_" . mt_rand() . "_" . Carbon::now()->timestamp . "." . $arr_data_file['image_actual_extension'];

            // Generate the file path within the custom folder
            $file_path = $arr_data_file['custom_folder'] . '/' . $file_name;

            // Save on Storage
            Storage::disk('public')->put($file_path, file_get_contents($arr_data_file['file_image']));
        }

        return $file_name;
    }

    // Merge the image or file on the payload
    public function arrMergeContentAndFile(
        $request = null,
        $data = null,
        $file_name = '',
        $field_name = '',
    ) {
        $arr_merge_data = $data;

        // Check if the request has a file and if it's valid
        if ($request->hasFile($field_name) && $request->file($field_name)->isValid() && $file_name != '') {
            // Merge the $field_name key with the existing validated data
            $arr_merge_data[$field_name] = $file_name;
        }

        return $arr_merge_data;
    }


    // Check if theres Changes
    public function checkIfThereChanges(
        $model = null,
        $arr_update_fields = [],
        $user_input_data = [],
        $is_lower_case_result_merge_data = 0,
        $is_upper_case_result_merge_data = 0,
        $arr_fields_save_null = [],
    ) {
        $changes_item_for_logs = [];

        foreach ($arr_update_fields as $arr_update_field) {
            $existing_value = isset($model->$arr_update_field)
                ? ($this->isEncrypted($model->$arr_update_field) ? Crypt::decrypt($model->$arr_update_field) : $model->$arr_update_field)
                : null;

            $new_value = $user_input_data[$arr_update_field] ?? null;

            // Apply transformations based on flags
            if ($is_upper_case_result_merge_data === 1) {
                $new_value = strtoupper($new_value);
            }

            if ($is_lower_case_result_merge_data === 1) {
                $new_value = strtolower($new_value);
            }

            // Check if the existing value is a hash
            if ($this->isHash($existing_value)) {
                if (!Hash::check($new_value, $existing_value)) {
                    // If hash check fails, log the new value
                    $changes_item_for_logs[$arr_update_field] = $new_value;
                }
            } else {
                // This condition force to save null on database
                if (in_array($arr_update_field, $arr_fields_save_null)) {
                    $changes_item_for_logs[$arr_update_field] = $new_value;
                } else if ($new_value != null && $existing_value != $new_value) {
                    $changes_item_for_logs[$arr_update_field] = $new_value;
                }
            }
        }

        if (empty($changes_item_for_logs)) {
            return response()->json([
                'title_message' => 'Validation failed',
                'message' => 'No changes have been made.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $changes_item_for_logs;
    }

    // public function checkIfThereChanges(
    //     $model = null,
    //     $arr_update_fields = [],
    //     $user_input_data = [],
    //     $is_lower_case_result_merge_data = 0,
    //     $is_upper_case_result_merge_data = 0,
    // ) {
    //     $changes_item_for_logs = [];

    //     foreach ($arr_update_fields as $arr_update_field) {
    //         $existing_value = isset($model->$arr_update_field)
    //             ? ($this->isEncrypted($model->$arr_update_field) ? Crypt::decrypt($model->$arr_update_field) : $model->$arr_update_field)
    //             : null;
    //         $new_value = $user_input_data[$arr_update_field] ?? null;

    //         // Apply transformations based on flags
    //         if ($is_upper_case_result_merge_data === 1) {
    //             $new_value = strtoupper($new_value);
    //         }

    //         if ($is_lower_case_result_merge_data === 1) {
    //             $new_value = strtolower($new_value);
    //         }

    //         if ($existing_value != $new_value) {
    //             $changes_item_for_logs[$arr_update_field] = $new_value;
    //         }
    //     }

    //     if (empty($changes_item_for_logs)) {
    //         return response()->json([
    //             'title_message' => 'Validation failed',
    //             'message' => 'No changes have been made.'
    //         ], Response::HTTP_UNPROCESSABLE_ENTITY);
    //     }

    //     return $changes_item_for_logs;
    // }

    // Size of File
    public function fileFormatSize($size)
    {
        $units = [
            'PB'    => 1024 ** 5,
            'TB'    => 1024 ** 4,
            'GB'    => 1024 ** 3,
            'MB'    => 1024 ** 2,
            'KB'    => 1024,
            'bytes' => 1
        ];
        foreach ($units as $unit => $bytes) {
            if ($size >= $bytes) {
                $formattedSize = $size / $bytes;
                $formattedSize = round($formattedSize, 2);
                return "{$formattedSize} {$unit}";
            }
        }

        return '0 bytes';
    }

    // Force convert to int 
    function forceConvertToInt($data)
    {
        return (int) $data;
    }

    // Force convert to float
    public function forceConvertToFloat($data)
    {
        return number_format($data, 2, '.', '');  // Rounds to 2 decimal places
    }

    // Decrypt all encrypted
    public function decryptAllEncrypted(&$data)
    {
        foreach ($data as &$value) {  // Use reference to modify the original $value
            if ($this->isEncrypted($value)) {
                $value = Crypt::decrypt($value);  // Assign the decrypted value back to $value
            }
        }
        // No need to return anything, as data is passed by reference and modified
    }

    // This filter data for logs specific key only for displaying user input
    public function filterDataBaseOnKey(
        $data = [],
        $arr_key_fields_filter = []
    ) {
        $filtered = collect($data)->only($arr_key_fields_filter);

        return $filtered;
    }

    // Append other index
    public function appendOtherIndex(
        &$arr_container_datas = [],
        $model = null,
        $crud_settings = [],
        $key_container = '',
        $key_target_id = '',
        $arr_button_names = [],
        $arr_key_unset_fields = [],
        $arr_get_fillable_attributes = [],
        $arr_to_convert_ids_to_encrypted = [],
        $arr_fields_to_decrypt = [],
        $arr_fields_to_force_int = [],
        $arr_fields_to_force_float = [],
        $arr_to_convert_to_readable_date_time = [],
        $arr_to_modified_file = [],
    ) {
        if ($model) {
            foreach ($arr_get_fillable_attributes as $arr_get_fillable_attribute) {
                // Fields to encrypt
                if (in_array($arr_get_fillable_attribute, $arr_to_convert_ids_to_encrypted)) {
                    if ($key_container != '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = Crypt::encrypt($model->$arr_get_fillable_attribute);
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = Crypt::encrypt($model->$arr_get_fillable_attribute);
                    }
                }
                // Check if the current attribute needs modification
                else if (in_array($arr_get_fillable_attribute, array_keys($arr_to_modified_file))) {
                    $modified_value = $this->isEncrypted($model->$arr_get_fillable_attribute) ? Crypt::decrypt($model->$arr_get_fillable_attribute) : $model->$arr_get_fillable_attribute;
                    foreach ($arr_to_modified_file as $key => $value) {
                        if ($key == $arr_get_fillable_attribute) {

                            $final_result = $modified_value === null || $modified_value === ""  ? $modified_value : $value . $modified_value;
                            break;
                        }
                    }


                    // dd($modified_value);
                    // dd(array_keys($arr_to_modified_file));
                    // dd($model->$arr_get_fillable_attribute);
                    // dd($model);

                    if ($key_container !== '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $final_result;
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = $final_result;
                    }
                }
                // Fields to decrypt
                else if (in_array($arr_get_fillable_attribute, $arr_fields_to_decrypt)) {
                    if ($key_container != '') {
                        $decryptedValue = $this->isEncrypted($model->$arr_get_fillable_attribute)
                            ? Crypt::decrypt($model->$arr_get_fillable_attribute)
                            : $model->$arr_get_fillable_attribute;
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $decryptedValue ?? null;
                    } else {
                        $decryptedValue = $this->isEncrypted($model->$arr_get_fillable_attribute)
                            ? Crypt::decrypt($model->$arr_get_fillable_attribute)
                            : $model->$arr_get_fillable_attribute;
                        $arr_container_datas[$arr_get_fillable_attribute] = $decryptedValue ?? null;
                    }
                }
                // Fields to force int
                else if (in_array($arr_get_fillable_attribute, $arr_fields_to_force_int)) {
                    if ($key_container != '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $this->forceConvertToInt($model->$arr_get_fillable_attribute);
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = $this->forceConvertToInt($model->$arr_get_fillable_attribute);
                    }
                }
                // Fields to force float
                else if (in_array($arr_get_fillable_attribute, $arr_fields_to_force_float)) {
                    if ($key_container != '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $this->forceConvertToFloat($model->$arr_get_fillable_attribute);
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = $this->forceConvertToFloat($model->$arr_get_fillable_attribute);
                    }
                }
                //  Fields to convert readable time
                else if (in_array($arr_get_fillable_attribute, $arr_to_convert_to_readable_date_time)) {
                    if ($key_container != '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $this->convertReadableTimeDate($model->$arr_get_fillable_attribute);
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = $this->convertReadableTimeDate($model->$arr_get_fillable_attribute);
                    }
                }
                // Just declare
                else {
                    if ($key_container != '') {
                        $arr_container_datas[$key_container][$arr_get_fillable_attribute] = $model->$arr_get_fillable_attribute;
                    } else {
                        $arr_container_datas[$arr_get_fillable_attribute] = $model->$arr_get_fillable_attribute;
                    }
                }
            }


            // ***************************** /
            // Start Format the action details
            $this->detailsFormatIndex(
                $arr_container_datas,
                $key_container, // make it null if no action key
                $key_target_id,
                $arr_button_names,
                $crud_settings,
            );
            // End Format the action details
            // ***************************** /

            // ***************************** /
            // Start Unset key not needed
            $this->unsetKeyOnArray(
                $arr_key_unset_fields,
                $arr_container_datas,
                $key_container
            );
            // End Unset key not needed
            // ***************************** /
        } else {
            if ($key_container != '') {
                $arr_container_datas[$key_container] = [];
            } else {
                $arr_container_datas = [];
            }
        }
    }

    public function detailsFormatIndex(
        &$arr_container_datas,
        $key_for_action = '',
        $key_target_id = '',
        $arr_button_names = [],
        $crud_settings,
        $arr_field_with_keys = [],
        $arr_modified_keys = [],
    ) {
        // Make array values
        if ($key_for_action != '') {
            $arr_container_datas[$key_for_action]['actions'] = array_values($crud_settings);
        } else {
            $arr_container_datas['actions'] = array_values($crud_settings);
        }

        // Loop through each button name in the array
        foreach ($arr_button_names as $button_name) {
            // Get actions based on the existence of $key_for_action
            $actions = $key_for_action != '' && isset($arr_container_datas[$key_for_action]['actions'])
                ? $arr_container_datas[$key_for_action]['actions']
                : ($arr_container_datas['actions'] ?? []);

            foreach ($actions as &$action) {

                // Check if the action's button_name matches the current button
                if (isset($action['button_name']) && $action['button_name'] == $button_name) {
                    // Update the URL dynamically for each button
                    $action['url'] = $action['url'] . "/" . ($key_for_action != ''
                        ? ($arr_container_datas[$key_for_action][$key_target_id] ?? '')
                        : ($arr_container_datas[$key_target_id] ?? ''));

                    // Ensure the 'details' array is always present
                    if (!isset($action['details'])) {
                        $action['details'] = []; // Initialize as empty if not set
                    }

                    // Loop through the existing details array and populate with correct values
                    if (!empty($action['details'])) {
                        foreach ($action['details'] as $key => &$details) {
                            if (!isset($details['key'])) {
                                continue; // Skip if 'key' is not set
                            }

                            // Fetch the value for this detail key
                            $value = $key_for_action != ''
                                ? ($arr_container_datas[$key_for_action][$details['key']] ?? null)
                                : ($arr_container_datas[$details['key']] ?? null);

                            if (!empty($arr_field_with_keys)) {
                                foreach ($arr_field_with_keys as $index_key => $wrap_value) {
                                    if (in_array($details['key'], $wrap_value)) {
                                        $action['details'][$index_key][] = [
                                            'key' => $details['key'] ?? '',
                                            'label' => $details['label'] ?? '',
                                            'type' => $details['type'] ?? 'input',
                                            'value' => $this->detectAndConvertType($details['value']) ?? $this->detectAndConvertType($value)  ?? '',
                                            'is_hidden' => $details['is_hidden'] ?? false,
                                            'is_required' => $details['is_required'] ?? false,
                                            'option' => $details['option'] ?? [],
                                        ];
                                    } else {
                                        $action['details'][$key] = [
                                            'key' => $details['key'] ?? '',
                                            'label' => $details['label'] ?? '',
                                            'type' => $details['type'] ?? 'input',
                                            'value' => $this->detectAndConvertType($details['value']) ?? $this->detectAndConvertType($value)  ?? '',
                                            'is_hidden' => $details['is_hidden'] ?? false,
                                            'is_required' => $details['is_required'] ?? false,
                                            'option' => $details['option'] ?? [],
                                        ];
                                    }
                                }
                            } else {
                                // Initialize the detail array with updated or default values
                                $action['details'][$key] = [
                                    'key' => $details['key'] ?? '',
                                    'label' => $details['label'] ?? '',
                                    'type' => $details['type'] ?? 'input',
                                    'value' => $this->detectAndConvertType($details['value']) ?? $this->detectAndConvertType($value)  ?? '',
                                    'is_hidden' => $details['is_hidden'] ?? false,
                                    'is_required' => $details['is_required'] ?? false,
                                    'option' => $details['option'] ?? [],
                                ];
                            }
                        }
                    }
                }
            }

            // Unset
            if (!empty($arr_field_with_keys)) {
                foreach ($action['details'] as $action_key => $action_value) {
                    if (is_numeric($action_key)) {
                        unset($action['details'][$action_key]);
                    }
                }
            }


            // Ensure the updated 'actions' array is saved back to the parent structure
            if ($key_for_action != '') {
                $arr_container_datas[$key_for_action]['actions'] = $actions;
            } else {
                $arr_container_datas['actions'] = $actions;
            }
        }
    }

    function detectAndConvertType(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return $value + 0; // Convert to int or float
        }

        if (is_string($value)) {
            $lower_value = strtolower($value);
            if ($lower_value === 'true') {
                return true;
            } elseif ($lower_value === 'false') {
                return false;
            }
        }

        return $value; // Return as is if no conversion needed
    }

    // Check if value is hash Crypt::encrypt()
    public function isHash($value)
    {
        return is_string($value) && preg_match('/^\$2[aby]?\$\d{2}\$[A-Za-z0-9\.\/]{53}$/', $value);
    }

    // Return role
    public function currentRole($model)
    {
        if (!$model) {
            return null;
        }

        return $this->isEncrypted($model->role) ? Crypt::decrypt($model->role) : $model->role;
    }

    public function convertLaravelDbDateTimeParse($date_time = null)
    {
        if (!empty($date_time)) {
            return Carbon::parse($date_time)->toDateTimeString();
        }
    }
}
