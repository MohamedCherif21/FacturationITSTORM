<?php

namespace App\Form;

use App\Entity\EmailTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('subject', TextType::class, [
            'label' => 'Objet',
            'attr' => ['id' => 'subject'] // Ajoutez cette ligne pour définir l'ID du champ
        ])
        ->add('body', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => 10,
                'cols' => 50,
            ],
        ])
        ->add('type', ChoiceType::class, [
            'label' => 'Type',
            'choices' => [
                'PremierEnvoie' => 'PremierEnvoie',
                'Relance' => 'Relance',
                'Autre' => 'Autre',
            ],
            'attr' => ['class' => 'form-control'],
        ])
        // ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailTemplate::class,
        ]);
    }
}
