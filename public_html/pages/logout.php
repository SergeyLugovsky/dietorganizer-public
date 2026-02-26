<?php
// public_html/pages/logout.php
log_out_user();
flash('success', t('Ви вийшли з акаунта.'));
redirect('/');
