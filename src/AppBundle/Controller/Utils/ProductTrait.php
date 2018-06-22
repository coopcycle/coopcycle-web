<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Base\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\ProductType;
use AppBundle\Form\ProductOptionType;
use Symfony\Component\HttpFoundation\Request;

trait ProductTrait
{
    public function createProductForm(LocalBusiness $localBusiness, Product $product) {

        call_user_func(array($product, 'set'. (new \ReflectionClass($localBusiness))->getShortName()), $localBusiness);

        return $this->createForm(ProductType::class, $product);
    }

    public function productsAction($id, string $class, Request $request)
    {
        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'products' => $localBusiness->getProducts(),
            'restaurant' => $localBusiness,
        ], $routes));
    }

    public function productAction($id, string $class, $productId, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $product = $this->get('sylius.repository.product')
            ->find($productId);

        $form = $this->createProductForm($localBusiness, $product);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->get('sylius.manager.product')->flush();

            return $this->redirectToRoute($routes['products'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $localBusiness,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    public function newProductAction($id, string $class, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $product = $this->get('sylius.factory.product')
            ->createNew();

        $form = $this->createProductForm($localBusiness, $product);

        $routes = $request->attributes->get('routes');

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $product = $form->getData();

            $this->get('sylius.repository.product')->add($product);

            return $this->redirectToRoute($routes['products'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $localBusiness,
            'product' => $product,
            'form' => $form->createView()
        ], $routes));
    }

    public function productOptionsAction($id, string $class, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $routes = $request->attributes->get('routes');

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'options' => $localBusiness->getProductOptions(),
            'restaurant' => $localBusiness,
        ], $routes));
    }

    public function productOptionAction($id, string $class, $optionId, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $productOption = $this->get('sylius.repository.product_option')
            ->find($optionId);

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            foreach ($productOption->getValues() as $optionValue) {
                if (null === $optionValue->getCode()) {
                    $optionValue->setCode(Uuid::uuid4()->toString());
                }
            }

            $this->get('sylius.manager.product_option')->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $localBusiness,
            'form' => $form->createView(),
        ], $routes));
    }

    public function newProductOptionAction($id, string $class, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $productOption = $this->get('sylius.factory.product_option')
            ->createNew();

        call_user_func(array($productOption, 'set'. (new \ReflectionClass($localBusiness))->getShortName()), $localBusiness);

        $routes = $request->attributes->get('routes');

        $form = $this->createForm(ProductOptionType::class, $productOption);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $productOption = $form->getData();

            $productOption->setCode(Uuid::uuid4()->toString());
            foreach ($productOption->getValues() as $optionValue) {
                $optionValue->setCode(Uuid::uuid4()->toString());
            }

            $this->get('sylius.manager.product_option')->flush();

            return $this->redirectToRoute($routes['product_options'], ['id' => $id]);
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'restaurant' => $localBusiness,
            'form' => $form->createView(),
        ], $routes));
    }

}
