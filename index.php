<?php
// конфиг из докера
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'phpcalc';
$user = getenv('DB_USER') ?: 'phpcalc';
$pass = getenv('DB_PASS') ?: 'phpcalc_password';

$pdo = null;
$error = null;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function fmtTime($ts) {
  return date('d.m.y H:i', strtotime($ts));
}

try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  // таблиица
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS entries (
      id SERIAL PRIMARY KEY,
      value TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT NOW(),
      updated_at TIMESTAMP NOT NULL DEFAULT NOW()
    )
  ");


  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
      $val = trim((string)($_POST['new_value'] ?? ''));
      if ($val !== '') {
        $stmt = $pdo->prepare("INSERT INTO entries(value) VALUES(:v)");
        $stmt->execute([':v' => $val]);
      }
      header("Location: /");
      exit;
    }

    if ($action === 'update') {
      $id  = (int)($_POST['id'] ?? 0);
      $val = trim((string)($_POST['edit_value'] ?? ''));
      if ($id > 0 && $val !== '') {
        $stmt = $pdo->prepare("UPDATE entries SET value = :v, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':v' => $val, ':id' => $id]);
      }
      header("Location: /?selected_id=" . $id);
      exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM entries WHERE id = :id");
        $stmt->execute([':id' => $id]);
      }
      header("Location: /");
      exit;
    }
  }

} catch (Throwable $e) {
  $error = $e->getMessage();
}

// чтение
$selectedId = isset($_GET['selected_id']) ? (int)$_GET['selected_id'] : 0;
$rows = [];
$selectedRow = null;

if ($pdo) {
  $rows = $pdo->query("SELECT id, value, created_at, updated_at FROM entries ORDER BY id DESC LIMIT 200")
              ->fetchAll(PDO::FETCH_ASSOC);

  if ($selectedId > 0) {
    $stmt = $pdo->prepare("SELECT id, value, created_at, updated_at FROM entries WHERE id = :id");
    $stmt->execute([':id' => $selectedId]);
    $selectedRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>PHPCalc — Table CRUD (PHP + PostgreSQL)</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1>Таблица с функцией редактирования</h1>
        <div class="small"></div>
      </div>
    </div>

    <!-- ввод -->
    <div class="card">
      <form class="top-form" method="post">
        <input name="new_value" placeholder="Введите новую запись и нажмите Добавить" />
        <input type="hidden" name="action" value="add" />
        <button class="btn btn-primary" type="submit">Добавить</button>
      </form>

      <?php if ($error): ?>
        <div class="notice warn" style="margin-top:12px;">
          Ошибка подключения к БД: <?= h($error) ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid">
      <!-- таблица слева -->
      <div class="card">
        <h2>Таблица записей</h2>

        <div class="table-wrap">
          <table id="entriesTable">
            <thead>
              <tr>
                <th style="width:80px;">ID</th>
                <th>Value</th>
                <th style="width:190px;">Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <?php $isSel = ($selectedId > 0 && (int)$r['id'] === $selectedId); ?>
                <tr data-id="<?= (int)$r['id'] ?>" class="<?= $isSel ? 'selected' : '' ?>">
                  <td class="mono"><?= (int)$r['id'] ?></td>
                  <td><?= h($r['value']) ?></td>
                  <td class="dim"><?= fmtTime($r['updated_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="notice">
          Кликни по строке — она станет выбранной и откроется справа для редактирования/удаления.
        </div>
      </div>

      <!-- операции справа -->
      <div class="card">
        <h2>Операции</h2>

        <?php if (!$selectedRow): ?>
          <div class="notice">
            Запись не выбрана. Выбери строку в таблице слева.
          </div>
        <?php else: ?>
          <div class="notice">
            <div><b>ID:</b> <?= (int)$selectedRow['id'] ?></div>
            <div class="small" style="margin-top:6px;">
              Created: <?= fmtTime($selectedRow['created_at']) ?><br/>
              Updated: <?= fmtTime($selectedRow['updated_at']) ?>
            </div>
          </div>

          <!-- редактирование -->
          <form class="side-form" method="post" style="margin-top:12px;">
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="id" value="<?= (int)$selectedRow['id'] ?>" />

            <label>Редактировать значение</label>
            <textarea name="edit_value"><?= h($selectedRow['value']) ?></textarea>

            <div class="actions">
              <button class="btn btn-primary" type="submit">Сохранить</button>
              <a class="btn btn-ghost" href="/">Снять выбор</a>
            </div>
          </form>

          <!-- удаление -->
          <form method="post" style="margin-top:10px;" onsubmit="return confirm('Удалить запись ID <?= (int)$selectedRow['id'] ?>?');">
            <input type="hidden" name="action" value="delete" />
            <input type="hidden" name="id" value="<?= (int)$selectedRow['id'] ?>" />
            <button class="btn btn-danger" type="submit" style="width:100%;">Удалить</button>
          </form>
        <?php endif; ?>

        <div class="footer"></div>
      </div>
    </div>
  </div>

<script>
(() => {
  const table = document.getElementById('entriesTable');
  if (!table) return;

  table.addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-id]');
    if (!tr) return;
    const id = tr.getAttribute('data-id');
    window.location.href = '/?selected_id=' + encodeURIComponent(id);
  });
})();
</script>
</body>
</html>