<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

// src/Repository/InvoiceRepository.php

public function countInvoicesInCurrentMonth(): int
{
    $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
    $endOfMonth = new \DateTimeImmutable('last day of this month 23:59:59');

    return $this->createQueryBuilder('i')
        ->select('count(i.id)')
        ->where('i.createdAt BETWEEN :start AND :end')
        ->setParameter('start', $startOfMonth)
        ->setParameter('end', $endOfMonth)
        ->getQuery()
        ->getSingleScalarResult();
}
}
