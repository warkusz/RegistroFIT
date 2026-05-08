<?php
// -----------------------------------------------------------------------------
// calendar.php
// Calendário mensal dos treinos do utilizador:
// - agenda dias já criados;
// - marca treino como concluído;
// - remove marcações.
// -----------------------------------------------------------------------------
// Página protegida: exige sessão válida para mostrar o calendário pessoal.
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

$createDaysTableSql = "CREATE TABLE IF NOT EXISTS workout_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createDaysTableSql);

// Tabela que guarda as marcações no calendário (um dia de treino por data).
$createCalendarTableSql = "CREATE TABLE IF NOT EXISTS workout_calendar_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    workout_day_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendar_workout_day
        FOREIGN KEY (workout_day_id) REFERENCES workout_days(id)
        ON DELETE CASCADE,
    UNIQUE KEY unique_day_schedule (workout_day_id, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createCalendarTableSql);

$checkDaysUserId = $conn->query("SHOW COLUMNS FROM workout_days LIKE 'user_id'");
if ($checkDaysUserId instanceof mysqli_result) {
    $hasDaysUserId = $checkDaysUserId->num_rows > 0;
    $checkDaysUserId->free();
    if (!$hasDaysUserId) {
        $conn->query("ALTER TABLE workout_days ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
    }
}

$checkCalendarUserId = $conn->query("SHOW COLUMNS FROM workout_calendar_entries LIKE 'user_id'");
if ($checkCalendarUserId instanceof mysqli_result) {
    $hasCalendarUserId = $checkCalendarUserId->num_rows > 0;
    $checkCalendarUserId->free();
    if (!$hasCalendarUserId) {
        $conn->query("ALTER TABLE workout_calendar_entries ADD COLUMN user_id INT NOT NULL DEFAULT 0 AFTER id");
    }
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

$checkDoneColumn = $conn->query("SHOW COLUMNS FROM workout_calendar_entries LIKE 'is_done'");
if ($checkDoneColumn instanceof mysqli_result) {
    $hasDoneColumn = $checkDoneColumn->num_rows > 0;
    $checkDoneColumn->free();
    if (!$hasDoneColumn) {
        $conn->query("ALTER TABLE workout_calendar_entries ADD COLUMN is_done TINYINT(1) NOT NULL DEFAULT 0 AFTER scheduled_date");
    }
}

if (isset($_POST['btn-add-calendar-entry'])) {
    // Adiciona uma marcação ao calendário no mês/ano em contexto.
    $calendarWorkoutDayId = (int)($_POST['calendar_workout_day_id'] ?? 0);
    $scheduledDate = trim($_POST['scheduled_date'] ?? '');
    $redirectMonth = (int)($_POST['calendar_month'] ?? date('n'));
    $redirectYear = (int)($_POST['calendar_year'] ?? date('Y'));

    if ($calendarWorkoutDayId < 1 || $scheduledDate === '') {
        $erro = 'Seleciona um dia de treino e uma data para o calendario.';
    } else {
        $dateIsValid = DateTime::createFromFormat('Y-m-d', $scheduledDate) !== false;
        if (!$dateIsValid) {
            $erro = 'Data invalida para o calendario.';
        } else {
            $stmtCheckDayOwner = $conn->prepare("SELECT id FROM workout_days WHERE id = ? AND user_id = ? LIMIT 1");
            if ($stmtCheckDayOwner) {
                $stmtCheckDayOwner->bind_param("ii", $calendarWorkoutDayId, $userId);
                $stmtCheckDayOwner->execute();
                $dayResult = $stmtCheckDayOwner->get_result();
                $dayExistsForUser = $dayResult && $dayResult->num_rows > 0;
                $stmtCheckDayOwner->close();

                if (!$dayExistsForUser) {
                    $erro = 'Dia de treino invalido para este utilizador.';
                } else {
                    $stmtCalendarInsert = $conn->prepare("INSERT INTO workout_calendar_entries (user_id, workout_day_id, scheduled_date) VALUES (?, ?, ?)");
                    if ($stmtCalendarInsert) {
                        $stmtCalendarInsert->bind_param("iis", $userId, $calendarWorkoutDayId, $scheduledDate);
                        if ($stmtCalendarInsert->execute()) {
                            $stmtCalendarInsert->close();
                            header('Location: calendar.php?calendar_saved=1&month=' . $redirectMonth . '&year=' . $redirectYear);
                            exit;
                        }
                        $erro = 'Nao foi possivel guardar no calendario (talvez ja exista esse treino nessa data).';
                        $stmtCalendarInsert->close();
                    } else {
                        $erro = 'Erro ao preparar o registo no calendario.';
                    }
                }
            } else {
                $erro = 'Erro ao validar o dia de treino.';
            }
        }
    }
}

if (isset($_POST['btn-delete-calendar-entry'])) {
    // Remove marcação específica do utilizador autenticado.
    $calendarEntryId = (int)($_POST['calendar_entry_id'] ?? 0);
    $redirectMonth = (int)($_POST['calendar_month'] ?? date('n'));
    $redirectYear = (int)($_POST['calendar_year'] ?? date('Y'));

    if ($calendarEntryId > 0) {
        $stmtCalendarDelete = $conn->prepare("DELETE FROM workout_calendar_entries WHERE id = ? AND user_id = ?");
        if ($stmtCalendarDelete) {
            $stmtCalendarDelete->bind_param("ii", $calendarEntryId, $userId);
            $stmtCalendarDelete->execute();
            $stmtCalendarDelete->close();
        }
    }

    header('Location: calendar.php?calendar_deleted=1&month=' . $redirectMonth . '&year=' . $redirectYear);
    exit;
}

if (isset($_POST['btn-toggle-calendar-done'])) {
    // Alterna estado concluído/não concluído para controlo do progresso diário.
    $calendarEntryId = (int)($_POST['calendar_entry_id'] ?? 0);
    $newDoneValue = (int)($_POST['new_done_value'] ?? 0) === 1 ? 1 : 0;
    $redirectMonth = (int)($_POST['calendar_month'] ?? date('n'));
    $redirectYear = (int)($_POST['calendar_year'] ?? date('Y'));

    if ($calendarEntryId > 0) {
        $stmtToggleDone = $conn->prepare("UPDATE workout_calendar_entries SET is_done = ? WHERE id = ? AND user_id = ?");
        if ($stmtToggleDone) {
            $stmtToggleDone->bind_param("iii", $newDoneValue, $calendarEntryId, $userId);
            $stmtToggleDone->execute();
            $stmtToggleDone->close();
        }
    }

    header('Location: calendar.php?month=' . $redirectMonth . '&year=' . $redirectYear);
    exit;
}

$dayOptions = [];
$daysResultStmt = $conn->prepare("SELECT id, day_name FROM workout_days WHERE user_id = ? ORDER BY created_at DESC, id DESC");
if ($daysResultStmt) {
    $daysResultStmt->bind_param("i", $userId);
    $daysResultStmt->execute();
    $daysResult = $daysResultStmt->get_result();
    while ($dayRow = $daysResult->fetch_assoc()) {
        $dayOptions[] = $dayRow;
    }
    $daysResultStmt->close();
}

$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear = (int)($_GET['year'] ?? date('Y'));
// Sanitização simples para evitar meses/anos inválidos no URL.
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('n');
}
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}

