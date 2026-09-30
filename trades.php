<?php
require 'config.php';
require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tradeId = (int)($_POST['trade_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM trades WHERE id = ? FOR UPDATE');
        $st->execute([$tradeId]);
        $tr = $st->fetch();
        if (!$tr || $tr['status'] !== 'Pending') throw new RuntimeException('This trade is no longer pending.');

        $isOwner = (int)$tr['owner_id'] === (int)$uid;
        $isRequester = (int)$tr['requester_id'] === (int)$uid;

        if ($action === 'accept' && $isOwner) {
            $pdo->prepare("UPDATE items SET status = 'Traded' WHERE id IN (?, ?)")->execute([$tr['item_id'], $tr['offered_item_id']]);
            $pdo->prepare("UPDATE trades SET status = 'Accepted' WHERE id = ?")->execute([$tradeId]);
            $msg = 'Trade accepted. Both items are now marked Traded.';
        } elseif ($action === 'decline' && $isOwner) {
            $pdo->prepare("UPDATE items SET status = 'Available' WHERE id IN (?, ?)")->execute([$tr['item_id'], $tr['offered_item_id']]);
            $pdo->prepare("UPDATE trades SET status = 'Declined' WHERE id = ?")->execute([$tradeId]);
            $msg = 'Trade declined. Both items are Available again.';
        } elseif ($action === 'cancel' && $isRequester) {
            $pdo->prepare("UPDATE items SET status = 'Available' WHERE id IN (?, ?)")->execute([$tr['item_id'], $tr['offered_item_id']]);
            $pdo->prepare("UPDATE trades SET status = 'Cancelled' WHERE id = ?")->execute([$tradeId]);
            $msg = 'Trade request cancelled.';
        } else {
            throw new RuntimeException('You cannot perform that action.');
        }
        $pdo->commit();
        flash($msg);
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'error');
    }
    redirect('trades.php');
}

$base = 'SELECT t.*, i.name AS item_name, i.image AS item_image, oi.name AS offered_name, oi.image AS offered_image,
                ru.name AS requester_name, ru.contact AS requester_contact,
                ou.name AS owner_name, ou.contact AS owner_contact
         FROM trades t
         JOIN items i ON i.id = t.item_id
         JOIN items oi ON oi.id = t.offered_item_id
         JOIN users ru ON ru.id = t.requester_id
         JOIN users ou ON ou.id = t.owner_id';

$st = $pdo->prepare($base . ' WHERE t.owner_id = ? ORDER BY t.created_at DESC, t.id DESC');
$st->execute([$uid]);
$incoming = $st->fetchAll();

$st = $pdo->prepare($base . ' WHERE t.requester_id = ? ORDER BY t.created_at DESC, t.id DESC');
$st->execute([$uid]);
$outgoing = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM items WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$st->execute([$uid]);
$mine = $st->fetchAll();

function thumb($img) {
    return $img
        ? '<img src="uploads/' . e($img) . '" alt="" class="w-14 h-14 rounded-md object-cover shrink-0">'
        : '<div class="w-14 h-14 rounded-md bg-slate-200 text-slate-400 flex items-center justify-center shrink-0">' . icon('image', 'w-6 h-6') . '</div>';
}

