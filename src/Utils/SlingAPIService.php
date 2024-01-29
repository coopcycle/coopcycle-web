<?php

namespace AppBundle\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SlingApiService
{
    private Client $client;
    private string $baseUrl = 'https://api.getsling.com/v1';

    public function __construct(string $token)
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $token
            ]
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function fetchData(string $url): array
    {
            $response = $this->client->request('GET', $url);
            return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @throws GuzzleException
     */
    public function fetchConcise(array $session): array
    {
        $url = "/{$session['org']['id']}/users/concise?user-fields=full";
        return $this->fetchData($url);
    }

    /**
     * @throws GuzzleException
     */
    public function fetchSession(): array
    {
        $url = '/account/session';
        return $this->fetchData($url);
    }

    /**
     * @throws GuzzleException
     */
    public function fetchCalendar(array $session, \DateTime $from, \DateTime $to): array
    {
        $url = "/{$session['org']['id']}/calendar/{$session['org']['id']}/users/{$session['id']}?dates={$from->format('c')}/{$to->format('c')}";
        return $this->fetchData($url);
    }


    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function getCalendar(\DateTime $from, \DateTime $to): array
    {
        // Fetch session details
        $sessionData = $this->fetchSession();
        $session = [
            'id' => $sessionData['user']['id'],
            'firstname' => $sessionData['user']['name'],
            'lastname' => $sessionData['user']['lastname'],
            'org' => [
                'id' => $sessionData['org']['id'],
                'name' => $sessionData['org']['name']
            ]
        ];

        // Build users list
        $conciseUserData = $this->fetchConcise($session);
        $users = array_map(function ($user) {
            return [
                'id' => $user['id'],
                'firstname' => $user['name'],
                'lastname' => $user['lastname']
            ];
        }, $conciseUserData['users']);
        usort($users, 'userSort');

        // Build groups list
        $groups = array_map(function ($group) {
            return [
                'id' => $group['id'],
                'name' => $group['name']
            ];
        }, array_values($conciseUserData['groups']));
        usort($groups, 'grpSort');

        // Fetch and process calendar data
        $calendarData = $this->fetchCalendar($session, $from, $to);
        $processedCalendar = array_values(array_filter($calendarData, function ($event) {
            return $event['type'] === 'shift';
        }));

        foreach ($processedCalendar as &$event) {
            $start = strtotime($event['dtstart']);
            $end = strtotime($event['dtend']);
            $day = strtotime(date('Y-m-d', $start));

            $event = [
                'id' => $event['id'],
                'day' => date('Y-m-d', $day),
                'notes' => $event['assigneeNotes'],
                'start' => $start,
                'end' => $end,
                'delta' => ceil(($end - $start) / 60), // Calculate duration in minutes
                'user' => self::getUserById($users, $event['user']['id']),
                'task' => self::getGroupById($groups, $event['position']['id'])
            ];
        }
        usort($processedCalendar, ['DataUtility', 'calSort']);

        return [
            'data' => $processedCalendar,
            'session' => $session,
            'groups' => $groups,
            'users' => $users
        ];
    }

    /**
     * @throws \Exception
     */
    public static function subDivideDays(array $calendar, int $subDivideBy): array
    {
        $divided = [];

        foreach ($calendar['data'] as $shift) {
            for ($date = $shift['start']; $date < $shift['end']; $date = strtotime("+{$subDivideBy} minutes", $date)) {
                $end = strtotime("+{$subDivideBy} minutes", $date);
                $start = $date;

                $dividedShift = $shift;
                $dividedShift['day'] = date('Y-m-d H:i:s', $start);
                $dividedShift['start'] = $start;
                $dividedShift['end'] = $end;
                $dividedShift['delta'] = self::deltaWorkedInTimeRange($shift, new \DateTime('@'.$date), new \DateTime($end));

                $divided[] = $dividedShift;
            }
        }

        return [
            'data' => $divided,
            'session' => $calendar['session'],
            'groups' => $calendar['groups'],
            'users' => $calendar['users']
        ];
    }

    public static function toCSV($data, $div)
    {
        if (empty($data) || count($data['data']) === 0) {
            return '';
        }

        // Extract group names and prepare the header
        $groupNames = array_map(function ($group) {
            return $group['name'];
        }, $data['groups']);

        $firstCols = ['Name', 'Day', 'Start', 'End'];
        $headerRow = implode(',', array_merge($firstCols, $groupNames));
        $csvLines = [$headerRow]; // Header row

        $previousUser = '';
        $previousDay = '';
        $currentRow = [];

        foreach ($data['data'] as $record) {
            $isNewUser = $previousUser !== $record['user']['id'];
            $isNewDay = $previousDay !== $record['day'];

            // Start a new row for a different user or day
            if ($isNewUser || $isNewDay) {
                // Add the current row to CSV lines if it's not empty
                if (!empty($currentRow)) {
                    $csvLines[] = implode(',', $currentRow);
                }

                $previousUser = $record['user']['id'];
                $previousDay = $record['day'];
                $currentRow = array_fill(0, count($groupNames) + count($firstCols), '');
            }

            // Fill the row with user details and day
            $currentRow[0] = $record['user']['firstname'] . ' ' . $record['user']['lastname'];
            $currentRow[1] = (new DateTime($record['day']))->format('Y-m-d');
            $currentRow[2] = (new DateTime())->setTimestamp($record['start'])->format('H:i:s');
            $currentRow[3] = (new DateTime())->setTimestamp($record['end'])->format('H:i:s');

            // Update the specific column for the task
            $taskColumnIndex = array_search($record['task']['name'], $groupNames) + count($firstCols);
            if (is_numeric($currentRow[$taskColumnIndex])) {
                $currentRow[$taskColumnIndex] += $record['delta'];
            } else {
                $currentRow[$taskColumnIndex] = $record['delta'];
            }
        }

        // Add the last row to CSV lines
        if (!empty($currentRow)) {
            $csvLines[] = implode(',', $currentRow);
        }

        return implode("\n", $csvLines);
    }

    private static function getUserById(array &$users, $id)
    {
        foreach ($users as &$user) {
            if ($user['id'] == $id) {
                return $user;
            }
        }
        return null;
    }

    private static function getGroupById(array &$groups, $id)
    {
        foreach ($groups as &$group) {
            if ($group['id'] == $id) {
                return $group;
            }
        }
        return null;
    }

    // Sorting functions
    private static function grpSort(array &$a, array &$b): int
    {
        return strcmp($a['name'], $b['name']);
    }

    private static function dateSort(\DateTime &$a, \DateTime &$b): int
    {
        return $a > $b ? 1 : -1;
    }

    private static function userSort(array &$a, array &$b): int
    {
        return $a['id'] > $b['id'] ? 1 : -1;
    }

    private static function calSort(array &$a, array &$b): int
    {
        if ($a['user']['id'] != $b['user']['id']) {
            return self::userSort($a['user'], $b['user']);
        }
        if ($a['day'] != $b['day']) {
            return self::dateSort($a['day'], $b['day']);
        }
        return 0;
    }

    private static function deltaWorkedInTimeRange(array &$shift, \DateTime $from, \DateTime $to): int
    {
        $shiftStart = max($shift['start'], $from);
        $shiftEnd = min($shift['end'], $to);

        $delta = $shiftEnd - $shiftStart;
        return max($delta, 0) / 60000; // Ensure delta is non-negative
    }
}
