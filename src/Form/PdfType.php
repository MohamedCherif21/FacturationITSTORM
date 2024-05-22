<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class PdfType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('pdfFiles', FileType::class, [
            'label' => 'Relevés bancaires Quonto',
            'multiple' => true,
            'required' => false,
            'attr' => [
                'accept' => '.pdf', 
            ],
        ])

        ->add('pdfFilesCom', FileType::class, [
            'label' => 'Relevés bancaires LCL',
            'multiple' => true,
            'required' => false,
            'attr' => [
                'accept' => '.pdf', 
            ],
        ]);
        
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
        ]);
    }
}
