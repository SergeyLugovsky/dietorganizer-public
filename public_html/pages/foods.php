<?php
// public_html/pages/foods.php
require_login();
require APP_PUBLIC . '/includes/db.php';

$user = current_user();
$errors = [];
$success = flash('success');
$name = '';
$caloriesInput = '';
$seedCountry = '';
$search = trim($_GET['q'] ?? '');
$foodUploadsDir = APP_PUBLIC . '/uploads/foods';
$foodUploadsWebPath = '/uploads/foods';
$defaultFoodsByCountry = require APP_PUBLIC . '/includes/food_defaults.php';
$maxImageSize = 2 * 1024 * 1024;
$allowedImageMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

$ensureUploadDir = function () use ($foodUploadsDir, &$errors): bool {
    if (is_dir($foodUploadsDir)) {
        return true;
    }
    if (mkdir($foodUploadsDir, 0755, true) || is_dir($foodUploadsDir)) {
        return true;
    }
    $errors[] = t('Не вдалося створити папку для завантажень.');
    return false;
};

$saveUpload = function (array $file) use (
    $allowedImageMimes,
    $maxImageSize,
    $foodUploadsDir,
    $foodUploadsWebPath,
    $ensureUploadDir,
    &$errors,
    $user
): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = t('Не вдалося завантажити зображення.');
        return null;
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxImageSize) {
        $errors[] = t('Зображення занадто велике (макс. 2 МБ).');
        return null;
    }
    if (!$ensureUploadDir()) {
        return null;
    }
    $info = @getimagesize($file['tmp_name'] ?? '');
    if ($info === false || !isset($info['mime'])) {
        $errors[] = t('Некоректний файл зображення.');
        return null;
    }
    $mime = $info['mime'];
    if (!isset($allowedImageMimes[$mime])) {
        $errors[] = t('Непідтримуваний тип зображення. Дозволені JPG і PNG.');
        return null;
    }
    $filename = 'food_' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $allowedImageMimes[$mime];
    $targetPath = $foodUploadsDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[] = t('Не вдалося зберегти зображення.');
        return null;
    }
    return $foodUploadsWebPath . '/' . $filename;
};

$deleteImage = function (?string $imagePath) use ($foodUploadsDir, $foodUploadsWebPath): void {
    if (!$imagePath) {
        return;
    }
    $prefix = $foodUploadsWebPath . '/';
    if (strpos($imagePath, $prefix) !== 0) {
        return;
    }
    $basename = basename($imagePath);
    if ($basename === '') {
        return;
    }
    $fullPath = $foodUploadsDir . DIRECTORY_SEPARATOR . $basename;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
};

