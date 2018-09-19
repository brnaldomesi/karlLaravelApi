<?php

namespace App\Jobs;

use App\Model\Company;
use App\Model\Driver;
use App\Model\RunningError;
use App\Model\Sale;
use App\QueueName;
use App\Method\UrlSpell;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class SendEmailSalePasswordJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $email;
    private $password;

    public function __construct($email,$password)
    {
        $this->email = $email;
        $this->password = $password;
        $this->onQueue(QueueName::EmailSalePassword);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function work()
    {

        $sale = Sale::leftJoin("users","users.id","=","sales.user_id")
            ->where("users.company_id",0)
            ->where("users.email",$this->email)
            ->select("users.lang","sales.sale_id")->first();

        \app('translator')->setLocale($sale->lang);
        $subTitle = Lang::get("password.welcome");
        $view = view('re_set_sl_pwd_email', ["saleId" => $sale->sale_id, "type" => 1,'pwd'=>$this->password])->render();
        $host = $_SERVER['SMTP_HOST'];
        $email = $_SERVER['SMTP_EMAIL'];
        $pwd = $_SERVER['SMTP_PWD'];
        $port = $_SERVER['SMTP_PORT'];
        $mail = $this->initPhpEmailBox($host, $email, $pwd, $port);

        try {
            $mail->setFrom($email, 'KARL Support');
            $mail->Subject = $subTitle;
            $mail->addAddress($this->email);
            $mail->isHTML(true);
            $mail->msgHTML($view);
            if (!$mail->send()) {
                \Log::error($mail->ErrorInfo);
            } else {
                RunningError::recordRunningError(
                    RunningError::TYPE_EMAIL,
                    RunningError::STATE_SUCCESS,
                    "send customer success"
                );
            }
        } catch (\Exception $ex) {
            \Log::error($ex->getMessage());
        }
    }
}