$firstDayOfMonth = DateTime::createFromFormat('Y-n-j', $selectedYear . '-' . $selectedMonth . '-1');
$daysInMonth = (int)$firstDayOfMonth->format('t');
$startWeekday = (int)$firstDayOfMonth->format('N');
$monthLabel = $firstDayOfMonth->format('F Y');

$startDate = $firstDayOfMonth->format('Y-m-01');
$endDate = $firstDayOfMonth->format('Y-m-t');
$calendarMap = [];
// Carrega todos os registos do mês atual para desenhar a grelha.
$calendarResultStmt = $conn->prepare("
    SELECT c.id, c.scheduled_date, c.is_done, d.day_name
    FROM workout_calendar_entries c
    INNER JOIN workout_days d ON d.id = c.workout_day_id
    WHERE c.user_id = ? AND c.scheduled_date BETWEEN ? AND ?
    ORDER BY c.scheduled_date ASC, c.created_at ASC
");
if ($calendarResultStmt) {
    $calendarResultStmt->bind_param("iss", $userId, $startDate, $endDate);
    $calendarResultStmt->execute();
    $calendarResult = $calendarResultStmt->get_result();
    while ($calendarRow = $calendarResult->fetch_assoc()) {
        $dayNumber = (int)date('j', strtotime($calendarRow['scheduled_date']));
        if (!isset($calendarMap[$dayNumber])) {
            $calendarMap[$dayNumber] = [];
        }
        $calendarMap[$dayNumber][] = $calendarRow;
    }
    $calendarResultStmt->close();
}

$prevMonth = $selectedMonth - 1;
$prevYear = $selectedYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$nextMonth = $selectedMonth + 1;
$nextYear = $selectedYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Calendario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="/RegistroFIT-main/images/2307827.png" />
    <link rel="stylesheet" href="/RegistroFIT-main/style.css" />
</head>
<body>
    <main class="d-flex flex-nowrap vh-100">
        <!-- Sidebar de navegação da zona autenticada -->
        <div class="d-flex flex-column flex-shrink-0 p-3 text-bg-dark" style="width: 280px;">
            <a href="index.html" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                <svg class="bi pe-none" width="40" height="32"></svg>
                <img src="/RegistroFIT-main/images/2307827.png" alt="Workout Planner Logo" width="62" height="60" class="me-4" />
                <span class="fs-4">RegistoFIT</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="main.php" class="nav-link text-white">
                        Treinos
                    </a>
                </li>
                <li>
                    <a href="calendar.php" class="nav-link active" aria-current="page">
                        Calendário
                    </a>
                </li>
                <li>
                    <a href="perfil.php" class="nav-link text-white">
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

        <div class="flex-grow-1 p-4 overflow-y-auto">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h1 class="h4 mb-0">Calendario de Treinos</h1>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-success" href="calendar.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>">Mes anterior</a>
                            <a class="btn btn-sm btn-outline-success" href="calendar.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>">Proximo mes</a>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCalendarEntryModal">Adicionar ao calendario</button>
                        </div>
                    </div>
                    <p class="mb-3"><?php echo htmlspecialchars($monthLabel); ?></p>

                    <?php if (isset($_GET['calendar_saved'])): ?>
                        <div class="alert alert-success" role="alert">Treino adicionado ao calendario.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['calendar_deleted'])): ?>
                        <div class="alert alert-success" role="alert">Marcacao removida do calendario.</div>
                    <?php endif; ?>
                    <?php if ($erro !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($erro); ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <!-- Grelha mensal com os treinos agendados -->
                        <table class="table table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Seg</th><th>Ter</th><th>Qua</th><th>Qui</th><th>Sex</th><th>Sab</th><th>Dom</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $currentDay = 1;
                                for ($week = 0; $week < 6; $week++):
                                    if ($currentDay > $daysInMonth) {
                                        break;
                                    }
                                    $hasDayInWeek = false;
                                ?>
                                    <tr>
                                        <?php for ($weekday = 1; $weekday <= 7; $weekday++): ?>
                                            <?php
                                            $isBeforeStart = ($week === 0 && $weekday < $startWeekday);
                                            $isAfterMonth = $currentDay > $daysInMonth;
                                            if ($isBeforeStart || $isAfterMonth):
                                            ?>
                                                <td style="height: 150px;"></td>
                                            <?php else: ?>
                                                <?php $hasDayInWeek = true; ?>
                                                <td style="height: 120px; vertical-align: top;">
                                                    <div class="fw-bold"><?php echo $currentDay; ?></div>
                                                    <?php if (isset($calendarMap[$currentDay])): ?>
                                                        <?php foreach ($calendarMap[$currentDay] as $calendarItem): ?>
                                                            <div
                                                                class="small d-inline-flex align-items-center gap-2 mt-1 mb-1 px-1 py-0 rounded"
                                                                style="background: <?php echo (int)$calendarItem['is_done'] === 1 ? 'rgba(25, 135, 84, 0.28)' : 'rgba(200,16,46,0.20)'; ?>;"
                                                            >
                                                                <span>
                                                                    <?php echo htmlspecialchars($calendarItem['day_name']); ?>
                                                                    <?php if ((int)$calendarItem['is_done'] === 1): ?>
                                                                        <span class="text-success ms-1">Done</span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="d-flex gap-2">
                                                                    <form method="POST" action="calendar.php">
                                                                        <input type="hidden" name="calendar_entry_id" value="<?php echo (int)$calendarItem['id']; ?>">
                                                                        <input type="hidden" name="calendar_month" value="<?php echo $selectedMonth; ?>">
                                                                        <input type="hidden" name="calendar_year" value="<?php echo $selectedYear; ?>">
                                                                        <input type="hidden" name="new_done_value" value="<?php echo (int)$calendarItem['is_done'] === 1 ? 0 : 1; ?>">
                                                                        <button
                                                                            type="submit"
                                                                            class="btn btn-sm py-0 px-1 lh-sm <?php echo (int)$calendarItem['is_done'] === 1 ? 'btn-success' : 'btn-outline-light'; ?>"
                                                                            name="btn-toggle-calendar-done"
                                                                            title="Marcar como concluido"
                                                                        >✓</button>
                                                                    </form>
                                                                    <form method="POST" action="calendar.php">
                                                                        <input type="hidden" name="calendar_entry_id" value="<?php echo (int)$calendarItem['id']; ?>">
                                                                        <input type="hidden" name="calendar_month" value="<?php echo $selectedMonth; ?>">
                                                                        <input type="hidden" name="calendar_year" value="<?php echo $selectedYear; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1 lh-sm" name="btn-delete-calendar-entry" title="Remover">x</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <?php $currentDay++; ?>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </tr>
                                <?php
                                    if ($currentDay > $daysInMonth && !$hasDayInWeek) {
                                        break;
                                    }
                                endfor;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addCalendarEntryModal" tabindex="-1" aria-labelledby="addCalendarEntryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="calendar.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCalendarEntryModalLabel">Adicionar treino ao calendario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="calendar_month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="calendar_year" value="<?php echo $selectedYear; ?>">
                        <div class="mb-3">
                            <label for="calendarWorkoutDay" class="form-label">Dia de treino</label>
                            <select class="form-select" id="calendarWorkoutDay" name="calendar_workout_day_id" required>
                                <option value="">Seleciona um dia de treino</option>
                                <?php foreach ($dayOptions as $dayOption): ?>
                                    <option value="<?php echo (int)$dayOption['id']; ?>"><?php echo htmlspecialchars($dayOption['day_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="scheduledDate" class="form-label">Data</label>
                            <input type="date" class="form-control" id="scheduledDate" name="scheduled_date" required>
                        </div>
                        <?php if (count($dayOptions) === 0): ?>
                            <div class="form-text text-danger">Cria primeiro um dia de treino para o poderes colocar no calendario.</div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" name="btn-add-calendar-entry">Guardar no calendario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/RegistroFIT-main/page-transitions.js" defer></script>
</body>
</html>
