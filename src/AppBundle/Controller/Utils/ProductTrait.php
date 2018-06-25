<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Base\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Form\MenuEditorType;
use AppBundle\Form\MenuTaxonType;
use AppBundle\Form\ProductType;
use AppBundle\Form\ProductOptionType;
use AppBundle\Utils\MenuEditor;
use League\Geotools\ArrayCollection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

trait ProductTrait
{
    public function createProductForm(LocalBusiness $localBusiness, Product $product) {

        call_user_func(
            array($product, 'set'. (new \ReflectionClass($localBusiness))->getShortName()),
            $localBusiness);

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
            'local_business' => $localBusiness,
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
            'local_business' => $localBusiness,
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
            'local_business' => $localBusiness,
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
            'local_business' => $localBusiness,
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
            'local_business' => $localBusiness,
            'form' => $form->createView(),
        ], $routes));
    }

    public function newProductOptionAction($id, string $class, Request $request) {

        $localBusiness = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $productOption = $this->get('sylius.factory.product_option')
            ->createNew();

        call_user_func(
            array($productOption, 'set'. (new \ReflectionClass($localBusiness))->getShortName()),
            $localBusiness);

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
            'local_business' => $localBusiness,
            'form' => $form->createView(),
        ], $routes));
    }

    public function taxonAction($id, $class, $taxonId, $request, $formOptions = ['with_name' => true]) {

        $routes = $request->attributes->get('routes');

        $local_business = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $taxon = $this->get('sylius.repository.taxon')->find($taxonId);

        $form = $this->createForm(MenuTaxonType::class, $taxon, $formOptions);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $taxon = $form->getData();

            if ($form->getClickedButton() && 'addChild' === $form->getClickedButton()->getName()) {

                $childName = $form->get('childName')->getData();

                $uuid = Uuid::uuid1()->toString();

                $childTaxon = $this->get('sylius.factory.taxon')->createNew();
                $childTaxon->setCode($uuid);
                $childTaxon->setSlug($uuid);
                $childTaxon->setName($childName);

                $taxon->addChild($childTaxon);
                $this->get('sylius.manager.taxon')->flush();

                $this->addFlash(
                    'notice',
                    $this->get('translator')->trans('global.changesSaved')
                );

                return $this->redirect($request->headers->get('referer'));
            }

            $this->get('sylius.manager.taxon')->flush();

            return $this->redirectToRoute($routes['success'], ['id' => $local_business->getId()]);
        }

        $menuEditor = new MenuEditor($local_business, $taxon);
        $menuEditorForm = $this->createForm(MenuEditorType::class, $menuEditor);

        $originalTaxonProducts = new \SplObjectStorage();
        foreach ($menuEditor->getChildren() as $child) {
            $taxonProducts = new ArrayCollection();
            foreach ($child->getTaxonProducts() as $taxonProduct) {
                $taxonProducts->add($taxonProduct);
            }

            $originalTaxonProducts[$child] = $taxonProducts;
        }

        $menuEditorForm->handleRequest($request);
        if ($menuEditorForm->isSubmitted() && $menuEditorForm->isValid()) {

            $menuEditor = $menuEditorForm->getData();

            foreach ($menuEditor->getChildren() as $child) {
                foreach ($child->getTaxonProducts() as $taxonProduct) {

                    $taxonProduct->setTaxon($child);

                    foreach ($originalTaxonProducts[$child] as $originalTaxonProduct) {
                        if (!$child->getTaxonProducts()->contains($originalTaxonProduct)) {
                            $child->getTaxonProducts()->removeElement($originalTaxonProduct);
                            $this->getDoctrine()->getManagerForClass(ProductTaxon::class)->remove($originalTaxonProduct);
                        }
                    }
                }
            }

            $this->get('sylius.manager.taxon')->flush();

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('global.changesSaved')
            );

            return $this->redirect($request->headers->get('referer'));
        }

        return $this->render($request->attributes->get('template'), $this->withRoutes([
            'layout' => $request->attributes->get('layout'),
            'local_business' => $local_business,
            'form' => $form->createView(),
            'menu_editor_form' => $menuEditorForm->createView(),
        ], $routes));
    }

    public function deleteTaxonChildAction($id, $class, $taxonId, $sectionId, $request) {

        $local_business = $this->getDoctrine()
            ->getRepository($class)
            ->find($id);

        $taxon = $this->get('sylius.repository.taxon')->find($taxonId);
        $toRemove = $this->get('sylius.repository.taxon')->find($sectionId);

        $taxon->removeChild($toRemove);

        $this->get('sylius.manager.taxon')->flush();

        $routes = $request->attributes->get('routes');

        return $this->redirectToRoute($routes['menu_taxon'], [
            'id' => $local_business->getId(),
            'menuId' => $taxonId
        ]);

    }

}
