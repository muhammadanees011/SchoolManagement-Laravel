<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\SchoolsController;
use App\Http\Controllers\Api\StudentsController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//------------Auth Route---------------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//-------------Email Verification-------------
Route::post('/resend_email_verification_otp', [AuthController::class, 'resend_email_verification_otp']);
Route::post('/verify_email', [AuthController::class, 'verify_email']);

//------------Forgot Password---------------------
Route::post('/forgot_password', [AuthController::class, 'send_forgot_password_otp']);
Route::post('/forgot_password_verify_otp', [AuthController::class, 'forgot_password_verify_otp']);
Route::post('/set_new_password', [AuthController::class, 'set_new_password']);

Route::group(['middleware' => 'auth:api'], function () {
    //------------LOGOUT USER---------------
    Route::post('/logout', [AuthController::class, 'logout']);
    //------------Organizations-------------
    Route::post('/createOrganization',[OrganizationController::class,'create']);
    Route::get('/getAllOrganizations',[OrganizationController::class,'index']);
    Route::get('/editOrganization/{id}',[OrganizationController::class,'edit']);
    Route::put('/updateOrganization/{id}',[OrganizationController::class,'update']);
    Route::delete('/deleteOrganization/{id}',[OrganizationController::class,'delete']);
    //-------------Schools-----------------
    Route::post('/createSchool',[SchoolsController::class,'create']);
    Route::get('/getAllSchools',[SchoolsController::class,'index']);
    Route::get('/editSchool/{id}',[SchoolsController::class,'edit']);
    Route::put('/updateSchool/{id}',[SchoolsController::class,'update']);
    Route::delete('/deleteSchool/{id}',[SchoolsController::class,'delete']);
    //-------------Students-----------------
    Route::post('/createStudent',[StudentsController::class,'create']);
    Route::get('/getAllStudents',[StudentsController::class,'index']);
    Route::get('/editStudent/{id}',[StudentsController::class,'edit']);
    Route::put('/updateStudent/{id}',[StudentsController::class,'update']);
    Route::delete('/deleteStudent/{id}',[StudentsController::class,'delete']);
});
