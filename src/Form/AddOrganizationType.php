<?php
declare(strict_types=1);

namespace AppBundle\Form;

use AppBundle\Entity\Organization;
use AppBundle\Entity\OrganizationConfig;
use Sylius\Bundle\CustomerBundle\Form\Type\CustomerGroupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddOrganizationType extends AbstractType implements DataMapperInterface
{
    private ?Organization $organization;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->organization = $options['organization'];
        $builder->add('name')
            ->add('group', CustomerGroupType::class)
            ->add('address', AddressType::class)
            ->add('logo')
            ->add('deliveryPerimeterExpression')
            ->add('numberOfOrderAvailable')
            ->add('amountOfSubsidyPerEmployeeAndOrder')
            ->add('coverageOfDeliveryCostsByTheCompanyOrTheEmployee')
            ->add('orderLeadTime', DateTimeType::class)
            ->add('limitHourOrder')
            ->add('startHourOrder')
            ->add('dayOfOrderAvailable')
            ->setDataMapper($this)
        ;

    }

    public function mapDataToForms($viewData, $forms)
    {
        $forms = iterator_to_array($forms);
        $forms['group']->setData($this->organization->getGroup());
        $forms['name']->setData($this->organization->getConfig()->getName());
        $forms['address']->setData($this->organization->getConfig()->getAddresses()->first());
        $forms['logo']->setData($this->organization->getConfig()->getLogo());
        $forms['deliveryPerimeterExpression']->setData($this->organization->getConfig()->getDeliveryPerimeterExpression());
        $forms['numberOfOrderAvailable']->setData($this->organization->getConfig()->getNumberOfOrderAvailable());
        $forms['amountOfSubsidyPerEmployeeAndOrder']->setData($this->organization->getConfig()->getAmountOfSubsidyPerEmployeeAndOrder());
        $forms['coverageOfDeliveryCostsByTheCompanyOrTheEmployee']->setData($this->organization->getConfig()->getCoverageOfDeliveryCostsByTheCompanyOrTheEmployee());
        $forms['orderLeadTime']->setData($this->organization->getConfig()->getOrderLeadTime());
        $forms['limitHourOrder']->setData($this->organization->getConfig()->getLimitHourOrder());
        $forms['startHourOrder']->setData($this->organization->getConfig()->getStartHourOrder());
        $forms['dayOfOrderAvailable']->setData($this->organization->getConfig()->getDayOfOrderAvailable());

    }

    public function mapFormsToData($forms, &$viewData)
    {
        $forms = iterator_to_array($forms);
        $organization = new Organization(
            $forms['group']->getData(),
            new OrganizationConfig(
                $forms['name']->getData(),
                $forms['address']->getData(),
                $forms['logo']->getData(),
                $forms['deliveryPerimeterExpression']->getData(),
                $forms['numberOfOrderAvailable']->getData(),
                $forms['amountOfSubsidyPerEmployeeAndOrder']->getData(),
                $forms['coverageOfDeliveryCostsByTheCompanyOrTheEmployee']->getData(),
                $forms['orderLeadTime']->getData(),
                (int)$forms['limitHourOrder']->getData(),
                (int)$forms['startHourOrder']->getData(),
                (string)$forms['dayOfOrderAvailable']->getData(),
            ),
        );
        $viewData = $organization;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('organization');
    }
}
