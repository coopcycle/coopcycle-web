<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Tag;
use AppBundle\Form\TagType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TagController extends AbstractController
{
    public function newTagAction(Request $request, EntityManagerInterface $entityManager)
    {
        $tag = new Tag();

        $form = $this->createForm(TagType::class, $tag);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tag = $form->getData();

            $entityManager->persist($tag);
            $entityManager->flush();

            return $this->redirectToRoute($request->attributes->get('redirect_route'));
        }

        return $this->render($request->attributes->get('template'), [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    public function tagAction($slug, Request $request, EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $tag = $entityManager->getRepository(Tag::class)->findOneBySlug($slug);

        $form = $this->createForm(TagType::class, $tag);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tag = $form->getData();

            $entityManager->flush();

            return $this->redirectToRoute($request->attributes->get('redirect_route'));
        }

        return $this->render($request->attributes->get('template'), [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }
}
