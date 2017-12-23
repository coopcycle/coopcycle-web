<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Store;
use AppBundle\Form\StoreType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait StoreTrait
{
    /**
     * @Template("@App/Store/form.html.twig")
     */
    public function storeAction($id, Request $request)
    {
        $store = $this->getDoctrine()->getRepository(Store::class)->find($id);

        $form = $this->createForm(StoreType::class, $store);

        return [
            'store' => $store,
            'form' => $form->createView(),
        ];
    }
}
