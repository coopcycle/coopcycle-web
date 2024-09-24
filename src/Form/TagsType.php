<?php

namespace AppBundle\Form;

use AppBundle\Entity\Tag;
use AppBundle\Entity\Model\TaggableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;

class TagsType extends AbstractType
{
    private $objectManager;

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($tagsAsArray): string {
                if (is_null($tagsAsArray)) {
                    return implode(' ', []);
                } else {
                    return implode(' ', $tagsAsArray);
                }
            },
            function ($tagsAsString): array {
                return explode(' ', $tagsAsString);
            }
        ));

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();

            $propertyAccessor = PropertyAccess::createPropertyAccessor();

            $parentPropertyPath = $form->getPropertyPath()->getParent();

            $taggable = $parentPropertyPath === null ? $form->getParent()->getData() : $propertyAccessor->getValue($form->getParent()->getData(), $parentPropertyPath);

            if (!$taggable instanceof TaggableInterface) {
                return;
            }

            // As the "tags" property isn't mapped directly to objects,
            // we need to force Doctrine to schedule an update.
            // Otherwise, TaggableSubscriber won't detect the change.
            $unitOfWork = $this->objectManager->getUnitOfWork();
            if ($unitOfWork->isInIdentityMap($taggable)) {
                $this->objectManager->getUnitOfWork()->scheduleForUpdate($taggable);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $tags = $this->objectManager->getRepository(Tag::class)->findAll();

        $data = array_map(fn(Tag $tag) => [
            'id'    => $tag->getId(),
            'name'  => $tag->getName(),
            'slug'  => $tag->getSlug(),
            'color' => $tag->getColor(),
        ], $tags);

        $resolver->setDefaults(array(
            'required' => false,
            'attr' => [
                'data-tags' => json_encode($data)
            ]
        ));
    }

    public function getParent()
    {
        return TextType::class;
    }
}
