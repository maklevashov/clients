<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class OrganizationController extends AbstractController
{
    #[Route('/setup/organization', name: 'app_organization_setup')]
    #[IsGranted('ROLE_USER')]
    public function setup(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Если у пользователя уже есть организация, перенаправляем
        if ($user->getOrganization()) {
            return $this->redirectToRoute('app_booking');
        }

        if ($request->isMethod('POST')) {
            $orgName = $request->request->get('organization_name');
            $orgPhone = $request->request->get('organization_phone');
            $orgAddress = $request->request->get('organization_address');
            $orgEmail = $request->request->get('organization_email');

            if (empty($orgName)) {
                $this->addFlash('error', 'Название организации обязательно');
                return $this->redirectToRoute('app_organization_setup');
            }

            // Создаем организацию
            $organization = new Organization();
            $organization->setName($orgName);
            $organization->setPhone($orgPhone);
            $organization->setAddress($orgAddress);
            $organization->setEmail($orgEmail);

            $entityManager->persist($organization);

            // Привязываем пользователя к организации
            $user->setOrganization($organization);
            $user->setPosition('owner');
            $user->setPermissions(['all']);

            $entityManager->flush();

            $this->addFlash('success', 'Организация успешно создана!');
            return $this->redirectToRoute('app_booking');
        }

        return $this->render('organization/setup.html.twig');
    }

    #[Route('/organization/select', name: 'app_organization_select')]
    #[IsGranted('ROLE_USER')]
    public function select(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        // Если у пользователя уже есть организация, перенаправляем
        if ($user->getOrganization()) {
            return $this->redirectToRoute('app_booking');
        }

        // Получаем все организации (для выбора)
        $organizations = $entityManager->getRepository(Organization::class)->findAll();

        if ($request->isMethod('POST')) {
            $orgId = $request->request->get('organization_id');

            if (empty($orgId)) {
                $this->addFlash('error', 'Выберите организацию');
                return $this->redirectToRoute('app_organization_select');
            }

            $organization = $entityManager->getRepository(Organization::class)->find($orgId);

            if (!$organization) {
                $this->addFlash('error', 'Организация не найдена');
                return $this->redirectToRoute('app_organization_select');
            }

            // Привязываем пользователя к организации
            $user->setOrganization($organization);
            $user->setPosition('staff');
            $user->setPermissions(['view_appointments', 'create_appointments']);

            $entityManager->flush();

            $this->addFlash('success', 'Вы присоединились к организации!');
            return $this->redirectToRoute('app_booking');
        }

        return $this->render('organization/select.html.twig', [
            'organizations' => $organizations
        ]);
    }

    #[Route('/organization/{id}', name: 'app_organization_view')]
    #[IsGranted('ROLE_USER')]
    public function view(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $organization = $entityManager->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException('Организация не найдена');
        }

        // Проверяем, что пользователь принадлежит этой организации
        if ($user->getOrganization() !== $organization) {
            throw $this->createAccessDeniedException('У вас нет доступа к этой организации');
        }

        // Получаем статистику
        $totalClients = $entityManager->getRepository(\App\Entity\Client::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.organization = :org')
            ->setParameter('org', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAppointments = $entityManager->getRepository(\App\Entity\Appointment::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.organization = :org')
            ->setParameter('org', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $totalStaff = $entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.organization = :org')
            ->setParameter('org', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $recentAppointments = $entityManager->getRepository(\App\Entity\Appointment::class)
            ->createQueryBuilder('a')
            ->select('a', 'c')
            ->leftJoin('a.client', 'c')
            ->where('a.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('organization/view.html.twig', [
            'organization' => $organization,
            'totalClients' => $totalClients,
            'totalAppointments' => $totalAppointments,
            'totalStaff' => $totalStaff,
            'recentAppointments' => $recentAppointments
        ]);
    }

    #[Route('/organization/{id}/edit', name: 'app_organization_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $organization = $entityManager->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException('Организация не найдена');
        }

        // Проверяем, что пользователь является владельцем или администратором
        if ($user->getOrganization() !== $organization || $user->getPosition() !== 'owner') {
            throw $this->createAccessDeniedException('Только владелец может редактировать организацию');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $phone = $request->request->get('phone');
            $address = $request->request->get('address');
            $email = $request->request->get('email');
            $website = $request->request->get('website');

            if (empty($name)) {
                $this->addFlash('error', 'Название организации обязательно');
                return $this->redirectToRoute('app_organization_edit', ['id' => $id]);
            }

            $organization->setName($name);
            $organization->setPhone($phone);
            $organization->setAddress($address);
            $organization->setEmail($email);
            $organization->setWebsite($website);

            $entityManager->flush();

            $this->addFlash('success', 'Данные организации обновлены');
            return $this->redirectToRoute('app_organization_view', ['id' => $id]);
        }

        return $this->render('organization/edit.html.twig', [
            'organization' => $organization
        ]);
    }

    #[Route('/organization/{id}/settings', name: 'app_organization_settings')]
    #[IsGranted('ROLE_USER')]
    public function settings(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $organization = $entityManager->getRepository(Organization::class)->find($id);

        if (!$organization) {
            throw $this->createNotFoundException('Организация не найдена');
        }

        // Проверяем права
        if ($user->getOrganization() !== $organization || !in_array($user->getPosition(), ['owner', 'admin'])) {
            throw $this->createAccessDeniedException('У вас нет прав на изменение настроек');
        }

        $settings = $organization->getSettings() ?? [];

        if ($request->isMethod('POST')) {
            $settings = [
                'working_hours_start' => $request->request->get('working_hours_start', '09:00'),
                'working_hours_end' => $request->request->get('working_hours_end', '18:00'),
                'slot_duration' => (int) $request->request->get('slot_duration', 60),
                'break_duration' => (int) $request->request->get('break_duration', 0),
                'notification_enabled' => $request->request->has('notification_enabled'),
                'notification_minutes' => (int) $request->request->get('notification_minutes', 60),
                'timezone' => $request->request->get('timezone', 'Europe/Moscow'),
                'week_start' => $request->request->get('week_start', 'monday')
            ];

            $organization->setSettings($settings);
            $entityManager->flush();

            $this->addFlash('success', 'Настройки сохранены');
            return $this->redirectToRoute('app_organization_settings', ['id' => $id]);
        }

        return $this->render('organization/settings.html.twig', [
            'organization' => $organization,
            'settings' => $settings
        ]);
    }

    #[Route('/api/organizations', name: 'api_organizations', methods: ['GET'])]
    public function getOrganizations(EntityManagerInterface $entityManager): Response
    {
        $organizations = $entityManager->getRepository(Organization::class)->findAll();

        $data = [];
        foreach ($organizations as $org) {
            $data[] = [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'phone' => $org->getPhone(),
                'address' => $org->getAddress(),
                'email' => $org->getEmail()
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/organizations/{id}', name: 'api_organization', methods: ['GET'])]
    public function getOrganization(int $id, EntityManagerInterface $entityManager): Response
    {
        $organization = $entityManager->getRepository(Organization::class)->find($id);

        if (!$organization) {
            return $this->json(['error' => 'Organization not found'], 404);
        }

        return $this->json([
            'id' => $organization->getId(),
            'name' => $organization->getName(),
            'phone' => $organization->getPhone(),
            'address' => $organization->getAddress(),
            'email' => $organization->getEmail(),
            'website' => $organization->getWebsite(),
            'settings' => $organization->getSettings()
        ]);
    }
}
