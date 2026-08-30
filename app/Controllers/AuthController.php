<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) Auth::redirect('/dashboard');
        View::render('auth/login', ['error' => null], 'layouts/guest');
    }

    public function login(): void
    {
        $login = trim($_POST['login'] ?? '');
        if ($login !== '' && Auth::attempt($login, (string) ($_POST['password'] ?? ''))) {
            Auth::redirect(Auth::requiresPasswordChange() ? '/password/change' : '/dashboard');
        }
        usleep(300000);
        View::render('auth/login', ['error' => 'The credentials are invalid, the account is inactive, or it is temporarily locked.'], 'layouts/guest');
    }

    public function logout(): void
    {
        Auth::requireLogin();
        Auth::logout();
        header('Location: ' . url('/login'));
    }

    public function showPasswordChange(): void
    {
        Auth::requireLogin();
        if (!Auth::requiresPasswordChange()) Auth::redirect('/dashboard');
        View::render('auth/change-password', ['error' => null], 'layouts/guest');
    }

    public function changePassword(): void
    {
        Auth::requireLogin();
        if (!Auth::requiresPasswordChange()) Auth::redirect('/dashboard');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $error = null;
        if (strlen($password) < 10) $error = 'Your new password must contain at least 10 characters.';
        elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) $error = 'Use at least one uppercase letter, one lowercase letter, and one number.';
        elseif ($password !== $confirmation) $error = 'The password confirmation does not match.';
        if ($error === null) {
            try {
                Auth::replacePassword($password);
                flash('success', 'Password changed. Your account is ready to use.');
                Auth::redirect('/dashboard');
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }
        View::render('auth/change-password', compact('error'), 'layouts/guest');
    }
}