function trade_card($t, $mode) {
    $other = $mode === 'in' ? $t['requester_name'] : $t['owner_name'];
    $contact = $mode === 'in' ? $t['requester_contact'] : $t['owner_contact'];
    ob_start(); ?>
    <div class="bg-white border border-slate-300 rounded-lg p-4">
      <div class="flex items-center justify-between gap-2 mb-3">
        <p class="text-sm text-slate-600 flex items-center gap-1 min-w-0"><?= icon('user', 'w-4 h-4 shrink-0') ?><span class="truncate"><?= $mode === 'in' ? 'From ' : 'To ' ?><?= e($other) ?></span></p>
        <span class="text-xs font-semibold border rounded-full px-2.5 py-0.5 shrink-0 <?= status_classes($t['status']) ?>"><?= e($t['status']) ?></span>
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <?= thumb($mode === 'in' ? $t['offered_image'] : $t['offered_image']) ?>
          <div class="min-w-0"><p class="text-xs text-slate-500"><?= $mode === 'in' ? 'They offer' : 'You offer' ?></p><a href="item.php?id=<?= (int)$t['offered_item_id'] ?>" class="font-medium text-sm hover:underline break-words"><?= e($t['offered_name']) ?></a></div>
        </div>
        <span class="text-slate-400 self-center rotate-90 sm:rotate-0"><?= icon('repeat', 'w-5 h-5') ?></span>
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <?= thumb($t['item_image']) ?>
          <div class="min-w-0"><p class="text-xs text-slate-500"><?= $mode === 'in' ? 'For your item' : 'For their item' ?></p><a href="item.php?id=<?= (int)$t['item_id'] ?>" class="font-medium text-sm hover:underline break-words"><?= e($t['item_name']) ?></a></div>
        </div>
      </div>
      <?php if ($t['message']): ?><p class="mt-3 text-sm bg-slate-50 border border-slate-200 rounded-md p-3 break-words"><?= e($t['message']) ?></p><?php endif; ?>
      <?php if ($t['status'] === 'Accepted'): ?>
        <p class="mt-3 text-sm text-emerald-800 flex items-center gap-2"><?= icon('phone', 'w-4 h-4') ?>Arrange the meetup with <?= e($other) ?>: <strong><?= e($contact) ?></strong></p>
      <?php endif; ?>
      <?php if ($t['status'] === 'Pending'): ?>
        <form method="post" class="mt-4 flex gap-2">
          <?= csrf_field() ?><input type="hidden" name="trade_id" value="<?= (int)$t['id'] ?>">
          <?php if ($mode === 'in'): ?>
            <button name="action" value="accept" class="flex-1 sm:flex-none bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold rounded-md px-4 py-2 flex items-center justify-center gap-2"><?= icon('check', 'w-4 h-4') ?>Accept</button>
            <button name="action" value="decline" class="flex-1 sm:flex-none border border-slate-300 hover:bg-slate-50 text-sm font-semibold rounded-md px-4 py-2 flex items-center justify-center gap-2"><?= icon('x', 'w-4 h-4') ?>Decline</button>
          <?php else: ?>
            <button name="action" value="cancel" class="border border-slate-300 hover:bg-slate-50 text-sm font-semibold rounded-md px-4 py-2 flex items-center justify-center gap-2"><?= icon('x', 'w-4 h-4') ?>Cancel request</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

$pageTitle = 'My trades';
include 'includes/header.php';
?>
<h1 class="text-2xl font-bold text-slate-900 mb-6">My trades</h1>

<section class="mb-10">
  <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><?= icon('inbox', 'w-5 h-5') ?>Requests for my items</h2>
  <?php if (!$incoming): ?>
    <p class="bg-white border border-dashed border-slate-300 rounded-lg p-6 text-sm text-slate-600">No one has requested a trade yet. Requests for your items will appear here.</p>
  <?php else: ?>
    <div class="grid gap-4 lg:grid-cols-2"><?php foreach ($incoming as $t) echo trade_card($t, 'in'); ?></div>
  <?php endif; ?>
</section>

<section class="mb-10">
  <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><?= icon('send', 'w-5 h-5') ?>Requests I sent</h2>
  <?php if (!$outgoing): ?>
    <p class="bg-white border border-dashed border-slate-300 rounded-lg p-6 text-sm text-slate-600">You have not sent any trade requests. <a href="index.php" class="text-blue-800 underline">Browse items</a> to find a swap.</p>
  <?php else: ?>
    <div class="grid gap-4 lg:grid-cols-2"><?php foreach ($outgoing as $t) echo trade_card($t, 'out'); ?></div>
  <?php endif; ?>
</section>

<section>
  <h2 class="text-lg font-semibold mb-3 flex items-center gap-2"><?= icon('package', 'w-5 h-5') ?>My listed items</h2>
  <?php if (!$mine): ?>
    <p class="bg-white border border-dashed border-slate-300 rounded-lg p-6 text-sm text-slate-600">You have no items yet. <a href="add.php" class="text-blue-800 underline">Post your first item</a>.</p>
  <?php else: ?>
    <div class="bg-white border border-slate-300 rounded-lg divide-y divide-slate-200">
      <?php foreach ($mine as $m): ?>
        <a href="item.php?id=<?= (int)$m['id'] ?>" class="flex items-center gap-3 p-3 hover:bg-slate-50">
          <?= thumb($m['image']) ?>
          <div class="flex-1 min-w-0"><p class="font-medium truncate"><?= e($m['name']) ?></p><p class="text-xs text-slate-500"><?= e($m['category']) ?> &bull; <?= e($m['item_condition']) ?></p></div>
          <span class="text-xs font-semibold border rounded-full px-2.5 py-0.5 shrink-0 <?= status_classes($m['status']) ?>"><?= e($m['status']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
