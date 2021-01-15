<?php

namespace AppBundle\Service;

use Twilio\Rest\Client as TwilioClient;
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
        $gateway = $this->settingsManager->get('sms_gateway');

        switch ($gateway) {
            case 'mailjet':
                return $this->sendWithMailjet($text, $to);
            case 'twilio':
                return $this->sendWithTwilio($text, $to);
        }

        throw new \Exception(sprintf('Gateway "%s" not supported', $gateway));
    }

    private function sendWithMailjet($text, $to)
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

    /**
     * @link https://www.twilio.com/docs/libraries/php
     */
    private function sendWithTwilio($text, $to)
    {
        $config = $this->settingsManager->get('sms_gateway_config');
        $config = json_decode($config, true);

        // Your Account SID and Auth Token from twilio.com/console
        $sid = $config['sid'];
        $token = $config['auth_token'];
        $client = new TwilioClient($sid, $token);

        // Use the client to do fun stuff like send text messages!
        $client->messages->create(
            // the number you'd like to send the message to
            $to,
            [
                // A Twilio phone number you purchased at twilio.com/console
                'from' => $config['from'],
                // the body of the text message you'd like to send
                'body' => $text
            ]
        );

        // FIXME Return real value
        return true;
    }
}
