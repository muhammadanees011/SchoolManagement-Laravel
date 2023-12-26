<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;
use App\Models\Wallet;
use App\Models\User;

class RegisteredUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registered:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Cron Job running at ". now());

        $tables = DB::connection('remote_mysql')->table('ebStudent')->whereDate('created',today())->get();
        $users = DB::table('users')->get();
        
        $tableEmails = $tables->pluck('eMail')->toArray();
        $userEmails = $users->pluck('email')->toArray();
        // Identify emails that are in $tables but not in $users
        $newEmails = array_diff($tableEmails, $userEmails);
        // Fetch the records corresponding to the new emails
        $newRecords = $tables->whereIn('eMail', $newEmails);

        foreach ($newRecords as $record) {
            //----------STORE NEW STUDENT------------
            $randomPassword = Str::random(10);
            $studentName = $record->firstName . ' ' . $record->surname;
            try{
                $userId=DB::table('users')->insertGetId([
                    'first_name' => $record->firstName,
                    'last_name' => $record->surname,
                    'email' => $record->eMail,
                    'password' => bcrypt($randomPassword),
                    'role' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                //----------SEND WELCOME MAIL--------------
                $mailData = [
                    'title' => 'Congratulations you have successfully created your StudentPay account!',
                    'body' => $randomPassword,
                    'user_name'=> $studentName,
                ];
                Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
                //----------CREATE STRIPE CUSTOMER------------
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                $customer=$stripe->customers->create([
                'name' => $studentName,
                'email' => $record->eMail,
                ]);
                $user=User::where('id',$userId)->first();
                $user->stripe_id=$customer->id;
                $user->created_at=now();
                $user->updated_at=now();
                $user->save();
                //----------CREATE STUDENT WALLET-------------
                $userWallet=new Wallet();
                $userWallet->user_id=$userId;
                $userWallet->ballance=0;
                $userWallet->save();
                } catch (\Exception $e) {
            }
    
        }
    }
}
