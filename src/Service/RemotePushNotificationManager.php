<?php

namespace AppBundle\Service;

use AppBundle\Entity\User;
use AppBundle\Entity\RemotePushToken;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Pushok;

class RemotePushNotificationManager
{
    private static $enabled = true;

    public function __construct(
        private Messaging $firebaseMessaging,
        private Pushok\Client $apnsClient,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private LoggerInterface $pushNotificationLogger,
        private LoggingUtils $loggingUtils)
    {
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
    private function fcm($notification, array $tokens, $data)
    {
        if (count($tokens) === 0) {
            return;
        }

        // @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
        // @see https://developer.android.com/guide/topics/ui/notifiers/notifications#ManageChannels
        $payload['android'] = [
            'priority' => 'high'
        ];

        if (null !== $notification) {
            $payload['notification'] = [
                'title' => $notification,
                'body' => $this->translator->trans('notifications.tap_to_open'),
            ];
            $payload['android']['notification'] = [
                'sound' => 'default',
                'channel_id' => 'coopcycle_important',
            ];

            if (!empty($data) && array_key_exists('event', $data)) {
                $event = $data['event'];
                if (!empty($event['name'])) {
                    // set a tag, only one notification per tag could be shown
                    // in the notification center (new notifications replace old ones)
                    $payload['android']['notification']['tag'] = $event['name'];
                }
            }
        }

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

        // @see https://firebase-php.readthedocs.io/en/stable/cloud-messaging.html#send-messages-in-batches
        $report = $this->firebaseMessaging->sendMulticast($message, $deviceTokens);

        if ($report->hasFailures()) {
            foreach ($report->failures()->getItems() as $failure) {

                if ($failure->target()->type() === MessageTarget::TOKEN) {

                    $this->pushNotificationLogger->error(sprintf('FCM: Error sending message to token "%s": %s',
                        $this->loggingUtils->redact($failure->target()->value()),
                        $failure->error()->getMessage()
                    ));

                    // @see https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode
                    // If the token was not found, we remove it from database
                    if ($failure->messageWasSentToUnknownToken()) {
                        foreach ($tokens as $token) {
                            if ($token->getToken() === $failure->target()->value()) {

                                $this->pushNotificationLogger->info(sprintf('FCM: Removing remote push token "%s"',
                                    $this->loggingUtils->redact($failure->target()->value()),
                                ));

                                $this->entityManager->remove($token);
                                $this->entityManager->flush();
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            $this->pushNotificationLogger->info(sprintf('FCM: Message sent to %d devices; tokens: %s',
                count($deviceTokens),
                implode(', ', array_map(function ($token) {
                    return $this->loggingUtils->redact($token);
                }, $deviceTokens))));
        }
    }

    private function apns($text, array $tokens, $data = [])
    {
        if (count($tokens) === 0) {
            return;
        }

        $alert = Pushok\Payload\Alert::create()->setTitle($text);
        // $alert = $alert->setBody('Lorem ipsum');

        $payload = Pushok\Payload::create()->setAlert($alert);
        $payload->setSound('default');

        foreach ($data as $key => $value) {
            $payload->setCustomValue($key, $value);
        }

        // @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/pushing_background_updates_to_your_app
        // @see https://developer.apple.com/library/archive/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CreatingtheNotificationPayload.html
        //
        // The system treats background notifications as low priority:
        // you can use them to refresh your app’s content, but the system doesn’t guarantee their delivery.
        // In addition, the system may throttle the delivery of background notifications if the total number becomes excessive.
        // The number of background notifications allowed by the system depends on current conditions,
        // but don’t try to send more than two or three per hour.
        //
        // $payload->setContentAvailability(true);

        $payload->setPushType('alert');

        $notifications = [];
        foreach ($tokens as $token) {
            $notification = new Pushok\Notification($payload, $token->getToken());
            $notification->setHighPriority();
            $notifications[] = $notification;
        }

        $this->apnsClient->addNotifications($notifications);

        $responses = $this->apnsClient->push();

        foreach ($responses as $response) {
            if (200 !== $response->getStatusCode()) {
                $this->pushNotificationLogger->error(sprintf('APNS: Error sending message to token "%s; returned "%s" "%s" "%s"',
                    $this->loggingUtils->redact($response->getDeviceToken()),
                    $response->getStatusCode(),
                    $response->getErrorReason(),
                    $response->getErrorDescription()
                ));
            } else {
                $this->pushNotificationLogger->info(sprintf('APNS: Message sent to token: %s; response: %s',
                    $this->loggingUtils->redact($response->getDeviceToken()),
                    $response->getReasonPhrase()));
            }
        }
    }

    /**
     * @param string $textMessage
     * @param mixed $recipients
     */
    public function send($textMessage, $recipients, $data = [])
    {
        if (!is_array($recipients)) {
            $recipients = [ $recipients ];
        }

        $tokens = [];
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof RemotePushToken && !$recipient instanceof User) {
                throw new \InvalidArgumentException(sprintf('$recipients must be an instance of %s or %s',
                    RemotePushToken::class, User::class));
            }

            if ($recipient instanceof RemotePushToken) {
                $tokens[] = $recipient;
            }
            if ($recipient instanceof User) {
                foreach ($recipient->getRemotePushTokens() as $remotePushToken) {
                    $tokens[] = $remotePushToken;
                }
            }
        }

        $this->pushNotificationLogger->info(sprintf('Sending push notification to %s; found tokens: %s',
            implode(', ', array_map(function ($recipient) {
                if ($recipient instanceof RemotePushToken) {
                    return 'token: '.$this->loggingUtils->redact($recipient->getToken());
                } else if ($recipient instanceof User) {
                    return 'user: '.$recipient->getId();
                } else {
                    return 'unknown recipient';
                }
            }, $recipients)),
            implode(', ', array_map(function (RemotePushToken $token) {
                return $this->loggingUtils->redact($token->getToken());
            }, $tokens))));


        $fcmTokens = array_filter($tokens, function (RemotePushToken $token) {
            return $token->getPlatform() === 'android';
        });

        $apnsTokens = array_filter($tokens, function (RemotePushToken $token) {
            return $token->getPlatform() === 'ios';
        });

        //todo send both "notification+data" and "data-only" messages on android
        // until we figure out if we need to handle it differently
        // reasons:
        // 1. in the background android is able to handle only "data-only" messages
        // impact:
        // for the versions before this change - nothing, they don't handle "data-only" messages at all
        // for the versions after this change - implementation should expect to receive
        // both "notification+data" and "data-only" messages and handle them correctly

        $this->fcm($textMessage, $fcmTokens, $data); // send "notification+data" message
        $this->fcm(null, $fcmTokens, $data); // send "data-only" message

        $this->apns($textMessage, $apnsTokens, $data);
    }
}
