<?php
declare(strict_types=1);
require_once __DIR__ . '/Repositorio.php';

/**
 * Ventas: VENTA, DETALLE_VENTA, PAGO, CLIENTE y METODO_PAGO.
 * Reglas que cuida la base: R7 (caja abierta), R5 (el trigger descuenta stock),
 * R4 (no se borra, se anula y el stock vuelve), CK_VEN_3 (factura con cliente).
 */
class VentaRepository extends Repositorio
{
    private const SELECT_VENTA = '
        SELECT v.*, s.ID_CAJERO, s.ID_TURNO, c.NUMERO_CAJA, c.ID_TIENDA,
               t.NOMBRE AS TIENDA, t.DIRECCION, t.DISTRITO, t.TELEFONO AS TIENDA_TEL,
               tu.NOMBRE AS TURNO, e.NOMBRES AS CAJERO_NOMBRES, e.APELLIDOS AS CAJERO_APELLIDOS,
               cl.TIPO_DOCUMENTO, cl.NRO_DOCUMENTO, cl.NOMBRE_RAZON_SOCIAL
        FROM VENTA v
        JOIN SESION_CAJA s ON s.ID_SESION = v.ID_SESION
        JOIN CAJA c        ON c.ID_CAJA = s.ID_CAJA
        JOIN TIENDA t      ON t.ID_TIENDA = c.ID_TIENDA
        JOIN TURNO tu      ON tu.ID_TURNO = s.ID_TURNO
        JOIN EMPLEADO e    ON e.ID_EMPLEADO = s.ID_CAJERO
        LEFT JOIN CLIENTE cl ON cl.ID_CLIENTE = v.ID_CLIENTE';

