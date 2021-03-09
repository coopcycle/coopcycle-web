<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class OrderExportType extends AbstractType
{
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', DateType::class, [
                'label' => 'form.task_export.start.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'data' => new \DateTime('now'),
                'html5' => false,
            ])
            ->add('end', DateType::class, [
                'label' => 'form.task_export.end.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'data' => new \DateTime('now'),
                'html5' => false,
            ]);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('messenger', CheckboxType::class, [
                    'label' => 'form.order_export.messenger.label',
                    'required' => false,
                ]);
        }
    }
}
