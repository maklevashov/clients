<?php


namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Поиск клиентов по имени или телефону
     * @param string $searchTerm
     * @return Client[]
     */
    public function searchClients(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :search OR c.phone LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получение клиентов с количеством записей
     * @return array
     */
    public function findWithAppointmentCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(a.id) as appointment_count')
            ->leftJoin('c.appointments', 'a')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Проверка существования клиента по телефону
     * @param string $phone
     * @return bool
     */
    public function existsByPhone(string $phone): bool
    {
        return null !== $this->createQueryBuilder('c')
                ->select('c.id')
                ->where('c.phone = :phone')
                ->setParameter('phone', $phone)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
    }
}