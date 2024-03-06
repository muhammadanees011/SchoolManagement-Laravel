<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;


class addAmountFSM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:fsmamount';

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
        info("FSM Cron Job running at ". now());
        // $student = Student::where('fsm_activated', true)->update(['fsm_amount' => 3]);
        $student = Student::whereNotNull('fsm_amount')->update(['fsm_amount' => 3]);

    }
}
