<?php

namespace App\Controllers;

use App\Models\ActividadModel;
use App\Models\ConfiguracionModel;
use App\Models\CuotaModel;
use App\Models\ProyectoModel;

/** Control de facturas: pagos (cuotas) y avance por proyecto, agrupados por cliente. */
class Facturas extends BaseController
{
    private const MONEDAS = ['PEN', 'USD'];

    public function index()
    {
        return view('facturas/index', ['title' => 'Control de facturas']);
    }

    /** Todo lo que la pantalla necesita; se vuelve a pedir tras cada cambio. */
    public function datos()
    {
        $rows = $this->proyectosFacturables();
        $ids  = array_column($rows, 'id');

        $cuotas = $acts = [];
        if ($ids) {
            foreach ((new CuotaModel())->whereIn('proyecto_id', $ids)->orderBy('orden')->orderBy('id')->findAll() as $c) {
                $cuotas[$c['proyecto_id']][] = [
                    'id' => (int) $c['id'], 'label' => $c['etiqueta'], 'pct' => (float) $c['porcentaje'],
                    'estado' => $c['estado'], 'factura' => (string) $c['factura'], 'fecha' => (string) $c['fecha_pago'],
                ];
            }
            foreach ((new ActividadModel())->whereIn('proyecto_id', $ids)->orderBy('fecha')->orderBy('id')->findAll() as $a) {
                $acts[$a['proyecto_id']][] = [
                    'id' => (int) $a['id'], 'nombre' => $a['nombre'], 'fecha' => (string) $a['fecha'],
                    'estado' => $a['estado'], 'pct' => (int) $a['porcentaje'],
                ];
            }
        }

        $proyectos = array_map(static fn ($p) => [
            'id'      => (int) $p['id'],
            'nombre'  => $p['nombre'] ?: '(Sin nombre) ' . $p['departamento'],
            'empresa' => $p['empresa'] ?: 'Sin empresa asignada',
            'moneda'  => $p['moneda'],
            'monto'   => (float) $p['monto'],
            'cuotas'  => $cuotas[$p['id']] ?? [],
            'acts'    => $acts[$p['id']] ?? [],
        ], $rows);

        $disponibles = db_connect()->table('proyectos')->select('id, departamento, nombre')
            ->groupStart()->where('monto', null)->orWhere('monto', 0)->groupEnd()->orderBy('departamento')->orderBy('nombre')->get()->getResultArray();

        return $this->response->setJSON([
            'ok'          => true,
            'igv'         => $this->tasa('IGV', 18),
            'detraccion'  => $this->tasa('DETRACCION', 12),
            'proyectos'   => $proyectos,
            'disponibles' => array_map(static fn ($d) => ['id' => (int) $d['id'], 'label' => ($d['nombre'] ?: '(Sin nombre)') . ' · ' . $d['departamento']], $disponibles),
        ]);
    }

    /** Crea o actualiza el monto, la moneda y el plan de pagos de un proyecto. */
    public function plan()
    {
        $in = $this->request->getJSON(true);
        if (! is_array($in)) {
            return $this->fail('Solicitud inválida.', 400);
        }

        $proyectos = new ProyectoModel();
        $pid       = (int) ($in['proyecto_id'] ?? 0);
        if (! $proyectos->find($pid)) {
            return $this->fail('Selecciona un proyecto válido.', 422);
        }

        $moneda = (string) ($in['moneda'] ?? '');
        $monto  = $in['monto'] ?? null;
        if (! in_array($moneda, self::MONEDAS, true)) {
            return $this->fail('Elige la moneda.', 422);
        }
        if (! is_numeric($monto) || (float) $monto <= 0 || (float) $monto >= 1e9) {
            return $this->fail('El monto debe ser mayor que cero.', 422);
        }

        $lista = $in['cuotas'] ?? [];
        if (! is_array($lista) || count($lista) < 1 || count($lista) > 24) {
            return $this->fail('Agrega entre 1 y 24 pagos.', 422);
        }

        $cuotas   = new CuotaModel();
        $propias  = array_column($cuotas->where('proyecto_id', $pid)->findAll(), 'id');
        $limpias  = [];
        foreach (array_values($lista) as $i => $c) {
            $label = trim((string) ($c['label'] ?? ''));
            $pct   = $c['pct'] ?? null;
            $id    = (int) ($c['id'] ?? 0);
            if ($label === '' || mb_strlen($label) > 60) {
                return $this->fail('Cada pago necesita un nombre (máx. 60 caracteres).', 422);
            }
            if (! is_numeric($pct) || (float) $pct <= 0 || (float) $pct > 100) {
                return $this->fail('El porcentaje de cada pago debe estar entre 0 y 100.', 422);
            }
            if ($id && ! in_array((string) $id, array_map('strval', $propias), true)) {
                return $this->fail('Uno de los pagos no pertenece a este proyecto.', 422);
            }
            $limpias[] = ['id' => $id, 'etiqueta' => $label, 'porcentaje' => round((float) $pct, 4), 'orden' => $i];
        }

        $db = db_connect();
        $db->transStart();
        $proyectos->update($pid, ['moneda' => $moneda, 'monto' => round((float) $monto, 2)]);
        $conservar = array_filter(array_column($limpias, 'id'));
        $borrar    = array_diff($propias, $conservar);
        if ($borrar) {
            $cuotas->delete(array_values($borrar));
        }
        foreach ($limpias as $c) {
            $id = $c['id'];
            unset($c['id']);
            $id ? $cuotas->update($id, $c) : $cuotas->insert($c + ['proyecto_id' => $pid, 'estado' => 'pendiente']);
        }
        $db->transComplete();

        return $db->transStatus()
            ? $this->response->setJSON(['ok' => true, 'message' => 'Plan de pagos guardado.'])
            : $this->fail('No se pudo guardar el plan de pagos.', 500);
    }

