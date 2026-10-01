<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/** Cálculos de cobranza compartidos por Control de facturas, CSV y Kgi → Proyectos. */
class Facturacion
{
    public const EPS = 0.005;

    public float $igv;
    public float $detraccion;

    public function __construct()
    {
        $this->igv        = $this->tasa('IGV', 18);
        $this->detraccion = $this->tasa('DETRACCION', 12);
    }

    public function calc(float $monto, float $pct): array
    {
        $sub   = $monto * $pct / 100;
        $igv   = $sub * $this->igv / 100;
        $total = $sub + $igv;
        $det   = $total * $this->detraccion / 100;

        return ['sub' => $sub, 'igv' => $igv, 'total' => $total, 'det' => $det, 'neto' => $total - $det];
    }

    /**
     * @param list<array{id:int|string,monto:float|string}> $proyectos
     * @return array<int,array<string,mixed>> por id de proyecto: cuotas, acts, avance, neto, cobrado, estado
     */
    public function armar(array $proyectos): array
    {
        $ids = array_map('intval', array_column($proyectos, 'id'));
        if (! $ids) {
            return [];
        }
        $db   = db_connect();
        $hoy  = date('Y-m-d');
        $acts = $actsPor = $abonos = $cuotasPor = [];

        foreach ($db->table('actividades')->whereIn('proyecto_id', $ids)->orderBy('fecha')->orderBy('id')->get()->getResultArray() as $a) {
            $acts[$a['proyecto_id']][] = [
                'id' => (int) $a['id'], 'nombre' => $a['nombre'], 'fecha' => (string) $a['fecha'], 'estado' => $a['estado'], 'pct' => (int) $a['porcentaje'],
            ];
            $actsPor[$a['id']] = $a;
        }
        $cuotasDb = $db->table('cuotas')->whereIn('proyecto_id', $ids)->orderBy('orden')->orderBy('id')->get()->getResultArray();
        if ($cuotasDb) {
            foreach ($db->table('abonos')->whereIn('cuota_id', array_column($cuotasDb, 'id'))->orderBy('fecha')->orderBy('id')->get()->getResultArray() as $ab) {
                $abonos[$ab['cuota_id']][] = ['id' => (int) $ab['id'], 'fecha' => $ab['fecha'], 'monto' => (float) $ab['monto'], 'ref' => (string) $ab['referencia']];
            }
        }
        $montos = array_column($proyectos, 'monto', 'id');

        foreach ($cuotasDb as $c) {
            $pid   = (int) $c['proyecto_id'];
            $calc  = $this->calc((float) ($montos[$pid] ?? 0), (float) $c['porcentaje']);
            $lista = $abonos[$c['id']] ?? [];
            $suma  = array_sum(array_column($lista, 'monto'));
            $neto  = $calc['neto'];
            $pend  = $c['estado'] === 'pendiente';
            $cobr  = $c['estado'] === 'pagado' ? $neto : min($suma, $neto);
            $hito  = $c['actividad_id'] ? ($actsPor[$c['actividad_id']] ?? null) : null;
            $venc  = $c['estado'] === 'facturado' && $c['fecha_vencimiento'] && $c['fecha_vencimiento'] < $hoy;

            $cuotasPor[$pid][] = [
                'id' => (int) $c['id'], 'label' => $c['etiqueta'], 'pct' => (float) $c['porcentaje'], 'estado' => $c['estado'],
                'factura' => (string) $c['factura'], 'fecha' => (string) $c['fecha_pago'],
                'estimada' => (string) $c['fecha_estimada'], 'emision' => (string) $c['fecha_emision'], 'vencimiento' => (string) $c['fecha_vencimiento'],
                'hito_id' => $c['actividad_id'] ? (int) $c['actividad_id'] : null, 'hito' => $hito['nombre'] ?? null, 'hito_estado' => $hito['estado'] ?? null,
                'detr_fecha' => (string) $c['detr_fecha'], 'detr_ref' => (string) $c['detr_ref'],
                'neto' => $neto, 'cobrado' => $cobr, 'saldo' => $pend ? $neto : max(0, $neto - $cobr), 'abonos' => $lista,
                'listo' => $pend && $hito && $hito['estado'] === 'Concluido',
                'vencida' => $venc,
                'atraso' => $venc ? (int) ((strtotime($hoy) - strtotime($c['fecha_vencimiento'])) / 86400) : 0,
                'parcial' => $c['estado'] === 'facturado' && $cobr > self::EPS,
                'detr_pend' => ! $pend && ! $c['detr_fecha'],
            ] + $calc;
        }

        $out = [];
        foreach ($ids as $pid) {
            $cuotas  = $cuotasPor[$pid] ?? [];
            $lista   = $acts[$pid] ?? [];
            $avance  = min(100, array_sum(array_map(static fn ($a) => $a['estado'] === 'Concluido' ? $a['pct'] : 0, $lista)));
            $neto    = array_sum(array_column($cuotas, 'neto'));
            $cobrado = array_sum(array_column($cuotas, 'cobrado'));
            $todas   = $cuotas && ! array_filter($cuotas, static fn ($c) => $c['estado'] !== 'pagado');

            $estado = 'En ejecución';
            if ($todas && (! $lista || $avance >= 100)) {
                $estado = 'Cerrado';
            } elseif (array_filter($cuotas, static fn ($c) => $c['vencida'])) {
                $estado = 'Vencido';
            } elseif (array_filter($cuotas, static fn ($c) => $c['listo'])) {
                $estado = 'Por facturar';
            } elseif (array_filter($cuotas, static fn ($c) => $c['estado'] === 'facturado')) {
                $estado = 'Por cobrar';
            }

            $out[$pid] = ['cuotas' => $cuotas, 'acts' => $lista, 'avance' => $avance, 'neto' => $neto, 'cobrado' => $cobrado, 'estado' => $estado];
        }

        return $out;
    }

    private function tasa(string $clave, float $defecto): float
    {
        $row = (new ConfiguracionModel())->where('clave', $clave)->first();
        $v   = $row ? str_replace(',', '.', trim((string) $row['valor'])) : '';

        return is_numeric($v) && $v >= 0 && $v <= 100 ? (float) $v : $defecto;
    }
}
