<?php

namespace App\Controllers;

use App\Libraries\Facturacion;
use App\Models\AbonoModel;
use App\Libraries\Auditoria;
use App\Libraries\Avisos;
use App\Libraries\Permisos;
use App\Models\ActividadModel;
use App\Models\AdjuntoModel;
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
        $rows = $fx->proyectosFacturables();
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

        // Lo ya cobrado queda protegido: solo un perfil con permiso de cobranza puede alterarlo.
        $proyecto = $proyectos->find($pid);
        if (! Permisos::puede('facturas.cobrar')) {
            $cobradas = [];
            foreach ($cuotas->where('proyecto_id', $pid)->findAll() as $c) {
                if ($c['estado'] === 'pagado' || db_connect()->table('abonos')->where('cuota_id', $c['id'])->countAllResults() > 0) {
                    $cobradas[$c['id']] = $c;
                }
            }
            $enviadas = array_column($limpias, null, 'id');
            $cambia   = $cobradas && ((float) $proyecto['monto'] !== round((float) $monto, 2) || $proyecto['moneda'] !== $moneda);
            foreach ($cobradas as $cid => $c) {
                if (! isset($enviadas[$cid]) || abs((float) $c['porcentaje'] - $enviadas[$cid]['porcentaje']) > 0.00005) {
                    $cambia = true;
                }
            }
            if ($cambia) {
                return $this->fail('Hay pagos ya cobrados: solo un perfil con permiso para registrar cobros puede cambiar el monto, la moneda o sus porcentajes.', 403);
            }
        }

        $antes = ['monto' => $proyecto['monto'], 'moneda' => $proyecto['moneda'], 'pagos' => $this->resumenPlan($pid)];
        $db = db_connect();
        $db->transStart();
        $proyectos->update($pid, ['moneda' => $moneda, 'monto' => round((float) $monto, 2)]);
        $conservar = array_filter(array_column($limpias, 'id'));
        $borrar    = array_diff($propias, $conservar);
        if ($borrar) {
            (new AdjuntoModel())->purgarDeCuotas(array_values($borrar));
            (new AbonoModel())->whereIn('cuota_id', array_values($borrar))->delete();
            $cuotas->delete(array_values($borrar));
        }
        foreach ($limpias as $c) {
            $id = $c['id'];
            unset($c['id']);
            $id ? $cuotas->update($id, $c) : $cuotas->insert($c + ['proyecto_id' => $pid, 'estado' => 'pendiente']);
        }
        $db->transComplete();

        if ($db->transStatus()) {
            Auditoria::registrar('editar', 'proyecto', $pid, 'Plan de pagos: ' . ($proyecto['nombre'] ?: $proyecto['departamento']), $antes,
                ['monto' => round((float) $monto, 2), 'moneda' => $moneda, 'pagos' => $this->resumenPlan($pid)]);
        }

        return $db->transStatus()
            ? $this->response->setJSON(['ok' => true, 'message' => 'Plan de pagos guardado.'])
            : $this->fail('No se pudo guardar el plan de pagos.', 500);
    }

    /** Saca el proyecto del control de facturas (borra pagos y actividades). */
    public function quitar(int $id)
    {
        $proyectos = new ProyectoModel();
        if (! ($p = $proyectos->find($id))) {
            return $this->fail('El proyecto ya no existe.', 404);
        }
        $db = db_connect();
        $db->transStart();
        $cuotaIds = array_column((new CuotaModel())->where('proyecto_id', $id)->findAll(), 'id');
        if ($cuotaIds) {
            (new AdjuntoModel())->purgarDeCuotas($cuotaIds);
            (new AbonoModel())->whereIn('cuota_id', $cuotaIds)->delete();
        }
        (new CuotaModel())->where('proyecto_id', $id)->delete();
        (new ActividadModel())->where('proyecto_id', $id)->delete();
        $proyectos->update($id, ['moneda' => null, 'monto' => null]);
        $db->transComplete();
        Auditoria::registrar('eliminar', 'proyecto', $id, 'Quitado de facturación: ' . ($p['nombre'] ?: $p['departamento']), ['monto' => $p['monto'], 'moneda' => $p['moneda']], ['monto' => null, 'moneda' => null]);

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

        $cambiaCobro = $estado !== $c['estado'] && ($estado === 'pagado' || $c['estado'] === 'pagado');
        if ($cambiaCobro && ! Permisos::puede('facturas.cobrar')) {
            return $this->fail('Solo un perfil con permiso para registrar cobros puede marcar o desmarcar un pago como pagado.', 403);
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
        Auditoria::registrar($cambiaCobro ? 'cobro' : 'editar', 'cuota', $id, $this->nombreCuota($c) . ($estado !== $c['estado'] ? ": {$c['estado']} → $estado" : ''), $c, $model->find($id));

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
        Auditoria::registrar('cobro', 'cuota', $cuotaId, 'Abono registrado · ' . $this->nombreCuota($cuota), null, ['abono' => number_format($monto, 2) . " el $fecha" . ($ref ? " ($ref)" : ''), 'estado' => $this->estadoDe($cuotaId)]);

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
        $cuota = (new CuotaModel())->find($ab['cuota_id']);
        Auditoria::registrar('cobro', 'cuota', (int) $ab['cuota_id'], 'Abono eliminado · ' . $this->nombreCuota($cuota), ['abono' => number_format((float) $ab['monto'], 2) . ' el ' . $ab['fecha']], ['estado' => $cuota['estado']]);

        return $this->response->setJSON(['ok' => true, 'message' => 'Abono eliminado.']);
    }

    /** Últimos movimientos de un pago (quién y cuándo). */
    public function historial(int $cuotaId)
    {
        $rows = db_connect()->table('auditoria')->select('created_at, usuario_nombre, accion, resumen, cambios')
            ->where('entidad', 'cuota')->where('registro_id', $cuotaId)->orderBy('id', 'DESC')->limit(15)->get()->getResultArray();

        return $this->response->setJSON(['ok' => true, 'items' => $rows]);
    }

    // ---------------- Adjuntos ----------------

    public function adjuntar(int $cuotaId)
    {
        if (! (new CuotaModel())->find($cuotaId)) {
            return $this->fail('El pago ya no existe.', 404);
        }
        $tipo = (string) $this->request->getPost('tipo');
        if (! isset(AdjuntoModel::TIPOS[$tipo])) {
            return $this->fail('Elige el tipo de documento.', 422);
        }

        $file = $this->request->getFile('archivo');
        if (! $file || ! $file->isValid()) {
            return $this->fail('Selecciona un archivo (máx. 5 MB).', 422);
        }
        $ok = $this->validate(['archivo' => 'uploaded[archivo]|max_size[archivo,5120]|ext_in[archivo,pdf,xml,jpg,jpeg,png]|mime_in[archivo,application/pdf,text/xml,application/xml,image/jpeg,image/png]']);
        if (! $ok) {
            return $this->fail('Solo se admiten PDF, XML, JPG o PNG de hasta 5 MB.', 422);
        }

        $dir = AdjuntoModel::dir();
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $guardado = bin2hex(random_bytes(16)) . '.' . strtolower($file->getExtension());
        $nombre   = mb_substr(preg_replace('/[^\p{L}\p{N}._ ()-]+/u', '_', $file->getClientName()), 0, 150);
        $tam      = $file->getSize();
        $mime     = $file->getMimeType();
        $file->move($dir, $guardado);

        (new AdjuntoModel())->insert([
            'cuota_id' => $cuotaId, 'tipo' => $tipo, 'nombre' => $nombre, 'archivo' => $guardado,
            'mime' => $mime, 'tamano' => $tam, 'usuario_id' => usuario_actual()['id'] ?? null,
        ]);

        Auditoria::registrar('editar', 'cuota', $cuotaId, 'Documento adjuntado · ' . $this->nombreCuota((new CuotaModel())->find($cuotaId)), null, ['adjunto' => $nombre . ' (' . AdjuntoModel::TIPOS[$tipo] . ')']);

        return $this->response->setJSON(['ok' => true, 'message' => 'Documento adjuntado.']);
    }

    /** Descarga protegida: los archivos viven fuera de public/ y siempre se envían como descarga. */
    public function descargar(int $id)
    {
        $a = (new AdjuntoModel())->find($id);
        $f = $a ? AdjuntoModel::dir() . $a['archivo'] : null;
        if (! $a || ! is_file($f)) {
            return $this->response->setStatusCode(404)->setBody('Archivo no encontrado.');
        }

        return $this->response->download($f, null)->setFileName($a['nombre'])->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function adjuntoEliminar(int $id)
    {
        $model = new AdjuntoModel();
        $a     = $model->find($id);
        if (! $a) {
            return $this->fail('El documento ya no existe.', 404);
        }
        @unlink(AdjuntoModel::dir() . $a['archivo']);
        $model->delete($id);
        Auditoria::registrar('editar', 'cuota', (int) $a['cuota_id'], 'Documento eliminado · ' . $this->nombreCuota((new CuotaModel())->find($a['cuota_id'])), ['adjunto' => $a['nombre']], null);

        return $this->response->setJSON(['ok' => true, 'message' => 'Documento eliminado.']);
    }

    // ---------------- Avisos por correo ----------------

    public function avisos()
    {
        [$ok, $msg] = (new Avisos())->enviar(false, true);
        Auditoria::registrar('alerta', 'facturas', null, 'Resumen de cobranza por correo: ' . $msg);

        return $this->response->setStatusCode($ok ? 200 : 422)->setJSON(['ok' => $ok, 'message' => $msg]);
    }

    // ---------------- Flujo de caja ----------------

    public function flujo()
    {
        return view('facturas/flujo', ['title' => 'Flujo de caja']);
    }

    public function flujoDatos()
    {
        $fx = new Facturacion();

        return $this->response->setJSON([
            'ok' => true, 'hoy' => date('Y-m-d'),
            'flujo' => $fx->flujo((int) ($this->request->getGet('atras') ?? 3), (int) ($this->request->getGet('meses') ?? 12)),
        ]);
    }

    public function flujoExportar()
    {
        $fx  = new Facturacion();
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Moneda', 'Tipo', 'Periodo', 'Fecha', 'Empresa', 'Proyecto', 'Pago', 'Monto', 'Detalle'], ';');
        $f = static fn ($n) => number_format($n, 2, '.', '');
        foreach ($fx->flujo((int) ($this->request->getGet('atras') ?? 3), (int) ($this->request->getGet('meses') ?? 12)) as $mon => $d) {
            $emit = function ($tipo, $per, $items) use ($out, $mon, $f) {
                foreach ($items as $it) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [$mon, $tipo, $per, $it['fecha'], $it['empresa'], $it['proyecto'], $it['pago'], $f($it['monto']), $it['detalle']]), ';');
                }
            };
            foreach ($d['meses'] as $m) {
                $emit('Cobrado', $m['mes'], $m['cobros']);
                $emit('Esperado', $m['mes'], $m['prev']);
            }
            $emit('Esperado', 'Atrasado', $d['atrasado']['items']);
            $emit('Esperado', 'Sin fecha', $d['sin_fecha']['items']);
            $emit('Esperado', 'Posterior', $d['posterior']['items']);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->download('flujo-de-caja-' . date('Ymd') . '.csv', $csv);
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
        $antes = $id ? $model->find($id) : null;
        $id ? $model->update($id, $data) : $model->insert($data + ['proyecto_id' => $proyectoId]);
        $aid = $id ?: (int) $model->getInsertID();
        Auditoria::registrar($id ? 'editar' : 'crear', 'actividad', $aid, 'Actividad: ' . $nombre, $antes, $model->find($aid));

        return $this->response->setJSON(['ok' => true, 'message' => $id ? 'Actividad actualizada.' : 'Actividad agregada.']);
    }

    public function actividadEliminar(int $id)
    {
        $model = new ActividadModel();
        if (! $model->find($id)) {
            return $this->fail('La actividad ya no existe.', 404);
        }
        (new CuotaModel())->where('actividad_id', $id)->set(['actividad_id' => null])->update();
        $act = $model->find($id);
        $model->delete($id);
        Auditoria::registrar('eliminar', 'actividad', $id, 'Actividad: ' . $act['nombre'], $act, null);

        return $this->response->setJSON(['ok' => true, 'message' => 'Actividad eliminada.']);
    }

    /** CSV (UTF-8 con BOM, para Excel) con el detalle de todos los pagos. */
    public function exportar()
    {
        $fx   = new Facturacion();
        $rows = $fx->proyectosFacturables();
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

    private function nombreCuota(array $c): string
    {
        $p = (new ProyectoModel())->find($c['proyecto_id']);

        return ($p['nombre'] ?: $p['departamento']) . ' · ' . $c['etiqueta'];
    }

    private function estadoDe(int $cuotaId): string
    {
        return (string) (new CuotaModel())->find($cuotaId)['estado'];
    }

    private function resumenPlan(int $pid): string
    {
        return implode(' | ', array_map(static fn ($c) => $c['etiqueta'] . ' ' . (float) $c['porcentaje'] . '%', (new CuotaModel())->where('proyecto_id', $pid)->orderBy('orden')->findAll()));
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
