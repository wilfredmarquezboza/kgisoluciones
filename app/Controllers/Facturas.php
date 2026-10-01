<?php

namespace App\Controllers;

use App\Libraries\Facturacion;
use App\Models\AbonoModel;
use App\Models\ActividadModel;
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
        $fx   = new Facturacion();
        $rows = $this->proyectosFacturables();
        $info = $fx->armar($rows);

        $proyectos = array_map(static fn ($p) => [
            'id'      => (int) $p['id'],
            'nombre'  => $p['nombre'] ?: '(Sin nombre) ' . $p['departamento'],
            'empresa' => $p['empresa'] ?: 'Sin empresa asignada',
            'moneda'  => $p['moneda'],
            'monto'   => (float) $p['monto'],
        ] + $info[$p['id']], $rows);

        $disponibles = db_connect()->table('proyectos')->select('id, departamento, nombre')
            ->groupStart()->where('monto', null)->orWhere('monto', 0)->groupEnd()->orderBy('departamento')->orderBy('nombre')->get()->getResultArray();

        return $this->response->setJSON([
            'ok'          => true,
            'igv'         => $fx->igv,
            'detraccion'  => $fx->detraccion,
            'hoy'         => date('Y-m-d'),
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
            (new AbonoModel())->whereIn('cuota_id', array_values($borrar))->delete();
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
        $cuotaIds = array_column((new CuotaModel())->where('proyecto_id', $id)->findAll(), 'id');
        if ($cuotaIds) {
            (new AbonoModel())->whereIn('cuota_id', $cuotaIds)->delete();
        }
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

        $post    = $this->request->getPost();
        $estado  = (string) ($post['estado'] ?? '');
        $factura = trim((string) ($post['factura'] ?? ''));
        $ref     = trim((string) ($post['detr_ref'] ?? ''));
        $hito    = (int) ($post['actividad_id'] ?? 0);

        if (! in_array($estado, CuotaModel::ESTADOS, true)) {
            return $this->fail('Estado no válido.', 422);
        }
        if (mb_strlen($factura) > 30 || mb_strlen($ref) > 40) {
            return $this->fail('El N° de factura admite hasta 30 caracteres y la constancia hasta 40.', 422);
        }
        if ($estado === 'facturado' && $factura === '') {
            return $this->fail('Escribe el N° de factura para marcar el pago como facturado.', 422, 'factura');
        }

        $f = [];
        foreach (['fecha' => 'fecha de pago', 'estimada' => 'fecha estimada', 'emision' => 'fecha de emisión', 'vencimiento' => 'fecha de vencimiento', 'detr_fecha' => 'fecha de la detracción'] as $k => $label) {
            $v = trim((string) ($post[$k] ?? ''));
            if ($v !== '' && ! $this->fechaValida($v)) {
                return $this->fail("La $label no es válida.", 422, $k);
            }
            $f[$k] = $v === '' ? null : $v;
        }
        if ($f['emision'] && $f['vencimiento'] && $f['vencimiento'] < $f['emision']) {
            return $this->fail('El vencimiento no puede ser anterior a la emisión.', 422, 'vencimiento');
        }
        if ($hito && (new ActividadModel())->where('id', $hito)->where('proyecto_id', $c['proyecto_id'])->countAllResults() === 0) {
            return $this->fail('El hito no pertenece a este proyecto.', 422, 'actividad_id');
        }

        $facturado = $estado !== 'pendiente';   // sin factura no hay emisión, vencimiento ni detracción
        $model->update($id, [
            'estado'            => $estado,
            'factura'           => $facturado && $factura !== '' ? $factura : null,
            'fecha_pago'        => $estado === 'pagado' ? $f['fecha'] : null,
            'actividad_id'      => $hito ?: null,
            'fecha_estimada'    => $f['estimada'],
            'fecha_emision'     => $facturado ? $f['emision'] : null,
            'fecha_vencimiento' => $facturado ? $f['vencimiento'] : null,
            'detr_fecha'        => $facturado ? $f['detr_fecha'] : null,
            'detr_ref'          => $facturado && $ref !== '' ? $ref : null,
        ]);

        return $this->response->setJSON(['ok' => true, 'message' => 'Pago actualizado.']);
    }

    /** Registra un cobro (puede ser parcial). Si cubre el neto, el pago pasa a pagado. */
    public function abono(int $cuotaId)
    {
        $cuota = (new CuotaModel())->find($cuotaId);
        if (! $cuota) {
            return $this->fail('El pago ya no existe.', 404);
        }
        if ($cuota['estado'] === 'pendiente') {
            return $this->fail('Primero marca el pago como facturado.', 422);
        }

        $fecha = trim((string) $this->request->getPost('fecha'));
        $monto = $this->request->getPost('monto');
        $ref   = trim((string) $this->request->getPost('ref'));
        if (! $this->fechaValida($fecha)) {
            return $this->fail('Indica la fecha del abono.', 422, 'fecha');
        }
        if (! is_numeric($monto) || (float) $monto <= 0) {
            return $this->fail('El monto del abono debe ser mayor que cero.', 422, 'monto');
        }
        if (mb_strlen($ref) > 60) {
            return $this->fail('La referencia admite hasta 60 caracteres.', 422, 'ref');
        }

        [$neto, $suma] = $this->netoYAbonado($cuota);
        $monto         = round((float) $monto, 2);
        if ($monto > $neto - $suma + Facturacion::EPS) {
            return $this->fail('El abono supera el saldo del pago (' . number_format(max(0, $neto - $suma), 2) . ').', 422, 'monto');
        }

        (new AbonoModel())->insert(['cuota_id' => $cuotaId, 'fecha' => $fecha, 'monto' => $monto, 'referencia' => $ref ?: null]);
        $this->sincronizar($cuotaId);

        return $this->response->setJSON(['ok' => true, 'message' => 'Abono registrado.']);
    }

    public function abonoEliminar(int $id)
    {
        $model = new AbonoModel();
        $ab    = $model->find($id);
        if (! $ab) {
            return $this->fail('El abono ya no existe.', 404);
        }
        $model->delete($id);
        $this->sincronizar((int) $ab['cuota_id']);

        return $this->response->setJSON(['ok' => true, 'message' => 'Abono eliminado.']);
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
        (new CuotaModel())->where('actividad_id', $id)->set(['actividad_id' => null])->update();
        $model->delete($id);

        return $this->response->setJSON(['ok' => true, 'message' => 'Actividad eliminada.']);
    }

    /** CSV (UTF-8 con BOM, para Excel) con el detalle de todos los pagos. */
    public function exportar()
    {
        $fx   = new Facturacion();
        $rows = $this->proyectosFacturables();
        $info = $fx->armar($rows);

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Empresa', 'Proyecto', 'Moneda', 'Monto sin IGV', 'Pago', '%', 'Subtotal', 'IGV', 'Total con IGV', 'Detracción', 'Neto a cobrar',
            'Cobrado', 'Saldo', 'Estado', 'Factura', 'Emisión', 'Vencimiento', 'Días de atraso', 'Fecha de pago', 'Detracción depositada', 'Constancia', 'Hito'], ';');

        foreach ($rows as $p) {
            foreach ($info[$p['id']]['cuotas'] as $c) {
                $f = static fn ($n) => number_format($n, 2, '.', '');
                fputcsv($out, array_map([$this, 'csvSafe'], [
                    $p['empresa'] ?: 'Sin empresa asignada', $p['nombre'] ?: $p['departamento'], $p['moneda'], $f((float) $p['monto']),
                    $c['label'], $c['pct'], $f($c['sub']), $f($c['igv']), $f($c['total']), $f($c['det']), $f($c['neto']),
                    $f($c['cobrado']), $f($c['saldo']), $c['estado'], $c['factura'], $c['emision'], $c['vencimiento'], $c['atraso'] ?: '',
                    $c['fecha'], $c['detr_fecha'], $c['detr_ref'], $c['hito'],
                ]), ';');
            }
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

    /** @return array{0:float,1:float} neto del pago y suma de abonos registrados */
    private function netoYAbonado(array $cuota): array
    {
        $p    = (new ProyectoModel())->find($cuota['proyecto_id']);
        $neto = (new Facturacion())->calc((float) $p['monto'], (float) $cuota['porcentaje'])['neto'];
        $row  = db_connect()->table('abonos')->selectSum('monto', 'suma')->where('cuota_id', $cuota['id'])->get()->getRowArray();

        return [$neto, (float) ($row['suma'] ?? 0)];
    }

    /** Mantiene el estado coherente con los abonos: cubierto → pagado; si deja de estarlo → facturado. */
    private function sincronizar(int $cuotaId): void
    {
        $model = new CuotaModel();
        $cuota = $model->find($cuotaId);
        [$neto, $suma] = $this->netoYAbonado($cuota);

        if ($suma >= $neto - Facturacion::EPS && $suma > 0) {
            $ultimo = db_connect()->table('abonos')->selectMax('fecha', 'f')->where('cuota_id', $cuotaId)->get()->getRowArray();
            $model->update($cuotaId, ['estado' => 'pagado', 'fecha_pago' => $ultimo['f']]);
        } elseif ($cuota['estado'] === 'pagado') {
            $model->update($cuotaId, ['estado' => 'facturado', 'fecha_pago' => null]);
        }
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
