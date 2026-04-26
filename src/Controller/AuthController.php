<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_booking');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_booking');
        }

        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setEmail($request->request->get('email'));
            $user->setName($request->request->get('name'));
            $user->setPhone($request->request->get('phone'));
            $user->setPassword($passwordHasher->hashPassword($user, $request->request->get('password')));

            $errors = $validator->validate($user);

            if (count($errors) === 0) {
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Регистрация успешно завершена! Теперь вы можете войти.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function apiRegister(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setName($data['name']);
        $user->setPhone($data['phone'] ?? null);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => $errorMessages], 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User registered successfully'], 201);
    }

    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function apiLoginCheck(): JsonResponse
    {
        // JWT будет обработан автоматически
        return $this->json(['message' => 'Login successful']);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/api/user', name: 'api_user', methods: ['GET'])]
    public function getUserInfo(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'language' => $user->getLanguage()
        ]);
    }

    #[Route('/api/user', name: 'api_update_user', methods: ['PUT'])]
    public function updateUser(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if (isset($data['language'])) {
            $user->setLanguage($data['language']);
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/api/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $oldPassword = $data['old_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        return $this->json(['success' => true]);
    }
}
