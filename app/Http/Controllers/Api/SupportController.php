<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Mail\SupportEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    public function sendSupportEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required',
            'email' =>'required',
            'subject' =>'required',
            'message' =>'required',
        ]);
        if ($validator->fails())
        {
            return response()->json(['errors'=>$validator->errors()->all()], 422);
        }

        $data['fullname']=$request->fullName;
        $data['email']=$request->email;
        $data['subject']=$request->subject;
        $data['message']=$request->message;
        //----------SEND SUPPORT MAIL--------------
        Mail::to('itsanees011@gmail.com')->send(new SupportEmail($data));
    }
    
}
