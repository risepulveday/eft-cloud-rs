<?php
// =========================================================================
// CONFIGURACIÓN DE RECURSOS AWS - RICARDO SEPÚLVEDA (RS)
// =========================================================================
$db_host = getenv('DB_HOST') ?: 'PEGA_AQUI_TU_ENDPOINT_RDS';
$db_user = getenv('DB_USER') ?: 'admin';
$db_pass = getenv('DB_PASS') ?: 'TU_PASSWORD_RDS';
$db_name = getenv('DB_NAME') ?: 'bd_proyecto_rs';

// URL exacta de tu imagen subida en web-estatico-rs
$s3_img_url = 'https://web-estatico-rs.s3.us-east-1.amazonaws.com/banner.png';

// =========================================================================
// OBTENER METADATOS DE LA INSTANCIA EC2 (IMDSv2) PARA DEMOSTRAR BALANCEO ALB
// =========================================================================
function get_ec2_metadata($path) {
    $ch = curl_init('http://169.254.169.254/latest/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-aws-ec2-metadata-token-ttl-seconds: 60']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $token = curl_exec($ch);
    curl_close($ch);

    if (!$token) return 'Local / No disponible';

    $ch = curl_init('http://169.254.169.254/latest/meta-data/' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-aws-ec2-metadata-token: $token"]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data ?: 'Desconocido';
}

$instance_id = get_ec2_metadata('instance-id');
$private_ip  = get_ec2_metadata('local-ipv4');
$az          = get_ec2_metadata('placement/availability-zone');

// =========================================================================
// CONEXIÓN A AMAZON RDS (MULTI-AZ) Y OPERACIONES CRUD
// =========================================================================
$db_status = '';
$registros = [];
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    $db_status = "<span class='badge err'>Error de conexión RDS: " . htmlspecialchars($conn->connect_error) . "</span>";
} else {
    $db_status = "<span class='badge ok'>Conectado exitosamente a RDS Multi-AZ (rds-bd-rs)</span>";
    
    // Crear tabla automáticamente si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS visitas_rs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        comentario VARCHAR(255) NOT NULL,
        az_origen VARCHAR(50),
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insertar nuevo registro desde el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nombre']) && !empty($_POST['comentario'])) {
        $stmt = $conn->prepare("INSERT INTO visitas_rs (nombre, comentario, az_origen) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $_POST['nombre'], $_POST['comentario'], $az);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Consultar últimos 10 registros
    $res = $conn->query("SELECT * FROM visitas_rs ORDER BY id DESC LIMIT 10");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $registros[] = $row;
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arquitectura Cloud 3 Capas - Ricardo Sepúlveda (RS)</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; color: #232f3e; margin: 0; padding: 20px; }
        .container { max-width: 850px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        header { border-bottom: 3px solid #ff9900; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 1.5rem; }
        .s3-box { text-align: center; background: #fafafa; padding: 15px; border: 1px dashed #ccc; border-radius: 6px; margin-bottom: 20px; }
        .s3-box img { max-height: 110px; border-radius: 4px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .card { background: #232f3e; color: #fff; padding: 15px; border-radius: 6px; }
        .card small { color: #ff9900; text-transform: uppercase; font-weight: bold; }
        .card p { margin: 8px 0 0; font-size: 1.1rem; font-family: monospace; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.9rem; font-weight: bold; display: inline-block; }
        .ok { background: #d4edda; color: #155724; }
        .err { background: #f8d7da; color: #721c24; }
        form { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }
        input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex: 1; }
        button { background: #ff9900; color: #232f3e; border: none; padding: 10px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        button:hover { background: #e68a00; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; font-size: 0.92rem; }
        th { background: #232f3e; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>Arquitectura Cloud de 3 Capas en AWS</h1>
            <small>Estudiante: <strong>Ricardo Sepúlveda (RS)</strong> | Asignatura: CCY0010</small>
        </div>
        <div><?= $db_status ?></div>
    </header>

    <!-- CAPA DE ALMACENAMIENTO ESTÁTICO (S3) -->
    <div class="s3-box">
        <p style="margin-top:0; font-size:0.85rem; color:#555;">Recurso estático cargado desde Amazon S3 (<code>web-estatico-rs</code>):</p>
        <img src="<?= htmlspecialchars($s3_img_url) ?>" alt="Logo AWS S3 RS">
    </div>

    <!-- CAPA DE CÓMPUTO Y BALANCEO (ALB + ASG + EC2) -->
    <div class="grid">
        <div class="card">
            <small>Instancia EC2 Activa</small>
            <p><?= htmlspecialchars($instance_id) ?></p>
        </div>
        <div class="card">
            <small>IP Privada (Subred Pública)</small>
            <p><?= htmlspecialchars($private_ip) ?></p>
        </div>
        <div class="card">
            <small>Zona de Disponibilidad (AZ)</small>
            <p><?= htmlspecialchars($az) ?></p>
        </div>
    </div>

    <!-- CAPA DE DATOS (AMAZON RDS MULTI-AZ) -->
    <h3>Registro de Pruebas en Base de Datos (RDS Multi-AZ)</h3>
    <form method="POST">
        <input type="text" name="nombre" placeholder="Tu nombre (ej. Ricardo Sepúlveda)" required>
        <input type="text" name="comentario" placeholder="Mensaje de prueba de persistencia..." required>
        <button type="submit">Guardar en RDS</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Comentario</th>
                <th>AZ que procesó</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registros)): ?>
                <tr><td colspan="5">Aún no hay registros o falta conectar el Endpoint de RDS.</td></tr>
            <?php else: ?>
                <?php foreach ($registros as $fila): ?>
                    <tr>
                        <td><?= $fila['id'] ?></td>
                        <td><?= htmlspecialchars($fila['nombre']) ?></td>
                        <td><?= htmlspecialchars($fila['comentario']) ?></td>
                        <td><code><?= htmlspecialchars($fila['az_origen']) ?></code></td>
                        <td><?= $fila['fecha'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
