<?php
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
$userId = (int)$_SESSION['id'];
$userDisplayName = trim($_SESSION['nome'] ?? '') !== '' ? $_SESSION['nome'] : 'Utilizador';
$userProfilePhoto = trim($_SESSION['foto_perfil'] ?? '') !== '' ? $_SESSION['foto_perfil'] : 'https://github.com/mdo.png';

$conn->query("CREATE TABLE IF NOT EXISTS workout_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS workout_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    workout_day_id INT NOT NULL,
    exercise_type VARCHAR(20) NOT NULL DEFAULT 'exercicio',
    nome_exercicio VARCHAR(120) NOT NULL,
    reps VARCHAR(50) NOT NULL DEFAULT '',
    num_sets INT NOT NULL DEFAULT 0,
    kg DECIMAL(7,2) NULL,
    tempo_minutos INT NULL,
    distancia_metros INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_workout_day
        FOREIGN KEY (workout_day_id) REFERENCES workout_days(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migra reps de INT para VARCHAR se necessário
$checkRepsType = $conn->query("SHOW COLUMNS FROM workout_exercises LIKE 'reps'");
if ($checkRepsType instanceof mysqli_result) {
    $repsCol = $checkRepsType->fetch_assoc();
    $checkRepsType->free();
    if ($repsCol && stripos((string)($repsCol['Type'] ?? ''), 'varchar') === false) {
        $conn->query("ALTER TABLE workout_exercises MODIFY COLUMN reps VARCHAR(50) NOT NULL DEFAULT ''");
    }
}

// Garante que kg permite NULL
$checkKgColumn = $conn->query("SHOW COLUMNS FROM workout_exercises LIKE 'kg'");
if ($checkKgColumn instanceof mysqli_result) {
    $kgColumn = $checkKgColumn->fetch_assoc();
    $checkKgColumn->free();
    if ($kgColumn && strtoupper((string)($kgColumn['Null'] ?? 'NO')) !== 'YES') {
        $conn->query("ALTER TABLE workout_exercises MODIFY COLUMN kg DECIMAL(7,2) NULL");
    }
}

// Adiciona colunas novas se não existirem
foreach ([
    "exercise_type" => "ALTER TABLE workout_exercises ADD COLUMN exercise_type VARCHAR(20) NOT NULL DEFAULT 'exercicio' AFTER workout_day_id",
    "tempo_minutos" => "ALTER TABLE workout_exercises ADD COLUMN tempo_minutos INT NULL",
    "distancia_metros" => "ALTER TABLE workout_exercises ADD COLUMN distancia_metros INT NULL",
] as $col => $alterSql) {
    $check = $conn->query("SHOW COLUMNS FROM workout_exercises LIKE '$col'");
    if ($check instanceof mysqli_result) {
        if ($check->num_rows === 0) {
            $conn->query($alterSql);
        }
        $check->free();
    }
}

// Garante user_id nas tabelas
$checkDaysUserId = $conn->query("SHOW COLUMNS FROM workout_days LIKE 'user_id'");
if ($checkDaysUserId instanceof mysqli_result) {
    if ($checkDaysUserId->num_rows === 0) {
        $conn->query("ALTER TABLE workout_days ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
    }
    $checkDaysUserId->free();
}

$checkExercisesUserId = $conn->query("SHOW COLUMNS FROM workout_exercises LIKE 'user_id'");
if ($checkExercisesUserId instanceof mysqli_result) {
    if ($checkExercisesUserId->num_rows === 0) {
        $conn->query("ALTER TABLE workout_exercises ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
    }
    $checkExercisesUserId->free();
}

$dayNameUniqueIndex = $conn->query("SHOW INDEX FROM workout_days WHERE Key_name = 'day_name'");
if ($dayNameUniqueIndex instanceof mysqli_result) {
    if ($dayNameUniqueIndex->num_rows > 0) {
        $conn->query("ALTER TABLE workout_days DROP INDEX day_name");
    }
    $dayNameUniqueIndex->free();
}
$userDayUniqueIndex = $conn->query("SHOW INDEX FROM workout_days WHERE Key_name = 'unique_user_day'");
if ($userDayUniqueIndex instanceof mysqli_result) {
    if ($userDayUniqueIndex->num_rows === 0) {
        $conn->query("ALTER TABLE workout_days ADD UNIQUE KEY unique_user_day (user_id, day_name)");
    }
    $userDayUniqueIndex->free();
}

$exampleTemplates = [
    'leg-day' => [
        'day_name' => ' Leg Day',
        'exercises' => [
            ['nome' => 'Agachamento livre (bodyweight)', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Leg press (maquina)', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Cadeira extensora', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Mesa flexora', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Gemeos em pe (leg press)', 'sets' => 3, 'reps' => '15']
        ]
    ],
    'peito-triceps' => [
        'day_name' => ' Peito + Triceps',
        'exercises' => [
            ['nome' => 'Supino com halteres', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Flexoes (push-ups)', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Crucifixo com halteres', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Triceps na polia (corda)', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Extensao de triceps (halter)', 'sets' => 3, 'reps' => '12']
        ]
    ],
    'costas-biceps' => [
        'day_name' => ' Costas + Biceps',
        'exercises' => [
            ['nome' => 'Remada na polia baixa', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Lat pulldown (polia alta)', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Remada com halter (unilateral)', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Rosca direta com halteres', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Rosca martelo', 'sets' => 3, 'reps' => '12']
        ]
    ],
    'core' => [
        'day_name' => 'Core',
        'exercises' => [
            ['nome' => 'Prancha (plank) - seg', 'sets' => 3, 'reps' => '45'],
            ['nome' => 'Crunch no banco', 'sets' => 3, 'reps' => '15'],
            ['nome' => 'Elevacao de pernas (deitado)', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Russian twist (sem peso) - total', 'sets' => 3, 'reps' => '20'],
            ['nome' => 'Prancha lateral (cada lado) - seg', 'sets' => 2, 'reps' => '25']
        ]
    ],
    'ombros-bracos' => [
        'day_name' => 'Ombros + Bracos',
        'exercises' => [
            ['nome' => 'Press de ombros com halteres', 'sets' => 3, 'reps' => '10'],
            ['nome' => 'Elevacao lateral', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Elevacao frontal', 'sets' => 3, 'reps' => '12'],
            ['nome' => 'Rosca 21s (biceps)', 'sets' => 2, 'reps' => '21'],
            ['nome' => 'Triceps banco (bench dip)', 'sets' => 3, 'reps' => '10']
        ]
    ]
];


if (isset($_POST['btn-create-day'])) {
    $dayName = trim($_POST['day_name'] ?? '');
    if ($dayName === '') {
        $erro = 'Escreve o nome do dia de treino.';
    } else {
        $stmtCreateDay = $conn->prepare("INSERT INTO workout_days (user_id, day_name) VALUES (?, ?)");
        if ($stmtCreateDay) {
            $stmtCreateDay->bind_param("is", $userId, $dayName);
            if ($stmtCreateDay->execute()) {
                $stmtCreateDay->close();
                header('Location: main.php?day_saved=1');
                exit;
            }
            $erro = 'Nao foi possivel criar o dia de treino (talvez ja exista).';
            $stmtCreateDay->close();
        } else {
            $erro = 'Erro ao preparar a criacao do dia.';
        }
    }
}

if (isset($_POST['btn-guardar-workout'])) {
    $workoutDayId = (int)($_POST['workout_day_id'] ?? 0);
    $nomeExercicio = trim($_POST['nome_exercicio'] ?? '');
    $exerciseType = in_array($_POST['exercise_type'] ?? '', ['exercicio', 'corrida']) ? $_POST['exercise_type'] : 'exercicio';

    $reps = '';
    $numSets = 0;
    $kg = null;
    $tempoMinutos = null;
    $distanciaMetros = null;

    if ($exerciseType === 'corrida') {
        $tempoMinutos = (int)($_POST['tempo_minutos'] ?? 0);
        $distanciaMetros = (int)($_POST['distancia_metros'] ?? 0);
        if ($workoutDayId < 1 || $nomeExercicio === '' || $tempoMinutos < 1 || $distanciaMetros < 1) {
            $erro = 'Preenche os campos corretamente antes de guardar.';
        }
    } else {
        $reps = trim($_POST['reps'] ?? '');
        $numSets = (int)($_POST['num_sets'] ?? 0);
        $kgInput = trim((string)($_POST['kg'] ?? ''));
        if ($kgInput !== '') {
            $kg = (float)$kgInput;
        }
        if ($workoutDayId < 1 || $nomeExercicio === '' || $reps === '' || $numSets < 1 || ($kg !== null && $kg < 0)) {
            $erro = 'Preenche os campos corretamente antes de guardar.';
        }
    }

    if ($erro === '') {
        $stmtCheckDayOwner = $conn->prepare("SELECT id FROM workout_days WHERE id = ? AND user_id = ? LIMIT 1");
        if ($stmtCheckDayOwner) {
            $stmtCheckDayOwner->bind_param("ii", $workoutDayId, $userId);
            $stmtCheckDayOwner->execute();
            $dayResult = $stmtCheckDayOwner->get_result();
            $dayExistsForUser = $dayResult && $dayResult->num_rows > 0;
            $stmtCheckDayOwner->close();

            if (!$dayExistsForUser) {
                $erro = 'Dia de treino invalido para este utilizador.';
            } else {
                $stmtInsert = $conn->prepare("INSERT INTO workout_exercises (user_id, workout_day_id, exercise_type, nome_exercicio, reps, num_sets, kg, tempo_minutos, distancia_metros) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmtInsert) {
                    $stmtInsert->bind_param("iisssidii", $userId, $workoutDayId, $exerciseType, $nomeExercicio, $reps, $numSets, $kg, $tempoMinutos, $distanciaMetros);
                    if ($stmtInsert->execute()) {
                        $stmtInsert->close();
                        header('Location: main.php?saved=1');
                        exit;
                    }
                    $erro = 'Nao foi possivel guardar o exercicio.';
                    $stmtInsert->close();
                } else {
                    $erro = 'Erro ao preparar a gravacao do exercicio.';
                }
            }
        } else {
            $erro = 'Erro ao validar o dia de treino.';
        }
    }
}

if (isset($_POST['btn-add-example-template'])) {
    $templateKey = trim((string)($_POST['template_key'] ?? ''));
    if ($templateKey === '' || !isset($exampleTemplates[$templateKey])) {
        $erro = 'Modelo de treino invalido.';
    } else {
        $template = $exampleTemplates[$templateKey];
        $templateDayName = (string)$template['day_name'];
        $templateExercises = $template['exercises'];

        $stmtCheckDay = $conn->prepare("SELECT id FROM workout_days WHERE user_id = ? AND day_name = ? LIMIT 1");
        $existingDayId = 0;
        if ($stmtCheckDay) {
            $stmtCheckDay->bind_param("is", $userId, $templateDayName);
            $stmtCheckDay->execute();
            $existingDayResult = $stmtCheckDay->get_result();
            $existingDayRow = $existingDayResult ? $existingDayResult->fetch_assoc() : null;
            if ($existingDayRow) {
                $existingDayId = (int)$existingDayRow['id'];
            }
            $stmtCheckDay->close();
        }

        if ($existingDayId > 0) {
            $stmtHasExercises = $conn->prepare("SELECT id FROM workout_exercises WHERE user_id = ? AND workout_day_id = ? LIMIT 1");
            $alreadyPopulated = false;
            if ($stmtHasExercises) {
                $stmtHasExercises->bind_param("ii", $userId, $existingDayId);
                $stmtHasExercises->execute();
                $hasExercisesResult = $stmtHasExercises->get_result();
                $alreadyPopulated = $hasExercisesResult && $hasExercisesResult->num_rows > 0;
                $stmtHasExercises->close();
            }
            if ($alreadyPopulated) {
                $erro = 'Esse exemplo ja foi adicionado ao teu plano.';
            }
        }

        if ($erro === '') {
            $conn->begin_transaction();
            try {
                $dayIdToUse = $existingDayId;
                if ($dayIdToUse < 1) {
                    $stmtCreateTemplateDay = $conn->prepare("INSERT INTO workout_days (user_id, day_name) VALUES (?, ?)");
                    if (!$stmtCreateTemplateDay) {
                        throw new Exception('Erro ao preparar criacao do dia.');
                    }
                    $stmtCreateTemplateDay->bind_param("is", $userId, $templateDayName);
                    if (!$stmtCreateTemplateDay->execute()) {
                        $stmtCreateTemplateDay->close();
                        throw new Exception('Nao foi possivel criar o dia de exemplo.');
                    }
                    $dayIdToUse = (int)$stmtCreateTemplateDay->insert_id;
                    $stmtCreateTemplateDay->close();
                }

                $stmtInsertTemplateExercise = $conn->prepare("INSERT INTO workout_exercises (user_id, workout_day_id, exercise_type, nome_exercicio, reps, num_sets, kg, tempo_minutos, distancia_metros) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmtInsertTemplateExercise) {
                    throw new Exception('Erro ao preparar exercicios de exemplo.');
                }

                $exampleType = 'exercicio';
                $exampleKg = null;
                $exampleTempo = null;
                $exampleDist = null;

                foreach ($templateExercises as $templateExercise) {
                    $exerciseName = (string)$templateExercise['nome'];
                    $exerciseReps = (string)$templateExercise['reps'];
                    $exerciseSets = (int)$templateExercise['sets'];
                    $stmtInsertTemplateExercise->bind_param("iisssidii", $userId, $dayIdToUse, $exampleType, $exerciseName, $exerciseReps, $exerciseSets, $exampleKg, $exampleTempo, $exampleDist);
                    if (!$stmtInsertTemplateExercise->execute()) {
                        throw new Exception('Nao foi possivel inserir exercicios de exemplo.');
                    }
                }
                $stmtInsertTemplateExercise->close();
                $conn->commit();
                header('Location: main.php?examples_saved=1');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $erro = 'Nao foi possivel adicionar o exemplo agora.';
            }
        }
    }
}

if (isset($_POST['btn-update-exercise'])) {
    $exerciseId = (int)($_POST['exercise_id'] ?? 0);
    $nomeExercicio = trim((string)($_POST['nome_exercicio'] ?? ''));
    $exerciseType = in_array($_POST['exercise_type'] ?? '', ['exercicio', 'corrida']) ? $_POST['exercise_type'] : 'exercicio';

    $reps = '';
    $numSets = 0;
    $kg = null;
    $tempoMinutos = null;
    $distanciaMetros = null;

    if ($exerciseType === 'corrida') {
        $tempoMinutos = (int)($_POST['tempo_minutos'] ?? 0);
        $distanciaMetros = (int)($_POST['distancia_metros'] ?? 0);
        if ($exerciseId < 1 || $nomeExercicio === '' || $tempoMinutos < 1 || $distanciaMetros < 1) {
            $erro = 'Preenche os campos corretamente antes de editar.';
        }
    } else {
        $reps = trim((string)($_POST['reps'] ?? ''));
        $numSets = (int)($_POST['num_sets'] ?? 0);
        $kgInput = trim((string)($_POST['kg'] ?? ''));
        if ($kgInput !== '') {
            $kg = (float)$kgInput;
        }
        if ($exerciseId < 1 || $nomeExercicio === '' || $reps === '' || $numSets < 1 || ($kg !== null && $kg < 0)) {
            $erro = 'Preenche os campos corretamente antes de editar.';
        }
    }

    if ($erro === '') {
        $stmtUpdateExercise = $conn->prepare("UPDATE workout_exercises SET exercise_type = ?, nome_exercicio = ?, reps = ?, num_sets = ?, kg = ?, tempo_minutos = ?, distancia_metros = ? WHERE id = ? AND user_id = ?");
        if ($stmtUpdateExercise) {
            $stmtUpdateExercise->bind_param("sssidiiii", $exerciseType, $nomeExercicio, $reps, $numSets, $kg, $tempoMinutos, $distanciaMetros, $exerciseId, $userId);
            $stmtUpdateExercise->execute();
            $stmtUpdateExercise->close();
            header('Location: main.php?updated=1');
            exit;
        }
        $erro = 'Erro ao preparar edicao do exercicio.';
    }
}

if (isset($_POST['btn-delete-exercise'])) {
    $exerciseId = (int)($_POST['exercise_id'] ?? 0);
    if ($exerciseId > 0) {
        $stmtDeleteExercise = $conn->prepare("DELETE FROM workout_exercises WHERE id = ? AND user_id = ?");
        if ($stmtDeleteExercise) {
            $stmtDeleteExercise->bind_param("ii", $exerciseId, $userId);
            $stmtDeleteExercise->execute();
            $stmtDeleteExercise->close();
        }
    }
    header('Location: main.php?deleted=1');
    exit;
}

if (isset($_POST['btn-delete-day'])) {
    $dayId = (int)($_POST['day_id'] ?? 0);
    if ($dayId > 0) {
        $stmtDeleteDay = $conn->prepare("DELETE FROM workout_days WHERE id = ? AND user_id = ?");
        if ($stmtDeleteDay) {
            $stmtDeleteDay->bind_param("ii", $dayId, $userId);
            $stmtDeleteDay->execute();
            $stmtDeleteDay->close();
        }
    }
    header('Location: main.php?day_deleted=1');
    exit;
}


$dayOptions = [];
$daysData = [];
$daysResultStmt = $conn->prepare("SELECT id, day_name FROM workout_days WHERE user_id = ? ORDER BY created_at DESC, id DESC");
if ($daysResultStmt) {
    $daysResultStmt->bind_param("i", $userId);
    $daysResultStmt->execute();
    $daysResult = $daysResultStmt->get_result();
    while ($dayRow = $daysResult->fetch_assoc()) {
        $dayId = (int)$dayRow['id'];
        $dayOptions[] = $dayRow;
        $daysData[$dayId] = [
            'id' => $dayId,
            'day_name' => $dayRow['day_name'],
            'exercises' => []
        ];
    }
    $daysResultStmt->close();
}

$exercisesResultStmt = $conn->prepare("SELECT id, workout_day_id, exercise_type, nome_exercicio, reps, num_sets, kg, tempo_minutos, distancia_metros FROM workout_exercises WHERE user_id = ? ORDER BY created_at DESC, id DESC");
if ($exercisesResultStmt) {
    $exercisesResultStmt->bind_param("i", $userId);
    $exercisesResultStmt->execute();
    $exercisesResult = $exercisesResultStmt->get_result();
    while ($exerciseRow = $exercisesResult->fetch_assoc()) {
        $dayId = (int)$exerciseRow['workout_day_id'];
        if (isset($daysData[$dayId])) {
            $daysData[$dayId]['exercises'][] = $exerciseRow;
        }
    }
    $exercisesResultStmt->close();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Página Principal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="images/2307827.png" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <main class="d-flex flex-nowrap vh-100">
        <div class="d-flex flex-column flex-shrink-0 p-3 text-bg-dark" style="width: 280px;">
            <a href="index.html" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <svg class="bi pe-none" width="40" height="32"></svg>
                 <img
                            src="images/2307827.png"
                            alt="Workout Planner Logo"
                            width="62"
                            height="60"
                            class="me-4"
                        />
                <span class="fs-4">RegistoFIT</span>
            </a>
            <hr>

            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="main.php" class="nav-link active" aria-current="page">
                        <svg class="bi pe-none me-2" width="16" height="16"><use xlink:href="#home"></use></svg>
                        Treinos
                    </a>
                </li>
                <li>
                    <a href="calendar.php" class="nav-link text-white">
                        <svg class="bi pe-none me-2" width="16" height="16"><use xlink:href="#speedometer2"></use></svg>
                        Calendário
                    </a>
                </li>
                <li>
                    <a href="perfil.php" class="nav-link text-white">
                        <svg class="bi pe-none me-2" width="16" height="16"><use xlink:href="#table"></use></svg>
                        Perfil
                    </a>
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

        <div class="flex-grow-1 p-4 overflow-y-auto ">
            <section id="workouts-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-white">Plano de Treino</h1>
                    <p class="text-white mb-0">Cria um dia (ex: Leg Day), adiciona exercicios ou corridas e remove quando precisares.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addDayModal">
                        Criar dia
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWorkoutModal">
                        Adicionar
                    </button>
                    <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#exampleTemplatesModal">
                        Exemplos
                    </button>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="alert alert-success" role="alert">Exercicio guardado com sucesso.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['day_saved'])): ?>
                        <div class="alert alert-success" role="alert">Dia de treino criado com sucesso.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-success" role="alert">Exercicio removido.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['updated'])): ?>
                        <div class="alert alert-success" role="alert">Exercicio atualizado com sucesso.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['day_deleted'])): ?>
                        <div class="alert alert-success" role="alert">Dia e exercicios removidos.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['examples_saved'])): ?>
                        <div class="alert alert-success" role="alert">Exemplo adicionado ao teu plano.</div>
                    <?php endif; ?>
                    <?php if ($erro !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro); ?></div>
                    <?php endif; ?>
                    <?php if (count($daysData) > 0): ?>
                        <?php foreach ($daysData as $dayData): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 class="h5 mb-0"><?php echo htmlspecialchars($dayData['day_name']); ?></h2>
                                    <form method="POST" action="main.php" onsubmit="return confirm('Remover este dia e todos os exercicios?');">
                                        <input type="hidden" name="day_id" value="<?php echo (int)$dayData['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" name="btn-delete-day">Remover dia</button>
                                    </form>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Tipo</th>
                                                <th>Reps / Tempo (min)</th>
                                                <th>Sets / Dist (m)</th>
                                                <th>Kg</th>
                                                <th class="text-end">Acao</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($dayData['exercises']) > 0): ?>
                                                <?php foreach ($dayData['exercises'] as $workout): ?>
                                                    <?php
                                                        $wType = $workout['exercise_type'] ?? 'exercicio';
                                                        $isCorrida = $wType === 'corrida';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($workout['nome_exercicio']); ?></td>
                                                        <td>
                                                            <?php if ($isCorrida): ?>
                                                                <span class="badge bg-info text-dark">Corrida</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Exercício</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($isCorrida): ?>
                                                                <?php echo (int)$workout['tempo_minutos']; ?> min
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars((string)$workout['reps']); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($isCorrida): ?>
                                                                <?php echo (int)$workout['distancia_metros']; ?> m
                                                            <?php else: ?>
                                                                <?php echo (int)$workout['num_sets']; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($isCorrida): ?>
                                                                -
                                                            <?php else: ?>
                                                                <?php echo $workout['kg'] === null ? '-' : number_format((float)$workout['kg'], 1, '.', ''); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="d-inline-flex gap-2">
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-outline-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editWorkoutModal"
                                                                    data-exercise-id="<?php echo (int)$workout['id']; ?>"
                                                                    data-exercise-type="<?php echo htmlspecialchars($wType, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-exercise-name="<?php echo htmlspecialchars((string)$workout['nome_exercicio'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-exercise-reps="<?php echo htmlspecialchars((string)$workout['reps'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-exercise-sets="<?php echo (int)$workout['num_sets']; ?>"
                                                                    data-exercise-kg="<?php echo $workout['kg'] === null ? '' : htmlspecialchars((string)$workout['kg'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-exercise-tempo="<?php echo (int)($workout['tempo_minutos'] ?? 0); ?>"
                                                                    data-exercise-distancia="<?php echo (int)($workout['distancia_metros'] ?? 0); ?>"
                                                                >
                                                                    Editar
                                                                </button>
                                                                <form method="POST" action="main.php" onsubmit="return confirm('Remover este exercicio?');">
                                                                    <input type="hidden" name="exercise_id" value="<?php echo (int)$workout['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" name="btn-delete-exercise">Remover</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">Este dia ainda nao tem exercicios.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="emptyRow" class="text-center text-muted py-4">Ainda nao tens exercicios no plano.</div>
                    <?php endif; ?>
                </div>
            </div>
            </section>
        </div>

    </main>

    <!-- Modal: Criar dia -->
    <div class="modal fade" id="addDayModal" tabindex="-1" aria-labelledby="addDayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="main.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDayModalLabel">Criar dia de treino</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <label for="dayName" class="form-label">Nome do dia</label>
                        <input type="text" class="form-control" id="dayName" name="day_name" placeholder="Ex: Leg Day" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" name="btn-create-day">Criar dia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Adicionar exercício / corrida -->
    <div class="modal fade" id="addWorkoutModal" tabindex="-1" aria-labelledby="addWorkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="workoutForm" method="POST" action="main.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWorkoutModalLabel">Adicionar ao treino</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="workoutDayId" class="form-label">Dia de treino</label>
                            <select class="form-select" id="workoutDayId" name="workout_day_id" required>
                                <option value="">Seleciona um dia</option>
                                <?php foreach ($dayOptions as $dayOption): ?>
                                    <option value="<?php echo (int)$dayOption['id']; ?>">
                                        <?php echo htmlspecialchars($dayOption['day_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($dayOptions) === 0): ?>
                                <div class="form-text text-danger">Cria um dia primeiro para poderes adicionar exercicios.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de treino</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exercise_type" id="typeExercicio" value="exercicio" checked onchange="toggleAddFields(this.value)">
                                    <label class="form-check-label" for="typeExercicio">Exercício</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exercise_type" id="typeCorrida" value="corrida" onchange="toggleAddFields(this.value)">
                                    <label class="form-check-label" for="typeCorrida">Corrida</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="exerciseName" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="exerciseName" name="nome_exercicio" required>
                        </div>

                        <!-- Campos para Exercício -->
                        <div id="addFieldsExercicio">
                            <div class="row g-3">
                                <div class="col-4">
                                    <label for="exerciseReps" class="form-label">Reps</label>
                                    <input type="text" class="form-control" id="exerciseReps" name="reps" placeholder="Ex: 10 ou até à falha">
                                </div>
                                <div class="col-4">
                                    <label for="exerciseSets" class="form-label">Sets</label>
                                    <input type="number" min="1" class="form-control" id="exerciseSets" name="num_sets">
                                </div>
                                <div class="col-4">
                                    <label for="exerciseKg" class="form-label">Kg</label>
                                    <input type="number" min="0" step="0.5" class="form-control" id="exerciseKg" name="kg" placeholder="Opcional">
                                </div>
                            </div>
                        </div>

                        <!-- Campos para Corrida -->
                        <div id="addFieldsCorrida" style="display:none;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="exerciseTempo" class="form-label">Tempo (min)</label>
                                    <input type="number" min="1" class="form-control" id="exerciseTempo" name="tempo_minutos" placeholder="Ex: 30">
                                </div>
                                <div class="col-6">
                                    <label for="exerciseDistancia" class="form-label">Distância (m)</label>
                                    <input type="number" min="1" class="form-control" id="exerciseDistancia" name="distancia_metros" placeholder="Ex: 5000">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" name="btn-guardar-workout">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Exemplos -->
    <div class="modal fade" id="exampleTemplatesModal" tabindex="-1" aria-labelledby="exampleTemplatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleTemplatesModalLabel">Exemplos de dias de treino</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Clica no <strong>+</strong> para adicionar um exemplo ao teu plano. O campo de Kg fica vazio para preencheres depois.</p>
                    <?php foreach ($exampleTemplates as $templateKey => $templateData): ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <h3 class="h6 mb-2"><?php echo htmlspecialchars((string)$templateData['day_name']); ?></h3>
                                    <ul class="mb-0 small">
                                        <?php foreach ($templateData['exercises'] as $exercisePreview): ?>
                                            <li><?php echo htmlspecialchars((string)$exercisePreview['nome']) . ' - ' . (int)$exercisePreview['sets'] . ' x ' . htmlspecialchars((string)$exercisePreview['reps']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <form method="POST" action="main.php">
                                    <input type="hidden" name="template_key" value="<?php echo htmlspecialchars((string)$templateKey); ?>">
                                    <button type="submit" class="btn btn-success btn-sm" name="btn-add-example-template" title="Adicionar exemplo">+</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Editar exercício -->
    <div class="modal fade" id="editWorkoutModal" tabindex="-1" aria-labelledby="editWorkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="main.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editWorkoutModalLabel">Editar exercicio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="exercise_id" id="editExerciseId">

                        <div class="mb-3">
                            <label class="form-label">Tipo de treino</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exercise_type" id="editTypeExercicio" value="exercicio" checked onchange="toggleEditFields(this.value)">
                                    <label class="form-check-label" for="editTypeExercicio">Exercício</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="exercise_type" id="editTypeCorrida" value="corrida" onchange="toggleEditFields(this.value)">
                                    <label class="form-check-label" for="editTypeCorrida">Corrida</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editExerciseName" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="editExerciseName" name="nome_exercicio" required>
                        </div>

                        <!-- Campos para Exercício -->
                        <div id="editFieldsExercicio">
                            <div class="row g-3">
                                <div class="col-4">
                                    <label for="editExerciseReps" class="form-label">Reps</label>
                                    <input type="text" class="form-control" id="editExerciseReps" name="reps" placeholder="Ex: 10 ou até à falha">
                                </div>
                                <div class="col-4">
                                    <label for="editExerciseSets" class="form-label">Sets</label>
                                    <input type="number" min="1" class="form-control" id="editExerciseSets" name="num_sets">
                                </div>
                                <div class="col-4">
                                    <label for="editExerciseKg" class="form-label">Kg</label>
                                    <input type="number" min="0" step="0.5" class="form-control" id="editExerciseKg" name="kg" placeholder="Opcional">
                                </div>
                            </div>
                        </div>

                        <!-- Campos para Corrida -->
                        <div id="editFieldsCorrida" style="display:none;">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="editExerciseTempo" class="form-label">Tempo (min)</label>
                                    <input type="number" min="1" class="form-control" id="editExerciseTempo" name="tempo_minutos">
                                </div>
                                <div class="col-6">
                                    <label for="editExerciseDistancia" class="form-label">Distância (m)</label>
                                    <input type="number" min="1" class="form-control" id="editExerciseDistancia" name="distancia_metros">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" name="btn-update-exercise">Guardar alteracoes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAddFields(type) {
            document.getElementById('addFieldsExercicio').style.display = type === 'corrida' ? 'none' : '';
            document.getElementById('addFieldsCorrida').style.display = type === 'corrida' ? '' : 'none';

            const repsEl = document.getElementById('exerciseReps');
            const setsEl = document.getElementById('exerciseSets');
            const tempoEl = document.getElementById('exerciseTempo');
            const distEl = document.getElementById('exerciseDistancia');

            if (type === 'corrida') {
                repsEl.removeAttribute('required');
                setsEl.removeAttribute('required');
                tempoEl.setAttribute('required', '');
                distEl.setAttribute('required', '');
            } else {
                repsEl.setAttribute('required', '');
                setsEl.setAttribute('required', '');
                tempoEl.removeAttribute('required');
                distEl.removeAttribute('required');
            }
        }

        function toggleEditFields(type) {
            document.getElementById('editFieldsExercicio').style.display = type === 'corrida' ? 'none' : '';
            document.getElementById('editFieldsCorrida').style.display = type === 'corrida' ? '' : 'none';

            const repsEl = document.getElementById('editExerciseReps');
            const setsEl = document.getElementById('editExerciseSets');
            const tempoEl = document.getElementById('editExerciseTempo');
            const distEl = document.getElementById('editExerciseDistancia');

            if (type === 'corrida') {
                repsEl.removeAttribute('required');
                setsEl.removeAttribute('required');
                tempoEl.setAttribute('required', '');
                distEl.setAttribute('required', '');
            } else {
                repsEl.setAttribute('required', '');
                setsEl.setAttribute('required', '');
                tempoEl.removeAttribute('required');
                distEl.removeAttribute('required');
            }
        }

        // Inicializa required no modal de adicionar
        toggleAddFields('exercicio');

        const editWorkoutModal = document.getElementById('editWorkoutModal');
        if (editWorkoutModal) {
            editWorkoutModal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                if (!btn) return;

                const type = btn.getAttribute('data-exercise-type') || 'exercicio';

                document.getElementById('editExerciseId').value = btn.getAttribute('data-exercise-id') || '';
                document.getElementById('editExerciseName').value = btn.getAttribute('data-exercise-name') || '';
                document.getElementById('editExerciseReps').value = btn.getAttribute('data-exercise-reps') || '';
                document.getElementById('editExerciseSets').value = btn.getAttribute('data-exercise-sets') || '';
                document.getElementById('editExerciseKg').value = btn.getAttribute('data-exercise-kg') || '';
                document.getElementById('editExerciseTempo').value = btn.getAttribute('data-exercise-tempo') || '';
                document.getElementById('editExerciseDistancia').value = btn.getAttribute('data-exercise-distancia') || '';

                document.getElementById('editTypeExercicio').checked = type !== 'corrida';
                document.getElementById('editTypeCorrida').checked = type === 'corrida';
                toggleEditFields(type);
            });
        }
    </script>
    <script src="page-transitions.js" defer></script>
</body>
</html>
