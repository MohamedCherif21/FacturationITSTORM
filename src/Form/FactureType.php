<?php

namespace App\Form;

use App\Entity\Facture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dateFacturation = new \DateTime();
        $dateEcheance = new \DateTime();
        $dateEcheance->add(new \DateInterval('P1M'));

        $builder
        ->add('numFacture')
        ->add('client')
        ->add('dateFacturation', DateType::class, [
            'widget' => 'single_text',
            // 'data' => $dateFacturation,
            'label' => 'Date de facturation',
            'label_attr' => ['class' => 'form-label'], 
            'attr' => ['class' => 'form-control'],
        ])
        ->add('delaiPaiement', ChoiceType::class, [
            'label' => 'Echéance',
            'choices' => [
                '15 jours' => 15,
                '30 jours' => 30,
                '45 jours' => 45,
                '60 jours' => 60,
            ],
            'attr' => ['class' => 'form-control'],
        ])

        ->add('dateEcheance', DateType::class, [
            'widget' => 'single_text',
            // 'data' => $dateEcheance,
            'label' => 'Date d\'échéance',
            'label_attr' => ['class' => 'form-label'],
            'attr' => ['class' => 'form-control'],
        ])         
        
       
    ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
