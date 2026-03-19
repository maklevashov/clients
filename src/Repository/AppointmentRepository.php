<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Получение записей на конкретную дату
     * @param \DateTimeInterface $date
     * @return Appointment[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :date')
            ->setParameter('date', $date)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получение записей за период
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return Appointment[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.appointmentDate', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Проверка доступности времени
     * @param \DateTimeInterface $date
     * @param \DateTimeInterface $time
     * @return bool
     */
    public function isTimeSlotAvailable(\DateTimeInterface $date, \DateTimeInterface $time): bool
    {
        $endTime = clone $time;
        $endTime->modify('+3 hours');

        $existing = $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.appointmentDate = :date')
            ->andWhere('a.startTime <= :endTime')
            ->andWhere('a.endTime >= :startTime')
            ->setParameter('date', $date)
            ->setParameter('startTime', $time)
            ->setParameter('endTime', $endTime)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $existing === null;
    }

    /**
     * Получение записей клиента
     * @param int $clientId
     * @return Appointment[]
     */
    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('a.appointmentDate', 'DESC')
            ->addOrderBy('a.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получение сегодняшних записей
     * @return Appointment[]
     */
    public function findTodayAppointments(): array
    {
        $today = new \DateTime('today');

        return $this->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :today')
            ->setParameter('today', $today)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получение статистики по записям за месяц
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getMonthlyStats(int $month, int $year): array
    {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as total_appointments')
            ->addSelect('COUNT(DISTINCT a.client) as unique_clients')
            ->where('a.appointmentDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Поиск пересекающихся записей
     * @param \DateTimeInterface $date
     * @param \DateTimeInterface $startTime
     * @param \DateTimeInterface $endTime
     * @param int|null $excludeId
     * @return Appointment[]
     */
    public function findOverlappingAppointments(
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.appointmentDate = :date')
            ->andWhere('a.startTime < :endTime')
            ->andWhere('a.endTime > :startTime')
            ->setParameter('date', $date)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Получение ближайших записей
     * @param int $limit
     * @return Appointment[]
     */
    public function findUpcomingAppointments(int $limit = 10): array
    {
        $today = new \DateTime('today');

        return $this->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate >= :today')
            ->setParameter('today', $today)
            ->orderBy('a.appointmentDate', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
