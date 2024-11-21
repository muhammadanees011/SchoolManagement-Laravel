<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Mail\SupportEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\MicrosoftGraphService;

class SupportController extends Controller
{
    protected $graphService;

    public function __construct(MicrosoftGraphService $graphService)
    {
        $this->graphService = $graphService;
    }
    
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

        $user=Auth::user();
        $recipient_mail;
        if($user->role=='student'){
            $student=Student::where('user_id',$user->id)->first();
            if($student){
                $school=School::find($student->school_id);
            }
        }else if($user->role=='staff'){
            $staff=Staff::where('user_id',$user->id)->first();
            if($staff){
                $school=School::find($staff->school_id);
            }
        }else{
            return response()->json(['errors'=>['you can not send the mail']], 422);
        }
        if($school && $school->finance_coordinator_email){
            $recipient_mail=$school->finance_coordinator_email;
            //----------SEND SUPPORT MAIL--------------
            // Mail::to($recipient_mail)->send(new SupportEmail($data));

            $data = [
                'fullname' => $request->fullName,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ];
    
            $to = [$recipient_mail];
            $subject = $request->subject;
            $bodyView = 'emails.SupportEmail';
            $status = $this->graphService->sendEmail($to, $subject, $bodyView,null,null, $data);

        }else{
            return response()->json(['errors'=>['Recipient Email Not Found']], 422); 
        }
    }
    
}
