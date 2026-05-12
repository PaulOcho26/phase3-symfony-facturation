<?php

namespace App\Controller;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        InvoiceRepository $invoiceRepository, 
        CustomerRepository $customerRepository,
        ChartBuilderInterface $chartBuilder // On ajoute le constructeur de graphique ici
    ): Response {
        $user = $this->getUser();
        $invoices = $invoiceRepository->findBy(['owner' => $user]);
        
        $totalCA = 0;
        // On crée un tableau de 12 zéros (un par mois)
        $monthlyData = array_fill(0, 12, 0);
        $currentYear = (new \DateTime())->format('Y');

        foreach ($invoices as $invoice) {
            // On ne compte que les factures payées
            if ($invoice->getStatus() === 'payée') {
                $totalCA += $invoice->getTotalAmount();

                // Si la facture date de l'année en cours, on l'ajoute à son mois
                if ($invoice->getCreatedAt()->format('Y') === $currentYear) {
                    $monthIndex = (int)$invoice->getCreatedAt()->format('m') - 1;
                    $monthlyData[$monthIndex] += $invoice->getTotalAmount();
                }
            }
        }

        // Configuration du graphique
        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'],
            'datasets' => [
                [
                    'label' => 'CA Mensuel ' . $currentYear . ' (€)',
                    'backgroundColor' => 'rgb(79, 70, 229)', // Couleur Indigo
                    'data' => $monthlyData,
                ],
            ],
        ]);

        $chart->setOptions([
            'scales' => [
                'y' => ['suggestedMin' => 0]
            ],
            'maintainAspectRatio' => false,
        ]);

        return $this->render('dashboard/index.html.twig', [
            'totalCA' => $totalCA,
            'totalClients' => $customerRepository->count(['owner' => $user]),
            'recentInvoices' => $invoiceRepository->findBy(['owner' => $user], ['createdAt' => 'DESC'], 5),
            'chart' => $chart, // On envoie le graphique à Twig
        ]);
    }
}