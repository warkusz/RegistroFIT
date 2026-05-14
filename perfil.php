<?php
// -----------------------------------------------------------------------------
// perfil.php
// Gestão do perfil do utilizador e acompanhamento de progresso corporal:
// - atualizar nome/foto;
// - registar medições de peso;
// - visualizar histórico em tabela e gráfico.
// -----------------------------------------------------------------------------
// Acesso restrito: o perfil só pode ser aberto com sessão iniciada.
session_start();

if (!isset($_SESSION['id']) || (int)$_SESSION['id'] < 1) {
    header('Location: login.php?session_expired=1');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

include('conexao.php');

$erro = '';
$userDisplayName = trim($_SESSION['nome'] ?? '') !== '' ? $_SESSION['nome'] : 'Utilizador';
$userId = (int)($_SESSION['id'] ?? 0);
$userProfilePhoto = trim($_SESSION['foto_perfil'] ?? '') !== '' ? $_SESSION['foto_perfil'] : 'https://github.com/mdo.png';

$checkPhotoColumn = $conn->query("SHOW COLUMNS FROM utilizadores LIKE 'foto_perfil'");
if ($checkPhotoColumn instanceof mysqli_result) {
    $hasPhotoColumn = $checkPhotoColumn->num_rows > 0;
    $checkPhotoColumn->free();
    if (!$hasPhotoColumn) {
        $conn->query("ALTER TABLE utilizadores ADD COLUMN foto_perfil VARCHAR(255) DEFAULT NULL AFTER senha");
    }
}

$createMeasurementsTableSql = "CREATE TABLE IF NOT EXISTS user_measurements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    measured_on DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_measurements_user_date (user_id, measured_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createMeasurementsTableSql);

if (isset($_POST['btn-update-profile'])) {
    // Atualiza nome e, opcionalmente, foto de perfil com validação de upload.
    $newName = trim($_POST['profile_name'] ?? '');
    $newPhotoPath = '';

    if ($userId < 1) {
        $erro = 'Sessao invalida. Faz login novamente.';
    } elseif ($newName === '') {
        $erro = 'O nome nao pode ficar vazio.';
    } else {
        $profilePhotoToSave = null;
        if (isset($_FILES['profile_photo']) && (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_OK);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $uploadErrorsMap = [
                    UPLOAD_ERR_INI_SIZE => 'A imagem excede o limite de upload do servidor (upload_max_filesize).',
                    UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido pelo formulario.',
                    UPLOAD_ERR_PARTIAL => 'O upload foi interrompido. Tenta novamente.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta a pasta temporaria do PHP (upload_tmp_dir).',
                    UPLOAD_ERR_CANT_WRITE => 'O servidor nao conseguiu escrever o ficheiro no disco.',
                    UPLOAD_ERR_EXTENSION => 'Uma extensao do PHP bloqueou o upload.'
                ];
                $erro = $uploadErrorsMap[$uploadError] ?? 'Nao foi possivel enviar a foto.';
            } else {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $originalName = (string)($_FILES['profile_photo']['name'] ?? '');
                $tmpPath = (string)($_FILES['profile_photo']['tmp_name'] ?? '');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $erro = 'Formato invalido. Usa JPG, PNG ou WEBP.';
                } else {
                    $uploadsDir = __DIR__ . '/uploads/profile_photos';
                    if (!is_dir($uploadsDir)) {
                        if (!mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
                            $erro = 'Nao foi possivel criar a pasta de uploads no servidor.';
                        }
                    }

                    if ($erro === '' && !is_writable($uploadsDir)) {
                        $erro = 'A pasta de uploads existe mas nao tem permissoes de escrita para o Apache/PHP.';
                    }

                    if ($erro === '') {
                        // Nomeia o ficheiro com user_id + timestamp para reduzir colisões.
                        $newFileName = 'user_' . $userId . '_' . time() . '.' . $extension;
                        $destinationPath = $uploadsDir . '/' . $newFileName;
                        if (move_uploaded_file($tmpPath, $destinationPath)) {
                            $newPhotoPath = 'uploads/profile_photos/' . $newFileName;
                            $profilePhotoToSave = $newPhotoPath;
                        } else {
                            $erro = 'Falha ao guardar a foto no servidor. Verifica permissoes da pasta uploads/profile_photos.';
                        }
                    }
                }
            }
        }

        if ($erro === '') {
            if ($profilePhotoToSave !== null) {
                $stmtUpdateProfile = $conn->prepare("UPDATE utilizadores SET nome = ?, foto_perfil = ? WHERE id = ?");
                if ($stmtUpdateProfile) {
                    $stmtUpdateProfile->bind_param("ssi", $newName, $profilePhotoToSave, $userId);
                    $stmtUpdateProfile->execute();
                    $stmtUpdateProfile->close();
                    $_SESSION['nome'] = $newName;
                    $_SESSION['foto_perfil'] = $profilePhotoToSave;
                    header('Location: perfil.php?profile_saved=1');
                    exit;
                }
                $erro = 'Erro ao atualizar perfil.';
            } else {
                $stmtUpdateName = $conn->prepare("UPDATE utilizadores SET nome = ? WHERE id = ?");
                if ($stmtUpdateName) {
                    $stmtUpdateName->bind_param("si", $newName, $userId);
                    $stmtUpdateName->execute();
                    $stmtUpdateName->close();
                    $_SESSION['nome'] = $newName;
                    header('Location: perfil.php?profile_saved=1');
                    exit;
                }
                $erro = 'Erro ao atualizar nome.';
            }
        }
    }
}

