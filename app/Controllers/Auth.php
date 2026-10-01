<?php

namespace App\Controllers;

use App\Libraries\Mailer;
use App\Models\PasswordResetModel;
use App\Models\PerfilModel;
use App\Models\UsuarioModel;

class Auth extends BaseController
{
    private const MAX_INTENTOS = 5; // por minuto e IP

    public function login()
    {
        return view('auth/login', ['title' => 'Iniciar sesión']);
    }

    public function attempt()
    {
        $throttler = service('throttler');
        if (! $throttler->check('login_' . md5($this->request->getIPAddress()), self::MAX_INTENTOS, MINUTE)) {
            return redirect()->back()->withInput()->with('error', 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');
        }

        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');

        $usuarios = new UsuarioModel();
        $u        = $usuarios->findByEmail($email);

        // Siempre se verifica un hash para no revelar por tiempo si el correo existe.
        $hash = $u['password'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok   = password_verify($password, $hash) && $u !== null;

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Correo o contraseña incorrectos.');
        }
        if (! (int) $u['activo']) {
            return redirect()->back()->withInput()->with('error', 'Tu usuario está desactivado. Contacta al administrador.');
        }

        $this->startSession($u);
        $usuarios->update($u['id'], ['ultimo_acceso' => date('Y-m-d H:i:s')]);

        $destino = session()->get('redirect_to') ?: site_url('proyectos');
        session()->remove('redirect_to');

        return redirect()->to($destino);
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'Sesión cerrada correctamente.');
    }

    public function forgot()
    {
        return view('auth/forgot', ['title' => 'Recuperar contraseña']);
    }

    public function sendReset()
    {
        $email = (string) $this->request->getPost('email');
        $throttler = service('throttler');
        if (! $throttler->check('forgot_' . md5($this->request->getIPAddress()), 3, MINUTE)) {
            return redirect()->back()->with('error', 'Demasiadas solicitudes. Espera un minuto.');
        }

        if (! $this->validateData(['email' => $email], ['email' => 'required|valid_email'])) {
            return redirect()->back()->withInput()->with('error', 'Ingresa un correo válido.');
        }

        $u = (new UsuarioModel())->findByEmail($email);
        if ($u && (int) $u['activo']) {
            $token = (new PasswordResetModel())->issue((int) $u['id']);
            $link  = site_url('restablecer/' . $token);
            $html  = view('auth/mail_reset', ['nombre' => $u['nombres'], 'link' => $link, 'minutos' => PasswordResetModel::TTL_MINUTOS]);

            $enviado = (new Mailer())->send($u['email'], 'Recuperar contraseña - KGI Soluciones', $html);
            if (! $enviado && ENVIRONMENT !== 'production') {
                log_message('notice', 'Enlace de recuperación para {email}: {link}', ['email' => $u['email'], 'link' => $link]);
            }
        }

        // Respuesta idéntica exista o no el correo (evita enumeración de usuarios).
        return redirect()->to('/recuperar')->with('success', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');
    }

    public function reset(string $token)
    {
        if (! (new PasswordResetModel())->findValid($token)) {
            return redirect()->to('/recuperar')->with('error', 'El enlace no es válido o ha expirado. Solicita uno nuevo.');
        }

        return view('auth/reset', ['token' => $token, 'title' => 'Nueva contraseña']);
    }

    public function updatePassword(string $token)
    {
        $resets = new PasswordResetModel();
        $r      = $resets->findValid($token);
        if (! $r) {
            return redirect()->to('/recuperar')->with('error', 'El enlace no es válido o ha expirado. Solicita uno nuevo.');
        }

        $rules = [
            'password'      => 'required|min_length[8]|max_length[72]',
            'password_conf' => 'required|matches[password]',
        ];
        if (! $this->validate($rules, [
            'password'      => ['required' => 'Ingresa la nueva contraseña.', 'min_length' => 'La contraseña debe tener al menos 8 caracteres.'],
            'password_conf' => ['required' => 'Confirma la contraseña.', 'matches' => 'Las contraseñas no coinciden.'],
        ])) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        (new UsuarioModel())->update($r['usuario_id'], ['password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT)]);
        $resets->update($r['id'], ['usado_en' => date('Y-m-d H:i:s')]);

        return redirect()->to('/login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    private function startSession(array $u): void
    {
        $perfil = (new PerfilModel())->find($u['perfil_id']);
        session()->regenerate(true);
        session()->set('usuario', [
            'id'        => (int) $u['id'],
            'nombres'   => $u['nombres'],
            'email'     => $u['email'],
            'perfil_id' => (int) $u['perfil_id'],
            'perfil'    => $perfil['nombre'] ?? '',
        ]);
    }
}
