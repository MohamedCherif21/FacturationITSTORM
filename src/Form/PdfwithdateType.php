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
        // Get the initial values for startDate and endDate
        $startDate = $options['start_date'];
        $endDate = $options['end_date'];

        $builder
            ->add('pdfFile', FileType::class, [
                'label' => 'Fichier PDF',
                'required' => false,
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'data' => $startDate, // Set the initial value for startDate
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'data' => $endDate, // Set the initial value for endDate
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Set the default values for startDate and endDate to null
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
