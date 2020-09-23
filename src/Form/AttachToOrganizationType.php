<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttachToOrganizationType extends AbstractType
{
    protected $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $transformer = new CallbackTransformer(
            function ($entity) {

                if (is_array($entity)) {
                    $ids = array_map(fn($e) => $e->getId(), $entity);

                    return implode(',', $ids);
                }

                return '';
            },
            function ($ids) {

                $ids = explode(',', $ids);

                $qb = $this->entityManager->getRepository(Task::class)
                    ->createQueryBuilder('t');

                $qb->andWhere(
                    $qb->expr()->in('t.id', $ids)
                );

                return $qb->getQuery()->getResult();
            }
        );

        $builder
            ->add('tasks', HiddenType::class, array(
                'mapped' => false,
            ));
        $builder->get('tasks')
            ->addViewTransformer($transformer);

        $builder->add('store', EntityType::class, [
            'label' => 'form.delivery_import.store.label',
            'mapped' => false,
            'class' => Store::class,
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $repository) {
                return $repository->createQueryBuilder('s')
                    ->orderBy('s.name', 'ASC');
            },
            'required' => false,
        ]);
    }
}
