<?php
// public_html/pages/meal_categories.php
require_login();
require APP_PUBLIC . '/includes/db.php';

$user = current_user();
$errors = [];
$success = flash('success');
$name = '';
$reminderEnabledInput = false;
$reminderTimeInput = '';
$reminderDaysInput = [];

$dayOptions = [
    1 => t('Пн'),
    2 => t('Вт'),
    3 => t('Ср'),
    4 => t('Чт'),
    5 => t('Пт'),
    6 => t('Сб'),
    0 => t('Нд'),
];

$normalizeTime = function (?string $value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^([01]\\d|2[0-3]):([0-5]\\d)(?::([0-5]\\d))?$/', $value, $matches)) {
        return null;
    }
    return sprintf('%02d:%02d:00', (int)$matches[1], (int)$matches[2]);
};

$sanitizeDays = function ($values) use ($dayOptions): array {
    if (!is_array($values)) {
        return [];
    }
    $available = array_keys($dayOptions);
    $selected = [];
    foreach ($values as $value) {
        $day = (int)$value;
        if (in_array($day, $available, true)) {
            $selected[$day] = true;
        }
    }
    $ordered = [];
    foreach ($available as $day) {
        if (isset($selected[$day])) {
            $ordered[] = $day;
        }
    }
    return $ordered;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$reminderDaysInput) {
    $reminderDaysInput = array_keys($dayOptions);
}

$allDaysSelected = count($reminderDaysInput) === count($dayOptions);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = t('Некоректна категорія.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM meal_categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            if ($stmt->rowCount() === 0) {
                $errors[] = t('Категорію не знайдено.');
            } else {
                flash('success', t('Категорію видалено.'));
                redirect('/meal_categories');
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $reminderEnabledInput = isset($_POST['reminder_enabled']);
        $reminderTimeInput = trim($_POST['reminder_time'] ?? '');
        $reminderDaysInput = $sanitizeDays($_POST['reminder_days'] ?? []);
        $reminderTimeValue = $reminderEnabledInput ? $normalizeTime($reminderTimeInput) : null;
        $reminderDaysValue = $reminderEnabledInput ? implode(',', $reminderDaysInput) : null;

        if ($name === '') {
            $errors[] = t('Введіть назву категорії.');
        } elseif ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
            $errors[] = t('Назва занадто довга (до 100 символів).');
        }

        if ($reminderEnabledInput) {
            if ($reminderTimeValue === null) {
                $errors[] = t('Час нагадування обов’язковий.');
            }
            if (!$reminderDaysInput) {
                $errors[] = t('Оберіть хоча б один день нагадування.');
            }
        }

        if ($action === 'update' && $id <= 0) {
            $errors[] = t('Некоректна категорія.');
        }

        if (!$errors) {
            try {
                if ($action === 'update') {
                    $stmt = $pdo->prepare(
                        'UPDATE meal_categories
                         SET name = ?, reminder_enabled = ?, reminder_time = ?, reminder_days = ?
                         WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([
                        $name,
                        $reminderEnabledInput ? 1 : 0,
                        $reminderTimeValue,
                        $reminderDaysValue,
                        $id,
                        $user['id'],
                    ]);
                    if ($stmt->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT id FROM meal_categories WHERE id = ? AND user_id = ?');
                        $check->execute([$id, $user['id']]);
                        if (!$check->fetch()) {
                            $errors[] = t('Категорію не знайдено.');
                        } else {
                            flash('success', t('Категорію оновлено.'));
                            redirect('/meal_categories');
                        }
                    } else {
                        flash('success', t('Категорію оновлено.'));
                        redirect('/meal_categories');
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO meal_categories (user_id, name, reminder_enabled, reminder_time, reminder_days)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $user['id'],
                        $name,
                        $reminderEnabledInput ? 1 : 0,
                        $reminderTimeValue,
                        $reminderDaysValue,
                    ]);
                    flash('success', t('Категорію додано.'));
                    redirect('/meal_categories');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = t('Така категорія вже існує.');
                } else {
                    throw $e;
                }
            }
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT id, name, reminder_enabled, reminder_time, reminder_days
     FROM meal_categories
     WHERE user_id = ?
     ORDER BY name'
);
$stmt->execute([$user['id']]);
$categories = $stmt->fetchAll();

