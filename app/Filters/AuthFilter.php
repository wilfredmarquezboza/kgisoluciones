<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Exige sesión iniciada; para AJAX responde 401 en JSON. */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($sesion = session()->get('usuario')) {
            // Se revalida contra la base: un usuario desactivado o con otro perfil se refleja de inmediato.
            $u = db_connect()->table('usuarios')->select('id, activo, perfil_id, nombres')->where('id', $sesion['id'])->get()->getRowArray();
            if ($u && (int) $u['activo']) {
                if ((int) $u['perfil_id'] !== (int) $sesion['perfil_id'] || $u['nombres'] !== $sesion['nombres']) {
                    $sesion['perfil_id'] = (int) $u['perfil_id'];
                    $sesion['nombres']   = $u['nombres'];
                    session()->set('usuario', $sesion);
                }

                return null;
            }
            session()->destroy();
        }

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON(['ok' => false, 'message' => 'Sesión expirada.']);
        }

        session()->set('redirect_to', current_url());

        return redirect()->to('/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
