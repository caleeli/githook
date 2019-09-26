<?php

namespace App\Console\Commands;

use Mail;
use Illuminate\Console\Command;

/**
 * Class MailCommand
 *
 * @category Console_Command
 * @package  App\Console\Commands
 */
class MailCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'mail {email : Email address} {subject : Email subject} {message : Message to be sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mail a message';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');
        $subject = $this->argument('subject');
        $message = $this->argument('message');
        $data = ['email' => $email, 'message' => $message];
        Mail::send('mail', $data, function ($mail) use ($email, $subject) {
            $mail->to($email)
            ->subject($subject);
        });
    }
}
