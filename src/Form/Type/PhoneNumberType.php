<?php

namespace AppBundle\Form\Type;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhoneNumberType extends AbstractType
{
    private $country;

    public function __construct(string $country)
    {
        $this->country = $country;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $transformer = new CallbackTransformer(
            function ($data) {

                if (!$data) {
                    return '';
                }

                $util = PhoneNumberUtil::getInstance();

                try {
                    return $util->formatOutOfCountryCallingNumber(
                        $util->parse($data),
                        strtoupper($this->country)
                    );
                } catch (NumberParseException $e) {}

                return '';
            },
            function ($data) {

                if (!$data) {
                    return null;
                }

                $util = PhoneNumberUtil::getInstance();

                try {
                    return $util->format(
                        $util->parse($data, strtoupper($this->country)),
                        PhoneNumberFormat::E164
                    );
                } catch (NumberParseException $e) {}

                return null;
            }
        );

        $builder->addViewTransformer($transformer);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['type'] = 'tel';
        $view->vars['widget'] = 'single_text';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => false,
            'invalid_message' => 'This value is not a valid phone number.',
            'by_reference' => false,
            'error_bubbling' => false,
        ]);
    }
}
