<?php
// public_html/pages/register.php
if (is_logged_in()) {
    redirect('/dashboard');
}

require APP_PUBLIC . '/includes/db.php';

$errors = [];
$success = flash('success');
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $errors[] = t('Введіть нік (ім’я).');
    } elseif ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 100) {
        $errors[] = t('Нік занадто довгий (до 100 символів).');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Введіть коректний email.');
    }

    if ($password === '') {
        $errors[] = t('Введіть пароль.');
    } elseif ((function_exists('mb_strlen') ? mb_strlen($password) : strlen($password)) < 6) {
        $errors[] = t('Пароль має бути не коротше 6 символів.');
    }

    if ($password !== $passwordConfirm) {
        $errors[] = t('Паролі не збігаються.');
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = t('Користувач з таким email вже існує.');
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $email, $passwordHash]);

        flash('success', t('Акаунт створено. Увійдіть, використовуючи email і пароль.'));
        redirect('/login');
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Реєстрація')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Реєстрація')); ?></h1>
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
                <form method="post" action="/register">
                    <div>
                        <label for="name"><?php echo h(t('Нік / ім’я')); ?></label>
                        <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
                    </div>
                    <div>
                        <label for="email"><?php echo h(t('Email')); ?></label>
                        <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
                    </div>
                    <div>
                        <label for="password"><?php echo h(t('Пароль')); ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div>
                        <label for="password_confirm"><?php echo h(t('Повторіть пароль')); ?></label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>
                    <button type="submit"><?php echo h(t('Зареєструватися')); ?></button>
                </form>
                <p><?php echo h(t('Вже є акаунт?')); ?> <a href="/login"><?php echo h(t('Увійти')); ?></a></p>
            </div>
        </main>

        <footer>
            <div class="container">
                <?php echo h(t('Diet Organizer • простий спосіб вести щоденник харчування і тримати форму')); ?>
            </div>
        </footer>
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
        })();
    </script>
    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
