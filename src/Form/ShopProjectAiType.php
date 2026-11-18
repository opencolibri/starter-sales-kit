<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKit\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class ShopProjectAiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Shop name',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Catalog description',
                'constraints' => [new NotBlank()],
            ])
            ->add('url', TextType::class, [
                'label' => 'URL (optional)',
                'required' => false,
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'Number of products (max 20)',
                'data' => 20,
                'constraints' => [new Range(min: 1, max: 20)],
            ])
        ;

        ShopImageFields::addTo($builder, requireLogo: false);

        $builder
            ->add('save', SubmitType::class, [
                'label' => 'Save settings',
            ])
            ->add('refetch', SubmitType::class, [
                'label' => 'Re-build catalog',
            ])
        ;
    }
}
