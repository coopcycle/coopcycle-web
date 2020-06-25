<?php

namespace AppBundle\Service;

use Mailjet\Client as MailjetClient;
use Mailjet\Resources as MailjetResources;

class SmsManager
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    private function getMailjetClient()
    {
        $config = $this->settingsManager->get('sms_gateway_config');
        $config = json_decode($config, true);

        $apiToken = $config['api_token'];

        return new MailjetClient($apiToken,
            NULL, true,
            ['url' => 'api.mailjet.com', 'version' => 'v4', 'call' => false]
        );
    }

    public function send($text, $to)
    {
        $mj = $this->getMailjetClient();

        $body = [
            'Text' => $text,
            'To' => $to,
            'From' => $this->settingsManager->get('brand_name'),
        ];

        $response = $mj->post(MailjetResources::$SmsSend, ['body' => $body]);

        // print_r($response->getData());
        //
        // Array
        // (
        //     [ID] => 9808e53a-ddcd-45ae-ba93-4a15a5f7c640
        //     [From] => CoopCycle
        //     [To] => +33679066335
        //     [Status] => Array
        //         (
        //             [Code] => 1
        //             [Name] => sent_pending
        //             [Description] => Message is being sent
        //         )

        //     [Cost] => Array
        //         (
        //             [Value] => 0.04
        //             [Currency] => EUR
        //         )

        //     [CreationTS] => 1582647757
        //     [Text] => Hello from CoopCycle
        //     [SmsCount] => 1
        // )

        return $response->success();
    }
}
