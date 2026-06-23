<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Entity\Store;
use AppBundle\Service\ShopifyClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShopifySettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShopifyClient $shopifyClient,
    ) {}

    #[Route('/admin/stores/{id}/shopify', name: 'admin_store_shopify', methods: ['GET', 'POST'])]
    public function __invoke(int $id, Request $request): Response
    {
        $store = $this->entityManager->getRepository(Store::class)->find($id);

        if (!$store) {
            throw $this->createNotFoundException();
        }

        $shopifyShop = $this->entityManager
            ->getRepository(ShopifyShop::class)
            ->findOneBy(['store' => $store]);

        $error   = null;
        $success = false;

        if ($request->isMethod('POST') && $shopifyShop) {
            $raw          = $request->request->get('postal_codes', '');
            $postalCodes  = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $raw))
            ));

            $shopifyShop->setPostalCodes($postalCodes);
            $this->entityManager->flush();

            $synced = $this->shopifyClient->updateDeliveryPostalCodes($shopifyShop, $postalCodes);
            if (!$synced) {
                $error = 'Could not sync postal codes to Shopify. They are saved locally and will be retried on the next save.';
            } else {
                $success = true;
            }
        }

        return $this->render('admin/shopify_settings.html.twig', [
            'layout'               => 'admin.html.twig',
            'store'                => $store,
            'stores_route'         => 'admin_stores',
            'store_route'          => 'admin_store',
            'store_addresses_route' => 'admin_store_addresses',
            'shopify_shop'         => $shopifyShop,
            'error'                => $error,
            'success'              => $success,
        ]);
    }
}
