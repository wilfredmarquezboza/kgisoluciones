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
        if (session()->get('usuario')) {
            return null;
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
