<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Right;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class USER_HELPER extends BASE_HELPER
{
    ##======== REGISTER VALIDATION =======##
    static function register_rules(): array
    {
        return [
            'firstname' => 'required',
            'lastname' => 'required',
            'phone' => ['required', Rule::unique('users')],
            'email' => ['required', 'email', Rule::unique('users')],
            // 'password' => ['required'],
        ];
    }

    static function register_messages(): array
    {
        return [
            'firstname.required' => 'Le champ Firstname est réquis!',
            'lastname.required' => 'Le champ Lastname est réquis!',
            'email.required' => 'Le champ Email est réquis!',
            'email.email' => 'Ce champ est un mail!',
            'email.unique' => 'Ce mail existe déjà!',
            'phone.required' => 'Le champ Phone est réquis!',
            'phone.unique' => 'Un compte existe déjà au nom de ce phone!',
        ];
    }

    static function Register_Validator($formDatas)
    {
        #
        $rules = self::register_rules();
        $messages = self::register_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }

    ##======== NEW PASSWORD VALIDATION =======##
    static function NEW_PASSWORD_rules(): array
    {
        return [
            'old_password' => 'required',
            'new_password' => 'required',
        ];
    }

    static function NEW_PASSWORD_messages(): array
    {
        return [
            // 'new_password.required' => 'Veuillez renseigner soit votre username,votre phone ou soit votre email',
            // 'password.required' => 'Le champ Password est réquis!',
        ];
    }

    static function NEW_PASSWORD_Validator($formDatas)
    {
        #
        $rules = self::NEW_PASSWORD_rules();
        $messages = self::NEW_PASSWORD_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }

    ##======== LOGIN VALIDATION =======##
    static function login_rules(): array
    {
        return [
            'account' => 'required',
            'password' => 'required',
        ];
    }

    static function login_messages(): array
    {
        return [
            'account.required' => 'Le champ account est réquis!',
            'password.required' => 'Le champ Password est réquis!',
        ];
    }

    static function Login_Validator($formDatas)
    {
        #
        $rules = self::login_rules();
        $messages = self::login_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }



    ##======== ATTACH VALIDATION =======##
    static function ATTACH_rules(): array
    {
        return [
            'user_id' => 'required',
            'right_id' => 'required',
        ];
    }

    static function ATTACH_messages(): array
    {
        return [
            // 'user_id.required' => 'Veuillez renseigner soit votre username,votre phone ou soit votre email',
            // 'password.required' => 'Le champ Password est réquis!',
        ];
    }

    static function ATTACH_Validator($formDatas)
    {
        #
        $rules = self::ATTACH_rules();
        $messages = self::ATTACH_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }

    #CREATION D'UN USER
    static function createUser($formData)
    {
        $user = User::create($formData); #ENREGISTREMENT DU USER DANS LA DB

        $username = Get_Username($user, "MAST");
        $user->username = $username;
        $user->rang_id = 2;
        $user->profil_id = 6;
        $user->password = $username;

        if (request()->user()) { #Si le user(admin) est connecté et essaie de créer un compte pour autruit
            $user->owner = request()->user()->id;
        }

        $active_compte_code = Get_compte_active_Code($user, "ACT");
        $user->active_compte_code = $active_compte_code;
        $user->compte_actif = 0;
        $user->save();

        #===== ENVOIE D'SMS AU USER DU COMPTE =======~####

        $sms_login =  Login_To_Frik_SMS();

        if ($sms_login['status']) {
            $token =  $sms_login['data']['token'];
            #===== ENVOIE D'SMS AU USER DU COMPTE POUR CREATION DE COMPTE =======~####
            Send_SMS(
                $user->phone,
                "Votre compte Master a été crée avec succès sur FRIK-SMS. Voici ci-dessous vos identifiants de connexion: Username::" . $username,
                $token
            );

            #===== ENVOIE D'SMS AU USER DU COMPTE POUR ACTIVER LE COMPTE =======~####
            Send_SMS(
                $user->phone,
                "Votre compte n'est pas encore actif. Veuillez l'activer en utilisant le code ci-dessous :" . $active_compte_code,
                $token
            );
        }
        return self::sendResponse($user, 'User crée avec succès!!');
    }

    #AUTHENTIFICATION D'UN USER
    static function userAuthentification($request)
    {
        if (is_numeric($request->get('account'))) {
            $credentials  =  ['phone' => $request->get('account'), 'password' => $request->get('password')];
        } elseif (filter_var($request->get('account'), FILTER_VALIDATE_EMAIL)) {
            $credentials  =  ['email' => $request->get('account'), 'password' => $request->get('password')];
        } else {
            $credentials  =  ['username' => $request->get('account'), 'password' => $request->get('password')];
        }

        if (Auth::attempt($credentials)) { #SI LE USER EST AUTHENTIFIE
            $user = Auth::user();
            ###VERIFIONS SI LE COMPTE EST ACTIF
            if (!$user->compte_actif) {
                return self::sendError("Ce compte n'est pas actif! Veuillez l'activer", 404);
            }

            ###
            $token = $user->createToken('MyToken', ['api-access'])->accessToken;
            $user['token'] = $token;

            $user['rang'] = $user->rang;
            $user['profil'] = $user->profil;
            $user['token'] = $token;

            #renvoie des droits du user 
            $attached_rights = $user->drts; #drts represente les droits associés au user par relation #Les droits attachés
            // return $attached_rights;

            if ($attached_rights->count() == 0) { #si aucun droit ne lui est attaché
                if (Is_User_AN_ADMIN($user->id)) { #s'il est un admin
                    $user['rights'] = All_Rights();
                } else {
                    $user['rights'] = User_Rights($user->rang['id'], $user->profil['id']);
                }
            } else {
                $user['rights'] = $attached_rights; #Il prend uniquement les droits qui lui sont attachés
            }

            #RENVOIE D'ERREURE VIA **sendResponse** DE LA CLASS BASE_HELPER
            return self::sendResponse($user, 'Vous etes connecté(e) avec succès!!');
        }

        #RENVOIE D'ERREURE VIA **sendResponse** DE LA CLASS BASE_HELPER
        return self::sendError('Connexion échouée! Vérifiez vos données puis réessayez à nouveau!', 500);
    }

    static function activateAccount($request)
    {
        if (!$request->get("active_compte_code")) {
            return self::sendError("Le Champ **active_compte_code** est réquis", 505);
        }
        $user =  User::where(["active_compte_code" => $request->active_compte_code])->get();
        if ($user->count() == 0) {
            return self::sendError("Ce Code ne corresponds à aucun compte! Veuillez saisir le vrai code", 505);
        }

        $user = $user[0];
        ###VERIFIONS SI LE COMPTE EST ACTIF DEJA
        // return $user->compte_actif;
        if ($user->compte_actif) {
            return self::sendError("Ce compte est déjà actif!", 505);
        }

        $user->compte_actif = 1;
        $user->save();

        return self::sendResponse($user, 'Votre compte à été activé avec succès!!');
    }

    static function getUsers()
    {
        $users =  User::with(["rang", "profil"])->where(["owner" => request()->user()->id])->get();

        foreach ($users as $user) {
            #renvoie des droits du user 
            $attached_rights = $user->drts; #drts represente les droits associés au user par relation #Les droits attachés
            // return $attached_rights;

            if ($attached_rights->count() == 0) { #si aucun droit ne lui est attaché
                if (Is_User_AN_ADMIN($user->id)) { #s'il est un admin
                    $user['rights'] = All_Rights();
                } else {
                    $user['rights'] = User_Rights($user->rang['id'], $user->profil['id']);
                }
            } else {
                $user['rights'] = $attached_rights; #Il prend uniquement les droits qui lui sont attachés
            }
        }
        return self::sendResponse($users, 'Touts les utilisatreurs récupérés avec succès!!');
    }

    static function retrieveUsers($id)
    {
        $user = User::with(["rang", "profil"])->where(['id' => $id, "owner" => request()->user()->id])->get();
        if ($user->count() == 0) {
            return self::sendError("Ce utilisateur n'existe pas!", 404);
        }

        $user = $user[0];
        #renvoie des droits du user 
        $attached_rights = $user->drts; #drts represente les droits associés au user par relation #Les droits attachés
        // return $attached_rights;

        if ($attached_rights->count() == 0) { #si aucun droit ne lui est attaché
            if (Is_User_AN_ADMIN($id)) { #s'il est un admin
                $user['rights'] = All_Rights();
            } else {
                $user['rights'] = User_Rights($user->rang['id'], $user->profil['id']);
            }
        } else {
            $user['rights'] = $attached_rights; #Il prend uniquement les droits qui lui sont attachés
        }

        return self::sendResponse($user, "Utilisateur récupéré(e) avec succès:!!");
    }

    static function _demandReinitializePassword($request)
    {

        if (!$request->get("username")) {
            return self::sendError("Le Champ username est réquis!", 404);
        }
        $username = $request->get("username");

        $user = User::where(['username' => $username])->get();

        if (count($user) == 0) {
            return self::sendError("Ce compte n'existe pas!", 404);
        };
        // return "dfgh";

        #
        $user = $user[0];
        $pass_code = Get_passCode($user, "PASS");
        $user->pass_code = $pass_code;
        $user->pass_code_active = 1;
        $user->save();

        #===== ENVOIE D'SMS AUX ELECTEURS DU VOTE =======~####

        $sms_login =  Login_To_Frik_SMS();

        if ($sms_login['status']) {
            $token =  $sms_login['data']['token'];
            Send_SMS(
                $user->phone,
                "Demande de réinitialisation éffectuée avec succès! sur frik6sms! Voici vos informations de réinitialisation de password ::" . $pass_code,
                $token
            );
        }

        return self::sendResponse($user, "Demande de réinitialisation éffectuée avec succès! Veuillez vous connecter avec le code qui vous a été envoyé par phone ");
    }

    static function _reinitializePassword($request)
    {

        $pass_code = $request->get("pass_code");

        if (!$pass_code) {
            return self::sendError("Ce Champ pass_code est réquis!", 404);
        }

        $new_password = $request->get("new_password");

        if (!$new_password) {
            return self::sendError("Ce Champ new_password est réquis!", 404);
        }

        $user = User::where(['pass_code' => $pass_code])->get();

        if (count($user) == 0) {
            return self::sendError("Ce code n'est pas correct!", 404);
        };

        $user = $user[0];
        #Voyons si le passs_code envoyé par le user est actif
        if ($user->pass_code_active == 0) {
            return self::sendError("Ce Code a déjà été utilisé une fois!Veuillez faire une autre demande de réinitialisation", 404);
        }

        #UPDATE DU PASSWORD
        $user->update(['password' => $new_password]);

        #SIGNALONS QUE CE pass_code EST D2J0 UTILISE
        $user->pass_code_active = 0;
        $user->save();


        #===== ENVOIE D'SMS AUX ELECTEURS DU VOTE =======~####

        $sms_login =  Login_To_Frik_SMS();

        if ($sms_login['status']) {
            $token =  $sms_login['data']['token'];
            Send_SMS(
                $user->phone,
                "Réinitialisation de password éffectuée avec succès sur FRIK-SMS!",
                $token
            );
        }

        return self::sendResponse($user, "Réinitialisation éffectuée avec succès!");
    }

    static function userLogout($request)
    {
        $request->user()->token()->revoke();
        // DELETING ALL TOKENS REMOVED
        // Artisan::call('passport:purge');
        return self::sendResponse([], 'Vous etes déconnecté(e) avec succès!');
    }

    static function _updatePassword($formData, $id)
    {
        $user = User::where(['id' => $id])->get();
        if (count($user) == 0) {
            return self::sendError("Ce utilisateur n'existe pas!", 404);
        };

        if (Hash::check($formData["old_password"], $user[0]->password)) { #SI LE old_password correspond au password du user dans la DB
            $user[0]->update(["password" => $formData["new_password"]]);
            return self::sendResponse($user, 'Mot de passe modifié avec succès!');
        }
        return self::sendError("Votre mot de passe est incorrect", 505);
    }

    static function rightAttach($formData)
    {
        $user = User::where(['id' => $formData['user_id'], 'owner' => request()->user()->id])->get();
        if (count($user) == 0) {
            return self::sendError("Ce utilisateur n'existe pas!", 404);
        };

        $right = Right::where('id', $formData['right_id'])->get();
        if (count($right) == 0) {
            return self::sendError("Ce right n'existe pas!", 404);
        };

        $user = User::find($formData['user_id']);
        $right = Right::find($formData['right_id']);

        $right->user_id = $user->id;
        $right->save();

        return self::sendResponse([], "User attaché au right avec succès!!");
    }

    static function rightDesAttach($formData)
    {
        $user = User::where(['id' => $formData['user_id'], 'owner' => request()->user()->id])->get();
        if (count($user) == 0) {
            return self::sendError("Ce utilisateur n'existe pas!", 404);
        };

        $right = Right::where('id', $formData['right_id'])->get();
        if (count($right) == 0) {
            return self::sendError("Ce right n'existe pas!", 404);
        };

        $user = User::find($formData['user_id']);
        $right = Right::find($formData['right_id']);

        $right->user_id = null;
        $right->save();

        return self::sendResponse([], "User Dettaché du right avec succès!!");
    }
}
