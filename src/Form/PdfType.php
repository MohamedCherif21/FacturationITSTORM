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
        ->add('pdfFiles', CollectionType::class, [
            'entry_type' => FileType::class,
            'entry_options' => [
                'label' => false, // Vous pouvez définir un label ici si nécessaire
            ],
            'allow_add' => true, // Permet d'ajouter dynamiquement des champs de fichier
            'allow_delete' => true, // Permet de supprimer des champs de fichier
            'by_reference' => false, // Utilisez false pour manipuler les objets enfants
            'label' => 'Fichiers PDF', // Label global pour la collection
        ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
