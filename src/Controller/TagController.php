<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Tag;
use AppBundle\Form\TagType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class TagController extends AbstractController
{
    public function newTagAction(Request $request)
    {
        $tag = new Tag();

        $form = $this->createForm(TagType::class, $tag);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tag = $form->getData();

            $this->getDoctrine()->getManagerForClass(Tag::class)->persist($tag);
            $this->getDoctrine()->getManagerForClass(Tag::class)->flush();

            return $this->redirectToRoute($request->attributes->get('redirect_route'));
        }

        return $this->render($request->attributes->get('template'), [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    public function tagAction($slug, Request $request)
    {
        $tag = $this->getDoctrine()->getRepository(Tag::class)->findOneBySlug($slug);

        $form = $this->createForm(TagType::class, $tag);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $tag = $form->getData();

            $this->getDoctrine()->getManagerForClass(Tag::class)->flush();

            return $this->redirectToRoute($request->attributes->get('redirect_route'));
        }

        return $this->render($request->attributes->get('template'), [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }
}
