<?php
// public_html/pages/login.php
if (is_logged_in()) {
    redirect('/dashboard');
}

require APP_PUBLIC . '/includes/db.php';

$error = flash('error');
$success = flash('success');
$email = '';
$googleClientId = '';

if (isset($config) && is_array($config)) {
    $googleClientId = $config['google_client_id'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('Введіть коректний email.');
    } elseif ($password === '') {
        $error = t('Введіть пароль.');
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = t('Невірний email або пароль.');
        } else {
            unset($user['password_hash']);
            log_in_user($user);
            flash('success', t('Ви успішно увійшли.'));
            redirect('/dashboard');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo h(i18n_get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(t('Вхід')); ?></title>
    <link rel="stylesheet" href="/css/home.css?v=<?php echo filemtime(APP_PUBLIC . '/css/home.css'); ?>">
</head>
<body>
    <div class="wrapper">
        <?php require APP_PUBLIC . '/includes/header.php'; ?>

        <main>
            <div class="container page">
                <h1><?php echo h(t('Вхід')); ?></h1>
                <?php if ($success): ?>
                    <div class="notice success"><?php echo h($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice error"><?php echo h($error); ?></div>
                <?php endif; ?>
                <?php if ($googleClientId !== ''): ?>
                    <div id="google-error" class="notice error is-hidden"></div>
                    <div class="oauth-block">
                        <div
                            id="g_id_onload"
                            data-client_id="<?php echo h($googleClientId); ?>"
                            data-callback="handleGoogleCredential"
                            data-auto_prompt="false"
                        ></div>
                        <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-shape="pill"></div>
                    </div>
                    <div class="oauth-divider"><span><?php echo h(t('АБО')); ?></span></div>
                <?php endif; ?>
                <form method="post" action="/login">
                    <div>
                        <label for="email"><?php echo h(t('Email')); ?></label>
                        <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
                    </div>
                    <div>
                        <label for="password"><?php echo h(t('Пароль')); ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit"><?php echo h(t('Увійти')); ?></button>
                </form>
                <p><?php echo h(t('Немає акаунта?')); ?> <a href="/register"><?php echo h(t('Зареєструватися')); ?></a></p>
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
    <?php if ($googleClientId !== ''): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
            const googleErrorText = <?php echo json_encode(t('Помилка входу через Google.')); ?>;

            function showGoogleError(message) {
                const box = document.getElementById('google-error');
                if (!box) return;
                box.textContent = message;
                box.classList.remove('is-hidden');
            }

            function handleGoogleCredential(response) {
                if (!response || !response.credential) {
                    showGoogleError(googleErrorText);
                    return;
                }
                const body = new URLSearchParams();
                body.append('credential', response.credential);
                fetch('/google_login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.success && data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        showGoogleError((data && data.error) ? data.error : googleErrorText);
                    })
                    .catch(function () {
                        showGoogleError(googleErrorText);
                    });
            }
        </script>
    <?php endif; ?>
    <?php require APP_PUBLIC . '/includes/client_bootstrap.php'; ?>
</body>
</html>
