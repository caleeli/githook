<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
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
    protected $signature = 'mail {email : Email address} {subject : Email subject} {body : Message body to be sent} {logUrl? : Optional log url} {from?}';

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
        $body = $this->argument('body');
        $logUrl = $this->argument('logUrl');
        $from = $this->argument('from');
        $data = ['email' => $email, 'body' => $body, 'logUrl' => $logUrl];
        Mail::send('mail', $data, function ($mail) use ($email, $subject, $from) {
            if ($from) {
                $mail->from($from)
                ->to($email)
                ->subject($subject);
            } else {
                $mail->to($email)
                ->subject($subject);
            }
        });
    }
}
