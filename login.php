<?php
require 'config.php';
if (current_user_id()) redirect('index.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['user_name'] = $u['name'];
        flash('Welcome back, ' . $u['name'] . '.');
        redirect('index.php');
    }
    $error = 'Email or password is incorrect.';
}

$pageTitle = 'Log in';
include 'includes/header.php';
?>
<div class="max-w-md mx-auto bg-white border border-slate-300 rounded-lg p-6 sm:p-8">
  <h1 class="text-2xl font-bold text-slate-900">Log in</h1>
  <p class="text-sm text-slate-600 mt-1">Log in to post items and send trade requests.</p>

  <?php if ($error): ?>
    <p class="mt-4 rounded-md bg-rose-50 border border-rose-300 text-rose-800 text-sm p-3"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" class="mt-5 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label for="email" class="block text-sm font-medium mb-1">Email</label>
      <input id="email" type="email" name="email" required value="<?= e($email) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <div>
      <label for="password" class="block text-sm font-medium mb-1">Password</label>
      <input id="password" type="password" name="password" required class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-md py-2.5 flex items-center justify-center gap-2"><?= icon('log-in', 'w-4 h-4') ?>Log in</button>
  </form>
  <p class="text-sm text-slate-600 mt-5 text-center">New here? <a href="register.php" class="text-blue-800 font-semibold underline">Create an account</a></p>
</div>
<?php include 'includes/footer.php'; ?>
