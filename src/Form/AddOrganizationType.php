<?php

declare(strict_types=1);

namespace AppBundle\Form;

use AppBundle\Entity\Organization;
use AppBundle\Entity\OrganizationConfig;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Customer\Model\CustomerGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddOrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('address', AddressType::class, [
                'with_widget' => true,
            ])
            ->add('logo')
            ->add('deliveryPerimeterExpression')
            ->add('numberOfOrderAvailable')
            ->add('amountOfSubsidyPerEmployeeAndOrder')
            ->add('coverageOfDeliveryCostsByTheCompanyOrTheEmployee')
            ->add('orderLeadTime', DateTimeType::class)
            ->add('limitHourOrder')
            ->add('startHourOrder')
            ->add('dayOfOrderAvailable')
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $organizationConfig = $event->getForm()->getData();

            // Generate a group automatically
            if (null === $organizationConfig->getGroup()) {

                $organization = $organizationConfig->getOrganization();

                $group = new CustomerGroup();
                $group->setCode(sprintf(Uuid::uuid4()->toString()));
                $group->setName(sprintf('%s default group', $organization->getName()));

                $organizationConfig->setGroup($group);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OrganizationConfig::class,
        ));
    }
}
