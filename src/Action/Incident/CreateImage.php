<?php

namespace AppBundle\Action\Incident;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\Incident\IncidentImage;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Form\IncidentImageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/
 */
class CreateImage
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        protected FormFactoryInterface $formFactory,
        protected IriConverterInterface $iriConverter,
        protected ValidatorInterface $validator)
    {}

    public function __invoke(Request $request): IncidentImage
    {
        $incidentImage = new IncidentImage();

        if ($request->headers->has('X-Attach-To')) {
            /** @var Incident $incident */
            $incident = $this->iriConverter->getResourceFromIri($request->headers->get('X-Attach-To'));
        }

        $form = $this->formFactory->create(IncidentImageType::class, $incidentImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($incidentImage);

            if (isset($incident)) {
                $incidentImage->setIncident($incident);
            }

            $this->entityManager->flush();
            return $incidentImage;
        }

        throw new ValidationException($this->validator->validate($incidentImage));
    }

}
