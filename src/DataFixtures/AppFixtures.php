<?php

namespace App\DataFixtures;

use App\Entity\EmailTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $templates = [
            [
                'id' => 1,
                'subject' => 'Facture', 
                'body' =>  '<p>Bonjour,</p>'
                         . '<br>'
                         . '<p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>'
                         . '<p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>'
                         . '<p>Bien Cordialement,</p>'
                         . '<p style="margin: 0;">Farhat THABET, PhD</p>'
                         . '<p style="margin: 0;">Président IT STORM Consulting</p>'
                         . '<br>',
                'type' => 'PremierEnvoie'
            ],
            [
                'id' => 2,
                'subject' => 'Facture',
                'body' =>  '<p>Bonjour,</p>'
                         . '<br>'
                         . '<p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>'
                         . '<p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>'
                         . '<p>Bien Cordialement,</p>'
                         . '<p style="margin: 0;">Farhat THABET, PhD</p>'
                         . '<p style="margin: 0;">Président IT STORM Consulting</p>'
                         . '<br>',
                'type' => 'Relance'
            ],
            [
                'id' => 3,
                'subject' => 'Facture',
                'body' =>  '<p>Bonjour,</p>'
                         . '<br>'
                         . '<p>Veuillez trouver ci-joint les factures liées à nos prestations, pour la période indiquée dans l\'objet de ce mail.</p>'
                         . '<p>En attendant, nous restons à votre disposition pour tout complément d\'information.</p>'
                         . '<p>Bien Cordialement,</p>'
                         . '<p style="margin: 0;">Farhat THABET, PhD</p>'
                         . '<p style="margin: 0;">Président IT STORM Consulting</p>'
                         . '<br>',
                'type' => 'Autre'
            ],
        ];

        foreach ($templates as $data) {
            $template = new EmailTemplate();
            $template->setId($data['id']);
            $template->setSubject($data['subject']);
            $template->setBody($data['body']);
            $template->setType($data['type']);
            $manager->persist($template);
        }

        $manager->flush();
    }
}