$normalizeDecimal = function (string $value): ?float {
    $value = trim(str_replace(',', '.', $value));
    if ($value === '') {
        return 0.0;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (float)$value;
};

$formatDecimal = function ($value): string {
    $text = number_format((float)$value, 2, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text === '' ? '0' : $text;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'seed_defaults') {
        $seedCountry = trim(strtolower($_POST['country'] ?? ''));
        $countryData = $defaultFoodsByCountry[$seedCountry] ?? null;
        $foodsToSeed = $countryData['foods'] ?? null;

        if (!$foodsToSeed) {
            $errors[] = t('Оберіть країну зі списку.');
        }

        if (!$errors) {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM foods WHERE user_id = ?');
            $countStmt->execute([$user['id']]);
            if ((int)$countStmt->fetchColumn() > 0) {
                $errors[] = t('Стандартні продукти можна додати лише в порожній список.');
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $seedStmt = $pdo->prepare(
                    'INSERT INTO foods (user_id, name, calories_per_100g, proteins_per_100g, fats_per_100g, carbs_per_100g)
                     VALUES (?, ?, ?, 0, 0, 0)'
                );
                foreach ($foodsToSeed as $food) {
                    $seedStmt->execute([
                        $user['id'],
                        $food['name'],
                        round((float)$food['calories_per_100g'], 2),
                    ]);
                }
                $pdo->commit();
                $countryLabel = $countryData['label'] ?? t('країни');
                flash('success', t('Додано стандартні продукти: :country.', ['country' => $countryLabel]));
                redirect('/foods');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = t('Некоректний продукт.');
        } else {
            $imageStmt = $pdo->prepare('SELECT image_path FROM foods WHERE id = ? AND user_id = ?');
            $imageStmt->execute([$id, $user['id']]);
            $foodRow = $imageStmt->fetch();
            if (!$foodRow) {
                $errors[] = t('Продукт не знайдено.');
            } else {
                $stmt = $pdo->prepare('DELETE FROM foods WHERE id = ? AND user_id = ?');
                $stmt->execute([$id, $user['id']]);
                if ($stmt->rowCount() === 0) {
                    $errors[] = t('Продукт не знайдено.');
                } else {
                    $deleteImage($foodRow['image_path'] ?? null);
                    flash('success', t('Продукт видалено.'));
                    redirect('/foods' . ($search !== '' ? '?q=' . urlencode($search) : ''));
                }
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $caloriesInput = $_POST['calories_per_100g'] ?? '';
        $calories = $normalizeDecimal($caloriesInput);

        if ($name === '') {
            $errors[] = t('Введіть назву продукту.');
        } elseif ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 150) {
            $errors[] = t('Назва занадто довга (до 150 символів).');
        }

        if ($calories === null || $calories < 0) {
            $errors[] = t('Калорії мають бути числом більше або дорівнює 0.');
        }

        if ($action === 'update') {
            $editId = (int)($_POST['id'] ?? 0);
            if ($editId <= 0) {
                $errors[] = t('Некоректний продукт.');
            }
        }

        $uploadedImagePath = null;
        if (!$errors && isset($_FILES['image'])) {
            $uploadedImagePath = $saveUpload($_FILES['image']);
        }

        if (!$errors) {
            try {
                if ($action === 'update') {
                    $existingStmt = $pdo->prepare('SELECT image_path FROM foods WHERE id = ? AND user_id = ?');
                    $existingStmt->execute([$editId, $user['id']]);
                    $existingFood = $existingStmt->fetch();
                    if (!$existingFood) {
                        if ($uploadedImagePath) {
                            $deleteImage($uploadedImagePath);
                        }
                        $errors[] = t('Продукт не знайдено.');
                    } else {
                        $imagePathToSave = $uploadedImagePath ?: ($existingFood['image_path'] ?? null);
                        $stmt = $pdo->prepare(
                            'UPDATE foods
                             SET name = ?, calories_per_100g = ?, proteins_per_100g = 0, fats_per_100g = 0, carbs_per_100g = 0, image_path = ?
                             WHERE id = ? AND user_id = ?'
                        );
                        $stmt->execute([
                            $name,
                            round($calories, 2),
                            $imagePathToSave,
                            $editId,
                            $user['id'],
                        ]);
                        if ($uploadedImagePath && !empty($existingFood['image_path']) && $existingFood['image_path'] !== $uploadedImagePath) {
                            $deleteImage($existingFood['image_path']);
                        }
                        flash('success', t('Продукт оновлено.'));
                        redirect('/foods');
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO foods (user_id, name, calories_per_100g, proteins_per_100g, fats_per_100g, carbs_per_100g, image_path)
                         VALUES (?, ?, ?, 0, 0, 0, ?)'
                    );
                    $stmt->execute([
                        $user['id'],
                        $name,
                        round($calories, 2),
                        $uploadedImagePath,
                    ]);
                    flash('success', t('Продукт додано.'));
                    redirect('/foods');
                }
            } catch (PDOException $e) {
                if (!empty($uploadedImagePath)) {
                    $deleteImage($uploadedImagePath);
                }
                if ($e->getCode() === '23000') {
                    $errors[] = t('Продукт з такою назвою вже існує.');
                } else {
                    throw $e;
                }
            }
        }
    }
}

$sql = 'SELECT id, name, calories_per_100g, image_path FROM foods WHERE user_id = ?';
$params = [$user['id']];
if ($search !== '') {
    $sql .= ' AND name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$foods = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM foods WHERE user_id = ?');
$countStmt->execute([$user['id']]);
$totalFoods = (int)$countStmt->fetchColumn();
$hasFoods = $totalFoods > 0;
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Продукти')); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css">
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Продукти')); ?></h1>

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

                <form method="get" action="/foods" class="search-form">
                    <div>
                        <label for="q"><?php echo h(t('Пошук')); ?></label>
                        <input type="text" id="q" name="q" value="<?php echo h($search); ?>" placeholder="<?php echo h(t('Введіть назву')); ?>">
                    </div>
                    <button type="submit"><?php echo h(t('Знайти')); ?></button>
                    <?php if ($search !== ''): ?>
                        <a class="btn secondary small" href="/foods"><?php echo h(t('Скинути')); ?></a>
                    <?php endif; ?>
                </form>

                <?php if (!$hasFoods): ?>
                    <div class="notice">
                        <strong><?php echo h(t('Немає продуктів.')); ?></strong>
                        <div><?php echo h(t('Ви можете додати стандартний набір продуктів для своєї країни, а потім редагувати його під себе.')); ?></div>
                    </div>
                    <form method="post" action="/foods" class="seed-defaults-form">
                        <input type="hidden" name="action" value="seed_defaults">
                        <div>
                            <label for="default-country"><?php echo h(t('Країна')); ?></label>
                            <select id="default-country" name="country" required>
                                <option value=""><?php echo h(t('Оберіть країну')); ?></option>
                                <?php foreach ($defaultFoodsByCountry as $code => $data): ?>
                                    <option value="<?php echo h($code); ?>" <?php echo $seedCountry === $code ? 'selected' : ''; ?>>
                                        <?php echo h($data['label'] ?? $code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"><?php echo h(t('Додати стандартні продукти')); ?></button>
                    </form>
                <?php endif; ?>

                <h2><?php echo h(t('Додати продукт')); ?></h2>
                <form method="post" action="/foods" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label for="name"><?php echo h(t('Назва продукту')); ?></label>
                        <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
                    </div>
                    <div>
                        <label for="calories_per_100g"><?php echo h(t('Ккал на 100 г')); ?></label>
                        <input type="number" id="calories_per_100g" name="calories_per_100g" min="0" step="0.01" value="<?php echo h($caloriesInput); ?>">
                    </div>
                    <div>
                        <label for="image"><?php echo h(t('Зображення (необов’язково)')); ?></label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png">
                    </div>
                    <button type="submit"><?php echo h(t('Додати продукт')); ?></button>
                </form>

                <h2><?php echo h(t('Список продуктів')); ?></h2>
                <?php if ($foods): ?>
                    <div class="menu-cards">
                        <?php foreach ($foods as $food): ?>
                            <div class="menu-card">
                                <?php if (!empty($food['image_path'])): ?>
                                    <img class="food-image" src="<?php echo h($food['image_path']); ?>" alt="<?php echo h($food['name']); ?>">
                                <?php endif; ?>
                                <div class="menu-card-title"><?php echo h($food['name']); ?></div>
                                <div class="menu-card-text"><?php echo h(t('Ккал/100 г:')); ?> <?php echo h($formatDecimal($food['calories_per_100g'])); ?></div>
                                <div class="food-actions">
                                    <button
                                        class="btn secondary small edit-food"
                                        type="button"
                                        data-id="<?php echo h((string)$food['id']); ?>"
                                        data-name="<?php echo h($food['name']); ?>"
                                        data-calories="<?php echo h($formatDecimal($food['calories_per_100g'])); ?>"
                                    ><?php echo h(t('Редагувати')); ?></button>
                                    <form method="post" action="/foods">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h((string)$food['id']); ?>">
                                        <button class="btn danger small" type="submit"><?php echo h(t('Видалити')); ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><?php echo h(t('Немає продуктів.')); ?></p>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <div class="container">
                <?php echo h(t('Diet Organizer • простий спосіб вести щоденник харчування і тримати форму')); ?>
            </div>
        </footer>
    </div>
    <div class="modal" id="edit-food-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><?php echo h(t('Редагувати продукт')); ?></div>
                <button class="modal-close" type="button" aria-label="<?php echo h(t('Закрити')); ?>">&times;</button>
            </div>
            <form method="post" action="/foods" id="edit-food-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                <div>
                    <label for="edit-name"><?php echo h(t('Назва продукту')); ?></label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                <div>
                    <label for="edit-calories"><?php echo h(t('Ккал на 100 г')); ?></label>
                    <input type="number" id="edit-calories" name="calories_per_100g" min="0" step="0.01">
                </div>
                <div>
                    <label for="edit-image"><?php echo h(t('Зображення (необов’язково)')); ?></label>
                    <input type="file" id="edit-image" name="image" accept="image/jpeg,image/png">
                </div>
                <button type="submit"><?php echo h(t('Зберегти')); ?></button>
            </form>
        </div>
    </div>
    <div class="modal" id="image-crop-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><?php echo h(t('Обрізання зображення')); ?></div>
                <button class="modal-close" type="button" id="cropper-cancel-top" aria-label="<?php echo h(t('Закрити')); ?>">&times;</button>
            </div>
            <div class="cropper-hint"><?php echo h(t('Перетягніть зображення й масштабуйте колесом миші або жестом.')); ?></div>
            <div class="cropper-stage">
                <img id="cropper-image" alt="Crop preview">
            </div>
            <div class="cropper-actions">
                <button type="button" class="btn secondary" id="cropper-cancel"><?php echo h(t('Скасувати')); ?></button>
                <button type="button" class="btn primary" id="cropper-apply"><?php echo h(t('Обрізати')); ?></button>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>
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

            const modal = document.querySelector('#edit-food-modal');
            const modalClose = modal ? modal.querySelector('.modal-close') : null;
            const editButtons = document.querySelectorAll('.edit-food');
            const editId = document.querySelector('#edit-id');
            const editName = document.querySelector('#edit-name');
            const editCalories = document.querySelector('#edit-calories');
            const editImage = document.querySelector('#edit-image');

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
                    editCalories.value = button.dataset.calories || '';
                    if (editImage) {
                        editImage.value = '';
                    }
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

            const cropperModal = document.querySelector('#image-crop-modal');
            const cropperImage = document.querySelector('#cropper-image');
            const cropperApply = document.querySelector('#cropper-apply');
            const cropperCancel = document.querySelector('#cropper-cancel');
            const cropperCancelTop = document.querySelector('#cropper-cancel-top');
            const imageInputs = [document.querySelector('#image'), document.querySelector('#edit-image')].filter(Boolean);
            const cropperTexts = {
                allowedTypes: <?php echo json_encode(t('Дозволені лише JPG і PNG.')); ?>
            };

            if (!cropperModal || !cropperImage || !cropperApply || !cropperCancel || typeof Cropper === 'undefined') {
                return;
            }

            const allowedImageTypes = ['image/jpeg', 'image/png'];
            let cropperInstance = null;
            const cropperState = {
                input: null,
                file: null,
                objectUrl: null,
                mime: 'image/jpeg'
            };

            function openCropperModal() {
                cropperModal.classList.add('open');
                cropperModal.setAttribute('aria-hidden', 'false');
            }

            function closeCropperModal() {
                cropperModal.classList.remove('open');
                cropperModal.setAttribute('aria-hidden', 'true');
            }

            function destroyCropper() {
                if (cropperInstance) {
                    cropperInstance.destroy();
                    cropperInstance = null;
                }
                if (cropperState.objectUrl) {
                    URL.revokeObjectURL(cropperState.objectUrl);
                    cropperState.objectUrl = null;
                }
                cropperImage.removeAttribute('src');
            }

            function clearCropper() {
                if (cropperState.input) {
                    cropperState.input.value = '';
                    cropperState.input.removeAttribute('data-cropped');
                }
                cropperState.input = null;
                cropperState.file = null;
                destroyCropper();
                closeCropperModal();
            }

            function startCropperForFile(file, input) {
                if (!file) return;
                if (!file.type || allowedImageTypes.indexOf(file.type) === -1) {
                    alert(cropperTexts.allowedTypes);
                    input.value = '';
                    return;
                }
                cropperState.input = input;
                cropperState.file = file;
                cropperState.mime = file.type;
                input.removeAttribute('data-cropped');
                destroyCropper();
                cropperState.objectUrl = URL.createObjectURL(file);
                cropperImage.src = cropperState.objectUrl;
                openCropperModal();
                cropperImage.onload = function () {
                    if (cropperInstance) {
                        cropperInstance.destroy();
                    }
                    cropperInstance = new Cropper(cropperImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 1,
                        dragMode: 'move',
                        background: false,
                        responsive: true,
                        checkOrientation: true
                    });
                };
            }

            cropperApply.addEventListener('click', function () {
                if (!cropperInstance || !cropperState.input) return;
                const outputSize = 512;
                const canvas = cropperInstance.getCroppedCanvas({
                    width: outputSize,
                    height: outputSize,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                    fillColor: '#0f0f0f'
                });
                if (!canvas) {
                    clearCropper();
                    return;
                }
                const mime = cropperState.mime;
                const quality = mime === 'image/jpeg' ? 0.9 : undefined;
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        clearCropper();
                        return;
                    }
                    const baseName = cropperState.file ? cropperState.file.name.replace(/\.[^/.]+$/, '') : 'food';
                    const extMap = {
                        'image/jpeg': 'jpg',
                        'image/png': 'png'
                    };
                    const ext = extMap[mime] || 'jpg';
                    const croppedFile = new File([blob], baseName + '.' + ext, { type: mime });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(croppedFile);
                    cropperState.input.files = dataTransfer.files;
                    cropperState.input.setAttribute('data-cropped', '1');
                    cropperState.input = null;
                    cropperState.file = null;
                    destroyCropper();
                    closeCropperModal();
                }, mime, quality);
            });

            function attachCropperToInput(input) {
                input.addEventListener('change', function () {
                    const file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file) return;
                    startCropperForFile(file, input);
                });
            }

            imageInputs.forEach(attachCropperToInput);

            imageInputs.forEach(function (input) {
                const form = input.closest('form');
                if (!form) return;
                form.addEventListener('submit', function (event) {
                    if (input.files && input.files.length > 0 && input.getAttribute('data-cropped') !== '1') {
                        event.preventDefault();
                        startCropperForFile(input.files[0], input);
                    }
                });
            });

            if (cropperCancel) {
                cropperCancel.addEventListener('click', clearCropper);
            }
            if (cropperCancelTop) {
                cropperCancelTop.addEventListener('click', clearCropper);
            }
        })();
    </script>
    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
