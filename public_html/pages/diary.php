<?php
// public_html/pages/diary.php
require_login();
require APP_PUBLIC . '/includes/db.php';

$user = current_user();
$errors = [];
$success = flash('success');
$foodIdInput = '';
$categoryIdInput = '';
$dateInput = date('Y-m-d');
$quantityInput = '';
$notesInput = '';
$filterDate = trim($_GET['date'] ?? '');
$editId = 0;

$normalizeDecimal = function (string $value): ?float {
    $value = trim(str_replace(',', '.', $value));
    if ($value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (float) $value;
};

$formatDecimal = function ($value): string {
    $text = number_format((float) $value, 2, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text === '' ? '0' : $text;
};

if ($filterDate !== '') {
    $dateInput = $filterDate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Некоректний запис.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM diary_entries WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $user['id']]);
            if ($stmt->rowCount() === 0) {
                $errors[] = 'Запис не знайдено.';
            } else {
                flash('success', 'Запис видалено.');
                redirect('/diary' . ($filterDate !== '' ? '?date=' . urlencode($filterDate) : ''));
            }
        }
    } else {
        $foodIdInput = $_POST['food_id'] ?? '';
        $categoryIdInput = $_POST['meal_category_id'] ?? '';
        $dateInput = trim($_POST['entry_date'] ?? '');
        $quantityInput = $_POST['quantity_grams'] ?? '';
        $notesInput = trim($_POST['notes'] ?? '');
        $quantity = $normalizeDecimal($quantityInput);
        $foodId = (int) $foodIdInput;
        $categoryId = (int) $categoryIdInput;

        if ($foodId <= 0) {
            $errors[] = 'Оберіть продукт.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Оберіть категорію.';
        }
        if ($dateInput === '') {
            $errors[] = 'Оберіть дату.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $dateInput)) {
            $errors[] = 'Некоректна дата.';
        }
        if ($quantity === null || $quantity <= 0) {
            $errors[] = 'Маса має бути числом більше 0.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM foods WHERE id = ? AND user_id = ?');
            $stmt->execute([$foodId, $user['id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Продукт не знайдено.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM meal_categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$categoryId, $user['id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Категорію не знайдено.';
            }
        }

        if ($action === 'update') {
            $editId = (int) ($_POST['id'] ?? 0);
            if ($editId <= 0) {
                $errors[] = 'Некоректний запис.';
            }
        }

        if (!$errors) {
            if ($action === 'update') {
                $stmt = $pdo->prepare(
                    'UPDATE diary_entries
                     SET food_id = ?, meal_category_id = ?, entry_date = ?, quantity_grams = ?, notes = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([
                    $foodId,
                    $categoryId,
                    $dateInput,
                    round($quantity, 2),
                    $notesInput,
                    $editId,
                    $user['id'],
                ]);
                if ($stmt->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM diary_entries WHERE id = ? AND user_id = ?');
                    $check->execute([$editId, $user['id']]);
                    if (!$check->fetch()) {
                        $errors[] = 'Запис не знайдено.';
                    } else {
                        flash('success', 'Запис оновлено.');
                        redirect('/diary' . ($filterDate !== '' ? '?date=' . urlencode($filterDate) : ''));
                    }
                } else {
                    flash('success', 'Запис оновлено.');
                    redirect('/diary' . ($filterDate !== '' ? '?date=' . urlencode($filterDate) : ''));
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO diary_entries (user_id, food_id, meal_category_id, entry_date, quantity_grams, notes)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $user['id'],
                    $foodId,
                    $categoryId,
                    $dateInput,
                    round($quantity, 2),
                    $notesInput,
                ]);
                flash('success', 'Запис додано.');
                redirect('/diary' . ($filterDate !== '' ? '?date=' . urlencode($filterDate) : ''));
            }
        }
    }
}

$foodsStmt = $pdo->prepare('SELECT id, name, calories_per_100g FROM foods WHERE user_id = ? ORDER BY name');
$foodsStmt->execute([$user['id']]);
$foods = $foodsStmt->fetchAll();
$popularFoodsStmt = $pdo->prepare(
    'SELECT f.id, f.name, f.calories_per_100g, COUNT(*) AS total_entries
     FROM diary_entries d
     JOIN foods f ON f.id = d.food_id
     WHERE d.user_id = ? AND f.user_id = ?
     GROUP BY f.id, f.name, f.calories_per_100g
     ORDER BY total_entries DESC, f.name ASC
     LIMIT 6'
);
$popularFoodsStmt->execute([$user['id'], $user['id']]);
$popularFoods = $popularFoodsStmt->fetchAll();
$popularFoodIds = [];
foreach ($popularFoods as $food) {
    $popularFoodIds[(string) $food['id']] = true;
}
$otherFoods = [];
foreach ($foods as $food) {
    if (!isset($popularFoodIds[(string) $food['id']])) {
        $otherFoods[] = $food;
    }
}
$foodsById = [];
foreach ($foods as $food) {
    $foodsById[(string) $food['id']] = $food['name'];
}
$foodNameInput = $foodIdInput !== '' && isset($foodsById[$foodIdInput])
    ? $foodsById[$foodIdInput]
    : '';

