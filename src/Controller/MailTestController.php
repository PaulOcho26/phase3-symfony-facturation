<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailTestController extends AbstractController
{
    #[Route('/mail-test', name: 'mail_test')]
    public function index(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('facturation@local.test')
            ->to('client@test.com')
            ->subject('Test Mail Symfony + Mailpit')
            ->text('Hello 👋 Mail envoyé via Symfony Mailer');

        $mailer->send($email);

        return new Response('Mail envoyé avec succès 🚀');
    }
}