    /** Saca el proyecto del control de facturas (borra pagos y actividades). */
    public function quitar(int $id)
    {
        $proyectos = new ProyectoModel();
        if (! $proyectos->find($id)) {
            return $this->fail('El proyecto ya no existe.', 404);
        }
        $db = db_connect();
        $db->transStart();
        (new CuotaModel())->where('proyecto_id', $id)->delete();
        (new ActividadModel())->where('proyecto_id', $id)->delete();
        $proyectos->update($id, ['moneda' => null, 'monto' => null]);
        $db->transComplete();

        return $this->response->setJSON(['ok' => true, 'message' => 'Proyecto quitado del control de facturas.']);
    }

    public function cuota(int $id)
    {
        $model = new CuotaModel();
        $c     = $model->find($id);
        if (! $c) {
            return $this->fail('El pago ya no existe.', 404);
        }

        $estado  = (string) $this->request->getPost('estado');
        $factura = trim((string) $this->request->getPost('factura'));
        $fecha   = trim((string) $this->request->getPost('fecha'));

        if (! in_array($estado, CuotaModel::ESTADOS, true)) {
            return $this->fail('Estado no válido.', 422);
        }
        if (mb_strlen($factura) > 30) {
            return $this->fail('El N° de factura admite hasta 30 caracteres.', 422);
        }
        if ($estado === 'facturado' && $factura === '') {
            return $this->fail('Escribe el N° de factura para marcar el pago como facturado.', 422, 'factura');
        }
        if ($estado === 'pagado' && $fecha !== '' && ! $this->fechaValida($fecha)) {
            return $this->fail('La fecha de pago no es válida.', 422, 'fecha');
        }

        $model->update($id, [
            'estado'     => $estado,
            'factura'    => $factura === '' ? null : $factura,
            'fecha_pago' => $estado === 'pagado' && $fecha !== '' ? $fecha : null,
        ]);

        return $this->response->setJSON(['ok' => true, 'message' => 'Pago actualizado.']);
    }

    public function actividad(int $proyectoId)
    {
        if (! (new ProyectoModel())->find($proyectoId)) {
            return $this->fail('El proyecto ya no existe.', 404);
        }
        $model = new ActividadModel();
        $id    = (int) $this->request->getPost('id');
        if ($id && ($model->find($id)['proyecto_id'] ?? null) != $proyectoId) {
            return $this->fail('La actividad ya no existe.', 404);
        }

        $nombre = trim((string) $this->request->getPost('nombre'));
        $fecha  = trim((string) $this->request->getPost('fecha'));
        $estado = (string) $this->request->getPost('estado');
        $pct    = $this->request->getPost('pct');

        if ($nombre === '' || mb_strlen($nombre) > 120) {
            return $this->fail('Escribe el nombre de la actividad (máx. 120 caracteres).', 422, 'nombre');
        }
        if (! in_array($estado, ActividadModel::ESTADOS, true)) {
            return $this->fail('Estado no válido.', 422, 'estado');
        }
        if (! ctype_digit((string) $pct) || (int) $pct < 1 || (int) $pct > 100) {
            return $this->fail('El porcentaje debe estar entre 1 y 100.', 422, 'pct');
        }
        if ($fecha !== '' && ! $this->fechaValida($fecha)) {
            return $this->fail('La fecha no es válida.', 422, 'fecha');
        }

        $data = ['nombre' => $nombre, 'fecha' => $fecha ?: null, 'estado' => $estado, 'porcentaje' => (int) $pct];
        $id ? $model->update($id, $data) : $model->insert($data + ['proyecto_id' => $proyectoId]);

        return $this->response->setJSON(['ok' => true, 'message' => $id ? 'Actividad actualizada.' : 'Actividad agregada.']);
    }

