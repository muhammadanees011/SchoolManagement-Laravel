<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\SchoolsController;
use App\Http\Controllers\Api\StudentsController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\SchoolShopsController;
use App\Http\Controllers\Api\MenusController;
use App\Http\Controllers\Api\MenuItemsController;


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
    //-------------Payments-------------------
    Route::post('/addPaymentCard',[PaymentsController::class,'addPaymentCard']);
    Route::get('/getUserCards/{id}',[PaymentsController::class,'getUserCards']);
    Route::delete('/removeCardById/{id}',[PaymentsController::class,'removeCardById']);
    Route::post('/setupPaymentAccount',[PaymentsController::class,'setupPaymentAccount']);
    //------------School Shop-----------------
    Route::get('/getSchoolShop',[SchoolShopsController::class,'getSchoolShop']);
    Route::post('/addItem',[SchoolShopsController::class,'addItem']);
    Route::get('/getShopItems',[SchoolShopsController::class,'getShopItems']);
    Route::get('/editShopItem/{id}',[SchoolShopsController::class,'editShopItem']);
    Route::put('/updateShopItem/{id}',[SchoolShopsController::class,'updateShopItem']);
    Route::delete('/deleteShopItem/{id}',[SchoolShopsController::class,'deleteShopItem']);
    //------------Menus-------------------
    Route::post('/addMenu',[MenusController::class,'addMenu']);
    Route::get('/editMenu/{id}',[MenusController::class,'editMenu']);
    Route::put('/updateMenu/{id}',[MenusController::class,'updateMenu']);
    Route::delete('/deleteMenu/{id}',[MenusController::class,'deleteMenu']);
    Route::get('/getMenusBySchoolId/{id}',[MenusController::class,'getMenusBySchoolId']);
    //------------Menu Items--------------
    Route::post('/addMenuItem',[MenuItemsController::class,'addMenuItem']);
    Route::get('/editMenuItem/{id}',[MenuItemsController::class,'editMenuItem']);
    Route::put('/updateMenuItem/{id}',[MenuItemsController::class,'updateMenuItem']);
    Route::delete('/deleteMenuItem/{id}',[MenuItemsController::class,'deleteMenuItem']);
    Route::get('/getItemsByMenuId/{id}',[MenuItemsController::class,'getItemsByMenuId']);

    //----------stripe----------
    Route::post('/createCard', [PaymentsController::class, 'createCard']);
    Route::post('/createCustomer', [PaymentsController::class, 'createCustomer']);
    Route::post('/getPaymentMethods', [PaymentsController::class, 'getCustomerPaymentMethods']);
    Route::get('/removePaymentMethod', [PaymentsController::class, 'removePaymentMethod']);
    Route::get('/getWallet/{id}', [PaymentsController::class, 'getWallet']);


    Route::post('/setupPaymentInformation', [PaymentsController::class, 'setupPaymentInformation']);
    Route::post('/addExternalAccount', [PaymentsController::class, 'addExternalAccount']);
    Route::get('/getExternalAccounts', [PaymentsController::class, 'getExternalAccounts']);

    Route::post('payment/initiate', [PaymentsController::class, 'initiatePayment']);
    Route::post('payment/complete', [PaymentsController::class, 'completePayment']);
    Route::post('payment/failure', [PaymentsController::class, 'failPayment']);
});
