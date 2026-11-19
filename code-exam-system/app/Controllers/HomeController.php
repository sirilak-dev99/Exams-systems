<?php

declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        if (!Security::isAuthenticated()) {
            $this->redirect('/login');
        }

        $this->view('dashboard', [
            'pageTitle' => 'ภาพรวมระบบ',
            'user' => Security::currentUser(),
        ]);
    }
}
