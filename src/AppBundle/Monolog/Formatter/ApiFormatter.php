<?php

namespace AppBundle\Monolog\Formatter;

use Monolog\Formatter\LineFormatter;
use Monolog\Utils;

/**
 * Formats incoming records into Nginx-style logs
 * The logs will contain request & response body
 */
class ApiFormatter extends LineFormatter
{
    const LOG_FORMAT =
        "%remote_addr% - \"%request%\" %status% [%time_local%] \"%http_user_agent%\"\n< %request_body%\n> %response_body%\n";

    public function __construct()
    {
        parent::__construct(null, \DateTime::ATOM);
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        if (!isset($record['context']['request'], $record['context']['response'])) {

            return parent::format($record);
        }

        $request = $record['context']['request'];
        $response = $record['context']['response'];

        $output = self::LOG_FORMAT;

        $output = str_replace('%remote_addr%', $this->stringify($request->getClientIp()), $output);
        $output = str_replace('%request%',
            sprintf('%s %s %s', $request->getMethod(), $request->getRequestUri(), $request->getProtocolVersion()), $output);
        $output = str_replace('%status%', $this->stringify($response->getStatusCode()), $output);
        $output = str_replace('%request_body%', $request->getContent(), $output);
        $output = str_replace('%response_body%', $response->getContent(), $output);

        $userAgent = $request->headers->get('User-Agent');
        $output = str_replace('%http_user_agent%', $this->stringify($userAgent), $output);

        if ($record['datetime'] instanceof \DateTime) {
            $output = str_replace('%time_local%', $record['datetime']->format($this->dateFormat), $output);
        }

        return $output;
    }
}
