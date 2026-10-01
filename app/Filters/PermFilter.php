<?php

namespace App\Filters;

use App\Libraries\Permisos;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Uso en rutas: 'filter' => 'perm:clientes.editar'. Responde 403 si el perfil no tiene el permiso. */
class PermFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        foreach ((array) $arguments as $permiso) {
            if (Permisos::puede($permiso)) {
                return null;
            }
        }

        $msg = 'No tienes permiso para realizar esta acción.';
        if ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'message' => $msg]);
        }

        helper(['ui', 'security', 'form', 'url']);

        return service('response')->setStatusCode(403)->setBody(view('errors/sin_permiso', ['title' => 'Sin permiso', 'mensaje' => $msg]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
