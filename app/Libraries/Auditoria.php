<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/** Historial de cambios. Se desactiva con AUDITORIA = false en Configuración. */
class Auditoria
{
    private const SENSIBLE = '/pass|secret|token|clave_api/i';

    public static function registrar(string $accion, string $entidad, ?int $id, string $resumen, ?array $antes = null, ?array $despues = null, ?string $usuarioNombre = null): void
    {
        try {
            $row = (new ConfiguracionModel())->where('clave', 'AUDITORIA')->first();
            if ($row && strtolower(trim((string) $row['valor'])) === 'false') {
                return;
            }

            $u  = session()->get('usuario') ?? [];
            $ip = is_cli() ? null : service('request')->getIPAddress();

            db_connect()->table('auditoria')->insert([
                'usuario_id'     => $u['id'] ?? null,
                'usuario_nombre' => $usuarioNombre ?? ($u['nombres'] ?? null),
                'accion'         => $accion,
                'entidad'        => $entidad,
                'registro_id'    => $id,
                'resumen'        => mb_substr($resumen, 0, 255),
                'cambios'        => ($c = self::diff($entidad, $antes, $despues)) ? json_encode($c, JSON_UNESCAPED_UNICODE) : null,
                'ip'             => $ip,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // El historial nunca debe impedir la operación principal.
            log_message('error', 'Auditoría: ' . $e->getMessage());
        }
    }

    /** @return array<string,array{0:mixed,1:mixed}> campo => [antes, después], solo lo que cambió */
    public static function diff(string $entidad, ?array $antes, ?array $despues): array
    {
        $out    = [];
        $campos = array_unique(array_merge(array_keys($antes ?? []), array_keys($despues ?? [])));
        $mask   = $entidad === 'configuraciones' && preg_match(self::SENSIBLE, (string) (($despues ?? $antes)['clave'] ?? ''));

        foreach ($campos as $k) {
            if (in_array($k, ['id', 'created_at', 'updated_at', 'ultimo_acceso'], true)) {
                continue;
            }
            $a = $antes[$k] ?? null;
            $d = $despues[$k] ?? null;
            if ((string) $a === (string) $d) {
                continue;
            }
            $oculto  = preg_match(self::SENSIBLE, $k) || ($mask && $k === 'valor');
            $out[$k] = $oculto ? [$a === null ? null : '••••', $d === null ? null : '••••'] : [$a, $d];
        }

        return $out;
    }
}
