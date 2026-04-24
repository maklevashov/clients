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
}
