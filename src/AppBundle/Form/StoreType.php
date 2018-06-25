<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Taxonomy\Factory\TaxonFactory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Vich\UploaderBundle\Form\Type\VichImageType;

class StoreType extends LocalBusinessType
{
    protected $taxonFactory;

    protected $translator;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        $countryIso,
        TranslatorInterface $translator,
        TaxonFactory $taxonFactory
    ) {
        parent::__construct($authorizationChecker, $tokenStorage, $countryIso);
        $this->translator = $translator;
        $this->taxonFactory = $taxonFactory;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                    }
                ));
        }

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options) {
                $store = $event->getForm()->getData();

                if (is_null($store->getId())) {
                    // at first save create the catalog object for the store
                    $taxon = $this->taxonFactory->createNew();
                    $uuid = Uuid::uuid4()->toString();

                    $taxon->setCode($uuid);
                    $taxon->setSlug($uuid);
                    $taxon->setName($this->translator->trans('stores.catalog'));
                    $store->setMenuTaxon($taxon);
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
