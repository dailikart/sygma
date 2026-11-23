<?php

namespace App\Controllers\Admin;

class Profile extends AdminController
{
    public function index()
    {
        return $this->render('profile', ['user' => auth()->user()]);
    }

    public function update()
    {
        if (! $this->validate(['current' => 'required', 'password' => 'required|min_length[8]', 'confirm' => 'matches[password]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $user  = auth()->user();
        $check = auth()->check(['email' => $user->email, 'password' => $this->request->getPost('current')]);
        if (! $check->isOK()) {
            return $this->back('Current password is wrong.');
        }
        $user->fill(['password' => $this->request->getPost('password')]);
        auth()->getProvider()->save($user);

        return redirect()->to('admin/profile')->with('message', 'Password changed.');
    }
}
