<?php

namespace App\Http\Controllers;

use App\Helper\Helper;
use App\Models\UsersModel;
use Illuminate\Support\Str;
use App\Models\HistoryModel;
use Illuminate\Http\Request;
use App\Models\VariablesModel;
use Illuminate\Support\Carbon;
use App\Models\UsersUserIdModel;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\GlobalArrFieldsModel;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

use Illuminate\Support\Facades\Validator;
use App\Models\UsersPersonalAccessTokenModel;
use Symfony\Component\HttpFoundation\Response;

class UsersController extends Controller
{
    protected $helper,
        $fill_attr_gaf_model,
        $fill_attr_user_model;

    public function __construct(
        Helper $helper,
        GlobalArrFieldsModel $fill_attr_gaf_model,
        UsersModel $fill_attr_user_model,
    ) {
        $this->helper = $helper;
        $this->fill_attr_user_model = $fill_attr_user_model;
        $this->fill_attr_gaf_model = $fill_attr_gaf_model;
    }

    public function logout(Request $request)
    {
        $global_payload_error = null;

        // Name logs
        $logs_details_function = $this->fill_attr_user_model->logoutLogs();

        // Authorize the user
        $auth = $this->helper->authorizeUser(
            $request,
            $this->fill_attr_user_model->logoutAllowedRole()
        );
        // Check if authenticated user
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
            $user_personal_access_token_payload = [
                'status' => "inactive",
                'last_used_at' => Carbon::now()
            ];

            $number_user_id = $auth->number_user_id;
            $uuid_user_id = $auth->uuid_user_id;

            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($auth),
                $logs_details_function['users_personal_access_token_tbl']['start'],
                $user_personal_access_token_payload,
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
            $result_update_upatm = $this->helper->updateOnModel(
                $auth,
                $user_personal_access_token_payload,
                'Logout a session.'
            );
            if (
                is_object($result_update_upatm) &&
                method_exists($result_update_upatm, 'getStatusCode') &&
                $result_update_upatm->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
            ) {
                return $result_update_upatm;
            }
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($auth),
                $logs_details_function['users_personal_access_token_tbl']['end'],
                $user_personal_access_token_payload,
                $result_update_upatm,
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
            // User display logs
            $result_log = $this->helper->log(
                $number_user_id,
                $uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($auth),
                $logs_details_function['log_users_tbl']['user_display'],
                $user_personal_access_token_payload,
                null,
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

            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'title_message' => 'Success',
                'message' => 'User logout successfully',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => "loginEmail", // Get function name
                    'indicator' => "tryCatchOnLoginEmail",
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

    public function login(Request $request)
    {
        $arr_data = [];
        $verification_number = mt_rand(100000, 999999);

        $arr_data = [
            'verification_number' => $verification_number,
        ];


        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        if ($request->has('email') && ($request->input('email') !== '' || $request->input('email') !== null)) {
            // Start Validate header and request body
            $validator = Validator::make($request_data, [
                'email' => 'required|email',
                'password' => 'required|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'title_message' => 'Validation failed',
                    'message' => 'There was an error processing the inputs.',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Start Validate header and request body

            // Start add the validate data
            foreach ($this->fill_attr_user_model->arrFieldsToAddValidateLogin() as $arr_data_field) {
                $arr_data[$arr_data_field] = $request_data[$arr_data_field];
            }
            // End add the validate data

            return $this->loginEmail($request, $arr_data);
        }
    }

    public function loginEmail($request, $arr_data)
    {
        $global_payload_error = null;

        // 
        $logs_details_function = $this->fill_attr_user_model->loginEmailLogs();

        try {
            // Decrypt al email first
            $users = UsersModel::all();

            foreach ($users as $user) {
                $decrypted_email = Crypt::decrypt($user->email);
                $number_user_id = $user->id;
                $uuid_user_id = $user->uuid_user_id;

                // Check if Not Verified then redirect to Verify Email
                // if ($decrypted_email == $arr_data['email'] && Hash::check($arr_data['password'], $user->password) && $user->email_verified_at === null) {
                //     $expiration_time = Carbon::now()->addMinutes(5);

                //     // Start Update UsersModel
                //     $arr_user_update_data = [
                //         'verification_number' => $arr_data['verification_number'],
                //     ];
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_to_verified_users_tbl']['start'],
                //         $arr_user_update_data,
                //         null,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // Format the content
                //     $result_format = $this->helper->formatFieldsData(
                //         $this->fill_attr_user_model->arrToStores(), // Fields to store in database
                //         $arr_user_update_data, // Data to save on database
                //         [],
                //         $this->fill_attr_user_model->arrFieldsToEncrypt(),
                //         $this->fill_attr_user_model->arrFieldsToHash(),
                //         [],
                //         [],
                //     );
                //     //Start Update and format the payload and changes
                //     $result_update_user_model = $this->helper->updateOnModel(
                //         $user,
                //         $result_format,
                //         'Existing email to verify'
                //     );
                //     if (
                //         is_object($result_update_user_model) &&
                //         method_exists($result_update_user_model, 'getStatusCode') &&
                //         $result_update_user_model->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
                //     ) {
                //         return $result_update_user_model;
                //     }
                //     //End Update and format the payload and changes
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_to_verified_users_tbl']['end'],
                //         $arr_user_update_data,
                //         $result_update_user_model,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_to_verified_users_tbl']['user_display'],
                //         $this->helper->filterDataBaseOnKey(
                //             $arr_data,
                //             array_keys($request->all()),
                //         ),
                //         null,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         true,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // End Update UsersModel

                //     // Start Update UsersPersonalAccessTokenModel
                //     $user_record = UsersUserIdModel::where('uuid_user_id', $uuid_user_id)->first();
                //     $new_token = JWTAuth::claims(['exp' => $expiration_time->timestamp])->fromUser($user_record);
                //     $arr_upatm_update_data = [
                //         'status' => 'inactive',
                //         'expires_at' => Carbon::now(),
                //     ];

                //     // update old token to status inactive
                //     $update_upatm_models = UsersPersonalAccessTokenModel::where('uuid_user_id', $uuid_user_id)
                //         ->where('status', 'active')
                //         ->where(function ($query) {
                //             $query->where('name', 'VERIFY_ACCOUNT_TOKEN_EXISTING_ACCOUNT')
                //                 ->orWhere('name', 'VERIFY_ACCOUNT');
                //         })->get(); // Retrieve all matching records

                //     if ($update_upatm_models->isNotEmpty()) { // Ensure there are records to update
                //         foreach ($update_upatm_models as $update_upatm_model) {
                //             $result_log = $this->helper->log(
                //                 $number_user_id,
                //                 $uuid_user_id,
                //                 static::class,
                //                 $logs_details_function['function_name'],
                //                 get_class($update_upatm_model),
                //                 $logs_details_function['log_exist_email_to_verified_users_personal_access_token_tbl_update']['start'],
                //                 $arr_upatm_update_data,
                //                 null,
                //                 null,
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToEncrypt(),
                //                 [],
                //                 [],
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //                 false,
                //             );
                //             if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //                 return $result_log;
                //             }

                //             // Start Update and format the payload and changes for each model
                //             $result_update_upatm = $this->helper->updateOnModel(
                //                 $update_upatm_model,
                //                 $arr_upatm_update_data,
                //                 'Existing email to verify. Update the status to inactive and add expiration'
                //             );
                //             if (
                //                 is_object($result_update_upatm) &&
                //                 method_exists($result_update_upatm, 'getStatusCode') &&
                //                 $result_update_upatm->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
                //             ) {
                //                 return $result_update_upatm;
                //             }
                //             // End Update and format the payload and changes for each model

                //             $result_log = $this->helper->log(
                //                 $number_user_id,
                //                 $uuid_user_id,
                //                 static::class,
                //                 $logs_details_function['function_name'],
                //                 get_class($update_upatm_model),
                //                 $logs_details_function['log_exist_email_to_verified_users_personal_access_token_tbl_update']['end'],
                //                 $arr_upatm_update_data,
                //                 $result_update_upatm,
                //                 null,
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToEncrypt(),
                //                 [],
                //                 [],
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //                 false,
                //             );
                //             if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //                 return $result_log;
                //             }
                //         }
                //     }
                //     // End Update UsersPersonalAccessTokenModel

                //     // Start Create UsersPersonalAccessTokenModel
                //     $user_personal_access_token_model = new UsersPersonalAccessTokenModel;
                //     $user_personal_access_token_data = [
                //         'uuid_users_personal_access_token_id' => Str::uuid(),
                //         'number_user_id' => $number_user_id,
                //         'uuid_user_id' => $uuid_user_id,
                //         'tokenable_type' => 'App\Models\UsersUserIdModel',
                //         'name' => 'VERIFY_ACCOUNT_TOKEN_EXISTING_ACCOUNT',
                //         'abilities' => json_encode(['*']),
                //         'status' => 'active',
                //         'token' => $new_token,
                //         'expires_at' => $expiration_time,
                //     ];
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user_personal_access_token_model),
                //         $logs_details_function['log_exist_email_to_verified_users_personal_access_token_tbl_create']['start'],
                //         $user_personal_access_token_data,
                //         null,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // Create 
                //     $created_upatm = UsersPersonalAccessTokenModel::create($user_personal_access_token_data);
                //     if (!$created_upatm) {
                //         $global_payload_error = $user_personal_access_token_data;
                //         throw new \Exception('Failed to create UsersPersonalAccessTokenModel. Existing email to verify');
                //     }
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user_personal_access_token_model),
                //         $logs_details_function['log_exist_email_to_verified_users_personal_access_token_tbl_create']['end'],
                //         $user_personal_access_token_data,
                //         $created_upatm,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // End Create UsersPersonalAccessTokenModel

                //     $email_parts = explode('@', $arr_data['email']);
                //     $name = $email_parts[0];
                //     // Verification Mail
                //     $mail = $this->helper->verificationCodeMail($arr_data);
                //     if ($mail->getStatusCode() !== Response::HTTP_OK) {
                //         $global_payload_error = $arr_data;
                //         throw new \Exception('Failed to send code on mail. Login Fresh create account.');
                //     }

                //     return response()->json([
                //         'title_message' => 'Success',
                //         'message' => 'Email not verified. Redirect to verification page.',
                //         'register_token' => $new_token,
                //         'register_url_token' => 'verification/' . $new_token,
                //         'expire_at' => $expiration_time->diffInSeconds(Carbon::now()),
                //     ], Response::HTTP_OK);
                // }


                // Check if Verified email
                if ($decrypted_email == $arr_data['email'] && Hash::check($arr_data['password'], $user->password) && $user->email_verified_at !== null) {
                    $expiration_time = Carbon::now()->addDays(30);

                    // Fetch the model holder of jwt
                    $user_record = UsersUserIdModel::where('uuid_user_id', $uuid_user_id)->first();
                    if (!$user_record) {
                        return response()->json([
                            'title_message' => 'Not found',
                            'message' => 'Yout I.D not found. Please contact the admin to fix this problem.'
                        ], Response::HTTP_NOT_FOUND);
                    }
                    $new_token = JWTAuth::claims(['exp' => $expiration_time->timestamp])->fromUser($user_record);
                    $arr_upatm_update_data = [
                        'status' => 'inactive',
                        'expires_at' => Carbon::now(),
                    ];

                    // Start update old token to inactive UsersPersonalAccessTokenModel
                    $update_upatm_models = UsersPersonalAccessTokenModel::where('uuid_user_id', $uuid_user_id)
                        ->where('name', 'LOGIN')
                        ->where('status', 'active')
                        ->get(); // Retrieve all matching records
                    if ($update_upatm_models->isNotEmpty()) { // Ensure there are records to update
                        foreach ($update_upatm_models as $update_upatm_model) {
                            $result_log = $this->helper->log(
                                $number_user_id,
                                $uuid_user_id,
                                static::class,
                                $logs_details_function['function_name'],
                                get_class($update_upatm_model),
                                $logs_details_function['log_login_users_personal_access_token_tbl_update']['start'],
                                $arr_upatm_update_data,
                                null,
                                null,
                                [],
                                $this->fill_attr_gaf_model->arrToEncrypt(),
                                [],
                                [],
                                [],
                                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                                false,
                            );
                            if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                                return $result_log;
                            }

                            // Start Update and format the payload and changes for each model
                            $result_update_upatm = $this->helper->updateOnModel(
                                $update_upatm_model,
                                $arr_upatm_update_data,
                                'User has login. Update the status to inactive and add expiration'
                            );
                            if (
                                is_object($result_update_upatm) &&
                                method_exists($result_update_upatm, 'getStatusCode') &&
                                $result_update_upatm->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
                            ) {
                                return $result_update_upatm;
                            }
                            // End Update and format the payload and changes for each model

                            $result_log = $this->helper->log(
                                $number_user_id,
                                $uuid_user_id,
                                static::class,
                                $logs_details_function['function_name'],
                                get_class($update_upatm_model),
                                $logs_details_function['log_login_users_personal_access_token_tbl_update']['end'],
                                $arr_upatm_update_data,
                                $result_update_upatm,
                                null,
                                [],
                                $this->fill_attr_gaf_model->arrToEncrypt(),
                                [],
                                [],
                                [],
                                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                                false,
                            );
                            if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                                return $result_log;
                            }
                        }
                    }
                    // Start update old token to inactive UsersPersonalAccessTokenModel

                    // Start create token UsersPersonalAccessTokenModel
                    $user_personal_access_token_model = new UsersPersonalAccessTokenModel;
                    $upatm_payload_create = [
                        'uuid_users_personal_access_token_id' => Str::uuid(),
                        'number_user_id' => $number_user_id,
                        'uuid_user_id' => $uuid_user_id,
                        'tokenable_type' => 'App\Models\UserUserIdModel',
                        'name' => 'LOGIN',
                        'token' => $new_token,
                        'abilities' => json_encode(['*']),
                        'status' => 'active',
                        'expires_at' => $expiration_time,
                    ];

                    $result_log = $this->helper->log(
                        $number_user_id,
                        $uuid_user_id,
                        static::class,
                        $logs_details_function['function_name'],
                        get_class($user_personal_access_token_model),
                        $logs_details_function['log_login_users_personal_access_token_tbl_create']['start'],
                        $upatm_payload_create,
                        null,
                        null,
                        [],
                        $this->fill_attr_gaf_model->arrToEncrypt(),
                        [],
                        [],
                        [],
                        $this->fill_attr_gaf_model->arrToReadableDateTime(),
                        false,
                    );
                    if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                        return $result_log;
                    }

                    // Create a new personal access token
                    $user_personal_access_token = $user_personal_access_token_model->create($upatm_payload_create);
                    if (!$user_personal_access_token) {
                        $global_payload_error = $upatm_payload_create;
                        throw new \Exception('Failed to create UsersPersonalAccessTokenModel. User Login.');
                    }
                    $result_log = $this->helper->log(
                        $number_user_id,
                        $uuid_user_id,
                        static::class,
                        $logs_details_function['function_name'],
                        get_class($user_personal_access_token_model),
                        $logs_details_function['log_login_users_personal_access_token_tbl_create']['end'],
                        $upatm_payload_create,
                        $user_personal_access_token,
                        null,
                        [],
                        $this->fill_attr_gaf_model->arrToEncrypt(),
                        [],
                        [],
                        [],
                        $this->fill_attr_gaf_model->arrToReadableDateTime(),
                        false,
                    );
                    if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                        return $result_log;
                    }
                    $result_log = $this->helper->log(
                        $number_user_id,
                        $uuid_user_id,
                        static::class,
                        $logs_details_function['function_name'],
                        get_class($user),
                        $logs_details_function['log_login_users_tbl']['user_display'],
                        $this->helper->filterDataBaseOnKey(
                            $arr_data,
                            array_keys(['email', 'password']),
                        ),
                        null,
                        null,
                        [],
                        $this->fill_attr_gaf_model->arrToEncrypt(),
                        [],
                        [],
                        [],
                        $this->fill_attr_gaf_model->arrToReadableDateTime(),
                        true,
                    );
                    if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                        return $result_log;
                    }


                    // Prepare response data
                    $response_data = [
                        'title_message' => 'Success',
                        'message' => 'Login Successfully',
                        'token' => $new_token,
                        'token_expire_at' => $expiration_time->diffInSeconds(Carbon::now()),
                    ];

                    return response()->json($response_data, Response::HTTP_OK);
                }
            }

            return response()->json([
                'title_message' => 'Not found',
                'message' => 'Invalid credential'
            ], Response::HTTP_NOT_FOUND);
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

    public function register(Request $request)
    {
        // Declare Value
        $arr_data = [];
        $verification_number = mt_rand(100000, 999999);
        $status = env('ACCOUNT_ACTIVATED');
        $uuid = Str::uuid();

        $arr_data = [
            'role' => env('ROLE_SUPER_ADMIN'),
            'verification_number' => $verification_number,
            'status' => $status,
            'uuid_user_id' =>  $uuid,
            'email_verified_at' => Carbon::now(),
        ];

        // *********************************** //
        // Start merge header and request body
        $request_data = $this->helper->mergeHeaderAndRequestBody(
            $request, // Request Body
            $this->fill_attr_gaf_model->arrHeaderFields(), // Fields to merge to request body
        );
        // End merge header and request body
        // *********************************** //

        if ($request->has('email') && ($request->input('email') !== '' || $request->input('email') !== null)) {

            // Start Validate header and request body
            $validator = Validator::make($request_data, [
                'email' => 'required|string|email',
                'password' => 'required|min:8|confirmed:password_confirmation',
            ]);


            if ($validator->fails()) {
                return response()->json([
                    'title_message' => 'Validation failed',
                    'message' => 'There was an error processing the inputs.',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Start Validate header and request body


            // Start add the validate data
            foreach ($this->fill_attr_user_model->arrFieldsToAddValidate() as $arr_data_field) {
                $arr_data[$arr_data_field] = $request_data[$arr_data_field];
            }
            // End add the validate data

            return $this->emailRegister($request, $arr_data);
        }

        return response()->json(['message' => 'Please Input on Phone Number or Email'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function emailRegister($request, $arr_data)
    {
        // Globals
        $global_payload_error = null;

        // Logs
        $created_number_user_id = null;
        $created_uuid_user_id = null;

        // Exist email create logs
        $logs_details_function = $this->fill_attr_user_model->emailRegisterLogs();

        try {
            // Generate a new token for the user
            // $expiration_time = Carbon::now()->addMinutes(5);
            $users = UsersModel::all();

            // Decrypt | For existing email but not verified
            foreach ($users as $user) {
                // Start Decrypt
                $decrypted_email = Crypt::decrypt($user->email);

                // Validation Email exists
                if ($decrypted_email === $arr_data['email'] && $user->email_verified_at !== null) {
                    return response()->json(
                        [
                            'title_message' => 'Unprocessed',
                            'message' => 'Email already exist.'
                        ],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                // Check if the requested email exists in the decrypted emails and email_verified_at is null then send verification code
                // else if ($decrypted_email === $arr_data['email'] && $user->email_verified_at === null) {
                //     $number_user_id = $user->id;
                //     $uuid_user_id = $user->uuid_user_id;

                //     // Unset key not needed
                //     $this->helper->unsetKeyOnArray(
                //         $this->fill_attr_user_model->arrFieldsToUnsetNotNeeded(),
                //         $arr_data,
                //         null
                //     );

                //     // Start update UsersModel
                //     $arr_user_update_payload = [
                //         'verification_number' => $arr_data['verification_number'],
                //     ];

                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_not_verified_users_tbl']['start'],
                //         $arr_user_update_payload,
                //         null,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     $result_format = $this->helper->formatFieldsData(
                //         $this->fill_attr_user_model->arrToStores(), // Fields to store in database
                //         $arr_user_update_payload, // Data to save on database
                //         [],
                //         $this->fill_attr_user_model->arrFieldsToEncrypt(),
                //         $this->fill_attr_user_model->arrFieldsToHash(),
                //         [],
                //         [],
                //     );
                //     $result_update_user_model = $this->helper->updateOnModel(
                //         $user,
                //         $result_format,
                //         'Existing email to verify'
                //     );
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_not_verified_users_tbl']['end'],
                //         $arr_user_update_payload,
                //         $result_update_user_model,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // End update UsersModel

                //     // Generate a JWT for the token
                //     $user_user_id_model_jwt = UsersUserIdModel::where('uuid_user_id', $uuid_user_id)->first();
                //     $new_token = JWTAuth::claims(['exp' => $expiration_time->timestamp])->fromUser($user_user_id_model_jwt);

                //     // Start UsersPersonalAccessTokenModel
                //     $users_personal_access_token_model = new UsersPersonalAccessTokenModel;
                //     $arr_upatm_update_payload = [
                //         'status' => 'inactive',
                //         'expires_at' => Carbon::now(),
                //     ];
                //     // update old token to status inactive
                //     $update_upatm_models = $users_personal_access_token_model->where('uuid_user_id', $uuid_user_id,)
                //         ->where('status', 'active')
                //         ->where(function ($query) {
                //             $query->where('name', 'VERIFY_ACCOUNT_TOKEN_EXISTING_ACCOUNT')
                //                 ->orWhere('name', 'VERIFY_ACCOUNT');
                //         })->get();
                //     if ($update_upatm_models->isNotEmpty()) {
                //         foreach ($update_upatm_models as $update_upatm_model) {
                //             $result_log = $this->helper->log(
                //                 $number_user_id,
                //                 $uuid_user_id,
                //                 static::class,
                //                 $logs_details_function['function_name'],
                //                 get_class($users_personal_access_token_model),
                //                 $logs_details_function['log_exist_email_not_verified_user_personal_access_token_tbl']['start'],
                //                 $arr_upatm_update_payload,
                //                 null,
                //                 null,
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToEncrypt(),
                //                 [],
                //                 [],
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //                 false
                //             );
                //             if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //                 return $result_log;
                //             }
                //             $result_update_upatm = $this->helper->updateOnModel(
                //                 $update_upatm_model,
                //                 $arr_upatm_update_payload,
                //                 'Existing email to verify. Update the status to inactive and add expiration'
                //             );
                //             if (
                //                 is_object($result_update_upatm) &&
                //                 method_exists($result_update_upatm, 'getStatusCode') &&
                //                 $result_update_upatm->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR
                //             ) {
                //                 return $result_update_upatm;
                //             }
                //             $result_log = $this->helper->log(
                //                 $number_user_id,
                //                 $uuid_user_id,
                //                 static::class,
                //                 $logs_details_function['function_name'],
                //                 get_class($users_personal_access_token_model),
                //                 $logs_details_function['log_exist_email_not_verified_user_personal_access_token_tbl']['end'],
                //                 $arr_upatm_update_payload,
                //                 $result_update_upatm,
                //                 null,
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToEncrypt(),
                //                 [],
                //                 [],
                //                 [],
                //                 $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //                 false
                //             );
                //             if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //                 return $result_log;
                //             }
                //         }
                //     }
                //     // End UsersPersonalAccessTokenModel

                //     // Start UsersPersonalAccessTokenModel
                //     $user_personal_access_token_model = new UsersPersonalAccessTokenModel;
                //     $user_personal_access_token_payload = [
                //         'uuid_users_personal_access_token_id' => Str::uuid(),
                //         'number_user_id' => $number_user_id,
                //         'uuid_user_id' => $uuid_user_id,
                //         'tokenable_type' => 'App\Models\UsersUserIdModel',
                //         'name' => 'VERIFY_ACCOUNT_TOKEN_EXISTING_ACCOUNT',
                //         'abilities' => json_encode(['*']),
                //         'status' => 'active',
                //         'token' => $new_token,
                //         'expires_at' => $expiration_time,
                //     ];
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user_personal_access_token_model),
                //         $logs_details_function['log_exist_email_not_verified_upat']['start'],
                //         $user_personal_access_token_payload,
                //         null,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // Create 
                //     $created_upatm = $user_personal_access_token_model->create($user_personal_access_token_payload);
                //     if (!$created_upatm) {
                //         $global_payload_error = $user_personal_access_token_payload;
                //         throw new \Exception('Failed to create UsersPersonalAccessTokenModel. Existing email to verify');
                //     }
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user_personal_access_token_model),
                //         $logs_details_function['log_exist_email_not_verified_upat']['end'],
                //         $user_personal_access_token_payload,
                //         $created_upatm,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         false
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // End UsersPersonalAccessTokenModel

                //     $this->helper->unsetKeyOnArray(
                //         $this->fill_attr_user_model->arrFieldsToUnsetNotNeededUserLogs(),
                //         $arr_data,
                //         null
                //     );

                //     // Start user logs display
                //     $result_log = $this->helper->log(
                //         $number_user_id,
                //         $uuid_user_id,
                //         static::class,
                //         $logs_details_function['function_name'],
                //         get_class($user),
                //         $logs_details_function['log_exist_email_not_verified_users_tbl']['user_display'],
                //         $arr_data,
                //         $result_update_user_model,
                //         null,
                //         [],
                //         $this->fill_attr_gaf_model->arrToEncrypt(),
                //         [],
                //         [],
                //         [],
                //         $this->fill_attr_gaf_model->arrToReadableDateTime(),
                //         true,
                //     );
                //     if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                //         return $result_log;
                //     }
                //     // End user logs display

                //     return response()->json([
                //         'title_message' => 'Success',
                //         'message' => 'Email not verified. Redirect to verification page.',
                //         'register_token' => $new_token,
                //         'register_url_token' => 'verification/' . $new_token,
                //         'expire_at' => $expiration_time->diffInSeconds(Carbon::now()),
                //     ], Response::HTTP_OK);
                // }
            }

            // Start User Model
            $users_model = new UsersModel;
            $result_to_create_user = $this->helper->formatFieldsData(
                $this->fill_attr_user_model->arrToStores(), // Fields to store in database
                $arr_data, // Data to save on database
                [],
                $this->fill_attr_user_model->arrFieldsToEncrypt(),
                $this->fill_attr_user_model->arrFieldsToHash(),
                [],
                [],
                [],
            );

            $result_log = $this->helper->log(
                null,
                null,
                static::class,
                $logs_details_function['function_name'],
                get_class($users_model),
                $logs_details_function['log_users_tbl']['start'],
                $arr_data,
                null,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
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
            $created_user = $users_model->create($result_to_create_user);
            $created_number_user_id = $created_user->id;
            $created_uuid_user_id = $created_user->uuid_user_id;
            if (!$created_user) {
                $global_payload_error = $arr_data;
                throw new \Exception('Failed to save UsersModel. Fresh create account email.');
            }
            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($users_model),
                $logs_details_function['log_users_tbl']['end'],
                $arr_data,
                $created_user,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
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
            // User display logs
            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($users_model),
                $logs_details_function['log_users_tbl']['user_display'],
                $this->helper->filterDataBaseOnKey(
                    $arr_data,
                    array_keys($request->all()),
                ),
                $created_user,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
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
            // End User Model

            // Start History Model
            $history_model = new HistoryModel;
            $history_payload = [
                'uuid_history_id' => Str::uuid(),
                'number_tbl_id' => $created_number_user_id,
                'uuid_tbl_id' => $created_uuid_user_id,
                'tbl_name' => 'users_tbl',
                'column_name' => 'password',
                'value' => Crypt::encrypt($arr_data['password']),
            ];
            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($history_model),
                $logs_details_function['log_history_tbl']['start'],
                $history_payload,
                null,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false,
            );
            if ($result_log->getStatusCode() !== Response::HTTP_OK) {
                $global_payload_error = $history_payload;
                return $result_log;
            }

            $created_history = $history_model->create($history_payload);
            if (!$created_history) {
                $global_payload_error = $history_payload;
                throw new \Exception('Failed to save HistoryModel. Fresh create account email.');
            }

            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($history_model),
                $logs_details_function['log_history_tbl']['end'],
                $history_payload,
                $created_history,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
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
            // End History Model

            // Start Users User Id Model
            $users_user_id_model = new UsersUserIdModel;
            $users_user_id_payload = [
                'uuid_users_user_id_id' => Str::uuid(),
                'number_user_id' => $created_number_user_id,
                'uuid_user_id' => $created_uuid_user_id,
            ];
            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class($users_user_id_model),
                $logs_details_function['log_users_user_id_tbl']['start'],
                $users_user_id_payload,
                null,
                null,
                [],
                $this->fill_attr_gaf_model->arrToEncrypt(),
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            $create_user_id = $users_user_id_model->create($users_user_id_payload);
            if (!$create_user_id) {
                $global_payload_error = $users_user_id_payload;
                throw new \Exception('Failed to save UsersUserIdModel. Fresh create account email.');
            }
            $result_log = $this->helper->log(
                $created_number_user_id,
                $created_uuid_user_id,
                static::class,
                $logs_details_function['function_name'],
                get_class(object: $users_user_id_model),
                $logs_details_function['log_users_user_id_tbl']['end'],
                $users_user_id_payload,
                $create_user_id,
                null,
                [],
                [],
                [],
                [],
                [],
                $this->fill_attr_gaf_model->arrToReadableDateTime(),
                false
            );
            if (
                is_object($result_log)
                && method_exists($result_log, 'getStatusCode')
                && $result_log->getStatusCode() !== Response::HTTP_OK
            ) {
                return $result_log;
            }
            // End Users User Id Model

            // // Generate a JWT for the token
            // $new_token = JWTAuth::claims(['exp' => $expiration_time->timestamp])->fromUser($create_user_id);

            // // Start Users Personal Access token Model
            // $users_personal_access_token_model = new UsersPersonalAccessTokenModel;
            // $user_personal_access_token_payload = [
            //     'uuid_users_personal_access_token_id' => Str::uuid(),
            //     'number_user_id' => $created_number_user_id,
            //     'uuid_user_id' => $created_uuid_user_id,
            //     'tokenable_type' => get_class($users_user_id_model),
            //     'name' => 'VERIFY_ACCOUNT',
            //     'abilities' => json_encode(['*']),
            //     'status' => 'active',
            //     'token' =>  $new_token,
            //     'expires_at' => $expiration_time,
            // ];
            // $result_log = $this->helper->log(
            //     $created_number_user_id,
            //     $created_uuid_user_id,
            //     static::class,
            //     $logs_details_function['function_name'],
            //     get_class($users_personal_access_token_model),
            //     $logs_details_function['log_users_personal_access_token_tbl']['start'],
            //     $user_personal_access_token_payload,
            //     null,
            //     null,
            //     [],
            //     [],
            //     [],
            //     [],
            //     [],
            //     $this->fill_attr_gaf_model->arrToReadableDateTime(),
            //     false
            // );
            // if (
            //     is_object($result_log)
            //     && method_exists($result_log, 'getStatusCode')
            //     && $result_log->getStatusCode() !== Response::HTTP_OK
            // ) {
            //     return $result_log;
            // }
            // $created_upatm = $users_personal_access_token_model->create($user_personal_access_token_payload);
            // if (!$created_upatm) {
            //     $global_payload_error = $user_personal_access_token_payload;
            //     throw new \Exception('Failed to save UsersPersonalAccessTokenModel. Fresh create account email.');
            // }
            // $result_log = $this->helper->log(
            //     $created_number_user_id,
            //     $created_uuid_user_id,
            //     static::class,
            //     $logs_details_function['function_name'],
            //     get_class($users_personal_access_token_model),
            //     $logs_details_function['log_users_personal_access_token_tbl']['end'],
            //     $user_personal_access_token_payload,
            //     $created_upatm,
            //     null,
            //     [],
            //     [],
            //     [],
            //     [],
            //     [],
            //     $this->fill_attr_gaf_model->arrToReadableDateTime(),
            //     false
            // );
            // if (
            //     is_object($result_log)
            //     && method_exists($result_log, 'getStatusCode')
            //     && $result_log->getStatusCode() !== Response::HTTP_OK
            // ) {
            //     return $result_log;
            // }
            // // End Users Personal Access token Model

            return response()->json([
                'title_message' => 'Success',
                'message' => 'Successfully register email',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $variable_err_email = VariablesModel::where('function_name', 'isErrorEmail')->first();
            if ($variable_err_email && $variable_err_email->value == 1) {
                // Capture the error message
                $error_mail_data = [
                    'controller_class_name' => static::class,   // Get the current class name
                    'function_name' => $logs_details_function['function_name'] ?? null,  // Get function name
                    'indicator' => $logs_details_function['indicator_catch_error'] ?? null,
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
