<?php

namespace AppBundle\Service;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\RemotePushToken;
use Kreait\Firebase\Factory as FirebaseFactory;
use Kreait\Firebase\Exception\ServiceAccountDiscoveryFailed;
use Kreait\Firebase\Messaging\CloudMessage;

class RemotePushNotificationManager
{
    private $firebaseFactory;
    private $apns;
    private static $enabled = true;

    public function __construct(
        FirebaseFactory $firebaseFactory,
        \ApnsPHP_Push $apns,
        string $apnsCertificatePassPhrase)
    {
        $this->firebaseFactory = $firebaseFactory;

        $apns->setProviderCertificatePassphrase($apnsCertificatePassPhrase);
        $this->apns = $apns;
    }

    public static function isEnabled()
    {
        return self::$enabled;
    }

    public static function disable()
    {
        self::$enabled = false;
    }

    public static function enable()
    {
        self::$enabled = true;
    }

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/http-server-ref
     */
    private function fcm($message, array $tokens, $data)
    {
        if (count($tokens) === 0) {
            return;
        }

        try {
            $firebaseMessaging = $this->firebaseFactory->createMessaging();
        } catch (ServiceAccountDiscoveryFailed $e) {
            // TODO Log error
            return;
        }


        // @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
        // @see https://developer.android.com/guide/topics/ui/notifiers/notifications#ManageChannels
        $payload = [
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'coopcycle_important',
                ],
            ],
            'notification' => [
                'title' => $message,
                'body' => $message,
            ],
        ];

        // TODO Make sure data are key/value pairs as strings
        if (!empty($data)) {

            $dataFlat = [];
            foreach ($data as $key => $value) {
                if (!is_string($value)) {
                    $value = json_encode($value);
                }
                $dataFlat[$key] = $value;
            }

            $payload['data'] = $dataFlat;
        }

        $message = CloudMessage::fromArray($payload);

        $deviceTokens = array_map(function (RemotePushToken $token) {
            return $token->getToken();
        }, $tokens);

        // Make sure to have a zero-indexed array
        $deviceTokens = array_values($deviceTokens);

        try {
            $firebaseMessaging->sendMulticast($message, $deviceTokens);
        } catch (\Exception $e) {
            // TODO Log error
        }
    }

    private function apns($message, array $tokens, $data = [])
    {
        if (count($tokens) === 0) {
            return;
        }

        $this->apns->connect();

        // Instantiate a new Message with a single recipient
        $apnsMessage = new \ApnsPHP_Message();
        $apnsMessage->setText($message);
        $apnsMessage->setSound();

        // Set a custom identifier. To get back this identifier use the getCustomIdentifier() method
        // over a ApnsPHP_Message object retrieved with the getErrors() message.
        // $apnsMessage->setCustomIdentifier("Message-123456");

        // Set badge icon
        // $apnsMessage->setBadge(0);

        // Set a custom property
        // $apnsMessage->setCustomProperty('acme2', array('bang', 'whiz'));

        foreach ($data as $key => $value) {
            $apnsMessage->setCustomProperty($key, $value);
        }

        // Set the expiry value to 30 seconds
        $apnsMessage->setExpiry(30);

        foreach ($tokens as $token) {
            $apnsMessage->addRecipient($token->getToken());
        }

        // Add the message to the message queue
        $this->apns->add($apnsMessage);

        // Send all messages in the message queue
        $this->apns->send();

        // Disconnect from the Apple Push Notification Service
        $this->apns->disconnect();

        // Examine the error message container
        // $errors = $this->apns->getErrors();
    }

    /**
     * @param string $message
     * @param mixed $recipients
     */
    public function send($message, $recipients, $data = [])
    {
        if (!is_array($recipients)) {
            $recipients = [ $recipients ];
        }

        $tokens = [];
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof RemotePushToken && !$recipient instanceof ApiUser) {
                throw new \InvalidArgumentException(sprintf('$recipients must be an instance of %s or %s',
                    RemotePushToken::class, ApiUser::class));
            }

            if ($recipient instanceof RemotePushToken) {
                $tokens[] = $recipient;
            }
            if ($recipient instanceof ApiUser) {
                foreach ($recipient->getRemotePushTokens() as $remotePushToken) {
                    $tokens[] = $remotePushToken;
                }
            }
        }

        $fcmTokens = array_filter($tokens, function (RemotePushToken $token) {
            return $token->getPlatform() === 'android';
        });

        $apnsTokens = array_filter($tokens, function (RemotePushToken $token) {
            return $token->getPlatform() === 'ios';
        });

        $this->fcm($message, $fcmTokens, $data);
        $this->apns($message, $apnsTokens, $data);
    }
}
