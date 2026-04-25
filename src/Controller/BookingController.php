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
    public function index(EntityManagerInterface $entityManager): Response
    {
        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :today')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $clients = $entityManager->getRepository(Client::class)->findBy([], ['name' => 'ASC']);

        return $this->render('booking/index.html.twig', [
            'appointments' => $appointments,
            'clients' => $clients,
            'current_date' => new \DateTime()
        ]);
    }

    #[Route('/clients', name: 'app_clients')]
    public function clients(EntityManagerInterface $entityManager): Response
    {
        $clients = $entityManager->getRepository(Client::class)->findBy([], ['name' => 'ASC']);

        return $this->render('booking/clients.html.twig', [
            'clients' => $clients
        ]);
    }

    #[Route('/api/appointments', name: 'api_appointments', methods: ['GET'])]
    public function getAppointments(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $date = $request->query->get('date') ? new \DateTime($request->query->get('date')) : new \DateTime();

        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a.id', 'a.appointmentDate', 'a.startTime', 'a.endTime', 'c.id as client_id', 'c.name', 'c.phone')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :date')
            ->setParameter('date', $date)
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
        $data = json_decode($request->getContent(), true);

        try {
            $client = $entityManager->getRepository(Client::class)->find($data['client_id']);
            if (!$client) {
                return $this->json(['error' => 'Клиент не найден'], 404);
            }

            $startTime = new \DateTime($data['start_time']);
            $endTime = (clone $startTime)->modify('+3 hours');

            $appointment = new Appointment();
            $appointment->setClient($client);
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

    #[Route('/api/clients', name: 'api_create_client', methods: ['POST'])]
    public function createClient(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $client = new Client();
            $client->setName($data['name']);
            $client->setPhone($data['phone']);

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

    #[Route('/api/appointments/{id}', name: 'api_delete_appointment', methods: ['DELETE'])]
    public function deleteAppointment(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $appointment = $entityManager->getRepository(Appointment::class)->find($id);

        if (!$appointment) {
            return $this->json(['error' => 'Запись не найдена'], 404);
        }

        $entityManager->remove($appointment);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }


// Добавить в существующий контроллер

    #[Route('/client/{id}', name: 'client_profile')]
    public function clientProfile(int $id, EntityManagerInterface $entityManager): Response
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            throw $this->createNotFoundException('Клиент не найден');
        }

        return $this->render('booking/client_profile.html.twig', [
            'client_id' => $id,
            'client' => $client
        ]);
    }

    #[Route('/api/clients/{id}', name: 'api_get_client', methods: ['GET'])]
    public function getClient(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            return $this->json(['error' => 'Клиент не найден'], 404);
        }

        // Получаем дополнительные данные из метаданных (если есть)
        $metadata = $client->getMetadata() ?? [];

        return $this->json([
            'id' => $client->getId(),
            'name' => $client->getName(),
            'phone' => $client->getPhone(),
            'email' => $metadata['email'] ?? null,
            'gender' => $metadata['gender'] ?? null,
            'birthday' => $metadata['birthday'] ?? null,
            'categories' => $metadata['categories'] ?? [],
            'note' => $metadata['note'] ?? null,
            'created_at' => $client->getCreatedAt()->format('c')
        ]);
    }

    #[Route('/api/clients/{id}', name: 'api_update_client', methods: ['PUT'])]
    public function updateClient(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            return $this->json(['error' => 'Клиент не найден'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $metadata = $client->getMetadata() ?? [];

        // Обновляем основные поля
        if (isset($data['name'])) {
            $client->setName($data['name']);
        }

        if (isset($data['phone'])) {
            $client->setPhone($data['phone']);
        }

        // Обновляем метаданные
        if (isset($data['email'])) {
            $metadata['email'] = $data['email'];
        }

        if (isset($data['gender'])) {
            $metadata['gender'] = $data['gender'];
        }

        if (isset($data['birthday'])) {
            $metadata['birthday'] = $data['birthday'];
        }

        if (isset($data['note'])) {
            $metadata['note'] = $data['note'];
        }

        $client->setMetadata($metadata);

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/clients/{id}/categories', name: 'api_add_category', methods: ['POST'])]
    public function addCategory(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            return $this->json(['error' => 'Клиент не найден'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $category = $data['category'] ?? null;

        if (!$category) {
            return $this->json(['error' => 'Категория не указана'], 400);
        }

        $metadata = $client->getMetadata() ?? [];
        $categories = $metadata['categories'] ?? [];

        if (!in_array($category, $categories)) {
            $categories[] = $category;
            $metadata['categories'] = $categories;
            $client->setMetadata($metadata);
            $entityManager->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/clients/{id}/categories', name: 'api_remove_category', methods: ['DELETE'])]
    public function removeCategory(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            return $this->json(['error' => 'Клиент не найден'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $category = $data['category'] ?? null;

        if (!$category) {
            return $this->json(['error' => 'Категория не указана'], 400);
        }

        $metadata = $client->getMetadata() ?? [];
        $categories = $metadata['categories'] ?? [];

        $index = array_search($category, $categories);
        if ($index !== false) {
            array_splice($categories, $index, 1);
            $metadata['categories'] = $categories;
            $client->setMetadata($metadata);
            $entityManager->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/clients/{id}/appointments', name: 'api_client_appointments', methods: ['GET'])]
    public function getClientAppointments(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $client = $entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            return $this->json(['error' => 'Клиент не найден'], 404);
        }

        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.appointmentDate', 'DESC')
            ->addOrderBy('a.startTime', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($appointments as $appointment) {
            $data[] = [
                'id' => $appointment->getId(),
                'date' => $appointment->getAppointmentDate()->format('d.m.Y'),
                'start_time' => $appointment->getStartTime()->format('H:i'),
                'end_time' => $appointment->getEndTime()->format('H:i')
            ];
        }

        return $this->json($data);
    }
}
