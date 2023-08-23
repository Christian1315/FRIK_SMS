<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DeveloperKey;
use App\Models\Expeditor;
use App\Models\Sms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Illuminate\Validation\Rule;

class DEVELOPER_HELPER extends BASE_HELPER
{

    static function DEVELOPER_key_rules(): array
    {
        return [
            'key' => ['required', Rule::unique("developers")],
        ];
    }

    static function Developer_key_messages(): array
    {
        return [
            'key.required' => 'Le key est réquis!',
        ];
    }

    static function Developer_key_Validator($formDatas)
    {
        $rules = self::Developer_key_rules();
        $messages = self::Developer_key_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }


    static function sms_rules(): array
    {
        return [
            'phone' => ['required', 'numeric'],
            'message' => ['required', 'max:300'],
            'expediteur' => ['required'],
        ];
    }

    static function sms_messages(): array
    {
        return [
            'phone.required' => 'Le champ phone est réquis!',
            'expediteur.required' => 'Le champ expediteur est réquis!',
            'phone.numeric' => 'Le phone doit être un nombre entier',
            'message.required' => 'Le champ message est réquis!',
            'message.max' => 'Le message ne doit pas depasser 300 caractères!',
        ];
    }

    static function Sms_Validator($formDatas)
    {
        $rules = self::sms_rules();
        $messages = self::sms_messages();

        $validator = Validator::make($formDatas, $rules, $messages);
        return $validator;
    }

    static function _createDeveloperKey()
    {
        ##VERIFIONS SI CE USER DISPOSE DEJA D'UNE CLE
        $dev = DeveloperKey::where(["owner" => request()->user()->id])->get();
        if ($dev->count() != 0) {
            return self::sendError("Vous disposez déjà d'une clé API", 505);
        }
        $Developer = new DeveloperKey();
        $Developer->key = Str::uuid();
        $Developer->owner = request()->user()->id;
        $Developer->save();
        return self::sendResponse($Developer, 'La clé API a été générée avec succès!!');
    }

    static function retrieveDeveloperKey($id, $innerCall = false)
    {
        $Developer = DeveloperKey::with(["user"])->where('id', $id)->get();
        if ($Developer->count() == 0) {
            return self::sendError("Ce Developer n'existe pas!!", 404);
        }
        #$innerCall: Cette variable determine si la function **retrieveDeveloper** est appéle de l'intérieur
        if ($innerCall) {
            return $Developer;
        }
        return self::sendResponse($Developer, 'Developer récuperé avec succès!!');
    }

    static function allDeveloperKeys()
    {
        $Developers = DeveloperKey::with(["user"])->orderBy("id", "desc")->get();
        return self::sendResponse($Developers, 'Developers récupérés avec succès!!');
    }

    static function _deleteDeveloperKey($id)
    {
        $Developer = DeveloperKey::find($id);

        if (!$Developer) { #QUAND **$Developer** n'existe pas
            return self::sendError('Ce Developer n\'existe pas!', 404);
        };

        $Developer->delete(); #SUPPRESSION De Developer;
        return self::sendResponse($Developer, "Ce Expediteur a été supprimé avec succès!!");
    }

    static function _updateDeveloperKey($id)
    {
        $Developer = DeveloperKey::where(["id" => $id, "owner" => request()->user()->id])->get();

        if ($Developer->count() == 0) {
            return self::sendError("Cette clé API n'existe pas", 404);
        }
        $Developer = $Developer[0];
        $Developer->key = Str::uuid();
        $Developer->save();
        return self::sendResponse($Developer, "La Clé API de ce développeur a été modifié avec succès!!");
    }

    static function sendSms($request, $phone, $message)
    {
        ### VERIFIONS SI LA REQUETE CONTIENT la Clé API dans le header
        $api_key = $request->header()["key"][0];
        if ($api_key == '') {
            return self::sendError("La clé API est réquise dans le header", 505);
        }

        ### VERIFIONS SI Clé API Existe
        $dev = DeveloperKey::where(["key" => $api_key])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API n'existe pas", 505);
        }

