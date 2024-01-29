<?php

namespace AppBundle\Action\Sling;

use DateTime;
use Exception;

class ToCSV
{

    /**
     * @throws Exception
     */
    function fetchData($url, $token): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Authorization: ' . $token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        if (!$response) {
            throw new Exception('Error fetching data: ' . curl_error($ch));
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * @throws Exception
     */
    function fetchConcise($session): array
    {
        $url = "https://api.getsling.com/v1/{$session['org']['id']}/users/concise?user-fields=full";
        return $this->fetchData($url, $session['token']);
    }

    /**
     * @throws Exception
     */
    function fetchSession($token): array
    {
        $url = "https://api.getsling.com/v1/account/session";
        return $this->fetchData($url, $token);
    }

    /**
     * @throws Exception
     */
    function fetchCalendar($session, $from, $to): array
    {
        $url = "https://api.getsling.com/v1/{$session['org']['id']}/calendar/{$session['org']['id']}/users/{$session['id']}?dates={$from->format('c')}/{$to->format('c')}";
        return $this->fetchData($url, $session['token']);
    }

    function getUserById($users, $id): ?array
    {
        foreach ($users as $user) {
            if ($user['id'] == $id) {
                return $user;
            }
        }
        return null;
    }

    function getGroupById($groups, $id): ?array
    {
        foreach ($groups as $group) {
            if ($group['id'] == $id) {
                return $group;
            }
        }
        return null;
    }

    function _grpSort($a, $b): int
    {
        return strcmp($a['name'], $b['name']);
    }

    function _dateSort($a, $b): int
    {
        return ($a > $b) ? 1 : -1;
    }

    function _userSort($a, $b): int
    {
        return ($a['id'] > $b['id']) ? 1 : -1;
    }

    function _calSort($a, $b): int
    {
        if ($a['user']['id'] != $b['user']['id']) {
            return $this->_userSort($a['user'], $b['user']);
        }
        if ($a['day'] != $b['day']) {
            return $this->_dateSort($a['day'], $b['day']);
        }
        return 0;
    }

    /**
     * @throws Exception
     */
    function getCalendar($token, $from, $to): array
    {
        $sessionData = $this->fetchSession($token);
        $session = array(
            'token'     => $token,
            'id'        => $sessionData['user']['id'],
            'firstname' => $sessionData['user']['name'],
            'lastname'  => $sessionData['user']['lastname'],
            'org'       => array(
                'id'   => $sessionData['org']['id'],
                'name' => $sessionData['org']['name'],
            )
        );

        $conciseUserData = $this->fetchConcise($session);
        $users = array_map(function ($user) {
            return array(
                'id'        => $user['id'],
                'firstname' => $user['name'],
                'lastname'  => $user['lastname']
            );
        }, $conciseUserData['users']);
        usort($users, '_userSort');

        $groups = array_map(function ($group) {
            return array(
                'id'   => $group['id'],
                'name' => $group['name']
            );
        }, array_values($conciseUserData['groups']));
        usort($groups, '_grpSort');

        $calendarData = $this->fetchCalendar($session, $from, $to);
        $processedCalendar = array();
        foreach ($calendarData as $event) {
            if ($event['type'] === 'shift') {
                $start = new DateTime($event['dtstart']);
                $end = new DateTime($event['dtend']);
                $day = clone $start;
                $day->setTime(0, 0, 0, 0);

                $processedCalendar[] = array(
                    'id'    => $event['id'],
                    'day'   => $day,
                    'notes' => $event['assigneeNotes'],
                    'delta' => ceil(($end->getTimestamp() - $start->getTimestamp()) / 60),
                    'user'  => $this->getUserById($users, $event['user']['id']),
                    'task'  => $this->getGroupById($groups, $event['position']['id'])
                );
            }
        }
        usort($processedCalendar, '_calSort');

        return array(
            'data'    => $processedCalendar,
            'session' => $session,
            'groups'  => $groups,
            'users'   => $users
        );
    }

    function toCSV($data): string
    {
        if (empty($data) || count($data['data']) === 0) {
            return '';
        }

        $groupNames = array_map(function ($group) {
            return $group['name'];
        }, $data['groups']);
        $csvLines = array(implode(',', array_merge(array('', ''), $groupNames)));

        $previousUser = '';
        $previousDay = '';
        $currentRow = array();

        foreach ($data['data'] as $record) {
            $isNewUser = $previousUser !== $record['user']['id'];
            $isNewDay = $previousDay !== $record['day']->format('Y-m-d');

            if ($isNewUser || $isNewDay) {
                if (count($currentRow) > 0) {
                    $csvLines[] = implode(',', $currentRow);
                }

                $previousUser = $record['user']['id'];
                $previousDay = $record['day']->format('Y-m-d');
                $currentRow = array_fill(0, count($groupNames) + 2, '');
            }

            $currentRow[0] = $record['user']['firstname'] . ' ' . $record['user']['lastname'];
            $currentRow[1] = $record['day']->format('Y-m-d');

            $taskColumnIndex = array_search($record['task']['name'], $groupNames) + 2;
            if (isset($currentRow[$taskColumnIndex]) && is_numeric($currentRow[$taskColumnIndex])) {
                $currentRow[$taskColumnIndex] += $record['delta'];
            } else {
                $currentRow[$taskColumnIndex] = $record['delta'];
            }
        }

        if (count($currentRow) > 0) {
            $csvLines[] = implode(',', $currentRow);
        }

        return implode("\n", $csvLines);
    }

    public function __invoke()
    {
       return [];
    }

}
