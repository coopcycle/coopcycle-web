<?php

namespace Tests\Behat\Double;

use AppBundle\Service\RemotePushNotification;
use AppBundle\Service\RemotePushNotificationManager;

/**
 * Test-env replacement for RemotePushNotificationManager (see config/services_test.yaml).
 *
 * Push notifications should never actually be sent while running tests: doing
 * so hits the real Firebase/APNs clients, which either fail loudly (no
 * FIREBASE_CREDENTIALS configured in test, filling the logs with Messenger
 * retry errors) or, worse, could reach real devices if they were configured.
 */
final class NullRemotePushNotificationManager extends RemotePushNotificationManager
{
    public function send(string|RemotePushNotification $textOrPushNotification, $recipients = [], $data = [])
    {
        // do nothing
    }
}