    /** @return array<int, string> [ID_METODO_PAGO => NOMBRE] */
    public function metodos(): array
    {
        return $this->pdo->query('SELECT ID_METODO_PAGO, NOMBRE FROM METODO_PAGO ORDER BY ID_METODO_PAGO')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function clientePorDocumento(string $doc): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM CLIENTE WHERE NRO_DOCUMENTO = :d');
        $stmt->execute([':d' => $doc]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Guarda la venta completa en una sola transacción.
     * $v = [sesion => array, tipo => BOLETA|FACTURA, cliente => ?array(tipo, doc, nombre),
     *       lineas => [idProducto => cantidad], metodo => int, operacion => ?string, recibido => ?float]
     * Los precios se leen de la base (no del navegador) para que nadie los cambie.
     */
    public function registrar(array $v): int
    {
        return $this->enTransaccion(function () use ($v): int {
            $sesion = $v['sesion'];

            // R5: se revisa el stock antes de insertar (el trigger y CK_INV_1 lo vuelven a cuidar)
            $buscar = $this->pdo->prepare("
                SELECT p.NOMBRE, p.PRECIO_VENTA, i.STOCK_ACTUAL
                FROM PRODUCTO p JOIN INVENTARIO i ON i.ID_PRODUCTO = p.ID_PRODUCTO
                WHERE p.ID_PRODUCTO = :id AND p.ESTADO = 'A'");
            $lineas = [];
            $total = 0.0;
            foreach ($v['lineas'] as $idProducto => $cant) {
                $buscar->execute([':id' => $idProducto]);
                $p = $buscar->fetch(PDO::FETCH_ASSOC);
                if ($p === false) {
                    throw new DomainException('Un producto del carrito ya no está activo.');
                }
                if ($cant > (int) $p['STOCK_ACTUAL']) {
                    throw new DomainException("R5: solo hay {$p['STOCK_ACTUAL']} unidades de {$p['NOMBRE']}.");
                }
                $importe = round($cant * (float) $p['PRECIO_VENTA'], 2);
                $lineas[] = [$idProducto, $cant, (float) $p['PRECIO_VENTA'], $importe];
                $total += $importe;
            }
            $total = round($total, 2);
            $igv = round($total * 18 / 118, 2);         // el precio ya incluye IGV
            $subtotal = round($total - $igv, 2);        // CK_VEN_6: TOTAL = SUBTOTAL + IGV

            // Cliente: si el DNI/RUC no existe, se registra en CLIENTE
            $idCliente = null;
            if ($v['cliente'] !== null) {
                $existe = $this->clientePorDocumento($v['cliente']['doc']);
                if ($existe !== null) {
                    $idCliente = (int) $existe['ID_CLIENTE'];
                } else {
                    $idCliente = $this->siguienteId('CLIENTE', 'ID_CLIENTE');
                    $this->pdo->prepare('
                        INSERT INTO CLIENTE (ID_CLIENTE, TIPO_DOCUMENTO, NRO_DOCUMENTO, NOMBRE_RAZON_SOCIAL, FECHA_REGISTRO)
                        VALUES (:id, :tipo, :doc, :nombre, :fecha)')
                        ->execute([':id' => $idCliente, ':tipo' => $v['cliente']['tipo'], ':doc' => $v['cliente']['doc'],
                                   ':nombre' => $v['cliente']['nombre'], ':fecha' => self::ahora()]);
                }
            }

            // Serie por tienda: B001/F001 en la tienda 1, B002/F002 en la 2...
            $serie = ($v['tipo'] === 'FACTURA' ? 'F' : 'B') . str_pad((string) $sesion['ID_TIENDA'], 3, '0', STR_PAD_LEFT);
            $num = $this->pdo->prepare('SELECT COALESCE(MAX(NUMERO), 0) + 1 FROM VENTA WHERE SERIE = :s');
            $num->execute([':s' => $serie]);
            $numero = (int) $num->fetchColumn();

            $idVenta = $this->siguienteId('VENTA', 'ID_VENTA');
            $this->pdo->prepare("
                INSERT INTO VENTA (ID_VENTA, ID_SESION, ID_CLIENTE, TIPO_COMPROBANTE, SERIE, NUMERO, FECHA_HORA, SUBTOTAL, IGV, TOTAL, ESTADO)
                VALUES (:id, :sesion, :cliente, :tipo, :serie, :num, :fecha, :sub, :igv, :total, 'EMITIDA')")
                ->execute([':id' => $idVenta, ':sesion' => $sesion['ID_SESION'], ':cliente' => $idCliente,
                           ':tipo' => $v['tipo'], ':serie' => $serie, ':num' => $numero, ':fecha' => self::ahora(),
                           ':sub' => self::dinero($subtotal), ':igv' => self::dinero($igv), ':total' => self::dinero($total)]);

            // Cada línea dispara TRG_DETALLE_DESCUENTA_STOCK (R5)
            $det = $this->pdo->prepare('
                INSERT INTO DETALLE_VENTA (ID_VENTA, ID_PRODUCTO, CANTIDAD, PRECIO_UNITARIO, DESCUENTO, IMPORTE)
                VALUES (:venta, :prod, :cant, :precio, 0, :imp)');
            foreach ($lineas as [$idProducto, $cant, $precio, $importe]) {
                $det->execute([':venta' => $idVenta, ':prod' => $idProducto, ':cant' => $cant,
                               ':precio' => self::dinero($precio), ':imp' => self::dinero($importe)]);
            }

            $recibido = $v['recibido'] ?? null;
            $this->pdo->prepare('
                INSERT INTO PAGO (ID_PAGO, ID_VENTA, ID_METODO_PAGO, MONTO, NRO_OPERACION, MONTO_RECIBIDO, VUELTO)
                VALUES (:id, :venta, :metodo, :monto, :op, :rec, :vuelto)')
                ->execute([':id' => $this->siguienteId('PAGO', 'ID_PAGO'), ':venta' => $idVenta, ':metodo' => $v['metodo'],
                           ':monto' => self::dinero($total), ':op' => $v['operacion'] ?? null,
                           ':rec' => $recibido === null ? null : self::dinero($recibido),
                           ':vuelto' => $recibido === null ? null : self::dinero($recibido - $total)]);
            return $idVenta;
        });
    }

    /** Ventas que el usuario puede ver: todas (admin), de su tienda (supervisor) o de sus cajas (cajero) */
    public function listar(Usuario $u): array
    {
        [$donde, $params] = self::filtro($u);
        $stmt = $this->pdo->prepare(self::SELECT_VENTA . " WHERE $donde ORDER BY v.ID_VENTA DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Una venta con su detalle y pagos, solo si el usuario puede verla */
    public function buscar(int $idVenta, Usuario $u): ?array
    {
        [$donde, $params] = self::filtro($u);
        $stmt = $this->pdo->prepare(self::SELECT_VENTA . " WHERE v.ID_VENTA = :id AND $donde");
        $stmt->execute([':id' => $idVenta] + $params);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($venta === false) {
            return null;
        }
        $d = $this->pdo->prepare('
            SELECT d.*, p.NOMBRE, p.CODIGO_SKU FROM DETALLE_VENTA d
            JOIN PRODUCTO p ON p.ID_PRODUCTO = d.ID_PRODUCTO WHERE d.ID_VENTA = :id ORDER BY p.NOMBRE');
        $d->execute([':id' => $idVenta]);
        $venta['detalle'] = $d->fetchAll(PDO::FETCH_ASSOC);

        $p = $this->pdo->prepare('
            SELECT pa.*, m.NOMBRE AS METODO FROM PAGO pa
            JOIN METODO_PAGO m ON m.ID_METODO_PAGO = pa.ID_METODO_PAGO WHERE pa.ID_VENTA = :id ORDER BY pa.ID_PAGO');
        $p->execute([':id' => $idVenta]);
        $venta['pagos'] = $p->fetchAll(PDO::FETCH_ASSOC);
        return $venta;
    }

    // R4: no se borra, se anula. El trigger TRG_VENTA_ANULA_STOCK devuelve el stock.
    public function anular(int $idVenta, Usuario $u): array
    {
        $venta = $this->buscar($idVenta, $u);
        if ($venta === null || $venta['ESTADO'] !== 'EMITIDA') {
            throw new DomainException('Esa venta no existe o ya está anulada.');
        }
        $stmt = $this->pdo->prepare("UPDATE VENTA SET ESTADO = 'ANULADA' WHERE ID_VENTA = :id AND ESTADO = 'EMITIDA'");
        $stmt->execute([':id' => $idVenta]);
        return $venta;
    }

    /** @return array{0: string, 1: array} */
    private static function filtro(Usuario $u): array
    {
        if ($u->puedeVerTodasLasTiendas()) {
            return ['1 = 1', []];
        }
        if ($u->puedeCerrarCaja()) {
            return ['c.ID_TIENDA = :tienda', [':tienda' => $u->getIdTienda()]];
        }
        return ['s.ID_CAJERO = :emp', [':emp' => $u->getIdEmpleado()]];
    }
}
