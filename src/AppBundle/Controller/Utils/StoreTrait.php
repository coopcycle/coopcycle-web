<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Store;
use AppBundle\Form\StoreType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait StoreTrait
{
    protected function renderStoreForm(Store $store, Request $request)
    {
        $form = $this->createForm(StoreType::class, $store, [
            'additional_properties' => $this->getLocalizedLocalBusinessProperties(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $store = $form->getData();
            $this->getDoctrine()->getManagerForClass(Store::class)->persist($store);
            $this->getDoctrine()->getManagerForClass(Store::class)->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('Your changes were saved.')
            );

            return $this->redirectToRoute('admin_stores');
        }

        return [
            'store' => $store,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Template("@App/Store/form.html.twig")
     */
    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        return $this->renderStoreForm($store, $request);
    }
}