$formatReminderDays = function (?string $days) use ($dayOptions): string {
    if ($days === null || trim($days) === '') {
        return t('Щодня');
    }
    $parts = array_filter(array_map('trim', explode(',', $days)), 'strlen');
    $labels = [];
    foreach ($parts as $part) {
        $day = (int)$part;
        if (isset($dayOptions[$day])) {
            $labels[] = $dayOptions[$day];
        }
    }
    return $labels ? implode(', ', $labels) : t('Щодня');
};
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Категорії прийомів їжі')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Категорії прийомів їжі')); ?></h1>
                <?php if ($success): ?>
                    <div class="notice success"><?php echo h($success); ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="notice error">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo h($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/meal_categories" id="create-category-form" class="meal-form">
                    <input type="hidden" name="action" value="create">
                    <div class="field">
                        <label for="name"><?php echo h(t('Назва категорії')); ?></label>
                        <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
                    </div>
                    <div class="field">
                        <label class="check-control">
                            <input
                                type="checkbox"
                                id="reminder_enabled"
                                name="reminder_enabled"
                                value="1"
                                class="check-input"
                                <?php echo $reminderEnabledInput ? "checked" : ""; ?>
                            >
                            <span class="check-mark" aria-hidden="true"></span>
                            <span class="check-text"><?php echo h(t('Увімкнути нагадування')); ?></span>
                        </label>
                    </div>
                    <div class="field">
                        <label for="reminder_time"><?php echo h(t('Час нагадування')); ?></label>
                        <input type="time" id="reminder_time" name="reminder_time" value="<?php echo h($reminderTimeInput); ?>">
                    </div>
                    <div class="field">
                        <div class="field-label"><?php echo h(t('Дні нагадування')); ?></div>
                        <div class="days-toolbar">
                            <label class="check-control">
                                <input
                                    type="checkbox"
                                    id="reminder_days_all"
                                    class="check-input"
                                    <?php echo $allDaysSelected ? "checked" : ""; ?>
                                >
                                <span class="check-mark" aria-hidden="true"></span>
                                <span class="check-text"><?php echo h(t('Щодня')); ?></span>
                            </label>
                        </div>
                        <div class="days-grid">
                            <?php foreach ($dayOptions as $dayValue => $dayLabel): ?>
                                <label class="check-control">
                                    <input
                                        type="checkbox"
                                        name="reminder_days[]"
                                        value="<?php echo h((string)$dayValue); ?>"
                                        class="check-input"
                                        <?php echo in_array($dayValue, $reminderDaysInput, true) ? "checked" : ""; ?>
                                    >
                                    <span class="check-mark" aria-hidden="true"></span>
                                    <span class="check-text"><?php echo h($dayLabel); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit"><?php echo h(t('Додати')); ?></button>
                </form>

                <h2><?php echo h(t('Список категорій')); ?></h2>
                <?php if ($categories): ?>
                    <div class="menu-cards">
                        <?php foreach ($categories as $category): ?>
                            <div class="menu-card">
                                <div class="menu-card-title"><?php echo h($category['name']); ?></div>
                                <?php if ((int)$category['reminder_enabled'] === 1): ?>
                                    <div class="menu-card-text">
                                        <?php echo h(t('Нагадування:')); ?> <?php echo h($category['reminder_time'] ? substr($category['reminder_time'], 0, 5) : ''); ?>
                                        <?php echo $category['reminder_days'] ? ' - ' . h($formatReminderDays($category['reminder_days'])) : ''; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="food-actions">
                                    <button
                                        class="btn secondary small edit-category"
                                        type="button"
                                        data-id="<?php echo h((string)$category['id']); ?>"
                                        data-name="<?php echo h($category['name']); ?>"
                                        data-reminder-enabled="<?php echo h((string)$category['reminder_enabled']); ?>"
                                        data-reminder-time="<?php echo h($category['reminder_time'] ? substr($category['reminder_time'], 0, 5) : ''); ?>"
                                        data-reminder-days="<?php echo h($category['reminder_days'] ?? ''); ?>"
                                    ><?php echo h(t('Редагувати')); ?></button>
                                    <form method="post" action="/meal_categories">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h((string)$category['id']); ?>">
                                        <button class="btn danger small" type="submit"><?php echo h(t('Видалити')); ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php echo h(t('Поки немає категорій.')); ?></p>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <div class="container">
                <?php echo h(t('Diet Organizer • простий спосіб вести щоденник харчування і тримати форму')); ?>
            </div>
        </footer>
    </div>

    <div class="modal" id="edit-category-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><?php echo h(t('Редагувати категорію')); ?></div>
                <button class="modal-close" type="button" aria-label="<?php echo h(t('Закрити')); ?>">&times;</button>
            </div>
            <form method="post" action="/meal_categories" id="edit-category-form" class="meal-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-category-id">
                <div class="field">
                    <label for="edit-category-name"><?php echo h(t('Назва категорії')); ?></label>
                    <input type="text" id="edit-category-name" name="name" required>
                </div>
                <div class="field">
                    <label class="check-control">
                        <input
                            type="checkbox"
                            id="edit-reminder-enabled"
                            name="reminder_enabled"
                            value="1"
                            class="check-input"
                        >
                        <span class="check-mark" aria-hidden="true"></span>
                        <span class="check-text"><?php echo h(t('Увімкнути нагадування')); ?></span>
                    </label>
                </div>
                <div class="field">
                    <label for="edit-reminder-time"><?php echo h(t('Час нагадування')); ?></label>
                    <input type="time" id="edit-reminder-time" name="reminder_time">
                </div>
                <div class="field">
                    <div class="field-label"><?php echo h(t('Дні нагадування')); ?></div>
                    <div class="days-toolbar">
                        <label class="check-control">
                            <input
                                type="checkbox"
                                id="edit-reminder-days-all"
                                class="check-input"
                            >
                            <span class="check-mark" aria-hidden="true"></span>
                            <span class="check-text"><?php echo h(t('Щодня')); ?></span>
                        </label>
                    </div>
                    <div class="days-grid">
                        <?php foreach ($dayOptions as $dayValue => $dayLabel): ?>
                            <label class="check-control">
                                <input
                                    type="checkbox"
                                    name="reminder_days[]"
                                    value="<?php echo h((string)$dayValue); ?>"
                                    class="check-input"
                                >
                                <span class="check-mark" aria-hidden="true"></span>
                                <span class="check-text"><?php echo h($dayLabel); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit"><?php echo h(t('Зберегти')); ?></button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const btn = document.querySelector('.menu-toggle');
            const nav = document.querySelector('.nav-links');
            const closeBtn = document.querySelector('.menu-close');
            if (!btn || !nav) return;
            btn.addEventListener('click', function () {
                btn.classList.toggle('open');
                nav.classList.toggle('open');
            });
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    nav.classList.remove('open');
                    btn.classList.remove('open');
                });
            }
            nav.addEventListener('click', function (e) {
                if (e.target.tagName === 'A' && nav.classList.contains('open')) {
                    nav.classList.remove('open');
                    btn.classList.remove('open');
                }
            });

            const modal = document.querySelector('#edit-category-modal');
            const modalClose = modal ? modal.querySelector('.modal-close') : null;
            const editButtons = document.querySelectorAll('.edit-category');
            const editId = document.querySelector('#edit-category-id');
            const editName = document.querySelector('#edit-category-name');
            const createForm = document.querySelector('#create-category-form');
            const reminderEnabled = createForm ? createForm.querySelector('#reminder_enabled') : document.querySelector('#reminder_enabled');
            const reminderTime = createForm ? createForm.querySelector('#reminder_time') : document.querySelector('#reminder_time');
            const reminderDays = createForm ? createForm.querySelectorAll('input[name="reminder_days[]"]') : [];
            const reminderDaysAll = createForm ? createForm.querySelector('#reminder_days_all') : document.querySelector('#reminder_days_all');
            const editReminderEnabled = document.querySelector('#edit-reminder-enabled');
            const editReminderTime = document.querySelector('#edit-reminder-time');
            const editReminderDays = document.querySelectorAll('#edit-category-form input[name="reminder_days[]"]');
            const editReminderDaysAll = document.querySelector('#edit-reminder-days-all');

            function setAllDays(dayInputs, isChecked) {
                dayInputs.forEach(function (input) {
                    input.checked = isChecked;
                });
            }

            function updateAllDaysToggle(allToggle, dayInputs) {
                if (!allToggle) return;
                const allChecked = dayInputs.length > 0 && Array.from(dayInputs).every(function (input) {
                    return input.checked;
                });
                allToggle.checked = allChecked;
            }

            function ensureDaysSelected(dayInputs) {
                const anyChecked = Array.from(dayInputs).some(function (input) {
                    return input.checked;
                });
                if (!anyChecked) {
                    setAllDays(dayInputs, true);
                }
            }

            function bindAllDays(allToggle, dayInputs) {
                if (!allToggle) return;
                allToggle.addEventListener('change', function () {
                    setAllDays(dayInputs, allToggle.checked);
                });
                dayInputs.forEach(function (input) {
                    input.addEventListener('change', function () {
                        updateAllDaysToggle(allToggle, dayInputs);
                    });
                });
                updateAllDaysToggle(allToggle, dayInputs);
            }

            function syncReminderFields(toggle, timeInput, dayInputs, allToggle) {
                if (!toggle || !timeInput) return;
                const isEnabled = toggle.checked;
                timeInput.disabled = !isEnabled;
                dayInputs.forEach(function (input) {
                    input.disabled = !isEnabled;
                });
                if (allToggle) {
                    allToggle.disabled = !isEnabled;
                }
                if (isEnabled) {
                    ensureDaysSelected(dayInputs);
                    updateAllDaysToggle(allToggle, dayInputs);
                }
            }

            if (reminderEnabled) {
                syncReminderFields(reminderEnabled, reminderTime, reminderDays, reminderDaysAll);
                reminderEnabled.addEventListener('change', function () {
                    syncReminderFields(reminderEnabled, reminderTime, reminderDays, reminderDaysAll);
                });
            }
            if (editReminderEnabled) {
                editReminderEnabled.addEventListener('change', function () {
                    syncReminderFields(editReminderEnabled, editReminderTime, editReminderDays, editReminderDaysAll);
                });
            }

            bindAllDays(reminderDaysAll, reminderDays);
            bindAllDays(editReminderDaysAll, editReminderDays);

            function openModal() {
                if (!modal) return;
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            editButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    editId.value = button.dataset.id || '';
                    editName.value = button.dataset.name || '';
                    if (editReminderEnabled) {
                        editReminderEnabled.checked = button.dataset.reminderEnabled === '1';
                    }
                    if (editReminderTime) {
                        editReminderTime.value = button.dataset.reminderTime || '';
                    }
                    if (editReminderDays.length) {
                        const selectedDays = (button.dataset.reminderDays || '')
                            .split(',')
                            .map(function (value) { return value.trim(); })
                            .filter(Boolean);
                        if (selectedDays.length === 0) {
                            setAllDays(editReminderDays, true);
                        } else {
                            editReminderDays.forEach(function (checkbox) {
                                checkbox.checked = selectedDays.includes(checkbox.value);
                            });
                        }
                    }
                    updateAllDaysToggle(editReminderDaysAll, editReminderDays);
                    syncReminderFields(editReminderEnabled, editReminderTime, editReminderDays, editReminderDaysAll);
                    openModal();
                });
            });

            if (modalClose) {
                modalClose.addEventListener('click', closeModal);
            }
            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }
        })();
    </script>
    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
