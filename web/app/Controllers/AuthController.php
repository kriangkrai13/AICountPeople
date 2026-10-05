<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to('/');
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        $email = trim((string) $this->request->getPost('email'));
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())
            ->where('email', $email)
            ->where('is_active', 1)
            ->first();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return redirect()->back()->with('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง')->withInput();
        }

        session()->set([
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_role' => $user['role'],
        ]);

        return redirect()->to('/');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
