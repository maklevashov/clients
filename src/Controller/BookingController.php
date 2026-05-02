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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        $clients = $entityManager->getRepository(Client::class)
            ->findBy([], ['name' => 'ASC']);

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

    #[Route('/api/clients/{id}', name: 'api_update_client', methods: ['POST'])]
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

    #[Route('/api/appointments/all', name: 'api_all_appointments', methods: ['GET'])]
    public function getAllAppointments(EntityManagerInterface $entityManager): JsonResponse
    {
        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a.id', 'a.appointmentDate as date', 'a.startTime as start_time', 'a.endTime as end_time',
                'c.id as client_id', 'c.name as client_name', 'c.phone as client_phone')
            ->leftJoin('a.client', 'c')
            ->orderBy('a.appointmentDate', 'ASC')
            ->getQuery()
            ->getResult();

        $formatted = [];
        foreach ($appointments as $appointment) {
            $formatted[] = [
                'id' => $appointment['id'],
                'date' => $appointment['date']->format('Y-m-d'),
                'start_time' => $appointment['start_time']->format('H:i'),
                'end_time' => $appointment['end_time']->format('H:i'),
                'client_id' => $appointment['client_id'],
                'client_name' => $appointment['client_name'],
                'client_phone' => $appointment['client_phone']
            ];
        }

        return $this->json($formatted);
    }

    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    public function getClientsList(EntityManagerInterface $entityManager): JsonResponse
    {
        $clients = $entityManager->getRepository(Client::class)->findBy([], ['name' => 'ASC']);

        $data = array_map(function (Client $client) {
            return [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'phone' => $client->getPhone()
            ];
        }, $clients);

        return $this->json($data);
    }

    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function getNotifications(EntityManagerInterface $entityManager): JsonResponse
    {
        // Здесь можно генерировать уведомления из различных источников
        $notifications = [];

        // Получаем сегодняшние записи
        $today = new \DateTime('today');
        $appointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.appointmentDate = :today')
            ->setParameter('today', $today)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($appointments as $appointment) {
            $notifications[] = [
                'id' => $appointment->getId(),
                'type' => 'appointment',
                'title' => 'Новая запись',
                'message' => sprintf('%s записан(а) на %s %s',
                    $appointment->getClient()->getName(),
                    $appointment->getAppointmentDate()->format('d.m'),
                    $appointment->getStartTime()->format('H:i')
                ),
                'created_at' => $appointment->getCreatedAt()->format('c'),
                'read' => false
            ];
        }

        // Сортируем по дате
        usort($notifications, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $this->json($notifications);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notification_read', methods: ['POST'])]
    public function markNotificationRead(int $id): JsonResponse
    {
        // В реальном приложении здесь нужно сохранять статус прочтения в БД
        return $this->json(['success' => true]);
    }

    #[Route('/api/notifications/mark-all-read', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(): JsonResponse
    {
        // В реальном приложении здесь нужно сохранять статус прочтения в БД
        return $this->json(['success' => true]);
    }

    #[Route('/api/clients/search', name: 'api_clients_search', methods: ['GET'])]
    public function searchClients(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $qb = $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user);

        // Поиск по имени или телефону
        $qb->andWhere('c.name LIKE :query OR c.phone LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(20);

        $clients = $qb->getQuery()->getResult();

        $data = [];
        foreach ($clients as $client) {
            $metadata = $client->getMetadata() ?? [];
            $data[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'phone' => $client->getPhone(),
                'email' => $metadata['email'] ?? 'Неизвестно'
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/clients/recent', name: 'api_clients_recent', methods: ['GET'])]
    public function getRecentClients(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Получаем последних 10 клиентов, у которых были записи
        $clients = $entityManager->createQueryBuilder()
            ->select('c.id', 'c.name', 'c.phone', 'c.metadata')
            ->from('App\Entity\Client', 'c')
            ->leftJoin('App\Entity\Appointment', 'a', 'WITH', 'a.client = c.id')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('MAX(a.createdAt)', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($clients as $client) {
            $metadata = $client['metadata'] ?? [];
            $data[] = [
                'id' => $client['id'],
                'name' => $client['name'],
                'phone' => $client['phone'],
                'email' => $metadata['email'] ?? 'Неизвестно'
            ];
        }

        return $this->json($data);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        return $this->render('booking/profile.html.twig');
    }

    #[Route('/api/user/statistics', name: 'api_user_statistics', methods: ['GET'])]
    public function getUserStatistics(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $totalClients = $entityManager->getRepository(Client::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAppointments = $entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $createdAt = $user->getCreatedAt();
        $now = new \DateTime();
        $memberDays = $createdAt->diff($now)->days;

        return $this->json([
            'totalClients' => (int) $totalClients,
            'totalAppointments' => (int) $totalAppointments,
            'memberDays' => $memberDays
        ]);
    }

    #[Route('/api/user/profile', name: 'api_user_profile', methods: ['POST'])]
    public function updateProfile(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $field = $data['field'];
        $value = trim($data['value']);

        switch ($field) {
            case 'name':
                if (empty($value)) {
                    return $this->json(['error' => 'Имя не может быть пустым'], 400);
                }
                $user->setName($value);
                break;
            case 'email':
                if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->json(['error' => 'Введите корректный email'], 400);
                }
                // Проверка уникальности email
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $value]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    return $this->json(['error' => 'Этот email уже используется'], 400);
                }
                $user->setEmail($value);
                break;
            case 'phone':
                $user->setPhone($value ?: null);
                break;
            case 'language':
                $user->setLanguage($value);
                break;
            default:
                return $this->json(['error' => 'Неверное поле'], 400);
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/user/change-password', name: 'api_user_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'Текущий пароль неверен'], 400);
        }

        if (strlen($newPassword) < 6) {
            return $this->json(['error' => 'Пароль должен содержать минимум 6 символов'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}