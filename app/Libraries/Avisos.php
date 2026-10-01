<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\UsuarioModel;

/** Resumen de cobranza por correo: vencidos, por vencer y listos para facturar. */
class Avisos
{
    /** @return array{vencidos:list<array>,por_vencer:list<array>,listos:list<array>,dias:int} */
    public function resumen(): array
    {
        $fx    = new Facturacion();
        $rows  = $fx->proyectosFacturables();
        $info  = $fx->armar($rows);
        $dias  = max(1, min(30, (int) $this->config('AVISOS_DIAS_ANTES', '3')));
        $hoy   = date('Y-m-d');
        $limit = date('Y-m-d', strtotime("+$dias day"));
        $out   = ['vencidos' => [], 'por_vencer' => [], 'listos' => [], 'dias' => $dias];

        foreach ($rows as $p) {
            foreach ($info[$p['id']]['cuotas'] as $c) {
                $item = [
                    'empresa' => $p['empresa'] ?: 'Sin empresa asignada', 'proyecto' => $p['nombre'] ?: $p['departamento'], 'moneda' => $p['moneda'],
                    'pago' => $c['label'], 'factura' => $c['factura'], 'monto' => $c['saldo'], 'vencimiento' => $c['vencimiento'], 'atraso' => $c['atraso'], 'hito' => $c['hito'],
                ];
                if ($c['vencida']) {
                    $out['vencidos'][] = $item;
                } elseif ($c['estado'] === 'facturado' && $c['vencimiento'] && $c['vencimiento'] >= $hoy && $c['vencimiento'] <= $limit) {
                    $out['por_vencer'][] = $item;
                } elseif ($c['listo']) {
                    $out['listos'][] = $item;
                }
            }
        }
        usort($out['vencidos'], static fn ($a, $b) => $b['atraso'] <=> $a['atraso']);
        usort($out['por_vencer'], static fn ($a, $b) => strcmp($a['vencimiento'], $b['vencimiento']));

        return $out;
    }

    /** Correos configurados (AVISOS_EMAILS) o, si está vacío, de los usuarios activos. */
    public function destinatarios(): array
    {
        $lista = array_filter(array_map('trim', explode(',', $this->config('AVISOS_EMAILS', ''))));
        if (! $lista) {
            $lista = array_column((new UsuarioModel())->where('activo', 1)->findAll(), 'email');
        }

        return array_values(array_unique(array_filter($lista, static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    /**
     * @return array{0:bool,1:string}
     * $force ignora el límite de un envío por día; $dry no envía, solo informa.
     */
    public function enviar(bool $dry = false, bool $force = false): array
    {
        $r = $this->resumen();
        $n = count($r['vencidos']) + count($r['por_vencer']) + count($r['listos']);
        if ($n === 0) {
            return [true, 'No hay pagos vencidos, por vencer ni listos para facturar. No se envió correo.'];
        }
        $para = $this->destinatarios();
        if (! $para) {
            return [false, 'No hay destinatarios: define AVISOS_EMAILS en Configuración o activa algún usuario.'];
        }
        if (! $force && $this->config('AVISOS_ULTIMO', '') === date('Y-m-d')) {
            return [true, 'El resumen de hoy ya se envió. Usa --force para reenviarlo.'];
        }

        $asunto = sprintf('Cobranza KGI: %d vencido(s), %d por vencer, %d listo(s) para facturar', count($r['vencidos']), count($r['por_vencer']), count($r['listos']));
        if ($dry) {
            return [true, "[prueba] Asunto: $asunto | Para: " . implode(', ', $para)];
        }

        $html = view('mail/avisos', $r + ['link' => site_url('facturas')]);
        $mail = new Mailer();
        $fallos = 0;
        foreach ($para as $email) {
            $fallos += $mail->send($email, $asunto, $html) ? 0 : 1;
        }
        if ($fallos === count($para)) {
            return [false, 'No se pudo enviar el correo. Revisa SMTP_HOST, SMTP_PORT, SMTP_USER y SMTP_PASS en Configuración.'];
        }
        $this->guardar('AVISOS_ULTIMO', date('Y-m-d'));

        return [true, 'Resumen enviado a ' . (count($para) - $fallos) . ' destinatario(s).' . ($fallos ? " Fallaron $fallos." : '')];
    }

    private function config(string $k, string $def): string
    {
        $row = (new ConfiguracionModel())->where('clave', $k)->first();

        return $row && $row['valor'] !== null ? trim((string) $row['valor']) : $def;
    }

    private function guardar(string $k, string $v): void
    {
        $m   = new ConfiguracionModel();
        $row = $m->where('clave', $k)->first();
        $row ? $m->update($row['id'], ['valor' => $v]) : $m->insert(['clave' => $k, 'valor' => $v]);
    }
}
