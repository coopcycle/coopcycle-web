<?php

namespace AppBundle\Service;

use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Tagging;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr;

class TagManager
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function getAllTags()
    {
        return $this->doctrine->getRepository(Tag::class)->findAll();
    }

    public function getTags(TaggableInterface $taggable)
    {
        $tagRepository = $this->doctrine->getRepository(Tag::class);

        $qb = $tagRepository
            ->createQueryBuilder('tag')
            ->join(Tagging::class, 'tagging', Expr\Join::WITH, 'tagging.resourceClass = :resourceClass AND tagging.tag = tag.id')
            ->andWhere('tagging.resourceId = :resourceId')
            ->setParameter('resourceClass', $taggable->getTaggableResourceClass())
            ->setParameter('resourceId', $taggable->getId());

        return $qb->getQuery()->getResult();
    }

    public function addTagsAndFlush(TaggableInterface $taggable, $tags)
    {
        foreach ($tags as $tag) {
            $this->addTag($taggable, $tag);
        }

        if (count($tags) > 0) {
            $this->doctrine->getManagerForClass(Tagging::class)->flush();
        }
    }

    public function addTag(TaggableInterface $taggable, Tag $tag)
    {
        $taggingEntityManager = $this->doctrine->getManagerForClass(Tagging::class);

        $tagging = new Tagging();
        $tagging->setResourceClass($taggable->getTaggableResourceClass());
        $tagging->setResourceId($taggable->getId());
        $tagging->setTag($tag);

        $taggingEntityManager->persist($tagging);
    }

    public function removeTag(TaggableInterface $taggable, Tag $tag)
    {
        $taggingRepository = $this->doctrine->getRepository(Tagging::class);
        $taggingEntityManager = $this->doctrine->getManagerForClass(Tagging::class);

        $tagging = $taggingRepository->findOneBy([
            'resourceClass' => $taggable->getTaggableResourceClass(),
            'resourceId' => $taggable->getId(),
            'tag' => $tag,
        ]);

        $taggingEntityManager->remove($tagging);
    }

    public function fromSlugs(array $slugs)
    {
        $tagRepository = $this->doctrine->getRepository(Tag::class);

        $qb = $tagRepository->createQueryBuilder('tag');
        $qb->andWhere($qb->expr()->in('tag.slug', $slugs));

        return $qb->getQuery()->getResult();
    }
}
