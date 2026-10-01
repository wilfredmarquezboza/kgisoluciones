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
        if ($pendiente = $this->migracionesPendientes()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(503)->setJSON(['ok' => false, 'message' => 'Falta actualizar la base de datos (php spark migrate).']);
            }

            return service('response')->setStatusCode(503)->setBody(view('errors/actualizar', ['detalle' => $pendiente === true ? '' : $pendiente]));
        }

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

    /** @return string|bool false si todo está al día; texto/true si hay migraciones pendientes. Se cachea 5 min cuando está al día. */
    private function migracionesPendientes()
    {
        try {
            if (cache('kgi_migraciones_ok')) {
                return false;
            }
            $runner = service('migrations');
            $hechas = array_column($runner->getHistory(), 'version');
            $faltan = array_filter($runner->findMigrations(), static fn ($m) => ! in_array($m->version, $hechas, true));
            if ($faltan) {
                return count($faltan) . ' migración(es) pendiente(s)';
            }
            cache()->save('kgi_migraciones_ok', 1, 300);

            return false;
        } catch (\Throwable $e) {
            log_message('error', 'Verificación de migraciones: ' . $e->getMessage());

            return true;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
