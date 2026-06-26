<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$db   = 'si_dinas';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Koneksi database gagal']);
    exit;
}

// Jika error PHP terjadi, kembalikan JSON agar frontend/Cloudflare tidak melihat respon HTML/terputus
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode(['error' => 'PHP error: ' . $message]);
    exit;
});


$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';

switch ($path) {
    case 'docs':
        handleDocs($method, $pdo);
        break;
    case 'pegawai':
        handlePegawai($method, $pdo);
        break;
    case 'kegiatan':
        handleKegiatan($method, $pdo);
        break;
    case 'sync':
        handleSync($method, $pdo);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint tidak ditemukan']);
}

function handleDocs($method, $pdo) {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, data FROM docs ORDER BY id DESC");
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $doc = json_decode($row['data'], true);
            $doc['id'] = (int)$row['id'];
            $result[] = $doc;
        }
        echo json_encode($result);
        return;
    }
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { http_response_code(400); echo json_encode(['error' => 'Data tidak valid']); return; }
        if (isset($input['id']) && $input['id'] > 0) {
            $id = (int)$input['id'];
            unset($input['id']);
            $dataJson = json_encode($input);
            $stmt = $pdo->prepare("UPDATE docs SET data = ? WHERE id = ?");
            $stmt->execute([$dataJson, $id]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            unset($input['id']);
            $dataJson = json_encode($input);
            $stmt = $pdo->prepare("INSERT INTO docs (data) VALUES (?)");
            $stmt->execute([$dataJson]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        return;
    }
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID tidak valid']); return; }
        $stmt = $pdo->prepare("DELETE FROM docs WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        return;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handlePegawai($method, $pdo) {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM pegawai ORDER BY id DESC");
        echo json_encode($stmt->fetchAll());
        return;
    }
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { http_response_code(400); echo json_encode(['error' => 'Data tidak valid']); return; }
        if (isset($input['id']) && $input['id'] > 0) {
            $id = (int)$input['id'];
            $sql = "UPDATE pegawai SET nama=?, jabatan=?, akd=?, bank=?, rekening=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['nama'], $input['jabatan'] ?? '', $input['akd'] ?? '', $input['bank'] ?? '', $input['rekening'] ?? '', $id]);
            echo json_encode(['success' => true]);
        } else {
            $sql = "INSERT INTO pegawai (nama, jabatan, akd, bank, rekening) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['nama'], $input['jabatan'] ?? '', $input['akd'] ?? '', $input['bank'] ?? '', $input['rekening'] ?? '']);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        return;
    }
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID tidak valid']); return; }
        $stmt = $pdo->prepare("DELETE FROM pegawai WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        return;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleKegiatan($method, $pdo) {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM kegiatan ORDER BY id DESC");
        echo json_encode($stmt->fetchAll());
        return;
    }
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { http_response_code(400); echo json_encode(['error' => 'Data tidak valid']); return; }
        if (isset($input['id']) && $input['id'] > 0) {
            $id = (int)$input['id'];
            $sql = "UPDATE kegiatan SET tanggal=?, jenis=?, acara=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['tanggal'], $input['jenis'], $input['acara'], $id]);
            echo json_encode(['success' => true]);
        } else {
            $sql = "INSERT INTO kegiatan (tanggal, jenis, acara) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$input['tanggal'], $input['jenis'], $input['acara']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        return;
    }
    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'ID tidak valid']); return; }
        $stmt = $pdo->prepare("DELETE FROM kegiatan WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        return;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleSync($method, $pdo) {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }
        $pdo->beginTransaction();
        try {
            if (isset($input['docs'])) {
                $pdo->exec("DELETE FROM docs");
                foreach ($input['docs'] as $doc) {
                    $id = isset($doc['id']) ? (int)$doc['id'] : null;
                    unset($doc['id']);
                    $dataJson = json_encode($doc);
                    if ($id) {
                        $stmt = $pdo->prepare("INSERT INTO docs (id, data) VALUES (?, ?)");
                        $stmt->execute([$id, $dataJson]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO docs (data) VALUES (?)");
                        $stmt->execute([$dataJson]);
                    }
                }
            }
            if (isset($input['pegawai'])) {
                $pdo->exec("DELETE FROM pegawai");
                foreach ($input['pegawai'] as $p) {
                    $id = isset($p['id']) ? (int)$p['id'] : null;
                    $stmt = $pdo->prepare("INSERT INTO pegawai (id, nama, jabatan, akd, bank, rekening) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $p['nama'], $p['jabatan'] ?? '', $p['akd'] ?? '', $p['bank'] ?? '', $p['rekening'] ?? '']);
                }
            }
            if (isset($input['kegiatan'])) {
                $pdo->exec("DELETE FROM kegiatan");
                foreach ($input['kegiatan'] as $k) {
                    $id = isset($k['id']) ? (int)$k['id'] : null;
                    $stmt = $pdo->prepare("INSERT INTO kegiatan (id, tanggal, jenis, acara) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $k['tanggal'], $k['jenis'], $k['acara']]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Sync gagal: ' . $e->getMessage()]);
        }
        return;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}