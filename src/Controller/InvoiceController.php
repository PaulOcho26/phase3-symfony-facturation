<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Invoice;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/invoice')]
final class InvoiceController extends AbstractController
{
    #[Route(name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository): Response
    {
        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoiceRepository->findAll(),
        ]);
    }

    // src/Controller/InvoiceController.php

#[Route('/new', name: 'app_invoice_new', methods: ['GET', 'POST'])]
public function new(Request $request, EntityManagerInterface $entityManager, InvoiceRepository $invoiceRepository): Response
{
    $invoice = new Invoice();
    $form = $this->createForm(InvoiceType::class, $invoice);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // 1. Définir le propriétaire (User connecté)
        $invoice->setOwner($this->getUser());

        // 2. Générer le numéro unique (Format: FACT-YYYYMMDD-N)
        $datePart = (new \DateTime())->format('Ymd');
        $nextNumber = $invoiceRepository->countInvoicesInCurrentMonth() + 1;
        $invoice->setNumber("FACT-" . $datePart . "-" . $nextNumber);

        // 3. Enregistrer
        $entityManager->persist($invoice);
        $entityManager->flush();

        return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('invoice/new.html.twig', [
        'invoice' => $invoice,
        'form' => $form,
    ]);
}

#[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
public function show(Invoice $invoice): Response
{
    // Sécurité : Vérifie que la facture appartient bien à l'utilisateur connecté
    if ($invoice->getOwner() !== $this->getUser()) {
        throw $this->createAccessDeniedException("Vous n'avez pas accès à cette facture.");
    }

    return $this->render('invoice/show.html.twig', [
        'invoice' => $invoice,
    ]);
}

#[Route('/{id}/validate', name: 'app_invoice_validate', methods: ['POST'])]
    public function validate(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $invoice->setStatus('validée');
        $entityManager->flush();

        $this->addFlash('success', 'La facture a été validée.');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

      #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function downloadPdf(Invoice $invoice): Response
    {
        // 1. Sécurité
        if ($invoice->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 2. Configuration de Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        // 3. Récupération du HTML (on crée un template spécifique pour le PDF)
        $html = $this->renderView('invoice/pdf.html.twig', [
            'invoice' => $invoice,
        ]);

        // 4. Génération
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 5. Envoi au navigateur
        $dompdf->stream("facture-" . $invoice->getNumber() . ".pdf", [
            "Attachment" => true // true = télécharge, false = affiche dans le navigateur
        ]);

        return new Response();
    }
    // ==========================================================

#[Route('/{id}/send', name: 'app_invoice_send', methods: ['POST'])]
    public function sendEmail(Invoice $invoice, MailerInterface $mailer, Request $request): Response
    {
        // 1. Sécurité : seul le proprio peut envoyer la facture
        if ($invoice->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 2. Vérification du jeton CSRF (C'est ce qui manquait pour valider le clic)
        if (!$this->isCsrfTokenValid('send-email' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Sécurité : Jeton invalide.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        // 3. Génération du contenu PDF pour la pièce jointe
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('invoice/pdf.html.twig', [
            'invoice' => $invoice,
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $pdfOutput = $dompdf->output();

        // 4. Création de l'email
        $email = (new Email())
            ->from('noreply@ton-saas.com')
            ->to($invoice->getCustomer()->getEmail())
            ->subject('Votre facture ' . $invoice->getNumber())
            ->text('Bonjour ' . $invoice->getCustomer()->getName() . ', veuillez trouver ci-joint votre facture.')
            ->attach($pdfOutput, 'facture-' . $invoice->getNumber() . '.pdf', 'application/pdf');

        // 5. Envoi
        $mailer->send($email);

        $this->addFlash('success', 'La facture a été envoyée avec succès au client !');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }


#[Route('/{id}/remind', name: 'app_invoice_remind', methods: ['POST'])]
    public function sendReminder(Invoice $invoice, MailerInterface $mailer, Request $request): Response
    {
        // 1. Sécurité Propriétaire
        if ($invoice->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 2. Vérification du jeton CSRF (spécifique à la relance)
        if (!$this->isCsrfTokenValid('remind-email' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Sécurité : Jeton de relance invalide.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        // 3. Génération du PDF pour la pièce jointe
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('invoice/pdf.html.twig', ['invoice' => $invoice]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();

        // 4. Création de l'email de RELANCE (Message plus formel + IBAN)
        $email = (new Email())
            ->from('comptabilite@votre-saas.com')
            ->to($invoice->getCustomer()->getEmail())
            ->subject('RAPPEL : Facture en attente de paiement - ' . $invoice->getNumber())
            ->text(
                "Bonjour " . $invoice->getCustomer()->getName() . ",\n\n" .
                "Sauf erreur de notre part, nous n'avons pas encore reçu le règlement de la facture " . $invoice->getNumber() . ".\n" .
                "Nous vous prions de bien vouloir régulariser la situation dans les plus brefs délais par virement sur l'IBAN suivant : " . 
                $invoice->getOwner()->getIban() . "\n\n" .
                "Vous trouverez la facture originale en pièce jointe.\n" .
                "Cordialement,\n" . $invoice->getOwner()->getRaisonSociale()
            )
            ->attach($pdfOutput, 'facture-' . $invoice->getNumber() . '.pdf', 'application/pdf');

        // 5. Envoi
        $mailer->send($email);

        $this->addFlash('success', 'Le mail de relance a été envoyé au client avec vos coordonnées bancaires.');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/pay', name: 'app_invoice_pay', methods: ['POST'])]
    public function pay(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $invoice->setStatus('payée');
        $entityManager->flush();

        $this->addFlash('success', 'La facture a été marquée comme payée !');

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$invoice->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($invoice);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }
}