        ### VERIFIONS SI Clé API EST VALIDE(voyons si elle appartient au user en question)
        $dev = DeveloperKey::where(["key" => $api_key, "owner" => request()->user()->id])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API ne vous appartient pas", 505);
        }

        ####==== VOYONS SI L'EXPEDITEUR  EXISTE=======###
        $expeditor = Expeditor::where(["name" => $request->get("expediteur")])->get();
        if ($expeditor->count() == 0) {
            return self::sendError("Ce expéditeur n'existe pas!", 404);
        }

        ####==== VOYONS SI L'EXPEDITEUR APPARTIENT AU USER EN QUESTION =======###
        $expeditor = Expeditor::where(["name" => $request->get("expediteur"), "owner" => request()->user()->id])->get();
        if ($expeditor->count() == 0) {
            return self::sendError("Ce expéditeur ne vous appartient pas!", 404);
        }

        ##===== Verifions si l'expediteur est valide ou pas =========####
        if ($expeditor[0]->status != 3) {
            return self::sendError("Ce expéditeur existe, mais n'est pas validé!", 404);
        }
        $BASE_URL = env("BASE_URL");
        $API_KEY = env("API_KEY");
        $CLIENT_ID = env("CLIENT_ID");
        $EXPEDITEUR = $request->get("expediteur");

        $DESTINATAIRE = $phone;
        $MESSAGE = $message;

        $url = $BASE_URL . "/send"; #URL D'ENVOIE DE L'SMS

        $smsData   = array(
            'from' => $EXPEDITEUR, //l'expediteur
            'to' => '' . $DESTINATAIRE . '', //destination au format international sans "+" ni "00". Ex: 22890443679
            'type' => 1, //type de message text et flash
            'message' => $MESSAGE, //le contenu de votre sms
            'dlr' => 's' // 1 pour un retour par contre 0
        );

        $response = Http::withHeaders([
            'APIKEY' => $API_KEY,
            'CLIENTID' => $CLIENT_ID
        ])->post($url, $smsData);

        $result = json_decode($response);
        if (!$result->status === "ACT") { #LE MESSAGE N'A PAS ETE ENVOYE
            return self::sendError("L'envoie a échoué", 505);
        }

        #ENREGISTREMENT DES INFOS DE L'SMS DANS LA DB

        $data = [
            "messageId" => $result->messageId,
            "from" => $result->from,
            "to" => $result->to,
            "message" => $result->message,
            "type" => $result->type,
            "route" => $result->route,
            "sms_count" => $result->sms_count,
            "amount" => $result->amount,
            "currency" => $result->currency,
            "status" => $result->status
        ];

        $sms = Sms::create($data);
        $sms->owner = request()->user()->id;
        $sms->save();

        return self::sendResponse($result, 'Sms envoyé avec succès!!');
    }

    static function allSms($request)
    {
        ### VERIFIONS SI LA REQUETE CONTIENT la Clé API dans le header
        $api_key = $request->header()["key"][0];
        if ($api_key == '') {
            return self::sendError("La clé API est réquise dans le header", 505);
        }

        ### VERIFIONS SI Clé API Existe
        $dev = DeveloperKey::where(["key" => $api_key])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API n'existe pas", 505);
        }

        ### VERIFIONS SI Clé API EST VALIDE(voyons si elle appartient au user en question)
        $dev = DeveloperKey::where(["key" => $api_key, "owner" => request()->user()->id])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API ne vous appartient pas", 505);
        }

        $sms =  Sms::where(["owner" => request()->user()->id])->orderBy("id", "desc")->get();
        return self::sendResponse($sms, 'Tout les sms récupérés avec succès!!');
    }

    static function retrieveSms($request, $id)
    {
        ### VERIFIONS SI LA REQUETE CONTIENT la Clé API dans le header
        $api_key = $request->header()["key"][0];
        if ($api_key == '') {
            return self::sendError("La clé API est réquise dans le header", 505);
        }

        ### VERIFIONS SI Clé API Existe
        $dev = DeveloperKey::where(["key" => $api_key])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API n'existe pas", 505);
        }

        ### VERIFIONS SI Clé API EST VALIDE(voyons si elle appartient au user en question)
        $dev = DeveloperKey::where(["key" => $api_key, "owner" => request()->user()->id])->get();
        if ($dev->count() == 0) {
            return self::sendError("La clé API ne vous appartient pas", 505);
        }

        $sms = Sms::where(["id" => $id, "owner" => request()->user()->id])->get();
        if ($sms->count() == 0) {
            return self::sendError("Ce sms n'existe pas!", 404);
        }
        return self::sendResponse($sms, "Sms récupéré avec succès:!!");
    }
}