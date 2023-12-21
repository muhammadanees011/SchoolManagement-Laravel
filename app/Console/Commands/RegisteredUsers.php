<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WelcomeEmail;
use Carbon\Carbon;

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
            $randomPassword = Str::random(10);
            try{
                DB::table('users')->insert([
                    'first_name' => $record->firstName,
                    'last_name' => $record->surname,
                    'email' => $record->eMail,
                    'password' => bcrypt($randomPassword),
                    'role' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }catch (\Exception $exception) {
                DB::rollback();
                if (('APP_ENV') == 'local') {
                    dd($exception);
                } else {
                }
            }
            $mailData = [
                'title' => 'Congratulations you have successfully created your StudentPay account!',
                'body' => $randomPassword
            ];
            Mail::to($record->eMail)->send(new WelcomeEmail($mailData));
            $res="Email is sent successfully.";
        }
    }
}