$categoriesStmt = $pdo->prepare('SELECT id, name FROM meal_categories WHERE user_id = ? ORDER BY name');
$categoriesStmt->execute([$user['id']]);
$categories = $categoriesStmt->fetchAll();

$sql = 'SELECT d.id, d.entry_date, d.quantity_grams, d.notes, d.food_id, d.meal_category_id,
               f.name AS food_name, f.calories_per_100g, c.name AS category_name
        FROM diary_entries d
        JOIN foods f ON f.id = d.food_id
        JOIN meal_categories c ON c.id = d.meal_category_id
        WHERE d.user_id = ?';
$params = [$user['id']];
if ($filterDate !== '' && DateTime::createFromFormat('Y-m-d', $filterDate)) {
    $sql .= ' AND d.entry_date = ?';
    $params[] = $filterDate;
}
$sql .= ' ORDER BY d.entry_date DESC, d.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$groupedEntries = [];
foreach ($entries as $entry) {
    $entryCalories = ((float) $entry['quantity_grams']) * ((float) $entry['calories_per_100g']) / 100;
    $entry['entry_calories'] = $entryCalories;
    $categoryId = $entry['meal_category_id'];
    if (!isset($groupedEntries[$categoryId])) {
        $groupedEntries[$categoryId] = [
            'name' => $entry['category_name'],
            'total_calories' => 0.0,
            'entries' => [],
        ];
    }
    $groupedEntries[$categoryId]['entries'][] = $entry;
    $groupedEntries[$categoryId]['total_calories'] += $entryCalories;
}
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Щоденник харчування</title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>

