<?php

namespace AppBundle\Action\Incident;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
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

        $form = $this->formFactory->create(IncidentImageType::class, $incidentImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persisted first: Vich fills imageName on prePersist, and the
            // clones below are built from it.
            $this->entityManager->persist($incidentImage);

            if ($request->headers->has('X-Attach-To')) {
                $incidents = array_map(
                    fn(string $incident): Incident => $this->iriConverter->getResourceFromIri($incident),
                    explode(';', $request->headers->get('X-Attach-To'))
                );

                $this->cloneAndAttach($incidents, $incidentImage);
            }

            $this->entityManager->flush();
            return $incidentImage;
        }

        throw new ValidationException($this->validator->validate($incidentImage));
    }

    /**
     * The uploaded image is attached to the first incident; every other one
     * gets its own row pointing at the same file, so a single photo can back
     * several incidents without being uploaded again.
     *
     * @param array<int,Incident> $incidents
     */
    private function cloneAndAttach(array $incidents, IncidentImage $incidentImage): void
    {
        $first = array_shift($incidents);
        $incidentImage->setIncident($first);

        foreach ($incidents as $incident) {
            $otherIncidentImage = new IncidentImage();
            $otherIncidentImage->setImageName($incidentImage->getImageName());
            $otherIncidentImage->setIncident($incident);

            $this->entityManager->persist($otherIncidentImage);
        }
    }

}