if (isset($_POST['btn-save-measurement'])) {
    // Regista uma nova medição (peso/data/notas) para histórico pessoal.
    $weightKg = (float)($_POST['weight_kg'] ?? 0);
    $measuredOn = trim($_POST['measured_on'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($userId < 1) {
        $erro = 'Sessao invalida. Faz login novamente.';
    } elseif ($weightKg <= 0 || $measuredOn === '') {
        $erro = 'Preenche o peso e a data da medicao.';
    } else {
        $dateIsValid = DateTime::createFromFormat('Y-m-d', $measuredOn) !== false;
        if (!$dateIsValid) {
            $erro = 'Data invalida.';
        } else {
            $stmtInsertMeasurement = $conn->prepare("INSERT INTO user_measurements (user_id, weight_kg, measured_on, notes) VALUES (?, ?, ?, ?)");
            if ($stmtInsertMeasurement) {
                $stmtInsertMeasurement->bind_param("idss", $userId, $weightKg, $measuredOn, $notes);
                if ($stmtInsertMeasurement->execute()) {
                    $stmtInsertMeasurement->close();
                    header('Location: perfil.php?saved=1');
                    exit;
                }
                $erro = 'Nao foi possivel guardar a medicao.';
                $stmtInsertMeasurement->close();
            } else {
                $erro = 'Erro ao preparar o registo da medicao.';
            }
        }
    }
}

if (isset($_POST['btn-delete-measurement'])) {
    // Permite eliminar medições erradas ou duplicadas.
    $measurementId = (int)($_POST['measurement_id'] ?? 0);
    if ($measurementId > 0 && $userId > 0) {
        $stmtDeleteMeasurement = $conn->prepare("DELETE FROM user_measurements WHERE id = ? AND user_id = ?");
        if ($stmtDeleteMeasurement) {
            $stmtDeleteMeasurement->bind_param("ii", $measurementId, $userId);
            $stmtDeleteMeasurement->execute();
            $stmtDeleteMeasurement->close();
        }
    }
    header('Location: perfil.php?deleted=1');
    exit;
}

$measurements = [];
// Arrays usados no Chart.js (datas no eixo X e peso em Kg no eixo Y).
$chartLabels = [];
$chartWeights = [];
if ($userId > 0) {
    // Recarrega dados do utilizador para manter sessão sincronizada com a BD.
    $stmtUser = $conn->prepare("SELECT nome, foto_perfil FROM utilizadores WHERE id = ? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $userResult = $stmtUser->get_result();
        $userRow = $userResult->fetch_assoc();
        if ($userRow) {
            $userDisplayName = trim((string)$userRow['nome']) !== '' ? $userRow['nome'] : $userDisplayName;
            if (trim((string)($userRow['foto_perfil'] ?? '')) !== '') {
                $userProfilePhoto = $userRow['foto_perfil'];
            }
            $_SESSION['nome'] = $userDisplayName;
            $_SESSION['foto_perfil'] = $userProfilePhoto;
        }
        $stmtUser->close();
    }

    $stmtMeasurements = $conn->prepare("SELECT id, weight_kg, measured_on, notes FROM user_measurements WHERE user_id = ? ORDER BY measured_on DESC, id DESC");
    if ($stmtMeasurements) {
        $stmtMeasurements->bind_param("i", $userId);
        $stmtMeasurements->execute();
        $resultMeasurements = $stmtMeasurements->get_result();
        while ($row = $resultMeasurements->fetch_assoc()) {
            $measurements[] = $row;
        }
        $stmtMeasurements->close();
    }

    if (count($measurements) > 0) {
        $chartMeasurements = $measurements;
        usort($chartMeasurements, function ($a, $b) {
            if ($a['measured_on'] === $b['measured_on']) {
                return ((int)$a['id']) <=> ((int)$b['id']);
            }
            return strcmp((string)$a['measured_on'], (string)$b['measured_on']);
        });

        foreach ($chartMeasurements as $chartMeasurement) {
            $chartLabels[] = (string)$chartMeasurement['measured_on'];
            $chartWeights[] = (float)$chartMeasurement['weight_kg'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="images/2307827.png" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <main class="d-flex flex-nowrap vh-100">
        <!-- Sidebar padrão da área autenticada -->
        <div class="d-flex flex-column flex-shrink-0 p-3 text-bg-dark" style="width: 280px;">
            <a href="index.html" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <svg class="bi pe-none" width="40" height="32"></svg>
                <img src="images/2307827.png" alt="Workout Planner Logo" width="62" height="60" class="me-4" />
                <span class="fs-4">RegistoFIT</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="main.php" class="nav-link text-white">Treinos</a>
                </li>
                <li>
                    <a href="calendar.php" class="nav-link text-white">Calendário</a>
                </li>
                <li>
                    <a href="perfil.php" class="nav-link active" aria-current="page">Perfil</a>
                </li>
            </ul>
            <hr>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($userProfilePhoto); ?>" alt="Foto de perfil" width="32" height="32" class="rounded-circle me-2">
                    <strong><?php echo htmlspecialchars($userDisplayName); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                    <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
                </ul>
            </div>
        </div>

        <div class="flex-grow-1 p-4 overflow-y-auto">
            <!-- Cartão com edição de perfil e registo de novas medições -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h4 mb-3">Perfil e Medicoes</h1>
                    <?php if (isset($_GET['profile_saved'])): ?>
                        <div class="alert alert-success" role="alert">Perfil atualizado com sucesso.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="alert alert-success" role="alert">Medicao guardada com sucesso.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success" role="alert">Medicao removida.</div>
                    <?php endif; ?>
                    <?php if ($erro !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="perfil.php" class="row g-3 mb-4" enctype="multipart/form-data">
                        <div class="col-md-6">
                            <label for="profileName" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="profileName" name="profile_name" value="<?php echo htmlspecialchars($userDisplayName); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="profilePhoto" class="form-label">Foto de perfil</label>
                            <input type="file" class="form-control" id="profilePhoto" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="col-12 d-flex align-items-center gap-3">
                            <img src="<?php echo htmlspecialchars($userProfilePhoto); ?>" alt="Foto atual" width="56" height="56" class="rounded-circle">
                            <button type="submit" class="btn btn-success" name="btn-update-profile">Atualizar perfil</button>
                        </div>
                    </form>

                    <form method="POST" action="perfil.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="weightKg" class="form-label">Peso (kg)</label>
                            <input type="number" min="1" step="0.1" class="form-control" id="weightKg" name="weight_kg" required>
                        </div>
                        <div class="col-md-3">
                            <label for="measuredOn" class="form-label">Data da medicao</label>
                            <input type="date" class="form-control" id="measuredOn" name="measured_on" required>
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label">Notas (opcional)</label>
                            <input type="text" maxlength="255" class="form-control" id="notes" name="notes" placeholder="Ex: medido em jejum, apos treino, etc.">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success" name="btn-save-measurement">Guardar medicao</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Historico</h2>
                    <div class="mb-4">
                        <?php if (count($chartWeights) > 0): ?>
                            <canvas id="weightProgressChart" height="240"></canvas>
                        <?php else: ?>
                            <div class="text-muted small">Adiciona medicoes para veres o grafico de progresso do peso.</div>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Peso (kg)</th>
                                    <th>Notas</th>
                                    <th class="text-end">Acao</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($measurements) > 0): ?>
                                    <?php foreach ($measurements as $measurement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($measurement['measured_on']); ?></td>
                                            <td><?php echo number_format((float)$measurement['weight_kg'], 1, '.', ''); ?></td>
                                            <td><?php echo htmlspecialchars((string)($measurement['notes'] ?? '')); ?></td>
                                            <td class="text-end">
                                                <form method="POST" action="perfil.php" onsubmit="return confirm('Remover esta medicao?');">
                                                    <input type="hidden" name="measurement_id" value="<?php echo (int)$measurement['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" name="btn-delete-measurement">Remover</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Ainda nao tens medicoes guardadas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const weightLabels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const weightData = <?php echo json_encode($chartWeights, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const chartCanvas = document.getElementById('weightProgressChart');

            if (!chartCanvas || !Array.isArray(weightData) || weightData.length === 0) {
                return;
            }

            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: weightLabels,
                    datasets: [
                        {
                            label: 'Peso (kg)',
                            data: weightData,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.2)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: 0.25,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 1,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Kg'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        }
                    }
                }
            });
        })();
    </script>
    <script src="page-transitions.js" defer></script>
</body>
</html>
