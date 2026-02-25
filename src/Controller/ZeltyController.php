<?php
namespace AppBundle\Controller;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Integration\Zelty\ZeltyImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
class ZeltyController extends AbstractController
{
    public function __construct(
        private ZeltyImportService $importService,
        private EntityManagerInterface $entityManager,
    ) {}


    #[Route(path: '/zelty/webhook/catalog/{restaurantId}', name: 'zelty_webhook_catalog', methods: ['POST'])]
    public function catalogWebhook(int $restaurantId, Request $request): JsonResponse
    {
        $restaurant = $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->find($restaurantId);
        if (null === $restaurant) {
            return new JsonResponse(['error' => 'Restaurant not found'], 404);
        }
        $payload = $request->toArray();

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $this->importService->import($payload, $restaurant);
            /* $this->entityManager->getConnection()->commit(); */
            $this->entityManager->getConnection()->rollBack();
            return new JsonResponse(['status' => 'success']);
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw $e;
            /* return new JsonResponse(['error' => $e->getMessage()], 500); */
        }
    }
}
