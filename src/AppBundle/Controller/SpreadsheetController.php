<?php

namespace AppBundle\Controller;

use AppBundle\Spreadsheet\ProductSpreadsheetParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class SpreadsheetController extends AbstractController
{
    /**
     * @Route("/spreadsheets/examples/coopcycle-products-example.csv", name="spreadsheet_example_products")
     */
    public function productsExampleCsvAction(ProductSpreadsheetParser $psp)
    {
        $csv = $this->get('serializer')->serialize($psp->getExampleData(), 'csv');

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'coopcycle-products-example.csv'
        ));

        return $response;
    }
}
