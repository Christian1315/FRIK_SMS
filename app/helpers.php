<?php

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function userCount()
{
    return count(User::all()) + 1;
}

function Custom_Timestamp()
{
    $date = new DateTimeImmutable();
    $micro = (int)$date->format('Uu'); // Timestamp in microseconds
    return $micro;
}

function Get_Username($user, $type)
{
    $created_date = $user->created_at;

    $year = explode("-", $created_date)[0]; ##RECUPERATION DES TROIS PREMIERS LETTRES DU USERNAME
    $an = substr($year, -2);

    $username =  $type . $an . userCount();
    return $username;
}

##======== CE HELPER PERMET D'ENVOYER DES SMS VIA PHONE ==========## 

function Login_To_Frik_SMS()
{
    $response = Http::post(env("SEND_SMS_API_URL") . "/api/v1/login", [
        "username" => "admin",
        "password" => "admin",
    ]);

    return $response;
}

function Send_SMS($phone, $message, $token)
{

    $response = Http::withHeaders([
        'Authorization' => "Bearer " . $token,
    ])->post(env("SEND_SMS_API_URL") . "/api/v1/sms/send", [
        "phone" => $phone,
        "message" => $message,
        "expediteur" => env("EXPEDITEUR"),
    ]);

    $response->getBody()->rewind();
}
