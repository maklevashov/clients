<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Appointment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractController
{
    #[Route('/', name: 'app_booking')]
    #[Route('/booking', name: 'app_booking_alt')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Проверка авторизации
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();

        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :today')
            ->andWhere('a.user = :user')
            ->setParameter('today', new \DateTime())
            ->setParameter('user', $user)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $clients = $entityManager->getRepository(Client::class)
            ->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('booking/index.html.twig', [
            'appointments' => $appointments,
            'clients' => $clients,
            'current_date' => new \DateTime()
        ]);
    }

    #[Route('/clients', name: 'app_clients')]
    public function clients(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $clients = $entityManager->getRepository(Client::class)
            ->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('booking/clients.html.twig', [
            'clients' => $clients
        ]);
    }

    #[Route('/schedule', name: 'app_schedule')]
    public function schedule(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $clients = $entityManager->getRepository(Client::class)
            ->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('booking/schedule.html.twig', [
            'clients' => $clients
        ]);
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function notifications(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('booking/notifications.html.twig');
    }

    #[Route('/client/{id}', name: 'app_client_profile')]
    public function clientProfile(int $id, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $client = $entityManager->getRepository(Client::class)
            ->findOneBy(['id' => $id, 'user' => $user]);

        if (!$client) {
            throw $this->createNotFoundException('Клиент не найден');
        }

        return $this->render('booking/client_profile.html.twig', [
            'client_id' => $id,
            'client' => $client
        ]);
    }

    // API методы
    #[Route('/api/appointments', name: 'api_appointments', methods: ['GET'])]
    public function getAppointments(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->getUser();
        $date = $request->query->get('date') ? new \DateTime($request->query->get('date')) : new \DateTime();

        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a.id', 'a.appointmentDate', 'a.startTime', 'a.endTime', 'c.id as client_id', 'c.name', 'c.phone')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :date')
            ->andWhere('a.user = :user')
            ->setParameter('date', $date)
            ->setParameter('user', $user)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $formattedAppointments = [];
        foreach ($appointments as $appointment) {
            $formattedAppointments[] = [
                'id' => $appointment['id'],
                'client_name' => $appointment['name'],
                'client_phone' => $appointment['phone'],
                'client_id' => $appointment['client_id'],
                'start_time' => $appointment['startTime']->format('H:i'),
                'end_time' => $appointment['endTime']->format('H:i'),
                'date' => $appointment['appointmentDate']->format('Y-m-d')
            ];
        }

        return $this->json($formattedAppointments);
    }

    #[Route('/api/appointments', name: 'api_create_appointment', methods: ['POST'])]
    public function createAppointment(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $client = $entityManager->getRepository(Client::class)
                ->findOneBy(['id' => $data['client_id'], 'user' => $user]);

            if (!$client) {
                return $this->json(['error' => 'Клиент не найден'], 404);
            }

            $startTime = new \DateTime($data['start_time']);
            $endTime = (clone $startTime)->modify('+3 hours');

            $appointment = new Appointment();
            $appointment->setClient($client);
            $appointment->setUser($user);
            $appointment->setAppointmentDate(new \DateTime($data['date']));
            $appointment->setStartTime($startTime);
            $appointment->setEndTime($endTime);

            $entityManager->persist($appointment);
            $entityManager->flush();

            return $this->json(['success' => true, 'id' => $appointment->getId()]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/appointments/{id}', name: 'api_delete_appointment', methods: ['DELETE'])]
    public function deleteAppointment(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->getUser();
        $appointment = $entityManager->getRepository(Appointment::class)
            ->findOneBy(['id' => $id, 'user' => $user]);

        if (!$appointment) {
            return $this->json(['error' => 'Запись не найдена'], 404);
        }

        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/clients', name: 'api_clients', methods: ['GET'])]
    public function getClients(EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->getUser();
        $clients = $entityManager->getRepository(Client::class)
            ->findBy(['user' => $user], ['name' => 'ASC']);

        $data = [];
        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'phone' => $client->getPhone()
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/clients', name: 'api_create_client', methods: ['POST'])]
    public function createClient(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $client = new Client();
            $client->setName($data['name']);
            $client->setPhone($data['phone']);
            $client->setUser($user);

            $entityManager->persist($client);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'id' => $client->getId(),
                'name' => $client->getName(),
                'phone' => $client->getPhone()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}