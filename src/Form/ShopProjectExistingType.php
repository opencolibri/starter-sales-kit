<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ShopProjectExistingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Shop name',
                'constraints' => [new NotBlank()],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Site URL to import',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
        ;

        ShopImageFields::addTo($builder, requireLogo: false);

        $builder
            ->add('save', SubmitType::class, [
                'label' => 'Save settings',
            ])
            ->add('refetch', SubmitType::class, [
                'label' => 'Re-fetch site',
            ])
        ;
    }
}
