<?php
require 'config.php';

$q = trim($_GET['q'] ?? '');
$cat = $_GET['category'] ?? '';
$cond = $_GET['condition'] ?? '';
$stat = $_GET['status'] ?? '';
if (!in_array($cat, CATEGORIES, true)) $cat = '';
if (!in_array($cond, CONDITIONS, true)) $cond = '';
if (!in_array($stat, ['Available', 'Trade Pending', 'Traded'], true)) $stat = '';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(i.name LIKE :q1 OR i.description LIKE :q2 OR u.name LIKE :q3)';
    $like = '%' . $q . '%';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
}
if ($cat !== '') { $where[] = 'i.category = :cat'; $params[':cat'] = $cat; }
if ($cond !== '') { $where[] = 'i.item_condition = :cond'; $params[':cond'] = $cond; }
if ($stat !== '') { $where[] = 'i.status = :stat'; $params[':stat'] = $stat; }

$sql = 'SELECT i.*, u.name AS owner_name, u.location FROM items i JOIN users u ON u.id = i.user_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY i.created_at DESC, i.id DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$items = $st->fetchAll();

$pageTitle = 'Browse collections';
include 'includes/header.php';
$inputCls = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-700';
?>
<section class="bg-white border border-slate-300 rounded-lg p-5 sm:p-6 mb-6">
  <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Swap duplicates with collectors near you</h1>
  <p class="text-slate-600 mt-2 max-w-2xl">Browse what local collectors and hobby shops are offering, then send a trade request with one of your own items.</p>

  <form method="get" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
    <div class="relative sm:col-span-2 lg:col-span-4">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><?= icon('search', 'w-4 h-4') ?></span>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search item, description or owner" aria-label="Search" class="<?= $inputCls ?> pl-9">
    </div>
    <select name="category" aria-label="Category" class="<?= $inputCls ?> lg:col-span-3">
      <option value="">All categories</option>
      <?php foreach (CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= $c === $cat ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <select name="condition" aria-label="Condition" class="<?= $inputCls ?> lg:col-span-2">
      <option value="">Any condition</option>
      <?php foreach (CONDITIONS as $c): ?><option value="<?= e($c) ?>" <?= $c === $cond ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <select name="status" aria-label="Status" class="<?= $inputCls ?> lg:col-span-2">
      <option value="">Any status</option>
      <?php foreach (['Available', 'Trade Pending', 'Traded'] as $s): ?><option value="<?= e($s) ?>" <?= $s === $stat ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-md px-4 py-2 text-sm flex items-center justify-center gap-2 lg:col-span-1"><?= icon('search', 'w-4 h-4') ?><span class="lg:hidden">Search</span></button>
  </form>
</section>

<div class="flex items-center justify-between mb-4">
  <p class="text-sm text-slate-600"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> found</p>
  <?php if ($q !== '' || $cat !== '' || $cond !== '' || $stat !== ''): ?>
    <a href="index.php" class="text-sm text-blue-800 underline flex items-center gap-1"><?= icon('x', 'w-4 h-4') ?>Clear filters</a>
  <?php endif; ?>
</div>

<?php if (!$items): ?>
  <div class="bg-white border border-dashed border-slate-300 rounded-lg p-10 text-center">
    <div class="mx-auto w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500"><?= icon('package', 'w-6 h-6') ?></div>
    <h2 class="font-semibold text-lg mt-3">No items match your search</h2>
    <p class="text-slate-600 text-sm mt-1">Try different keywords or post your own item for others to find.</p>
    <a href="add.php" class="inline-flex items-center gap-2 mt-4 bg-blue-900 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-blue-800"><?= icon('plus', 'w-4 h-4') ?>Post an item</a>
  </div>
<?php else: ?>
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    <?php foreach ($items as $it): ?>
      <a href="item.php?id=<?= (int)$it['id'] ?>" class="bg-white border border-slate-300 rounded-lg overflow-hidden flex flex-col hover:border-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700">
        <div class="aspect-[4/3] bg-slate-200 flex items-center justify-center text-slate-400 relative">
          <?php if ($it['image']): ?>
            <img src="uploads/<?= e($it['image']) ?>" alt="<?= e($it['name']) ?>" class="w-full h-full object-cover" loading="lazy">
          <?php else: ?>
            <?= icon('image', 'w-12 h-12') ?>
          <?php endif; ?>
          <span class="absolute top-2 left-2 text-xs font-semibold border rounded-full px-2.5 py-0.5 <?= status_classes($it['status']) ?>"><?= e($it['status']) ?></span>
        </div>
        <div class="p-4 flex-1 flex flex-col">
          <h2 class="font-semibold text-slate-900 leading-snug"><?= e($it['name']) ?></h2>
          <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
            <span class="flex items-center gap-1"><?= icon('tag', 'w-3.5 h-3.5') ?><?= e($it['category']) ?></span>
            <span class="flex items-center gap-1"><?= icon('check', 'w-3.5 h-3.5') ?><?= e($it['item_condition']) ?></span>
          </div>
          <div class="mt-auto pt-3 text-xs text-slate-500 flex items-center gap-1 border-t border-slate-100">
            <?= icon('user', 'w-3.5 h-3.5') ?><span class="truncate"><?= e($it['owner_name']) ?></span>
            <span class="mx-1">&bull;</span><?= icon('map-pin', 'w-3.5 h-3.5') ?><span class="truncate"><?= e($it['location']) ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
