<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class MailController extends AbstractController
{
    #[Route('/mail-test', name: 'mail_test')]
    public function test(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('no-reply@facturation.local')
            ->to('test@example.com')
            ->subject('Test Symfony Mailer ✔')
            ->text('Ceci est un email de test envoyé depuis Symfony vers Mailpit.')
            ->html('<h1>Test Mailer OK</h1><p>Email envoyé via Symfony + Mailpit</p>');

        $mailer->send($email);

        return new Response('Email envoyé ✔ Vérifie Mailpit sur http://localhost:8025');
    }
}