<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1>Щоденник харчування</h1>

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

                <form method="get" action="/diary" class="search-form">
                    <div>
                        <label for="date">Фільтр за датою</label>
                        <input type="date" id="date" name="date" value="<?php echo h($filterDate); ?>">
                    </div>
                    <button type="submit">Показати</button>
                    <?php if ($filterDate !== ''): ?>
                        <a class="btn secondary small" href="/diary">Скинути</a>
                    <?php endif; ?>
                </form>

                <h2>Додати запис</h2>
                <form method="post" action="/diary">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label for="entry_date">Дата</label>
                        <input type="date" id="entry_date" name="entry_date" value="<?php echo h($dateInput); ?>"
                            required>
                    </div>
                    <div class="food-picker">
                        <label for="food_search">Продукт</label>
                        <input type="text" id="food_search" name="food_search" value="<?php echo h($foodNameInput); ?>"
                            placeholder="Почніть вводити назву" autocomplete="off" required>
                        <input type="hidden" id="food_id" name="food_id" value="<?php echo h($foodIdInput); ?>">
                        <div class="food-options" id="food-options">
                            <?php if ($popularFoods): ?>
                                <div class="food-options-title">Часто обирають</div>
                                <?php foreach ($popularFoods as $food): ?>
                                    <button type="button" class="food-option" data-id="<?php echo h((string) $food['id']); ?>"
                                        data-name="<?php echo h($food['name']); ?>">
                                        <div class="menu-card-title"><?php echo h($food['name']); ?></div>
                                        <div class="menu-card-text">
                                            Ккал/100 г: <?php echo h($formatDecimal($food['calories_per_100g'])); ?>
                                        </div>
                                    </button>
                                <?php endforeach; ?>
                                <div class="food-options-divider"></div>
                            <?php endif; ?>
                            <?php foreach ($otherFoods as $food): ?>
                                <button type="button" class="food-option" data-id="<?php echo h((string) $food['id']); ?>"
                                    data-name="<?php echo h($food['name']); ?>">
                                    <div class="menu-card-title"><?php echo h($food['name']); ?></div>
                                    <div class="menu-card-text">
                                        Ккал/100 г: <?php echo h($formatDecimal($food['calories_per_100g'])); ?>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label for="meal_category_id">Категорія</label>
                        <select id="meal_category_id" name="meal_category_id" required>
                            <option value="">Оберіть категорію</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo h((string) $category['id']); ?>">
                                    <?php echo h($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="quantity_grams">Маса (г)</label>
                        <input type="number" id="quantity_grams" name="quantity_grams" min="0.01" step="0.01"
                            value="<?php echo h($quantityInput); ?>" required>
                    </div>
                    <div>
                        <label for="notes">Нотатки</label>
                        <textarea id="notes" name="notes" rows="3"><?php echo h($notesInput); ?></textarea>
                    </div>
                    <button type="submit">Додати</button>
                </form>

                <h2>Записи за категоріями</h2>
                <?php if ($groupedEntries): ?>
                    <?php foreach ($groupedEntries as $group): ?>
                        <div class="category-group">
                            <div class="category-header">
                                <div class="category-title"><?php echo h($group['name']); ?></div>
                                <div class="category-total">Разом ккал:
                                    <?php echo h($formatDecimal($group['total_calories'])); ?></div>
                            </div>
                            <div class="menu-cards">
                                <?php foreach ($group['entries'] as $entry): ?>
                                    <div class="menu-card">
                                        <div class="menu-card-title"><?php echo h($entry['food_name']); ?></div>
                                        <div class="menu-card-text">
                                            Маса: <?php echo h($formatDecimal($entry['quantity_grams'])); ?> г,
                                            Ккал: <?php echo h($formatDecimal($entry['entry_calories'])); ?>,
                                            Дата: <?php echo h($entry['entry_date']); ?>
                                        </div>
                                        <?php if (!empty($entry['notes'])): ?>
                                            <div class="menu-card-text">Нотатки: <?php echo h($entry['notes']); ?></div>
                                        <?php endif; ?>
                                        <div class="food-actions">
                                            <button class="btn secondary small edit-entry" type="button"
                                                data-id="<?php echo h((string) $entry['id']); ?>"
                                                data-food="<?php echo h((string) $entry['food_id']); ?>"
                                                data-category="<?php echo h((string) $entry['meal_category_id']); ?>"
                                                data-date="<?php echo h($entry['entry_date']); ?>"
                                                data-quantity="<?php echo h($formatDecimal($entry['quantity_grams'])); ?>"
                                                data-notes="<?php echo h($entry['notes'] ?? ''); ?>">Редагувати</button>
                                            <form method="post" action="/diary">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo h((string) $entry['id']); ?>">
                                                <button class="btn danger small" type="submit">Видалити</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Поки немає записів.</p>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <div class="container">
                Diet Organizer • простий спосіб вести щоденник харчування і тримати форму
            </div>
        </footer>
    </div>

    <div class="modal" id="edit-entry-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Редагувати запис</div>
                <button class="modal-close" type="button" aria-label="Закрити">&times;</button>
            </div>
            <form method="post" action="/diary" id="edit-entry-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-entry-id">
                <div>
                    <label for="edit-entry-date">Дата</label>
                    <input type="date" id="edit-entry-date" name="entry_date" required>
                </div>
                <div>
                    <label for="edit-entry-food">Продукт</label>
                    <select id="edit-entry-food" name="food_id" required>
                        <option value="">Оберіть продукт</option>
                        <?php foreach ($foods as $food): ?>
                            <option value="<?php echo h((string) $food['id']); ?>"><?php echo h($food['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit-entry-category">Категорія</label>
                    <select id="edit-entry-category" name="meal_category_id" required>
                        <option value="">Оберіть категорію</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo h((string) $category['id']); ?>"><?php echo h($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit-entry-quantity">Маса (г)</label>
                    <input type="number" id="edit-entry-quantity" name="quantity_grams" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label for="edit-entry-notes">Нотатки</label>
                    <textarea id="edit-entry-notes" name="notes" rows="3"></textarea>
                </div>
                <button type="submit">Зберегти</button>
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

            const foodInput = document.querySelector('#food_search');
            const foodIdInput = document.querySelector('#food_id');
            const foodOptions = document.querySelector('#food-options');
            const foodButtons = foodOptions ? Array.from(foodOptions.querySelectorAll('.food-option')) : [];

            function closeFoodOptions() {
                if (foodOptions) {
                    foodOptions.classList.remove('open');
                }
            }

            function filterFoodOptions() {
                if (!foodOptions) return;
                const query = foodInput.value.trim().toLowerCase();
                let hasVisible = false;
                foodButtons.forEach(function (button) {
                    const name = (button.dataset.name || '').toLowerCase();
                    const match = query === '' || name.includes(query);
                    button.style.display = match ? 'block' : 'none';
                    if (match) {
                        hasVisible = true;
                    }
                });
                foodOptions.classList.toggle('is-filtering', query !== '');
                foodOptions.classList.toggle('open', hasVisible);
            }

            if (foodInput && foodOptions && foodIdInput) {
                foodInput.addEventListener('input', function () {
                    foodIdInput.value = '';
                    filterFoodOptions();
                });
                foodInput.addEventListener('focus', filterFoodOptions);
                foodButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        foodInput.value = button.dataset.name || '';
                        foodIdInput.value = button.dataset.id || '';
                        closeFoodOptions();
                    });
                });
                document.addEventListener('click', function (event) {
                    if (event.target !== foodInput && !foodOptions.contains(event.target)) {
                        closeFoodOptions();
                    }
                });
            }

            const modal = document.querySelector('#edit-entry-modal');
            const modalClose = modal ? modal.querySelector('.modal-close') : null;
            const editButtons = document.querySelectorAll('.edit-entry');
            const editId = document.querySelector('#edit-entry-id');
            const editDate = document.querySelector('#edit-entry-date');
            const editFood = document.querySelector('#edit-entry-food');
            const editCategory = document.querySelector('#edit-entry-category');
            const editQuantity = document.querySelector('#edit-entry-quantity');
            const editNotes = document.querySelector('#edit-entry-notes');

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
                    editFood.value = button.dataset.food || '';
                    editCategory.value = button.dataset.category || '';
                    editDate.value = button.dataset.date || '';
                    editQuantity.value = button.dataset.quantity || '';
                    editNotes.value = button.dataset.notes || '';
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
