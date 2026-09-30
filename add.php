<?php
require 'config.php';
require_login();

$errors = [];
$old = ['name' => '', 'category' => '', 'item_condition' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) $old[$k] = trim($_POST[$k] ?? '');

    if ($old['name'] === '' || mb_strlen($old['name']) > 120) $errors[] = 'Enter an item name (max 120 characters).';
    if (!in_array($old['category'], CATEGORIES, true)) $errors[] = 'Choose a category.';
    if (!in_array($old['item_condition'], CONDITIONS, true)) $errors[] = 'Choose a condition.';
    if (mb_strlen($old['description']) > 1000) $errors[] = 'Description must be 1000 characters or fewer.';

    $filename = null;
    $f = $_FILES['image'] ?? null;
    if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed. Try a smaller file.';
        } elseif ($f['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Photo must be 3 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
            if (!$ext) {
                $errors[] = 'Photo must be a JPG, PNG, WEBP or GIF image.';
            } else {
                $filename = bin2hex(random_bytes(12)) . '.' . $ext;
            }
        }
    } else {
        $errors[] = 'Add a photo of your item.';
    }

    if (!$errors) {
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $filename)) {
            $errors[] = 'Could not save the photo. Check that the uploads folder is writable.';
        } else {
            $st = $pdo->prepare('INSERT INTO items (user_id, name, category, item_condition, description, image) VALUES (?,?,?,?,?,?)');
            $st->execute([current_user_id(), $old['name'], $old['category'], $old['item_condition'], $old['description'], $filename]);
            flash('Your item is now listed as Available.');
            redirect('item.php?id=' . $pdo->lastInsertId());
        }
    }
}

$pageTitle = 'Post an item';
include 'includes/header.php';
$inputCls = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-700';
?>
<div class="max-w-2xl mx-auto bg-white border border-slate-300 rounded-lg p-6 sm:p-8">
  <h1 class="text-2xl font-bold text-slate-900">Post an item to trade</h1>
  <p class="text-sm text-slate-600 mt-1">List a duplicate or unwanted item so nearby collectors can offer a swap.</p>

  <?php if ($errors): ?>
    <ul class="mt-4 rounded-md bg-rose-50 border border-rose-300 text-rose-800 text-sm p-3 list-disc list-inside">
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-5 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label for="name" class="block text-sm font-medium mb-1">Item name</label>
      <input id="name" name="name" required maxlength="120" value="<?= e($old['name']) ?>" class="<?= $inputCls ?>">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label for="category" class="block text-sm font-medium mb-1">Category</label>
        <select id="category" name="category" required class="<?= $inputCls ?>">
          <option value="">Select category</option>
          <?php foreach (CATEGORIES as $c): ?><option value="<?= e($c) ?>" <?= $c === $old['category'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="item_condition" class="block text-sm font-medium mb-1">Condition</label>
        <select id="item_condition" name="item_condition" required class="<?= $inputCls ?>">
          <option value="">Select condition</option>
          <?php foreach (CONDITIONS as $c): ?><option value="<?= e($c) ?>" <?= $c === $old['item_condition'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div>
      <label for="description" class="block text-sm font-medium mb-1">Description <span class="text-slate-500 font-normal">(optional)</span></label>
      <textarea id="description" name="description" rows="4" maxlength="1000" class="<?= $inputCls ?>" placeholder="Year, edition, flaws, and what you would like in exchange"><?= e($old['description']) ?></textarea>
    </div>
    <div>
      <label for="image" class="block text-sm font-medium mb-1">Photo</label>
      <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-900 file:text-white file:px-4 file:py-2 file:font-semibold hover:file:bg-blue-800 border border-slate-300 rounded-md">
      <p class="text-xs text-slate-500 mt-1">JPG, PNG, WEBP or GIF, up to 3 MB.</p>
    </div>
    <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
      <a href="index.php" class="text-center border border-slate-300 rounded-md px-5 py-2.5 font-semibold hover:bg-slate-50">Cancel</a>
      <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold rounded-md px-5 py-2.5 flex items-center justify-center gap-2"><?= icon('plus', 'w-4 h-4') ?>Post item</button>
    </div>
  </form>
</div>
<?php include 'includes/footer.php'; ?>
