<?php

namespace App\Controllers\Admin;

use CodeIgniter\Shield\Entities\User;

/**
 * Staff users and their roles. One user can have several roles.
 */
class Users extends AdminController
{
    public function index()
    {
        $this->requirePermission('users.manage');
        $users = db_connect()->table('users u')
            ->select("u.id, u.username, u.active, u.status, u.last_active, i.secret AS email")
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)->orderBy('u.username')->get()->getResultArray();
        $groups = [];
        foreach (db_connect()->table('auth_groups_users')->get()->getResultArray() as $g) {
            $groups[$g['user_id']][] = setting('AuthGroups.groups')[$g['group']]['title'] ?? $g['group'];
        }

        return $this->render('users/index', ['users' => $users, 'groups' => $groups]);
    }

    public function form($id = null)
    {
        $this->requirePermission('users.manage');
        $user = $id ? auth()->getProvider()->findById((int) $id) : null;

        return $this->render('users/form', [
            'user'      => $user,
            'userRoles' => $user ? $user->getGroups() : [],
            'roles'     => $this->assignableRoles(),
        ]);
    }

    public function save($id = null)
    {
        $this->requirePermission('users.manage');
        $users = auth()->getProvider();
        $post  = $this->request->getPost();

        $rules = [
            'username' => 'required|alpha_numeric_punct|min_length[3]|max_length[30]',
            'email'    => 'required|valid_email',
            'roles'    => 'required',
        ];
        $rules['password'] = $id ? 'permit_empty|min_length[8]' : 'required|min_length[8]';
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $roles = array_values(array_intersect((array) $post['roles'], array_keys($this->assignableRoles())));
        if ($roles === []) {
            return $this->back('Choose at least one role you are allowed to assign.');
        }

        if (! $id && ($limit = \App\Libraries\Saas::userLimitMessage())) {
            return $this->back($limit);
        }
        $user = $id ? $users->findById((int) $id) : new User();
        if ($id && ! $user) {
            return $this->back('User not found.');
        }
        // Only a super admin may edit another super admin.
        if ($id && $user->inGroup('superadmin') && ! auth()->user()->inGroup('superadmin')) {
            return $this->back('Only a Super Admin can edit a Super Admin.');
        }

        $dupe = db_connect()->table('auth_identities')->where(['type' => 'email_password', 'secret' => $post['email']]);
        if ($id) {
            $dupe->where('user_id !=', (int) $id);
        }
        if ($dupe->countAllResults()) {
            return $this->back('Another user already uses this email.');
        }

        $user->fill(['username' => $post['username'], 'email' => $post['email']]);
        if (! empty($post['password'])) {
            $user->fill(['password' => $post['password']]);
        }
        try {
            $users->save($user);
        } catch (\Throwable $e) {
            return $this->back('Could not save: ' . $e->getMessage());
        }

        $user = $id ? $users->findById((int) $id) : $users->findById($users->getInsertID());
        if (! $id) {
            $user->activate();
        }
        $user->syncGroups(...$roles);

        return redirect()->to('admin/users')->with('message', 'User ' . $post['username'] . ' saved (roles: ' . implode(', ', $roles) . ').');
    }

    public function toggle($id)
    {
        $this->requirePermission('users.manage');
        $user = auth()->getProvider()->findById((int) $id);
        if (! $user || (int) $id === (int) auth()->id()) {
            return $this->back('You cannot deactivate yourself.');
        }
        // Shield "ban" blocks login and ends existing sessions on the next request.
        if ($user->isBanned()) {
            if ($limit = \App\Libraries\Saas::userLimitMessage()) {
                return $this->back($limit);
            }
            $user->unBan();
            $user->activate();
            $msg = 'activated';
        } else {
            $user->ban('Your account has been deactivated. Contact your administrator.');
            $user->deactivate();
            $msg = 'deactivated';
        }

        return redirect()->back()->with('message', 'User ' . $user->username . ' ' . $msg . '.');
    }

    /** Super admins can assign every role; others cannot create super admins. */
    private function assignableRoles(): array
    {
        $roles = setting('AuthGroups.groups');
        if (! auth()->user()->inGroup('superadmin')) {
            unset($roles['superadmin']);
        }

        return $roles;
    }
}
