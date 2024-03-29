<?php

use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Mail\SendEmail;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documentation', function () {
    return view('documentation');
});
Route::get("user/{id?}", function ($id = null) {
    return 'User ' . $id;
});

Route::get('pdf', [PdfController::class, 'getPostPdf']);

Route::get('/sendMail', function () {
    $details = [
        "subject" => "Activation de compte sur FRIK-SMS",
        "message" => "Hello bro",
    ];
    Mail::to("gogochristian009@gmail.com")->send(new SendEmail($details));
    dd("MESSAGE ENVOYE AVEC SUCCèS");
});

Route::get('/mail-template', function () {
    return view("emails.new_mail");
});