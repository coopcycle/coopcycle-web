<?php

namespace AppBundle\Controller\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait TransporterTrait
{
    /**
    * @Route("/admin/tasks/transporters/messages/{edi}", name="admin_transporter_message", methods={"GET"})
    */
    public function incidentImagePublicAction($edi, Request $request): Response
    {
        try {
            $imageBin = $this->edifactFilesystem->read($edi);
        } catch (\Exception $e) {
            throw $this->createNotFoundException(previous: $e);
        }
        return new Response($imageBin, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => sprintf('inline; filename="%s"', $edi)
        ]);
    }
}
