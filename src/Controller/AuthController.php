<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Если пользователь уже авторизован, перенаправляем на главную
        if ($this->getUser()) {
            return $this->redirectToRoute('app_booking');
        }

        // Получаем ошибку входа, если она есть
        $error = $authenticationUtils->getLastAuthenticationError();

        // Получаем последний введенный email
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // Если пользователь уже авторизован, перенаправляем на главную
        if ($this->getUser()) {
            return $this->redirectToRoute('app_booking');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $name = $request->request->get('name');
            $phone = $request->request->get('phone');
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            // Валидация
            if (empty($email) || empty($name) || empty($password)) {
                $this->addFlash('error', 'Пожалуйста, заполните все обязательные поля');
                return $this->redirectToRoute('app_register');
            }

            if ($password !== $passwordConfirm) {
                $this->addFlash('error', 'Пароли не совпадают');
                return $this->redirectToRoute('app_register');
            }

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Пароль должен содержать минимум 6 символов');
                return $this->redirectToRoute('app_register');
            }

            // Проверка существования пользователя
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'Пользователь с таким email уже существует');
                return $this->redirectToRoute('app_register');
            }

            // Создание нового пользователя
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setPhone($phone);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setRoles(['ROLE_USER']);
            $user->setIsActive(true);
            $user->setLanguage('ru');

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Регистрация успешно завершена! Теперь вы можете войти.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Этот метод будет перехвачен firewall'ом
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}