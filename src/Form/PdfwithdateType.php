<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;


class PdfwithdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $startDate = $options['start_date'];
        $endDate = $options['end_date'];

        $builder
 
        ->add('pdfFiles', FileType::class, [
            'label' => 'Relevés bancaires',
            'multiple' => true,
            'required' => false,
            'attr' => [
                'accept' => '.pdf', 
            ],
        ])
        
            ->add('startDate', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'data' => $startDate, 
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Jusqu\'au',
                'widget' => 'single_text',
                'data' => $endDate, 
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
