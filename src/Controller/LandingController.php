<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_landing')]
    public function index(): Response
    {
        // Если пользователь уже авторизован, перенаправляем в приложение
        if ($this->getUser()) {
            return $this->redirectToRoute('app_booking');
        }

        return $this->render('landing/index.html.twig');
    }

    #[Route('/demo', name: 'app_demo')]
    public function demo(): Response
    {
        return $this->render('landing/demo.html.twig');
    }
}
