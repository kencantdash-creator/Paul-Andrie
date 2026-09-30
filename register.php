<?php
require 'config.php';
if (current_user_id()) redirect('index.php');

$errors = [];
$old = ['name' => '', 'email' => '', 'contact' => '', 'location' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) $errors[] = 'Enter your name (max 100 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($old['contact'] === '' || mb_strlen($old['contact']) > 50) $errors[] = 'Enter a contact number or social handle.';
    if ($old['location'] === '' || mb_strlen($old['location']) > 120) $errors[] = 'Enter your barangay or city.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = 'That email is already registered. Try logging in.';
        } else {
            $st = $pdo->prepare('INSERT INTO users (name, email, password, contact, location) VALUES (?,?,?,?,?)');
            $st->execute([$old['name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT), $old['contact'], $old['location']]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_name'] = $old['name'];
            flash('Welcome to KolektoSwap! Post your first item to start trading.');
            redirect('add.php');
        }
    }
}

$pageTitle = 'Sign up';
include 'includes/header.php';
?>
<div class="max-w-md mx-auto bg-white border border-slate-300 rounded-lg p-6 sm:p-8">
  <h1 class="text-2xl font-bold text-slate-900">Create your account</h1>
  <p class="text-sm text-slate-600 mt-1">Collectors and hobby shops can join for free.</p>

  <?php if ($errors): ?>
    <ul class="mt-4 rounded-md bg-rose-50 border border-rose-300 text-rose-800 text-sm p-3 list-disc list-inside">
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" class="mt-5 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label for="name" class="block text-sm font-medium mb-1">Full name or shop name</label>
      <input id="name" name="name" required maxlength="100" value="<?= e($old['name']) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <div>
      <label for="email" class="block text-sm font-medium mb-1">Email</label>
      <input id="email" type="email" name="email" required value="<?= e($old['email']) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <div>
      <label for="contact" class="block text-sm font-medium mb-1">Contact number or Messenger</label>
      <input id="contact" name="contact" required maxlength="50" value="<?= e($old['contact']) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <div>
      <label for="location" class="block text-sm font-medium mb-1">Barangay / City</label>
      <input id="location" name="location" required maxlength="120" value="<?= e($old['location']) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <div>
      <label for="password" class="block text-sm font-medium mb-1">Password</label>
      <input id="password" type="password" name="password" required minlength="6" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700">
    </div>
    <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-md py-2.5 flex items-center justify-center gap-2"><?= icon('user', 'w-4 h-4') ?>Create account</button>
  </form>
  <p class="text-sm text-slate-600 mt-5 text-center">Already have an account? <a href="login.php" class="text-blue-800 font-semibold underline">Log in</a></p>
</div>
<?php include 'includes/footer.php'; ?>