    public function actividadEliminar(int $id)
    {
        $model = new ActividadModel();
        if (! $model->find($id)) {
            return $this->fail('La actividad ya no existe.', 404);
        }
        $model->delete($id);

        return $this->response->setJSON(['ok' => true, 'message' => 'Actividad eliminada.']);
    }

    /** CSV (UTF-8 con BOM, para Excel) con el detalle de todos los pagos. */
    public function exportar()
    {
        $igv = $this->tasa('IGV', 18) / 100;
        $det = $this->tasa('DETRACCION', 12) / 100;

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Empresa', 'Proyecto', 'Moneda', 'Monto sin IGV', 'Pago', '%', 'Subtotal', 'IGV', 'Total con IGV', 'Detracción', 'Neto a cobrar', 'Estado', 'Factura', 'Fecha de pago'], ';');

        $pagos = db_connect()->table('cuotas')
            ->select('clientes.nombre AS empresa, proyectos.nombre AS proyecto, proyectos.departamento, proyectos.moneda, proyectos.monto, cuotas.*')
            ->join('proyectos', 'proyectos.id = cuotas.proyecto_id')->join('clientes', 'clientes.id = proyectos.cliente_id', 'left')
            ->orderBy('empresa')->orderBy('proyectos.id')->orderBy('cuotas.orden')->get()->getResultArray();

        foreach ($pagos as $r) {
            $sub   = (float) $r['monto'] * (float) $r['porcentaje'] / 100;
            $total = $sub * (1 + $igv);
            $d     = $total * $det;
            fputcsv($out, array_map([$this, 'csvSafe'], [
                $r['empresa'] ?: 'Sin empresa asignada', $r['proyecto'] ?: $r['departamento'], $r['moneda'], number_format((float) $r['monto'], 2, '.', ''),
                $r['etiqueta'], (float) $r['porcentaje'], number_format($sub, 2, '.', ''), number_format($sub * $igv, 2, '.', ''),
                number_format($total, 2, '.', ''), number_format($d, 2, '.', ''), number_format($total - $d, 2, '.', ''),
                $r['estado'], $r['factura'], $r['fecha_pago'],
            ]), ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->download('control-de-facturas-' . date('Ymd') . '.csv', $csv);
    }

    // ---------------------------------------------------------------

    private function proyectosFacturables(): array
    {
        return db_connect()->table('proyectos')
            ->select('proyectos.id, proyectos.nombre, proyectos.departamento, proyectos.moneda, proyectos.monto, clientes.nombre AS empresa')
            ->join('clientes', 'clientes.id = proyectos.cliente_id', 'left')
            ->where('proyectos.monto >', 0)
            ->orderBy('clientes.nombre IS NULL', 'DESC', false)->orderBy('clientes.nombre')->orderBy('proyectos.id')
            ->get()->getResultArray();
    }

    private function tasa(string $clave, float $defecto): float
    {
        $row = (new ConfiguracionModel())->where('clave', $clave)->first();
        $v   = $row ? str_replace(',', '.', trim((string) $row['valor'])) : '';

        return is_numeric($v) && $v >= 0 && $v <= 100 ? (float) $v : $defecto;
    }

    private function fechaValida(string $f): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);

        return $d && $d->format('Y-m-d') === $f;
    }

    /** Evita inyección de fórmulas al abrir el CSV en Excel. */
    private function csvSafe($v)
    {
        return is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    }

    private function fail(string $msg, int $status, ?string $field = null)
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $msg] + ($field ? ['errors' => [$field => $msg]] : []));
    }
}
