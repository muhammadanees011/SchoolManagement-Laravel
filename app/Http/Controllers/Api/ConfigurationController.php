<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Configuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ConfigurationController extends Controller
{
    public function index(){
        $configuration= Configuration::first();
        return response()->json($configuration, 200);
    }

    public function save(Request $request){
        $validator = Validator::make($request->all(), [
            'ms_client_id' => 'required',
            'ms_client_secret' => 'required',
            'ms_tenent_id' => 'required',
            'ms_email_account' => 'required',
            ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }

        try {
            $configuration=Configuration::first();
            if(!$configuration){                
                $configuration=new Configuration();
            }
            $configuration->ms_client_id=$request->ms_client_id;
            $configuration->ms_client_secret=$request->ms_client_secret;
            $configuration->ms_tenent_id=$request->ms_tenent_id;
            $configuration->ms_email_account=$request->ms_email_account;
            $configuration->save();
            return response()->json('Successfully Updated', 200);
        } catch (\Exception $exception) {
            DB::rollback();
            if (('APP_ENV') == 'local') {
                return response()->json($exception, 500);
            } else {
                return response()->json($exception, 500);
            }
        }
    }
}
