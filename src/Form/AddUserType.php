<?php

namespace AppBundle\Form;

use AppBundle\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class AddUserType extends AbstractType
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $transformer = new CallbackTransformer(
            function ($entity) {
                if ($entity instanceof User) {
                    return $entity->getId();
                }

                return '';
            },
            function ($id) {
                if (!$id) {
                    return null;
                }

                return $this->doctrine->getRepository(User::class)->find($id);
            }
        );

        $builder
            ->add('user', HiddenType::class, array(
                'mapped' => false,
            ));
        $builder->get('user')
            ->addViewTransformer($transformer);
    }
}
