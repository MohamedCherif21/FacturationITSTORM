<?php

namespace App\Form;

use App\Entity\LigneFacture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


class FactureLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service')
            ->add('prestataire')
            ->add('prixUnitaire')
      
            ->add('taxeTVA', ChoiceType::class, [
                'choices' => [
                    '5%' => 5,
                    '10%' => 10,
                    '20%' => 20,
                ],
                // Valeur par défaut à 20%
                'data' => 20,
                'expanded' => false,
                'multiple' => false,
            ])
        ;
            
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LigneFacture::class,
        ]);
    }
}
