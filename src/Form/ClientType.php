<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control']
            ])
            ->add('numtel', TelType::class, [
                'label' => 'Numéro de téléphone',
                'attr' => ['class' => 'form-control', 'id' => 'phone']
            ])
            ->add('pays', CountryType::class, [
                'label' => 'Pays',
                'attr' => ['class' => 'form-control col-md-2'],
            ])
            ->add('siret', TextType::class, [
                'label' => 'Numéro de SIRET',
                'attr' => ['class' => 'form-control']
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'attr' => ['class' => 'form-control']
            ])
            ->add('referencebancaire', TextType::class, [
                'label' => 'Référence bancaire',
                'attr' => ['class' => 'form-control']
            ])
            ->add('contrat', TextType::class, [
                'label' => 'Contrat',
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
