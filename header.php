<?php
$uid = current_user_id();
$uname = $_SESSION['user_name'] ?? '';
$flash = get_flash();
$pendingCount = 0;
if ($uid) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM trades WHERE owner_id = ? AND status = 'Pending'");
    $st->execute([$uid]);
    $pendingCount = (int)$st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'KolektoSwap') ?> | KolektoSwap</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: { fontFamily: { sans: ['Sora', 'ui-sans-serif', 'system-ui', 'sans-serif'] } } } };
</script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans min-h-screen flex flex-col">

<header class="bg-blue-900 text-white sticky top-0 z-30">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2 font-bold text-lg">
      <span class="bg-amber-400 text-blue-900 rounded-md p-1.5"><?= icon('repeat', 'w-5 h-5') ?></span>
      KolektoSwap
    </a>

    <nav class="hidden md:flex items-center gap-1 text-sm">
      <a href="index.php" class="px-3 py-2 rounded-md hover:bg-blue-800 flex items-center gap-2"><?= icon('layers', 'w-4 h-4') ?>Browse</a>
      <?php if ($uid): ?>
        <a href="add.php" class="px-3 py-2 rounded-md hover:bg-blue-800 flex items-center gap-2"><?= icon('plus', 'w-4 h-4') ?>Post item</a>
        <a href="trades.php" class="px-3 py-2 rounded-md hover:bg-blue-800 flex items-center gap-2"><?= icon('inbox', 'w-4 h-4') ?>Trades
          <?php if ($pendingCount): ?><span class="bg-amber-400 text-blue-900 text-xs font-bold rounded-full px-2"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <span class="px-3 py-2 flex items-center gap-2 text-blue-200"><?= icon('user', 'w-4 h-4') ?><?= e($uname) ?></span>
        <a href="logout.php" class="px-3 py-2 rounded-md hover:bg-blue-800 flex items-center gap-2"><?= icon('log-out', 'w-4 h-4') ?>Log out</a>
      <?php else: ?>
        <a href="login.php" class="px-3 py-2 rounded-md hover:bg-blue-800 flex items-center gap-2"><?= icon('log-in', 'w-4 h-4') ?>Log in</a>
        <a href="register.php" class="ml-1 px-4 py-2 rounded-md bg-amber-400 text-blue-900 font-semibold hover:bg-amber-300">Sign up</a>
      <?php endif; ?>
    </nav>

    <button id="menuBtn" class="md:hidden p-2 rounded-md hover:bg-blue-800" aria-label="Open menu" aria-expanded="false"><?= icon('menu', 'w-6 h-6') ?></button>
  </div>

  <nav id="mobileMenu" class="hidden md:hidden border-t border-blue-800 px-4 py-2 text-sm">
    <a href="index.php" class="flex items-center gap-2 py-3"><?= icon('layers', 'w-4 h-4') ?>Browse</a>
    <?php if ($uid): ?>
      <a href="add.php" class="flex items-center gap-2 py-3"><?= icon('plus', 'w-4 h-4') ?>Post item</a>
      <a href="trades.php" class="flex items-center gap-2 py-3"><?= icon('inbox', 'w-4 h-4') ?>Trades<?= $pendingCount ? ' (' . $pendingCount . ' new)' : '' ?></a>
      <a href="logout.php" class="flex items-center gap-2 py-3"><?= icon('log-out', 'w-4 h-4') ?>Log out (<?= e($uname) ?>)</a>
    <?php else: ?>
      <a href="login.php" class="flex items-center gap-2 py-3"><?= icon('log-in', 'w-4 h-4') ?>Log in</a>
      <a href="register.php" class="flex items-center gap-2 py-3"><?= icon('user', 'w-4 h-4') ?>Sign up</a>
    <?php endif; ?>
  </nav>
</header>

<main class="flex-1 max-w-6xl w-full mx-auto px-4 py-6 sm:py-8">
<?php if ($flash): ?>
  <div class="mb-6 rounded-md border px-4 py-3 text-sm flex items-start gap-2 <?= $flash['type'] === 'error' ? 'bg-rose-50 border-rose-300 text-rose-800' : 'bg-emerald-50 border-emerald-300 text-emerald-800' ?>" role="alert">
    <?= icon($flash['type'] === 'error' ? 'x' : 'check', 'w-5 h-5 shrink-0') ?>
    <span><?= e($flash['msg']) ?></span>
  </div>
<?php endif; ?>
