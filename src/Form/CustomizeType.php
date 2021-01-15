<?php

namespace AppBundle\Form;

use AppBundle\Service\SettingsManager;
use League\Flysystem\Filesystem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Contracts\Cache\CacheInterface;

class CustomizeType extends AbstractType
{
    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        CacheInterface $projectCache)
    {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->projectCache = $projectCache;
    }

    private function getContentData($filename)
    {
        if ($this->assetsFilesystem->has($filename)) {

            return [
                $content = $this->assetsFilesystem->read($filename),
                true
            ];
        }

        return [
            '',
            false
        ];
    }

    private function onContentSubmit($filename, $content, $enabled)
    {
        if (empty(trim($content))) {
            $enabled = false;
        }

        if ($enabled) {
            if ($this->assetsFilesystem->has($filename)) {
                $this->assetsFilesystem->update($filename, $content);
            } else {
                $this->assetsFilesystem->write($filename, $content);
            }
        } else {
            if ($this->assetsFilesystem->has($filename)) {
                $this->assetsFilesystem->delete($filename);
            }
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('motto', TextType::class, [
                'required' => false,
                'label' => 'form.customize.motto.label',
                'attr' => ['placeholder' => 'index.banner'],
                'help' => 'form.customize.motto.help',
            ])
            ->add('aboutUsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'form.customize.about_us_enabled.label',
            ])
            ->add('aboutUs', TextareaType::class, [
                'required' => false,
                'label' => 'form.customize.about_us.label',
                'attr' => ['rows' => '12'],
                'help' => 'mardown_formatting.help',
            ])
            ;

        foreach (['legal', 'terms', 'privacy'] as $type) {
            $builder
                ->add(sprintf('custom%sEnabled', ucfirst($type)), CheckboxType::class, [
                    'required' => false,
                    'label' => sprintf('form.customize.custom_%s_enabled.label', $type),
                ])
                ->add(sprintf('custom%s', ucfirst($type)), TextareaType::class, [
                    'required' => false,
                    'label' => sprintf('form.customize.custom_%s.label', $type),
                    'attr' => ['rows' => '12'],
                    'help' => 'mardown_formatting.help',
                ]);
        }

        $builder
            ->add('orderConfirmEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'form.customize.order_confirm_enabled.label',
            ])
            ->add('orderConfirm', TextareaType::class, [
                'required' => false,
                'label' => 'form.customize.order_confirm.label',
                'attr' => ['rows' => '12'],
                'help' => 'mardown_formatting.help',
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            [ $aboutUs, $aboutUsEnabled ] = $this->getContentData('about_us.md');

            $form->get('aboutUs')->setData($aboutUs);
            $form->get('aboutUsEnabled')->setData($aboutUsEnabled);

            [ $orderConfirm, $orderConfirmEnabled ] = $this->getContentData('order_confirm.md');

            $form->get('orderConfirm')->setData($orderConfirm);
            $form->get('orderConfirmEnabled')->setData($orderConfirmEnabled);

            foreach (['legal', 'terms', 'privacy'] as $type) {
                [ $content, $enabled ] = $this->getContentData(sprintf('custom_%s.md', $type));
                $form->get(sprintf('custom%s', ucfirst($type)))->setData($content);
                $form->get(sprintf('custom%sEnabled', ucfirst($type)))->setData($enabled);
            }

            $motto = $this->settingsManager->get('motto');
            if (!empty($motto)) {
                $form->get('motto')->setData($motto);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            // About us

            $aboutUsEnabled = $form->get('aboutUsEnabled')->getData();
            $aboutUs = $form->get('aboutUs')->getData();

            $this->onContentSubmit(
                'about_us.md',
                $aboutUs,
                $aboutUsEnabled
            );

            $this->projectCache->delete('content.about_us');
            $this->projectCache->delete('content.about_us.exists');

            // Order confirm

            $orderConfirmEnabled = $form->get('orderConfirmEnabled')->getData();
            $orderConfirm = $form->get('orderConfirm')->getData();

            $this->onContentSubmit(
                'order_confirm.md',
                $orderConfirm,
                $orderConfirmEnabled
            );

            $this->projectCache->delete('content.order_confirm');
            $this->projectCache->delete('content.order_confirm.exists');

            // Custom legal, terms, privacy

            foreach (['legal', 'terms', 'privacy'] as $type) {
                $enabled = $form->get(sprintf('custom%sEnabled', ucfirst($type)))->getData();
                $content = $form->get(sprintf('custom%s', ucfirst($type)))->getData();
                $this->onContentSubmit(
                    sprintf('custom_%s.md', $type),
                    $content,
                    $enabled
                );
            }

            $motto = $form->get('motto')->getData();
            if (!empty($motto)) {
                $this->settingsManager->set('motto', $motto);
                $this->settingsManager->flush();
            }
        });
    }
}
