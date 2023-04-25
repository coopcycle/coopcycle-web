<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DeliveryImportType extends AbstractType
{
    private $spreadsheetParser;
    private $validator;

    public function __construct(
        DeliverySpreadsheetParser $spreadsheetParser,
        ValidatorInterface $validator)
    {
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.delivery_import.file.label'
            ));

        if ($options['with_store']) {
            $builder->add('store', EntityType::class, [
                'label' => 'form.delivery_import.store.label',
                'mapped' => false,
                'class' => Store::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('store')
                        ->orderBy('store.name', 'ASC');
                },            
                'choice_label' => 'name',
            ]);
        }

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $file = $event->getData();
            $parentForm = $event->getForm()->getParent();

            try {

                $deliveries = $this->spreadsheetParser->parse($file->getPathname());

                foreach ($deliveries as $delivery) {
                    $violations = $this->validator->validate($delivery);
                    if (count($violations) > 0) {
                        throw new \Exception((string) $violations->get(0));
                    }
                }

                $parentForm->setData($deliveries);

            } catch (\Exception $e) {
                $event->getForm()->addError(new FormError($e->getMessage()));
                return;
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'with_store' => false,
        ));
    }
}
