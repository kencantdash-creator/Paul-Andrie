<?php
require 'config.php';

$id = (int)($_GET['id'] ?? 0);
$uid = current_user_id();

function load_item(PDO $pdo, int $id) {
    $st = $pdo->prepare('SELECT i.*, u.name AS owner_name, u.location, u.contact, u.created_at AS member_since
                         FROM items i JOIN users u ON u.id = i.user_id WHERE i.id = ?');
    $st->execute([$id]);
    return $st->fetch();
}

$item = load_item($pdo, $id);
if (!$item) {
    http_response_code(404);
    $pageTitle = 'Item not found';
    include 'includes/header.php';
    echo '<div class="bg-white border border-slate-300 rounded-lg p-10 text-center"><h1 class="text-xl font-bold">Item not found</h1><a href="index.php" class="inline-block mt-4 text-blue-800 underline">Back to browse</a></div>';
    include 'includes/footer.php';
    exit;
}
$isOwner = $uid && (int)$item['user_id'] === (int)$uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && $isOwner) {
        if ($item['status'] === 'Trade Pending') {
            flash('Resolve the pending trade before deleting this item.', 'error');
        } else {
            $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
            if ($item['image'] && is_file(__DIR__ . '/uploads/' . $item['image'])) @unlink(__DIR__ . '/uploads/' . $item['image']);
            flash('Item deleted.');
            redirect('index.php');
        }
        redirect('item.php?id=' . $id);
    }

    if ($action === 'trade' && !$isOwner) {
        $offeredId = (int)($_POST['offered_item_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if (mb_strlen($message) > 500) $message = mb_substr($message, 0, 500);

        try {
            $pdo->beginTransaction();
            $t = $pdo->prepare('SELECT * FROM items WHERE id = ? FOR UPDATE');
            $t->execute([$id]);
            $target = $t->fetch();
            $t->execute([$offeredId]);
            $offered = $t->fetch();

            if (!$target || $target['status'] !== 'Available') {
                throw new RuntimeException('This item is no longer available for trade.');
            }
            if (!$offered || (int)$offered['user_id'] !== (int)$uid || $offered['status'] !== 'Available') {
                throw new RuntimeException('Choose one of your own Available items to offer.');
            }
            $pdo->prepare('INSERT INTO trades (item_id, offered_item_id, requester_id, owner_id, message) VALUES (?,?,?,?,?)')
                ->execute([$target['id'], $offered['id'], $uid, $target['user_id'], $message]);
            $pdo->prepare("UPDATE items SET status = 'Trade Pending' WHERE id IN (?, ?)")->execute([$target['id'], $offered['id']]);
            $pdo->commit();
            flash('Trade request sent. The owner will review your offer.');
            redirect('trades.php');
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            flash($ex->getMessage(), 'error');
            redirect('item.php?id=' . $id);
        }
    }
    redirect('item.php?id=' . $id);
}

$myItems = [];
if ($uid && !$isOwner && $item['status'] === 'Available') {
    $st = $pdo->prepare("SELECT id, name, item_condition FROM items WHERE user_id = ? AND status = 'Available' ORDER BY name");
    $st->execute([$uid]);
    $myItems = $st->fetchAll();
}

$pageTitle = $item['name'];
include 'includes/header.php';
?>
<a href="index.php" class="inline-flex items-center gap-2 text-sm text-blue-800 hover:underline mb-4"><?= icon('arrow-left', 'w-4 h-4') ?>Back to browse</a>

<div class="grid gap-6 lg:grid-cols-5">
  <div class="lg:col-span-3 space-y-6">
    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden">
      <div class="bg-slate-200 flex items-center justify-center text-slate-400 min-h-[16rem]">
        <?php if ($item['image']): ?>
          <img src="uploads/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="w-full max-h-[32rem] object-contain">
        <?php else: ?>
          <?= icon('image', 'w-16 h-16') ?>
        <?php endif; ?>
      </div>
      <div class="p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <h1 class="text-2xl font-bold text-slate-900"><?= e($item['name']) ?></h1>
          <span class="text-sm font-semibold border rounded-full px-3 py-1 <?= status_classes($item['status']) ?>"><?= e($item['status']) ?></span>
        </div>
        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
          <div><dt class="text-slate-500 flex items-center gap-1"><?= icon('tag', 'w-4 h-4') ?>Category</dt><dd class="font-medium mt-0.5"><?= e($item['category']) ?></dd></div>
          <div><dt class="text-slate-500 flex items-center gap-1"><?= icon('check', 'w-4 h-4') ?>Condition</dt><dd class="font-medium mt-0.5"><?= e($item['item_condition']) ?></dd></div>
        </dl>
        <h2 class="font-semibold mt-5">Description</h2>
        <p class="text-slate-700 mt-1 whitespace-pre-line break-words"><?= $item['description'] !== '' ? e($item['description']) : 'The owner did not add a description.' ?></p>
        <p class="text-xs text-slate-500 mt-4">Listed on <?= e(date('F j, Y', strtotime($item['created_at']))) ?></p>
      </div>
    </div>
  </div>

  <aside class="lg:col-span-2 space-y-6">
    <div class="bg-white border border-slate-300 rounded-lg p-5">
      <h2 class="font-semibold text-lg">Collector details</h2>
      <ul class="mt-3 space-y-3 text-sm">
        <li class="flex items-center gap-2"><?= icon('user', 'w-4 h-4 text-slate-500') ?><?= e($item['owner_name']) ?></li>
        <li class="flex items-center gap-2"><?= icon('map-pin', 'w-4 h-4 text-slate-500') ?><?= e($item['location']) ?></li>
        <li class="flex items-center gap-2"><?= icon('phone', 'w-4 h-4 text-slate-500') ?>
          <?php if ($uid): ?><?= e($item['contact']) ?><?php else: ?><a href="login.php" class="text-blue-800 underline">Log in to see contact</a><?php endif; ?>
        </li>
      </ul>
    </div>

    <div class="bg-white border border-slate-300 rounded-lg p-5">
      <h2 class="font-semibold text-lg flex items-center gap-2"><?= icon('repeat', 'w-5 h-5') ?>Trade request</h2>

      <?php if ($isOwner): ?>
        <p class="text-sm text-slate-600 mt-2">This is your item. Other collectors can send you trade requests while it is Available.</p>
        <?php if ($item['status'] !== 'Trade Pending'): ?>
        <form method="post" class="mt-4" onsubmit="return confirm('Delete this item permanently?');">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <button class="w-full border border-rose-300 text-rose-700 hover:bg-rose-50 rounded-md py-2 text-sm font-semibold flex items-center justify-center gap-2"><?= icon('trash', 'w-4 h-4') ?>Delete item</button>
        </form>
        <?php else: ?>
        <a href="trades.php" class="mt-4 block text-center bg-blue-900 text-white rounded-md py-2 text-sm font-semibold hover:bg-blue-800">Review trade</a>
        <?php endif; ?>

      <?php elseif ($item['status'] !== 'Available'): ?>
        <p class="text-sm text-slate-600 mt-2">
          <?= $item['status'] === 'Trade Pending' ? 'A trade is already pending for this item. Check back later.' : 'This item has already been traded.' ?>
        </p>

      <?php elseif (!$uid): ?>
        <p class="text-sm text-slate-600 mt-2">Log in to offer one of your items in exchange.</p>
        <a href="login.php" class="mt-4 flex items-center justify-center gap-2 bg-blue-900 text-white rounded-md py-2.5 text-sm font-semibold hover:bg-blue-800"><?= icon('log-in', 'w-4 h-4') ?>Log in</a>

      <?php elseif (!$myItems): ?>
        <p class="text-sm text-slate-600 mt-2">You need at least one Available item to offer in a trade.</p>
        <a href="add.php" class="mt-4 flex items-center justify-center gap-2 bg-blue-900 text-white rounded-md py-2.5 text-sm font-semibold hover:bg-blue-800"><?= icon('plus', 'w-4 h-4') ?>Post an item</a>

      <?php else: ?>
        <form method="post" class="mt-3 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="trade">
          <div>
            <label for="offered" class="block text-sm font-medium mb-1">Your item to offer</label>
            <select id="offered" name="offered_item_id" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-700">
              <option value="">Select an item</option>
              <?php foreach ($myItems as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['item_condition']) ?>)</option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="message" class="block text-sm font-medium mb-1">Message <span class="text-slate-500 font-normal">(optional)</span></label>
            <textarea id="message" name="message" rows="3" maxlength="500" placeholder="Suggest a meetup place or add details" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-700"></textarea>
          </div>
          <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-md py-2.5 flex items-center justify-center gap-2"><?= icon('send', 'w-4 h-4') ?>Send trade request</button>
        </form>
      <?php endif; ?>
    </div>
  </aside>
</div>
<?php include 'includes/footer.php'; ?>
