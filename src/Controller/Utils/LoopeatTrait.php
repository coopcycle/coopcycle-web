<?php

namespace AppBundle\Controller\Utils;

use AppBundle\CubeJs\TokenFactory as CubeJsTokenFactory;
use Carbon\Carbon;
use League\Csv\Writer as CsvWriter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

trait LoopeatTrait {

    public function zeroWasteTransactionsAction(Request $request, CubeJsTokenFactory $tokenFactory, HttpClientInterface $cubejsClient)
    {
        $this->denyAccessUnlessGranted('ROLE_LOOPEAT');

        $month = $request->query->get('month', date('Y-m'));

        $query = [
            'measures' => [],
            'timeDimensions' => [],
            'order' => [['Loopeat.orderDate','desc']],
            'dimensions' => [
                'Loopeat.restaurantName',
                'Loopeat.orderNumber',
                'Loopeat.orderDate',
                'Loopeat.customerEmail',
                'Loopeat.deliveredBy',
                'Loopeat.packagingFee'
            ],
            'filters' => [
                [
                    'member' => 'Loopeat.orderDate',
                    'operator' => 'inDateRange',
                    'values' => [
                        Carbon::parse($month)->startOfMonth()->format('Y-m-d'),
                        Carbon::parse($month)->endOfMonth()->format('Y-m-d')
                    ]
                ]
            ]
        ];

        $cubeJsToken = $tokenFactory->createToken();

        if ($request->isMethod('POST')) {

            $response = $cubejsClient->request('POST', 'load', [
                'headers' => [
                    'Authorization' => $cubeJsToken,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['query' => $query])
            ]);

            // Need to invoke a method on the Response,
            // to actually throw the Exception here
            // https://github.com/symfony/symfony/issues/34281
            // https://symfony.com/doc/5.4/http_client.html#handling-exceptions
            $content = $response->getContent();

            $resultSet = json_decode($content, true);

            $csv = CsvWriter::createFromString('');
            $csv->insertOne(array_keys($resultSet['data'][0]));
            $csv->insertAll($resultSet['data']);

            $response = new Response($csv->getContent());
            $response->headers->add(['Content-Type' => 'text/csv']);
            $response->headers->add([
                'Content-Disposition' => $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'loopeat.csv'
                )
            ]);

            return $response;
        }

        return $this->render('profile/loopeat.html.twig', [
            'cube_token' => $cubeJsToken,
            'query' => $query,
            'month' => $month,
        ]);
    }
}
