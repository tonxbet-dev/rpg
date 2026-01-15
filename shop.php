<?php
/**
 * Магазин: категории и предметы
 * Доступен всем пользователям
 */
require_once 'slots.php';

$slotDefinitions = getSlotDefinitions();
$shopMessage = null;
$shopMessageType = 'success';

function setShopMessage($text, $type = 'success') {
    global $shopMessage, $shopMessageType;
    $shopMessage = $text;
    $shopMessageType = $type;
}

function fetchShopCategories() {
    global $conn;
    $list = [];
    $res = $conn->query("SELECT * FROM shop_categories ORDER BY slot_number ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function fetchShopItems() {
    global $conn;
    $list = [];
    $res = $conn->query("SELECT i.*, c.name as category_name, c.slot_number, c.status as category_status FROM items i LEFT JOIN shop_categories c ON i.category_id = c.id ORDER BY i.id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

function uploadShopImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $base = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', $base);
    $safeBase = $safeBase ?: 'item';
    $fileName = $safeBase . '_' . time() . '.' . $ext;
    $targetDir = __DIR__ . '/img/shop';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $targetPath = $targetDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return '';
    }
    return 'img/shop/' . $fileName;
}

if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    $slotNumber = (int)($_POST['slot_number'] ?? 0);
    $status = ($_POST['category_status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';

    if ($name === '' || empty($slotDefinitions[$slotNumber])) {
        setShopMessage('Заполните название и выберите слот.', 'error');
    } else {
        $check = $conn->query("SELECT id FROM shop_categories WHERE slot_number = $slotNumber");
        if ($check && $check->num_rows > 0) {
            setShopMessage('Для этого слота уже есть категория.', 'error');
        } else {
            $nameEsc = $conn->real_escape_string($name);
            $conn->query("INSERT INTO shop_categories (name, slot_number, status) VALUES ('$nameEsc', $slotNumber, '$status')");
            setShopMessage('Категория добавлена.');
        }
    }
}

if (isset($_POST['edit_category'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['category_name'] ?? '');
    $slotNumber = (int)($_POST['slot_number'] ?? 0);
    $status = ($_POST['category_status'] ?? 'active') === 'hidden' ? 'hidden' : 'active';
    if ($categoryId < 1 || $name === '' || empty($slotDefinitions[$slotNumber])) {
        setShopMessage('Некорректные данные категории.', 'error');
    } else {
        $check = $conn->query("SELECT id FROM shop_categories WHERE slot_number = $slotNumber AND id <> $categoryId");
        if ($check && $check->num_rows > 0) {
            setShopMessage('Слот уже занят другой категорией.', 'error');
        } else {
            $nameEsc = $conn->real_escape_string($name);
            $conn->query("UPDATE shop_categories SET name = '$nameEsc', slot_number = $slotNumber, status = '$status' WHERE id = $categoryId");
            setShopMessage('Категория обновлена.');
        }
    }
}

if (isset($_GET['delete_category'])) {
    $categoryId = (int)$_GET['delete_category'];
    if ($categoryId > 0) {
        $itemsRes = $conn->query("SELECT id FROM items WHERE category_id = $categoryId");
        if ($itemsRes) {
            while ($row = $itemsRes->fetch_assoc()) {
                $itemId = (int)$row['id'];
                $conn->query("DELETE FROM inventory WHERE item_id = $itemId");
            }
        }
        $conn->query("DELETE FROM items WHERE category_id = $categoryId");
        $conn->query("DELETE FROM shop_categories WHERE id = $categoryId");
        setShopMessage('Категория удалена.');
    }
}

if (isset($_POST['add_item'])) {
    $categoryId = (int)($_POST['item_category'] ?? 0);
    $name = trim($_POST['item_name'] ?? '');
    $requiredExp = (int)($_POST['required_exp'] ?? 0);
    $price = (int)($_POST['item_price'] ?? 0);
    $statHealth = (int)($_POST['stat_health'] ?? 0);
    $statStrength = (int)($_POST['stat_strength'] ?? 0);
    $statAgility = (int)($_POST['stat_agility'] ?? 0);
    $statStamina = (int)($_POST['stat_stamina'] ?? 0);
    $statPerception = (int)($_POST['stat_perception'] ?? 0);
    $statCunning = (int)($_POST['stat_cunning'] ?? 0);
    $statCharisma = (int)($_POST['stat_charisma'] ?? 0);
    $statStr = (int)($_POST['stat_str'] ?? 0);
    $statDef = (int)($_POST['stat_def'] ?? 0);
    $statHp = (int)($_POST['stat_hp'] ?? 0);

    $catRes = $conn->query("SELECT * FROM shop_categories WHERE id = $categoryId");
    $category = $catRes ? $catRes->fetch_assoc() : null;
    if ($name === '' || !$category) {
        setShopMessage('Выберите категорию и введите название предмета.', 'error');
    } else {
        $slotNumber = (int)$category['slot_number'];
        $slotKey = $slotDefinitions[$slotNumber]['key'];
        $imagePath = uploadShopImage($_FILES['item_image'] ?? []);
        $nameEsc = $conn->real_escape_string($name);
        $imageEsc = $conn->real_escape_string($imagePath);
        $conn->query("INSERT INTO items (name, slot, stat_str, stat_def, stat_hp, price, image, category_id, required_exp, stat_health, stat_strength, stat_agility, stat_stamina, stat_perception, stat_cunning, stat_charisma)
                      VALUES ('$nameEsc', '$slotKey', $statStr, $statDef, $statHp, $price, '$imageEsc', $categoryId, $requiredExp, $statHealth, $statStrength, $statAgility, $statStamina, $statPerception, $statCunning, $statCharisma)");
        setShopMessage('Предмет добавлен.');
    }
}

if (isset($_POST['edit_item'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $categoryId = (int)($_POST['item_category'] ?? 0);
    $name = trim($_POST['item_name'] ?? '');
    $requiredExp = (int)($_POST['required_exp'] ?? 0);
    $price = (int)($_POST['item_price'] ?? 0);
    $statHealth = (int)($_POST['stat_health'] ?? 0);
    $statStrength = (int)($_POST['stat_strength'] ?? 0);
    $statAgility = (int)($_POST['stat_agility'] ?? 0);
    $statStamina = (int)($_POST['stat_stamina'] ?? 0);
    $statPerception = (int)($_POST['stat_perception'] ?? 0);
    $statCunning = (int)($_POST['stat_cunning'] ?? 0);
    $statCharisma = (int)($_POST['stat_charisma'] ?? 0);
    $statStr = (int)($_POST['stat_str'] ?? 0);
    $statDef = (int)($_POST['stat_def'] ?? 0);
    $statHp = (int)($_POST['stat_hp'] ?? 0);

    $catRes = $conn->query("SELECT * FROM shop_categories WHERE id = $categoryId");
    $category = $catRes ? $catRes->fetch_assoc() : null;
    if ($itemId < 1 || $name === '' || !$category) {
        setShopMessage('Некорректные данные предмета.', 'error');
    } else {
        $slotNumber = (int)$category['slot_number'];
        $slotKey = $slotDefinitions[$slotNumber]['key'];
        $imagePath = uploadShopImage($_FILES['item_image'] ?? []);
        $imageSql = $imagePath !== '' ? ", image = '" . $conn->real_escape_string($imagePath) . "'" : '';
        $nameEsc = $conn->real_escape_string($name);
        $conn->query("UPDATE items SET name = '$nameEsc', slot = '$slotKey', category_id = $categoryId, required_exp = $requiredExp, price = $price,
                      stat_health = $statHealth, stat_strength = $statStrength, stat_agility = $statAgility, stat_stamina = $statStamina,
                      stat_perception = $statPerception, stat_cunning = $statCunning, stat_charisma = $statCharisma,
                      stat_str = $statStr, stat_def = $statDef, stat_hp = $statHp $imageSql WHERE id = $itemId");
        setShopMessage('Предмет обновлен.');
    }
}

if (isset($_GET['delete_item'])) {
    $itemId = (int)$_GET['delete_item'];
    if ($itemId > 0) {
        $imgRes = $conn->query("SELECT image FROM items WHERE id = $itemId");
        if ($imgRes && $imgRow = $imgRes->fetch_assoc()) {
            $imgPath = $imgRow['image'] ?? '';
            if ($imgPath && file_exists(__DIR__ . '/' . $imgPath)) {
                unlink(__DIR__ . '/' . $imgPath);
            }
        }
        $conn->query("DELETE FROM inventory WHERE item_id = $itemId");
        $conn->query("DELETE FROM items WHERE id = $itemId");
        setShopMessage('Предмет удален.');
    }
}

if (isset($_POST['buy_item'])) {
    $itemId = (int)($_POST['buy_item'] ?? 0);
    $itemRes = $conn->query("SELECT i.*, c.status as category_status FROM items i LEFT JOIN shop_categories c ON i.category_id = c.id WHERE i.id = $itemId");
    $item = $itemRes ? $itemRes->fetch_assoc() : null;
    if (!$item) {
        setShopMessage('Предмет не найден.', 'error');
    } elseif ($item['category_status'] === 'hidden') {
        setShopMessage('Слот категории заблокирован.', 'error');
    } elseif ((int)$currentUser['money'] < (int)$item['price']) {
        setShopMessage('Недостаточно золота.', 'error');
    } elseif ((int)$currentUser['exp'] < (int)$item['required_exp']) {
        setShopMessage('Недостаточно опыта для покупки.', 'error');
    } else {
        $price = (int)$item['price'];
        $conn->query("UPDATE users SET money = money - $price WHERE id = {$currentUser['id']}");
        $conn->query("INSERT INTO inventory (user_id, item_id, is_equipped) VALUES ({$currentUser['id']}, $itemId, 0)");
        setShopMessage('Покупка успешна. Предмет добавлен в инвентарь.');
    }
}

$categories = fetchShopCategories();
$items = fetchShopItems();
?>

<div class="shop-wrapper">
    <div class="shop-header">Магазин</div>

    <?php if ($shopMessage): ?>
        <div class="shop-message <?= $shopMessageType ?>"><?= htmlspecialchars($shopMessage) ?></div>
    <?php endif; ?>

    <div class="shop-panels">
        <div class="shop-panel">
            <h3>Категории слотов</h3>
            <form method="post" class="shop-form">
                <input type="hidden" name="add_category" value="1">
                <input type="text" name="category_name" placeholder="Название категории" required>
                <select name="slot_number" required>
                    <option value="">Слот</option>
                    <?php foreach ($slotDefinitions as $slotNumber => $slotData): ?>
                        <option value="<?= $slotNumber ?>">#<?= $slotNumber ?> - <?= $slotData['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="category_status">
                    <option value="active">Активный</option>
                    <option value="hidden">Скрытый</option>
                </select>
                <button type="submit" class="shop-btn">Добавить категорию</button>
            </form>

            <?php if (empty($categories)): ?>
                <div class="shop-empty">Категорий пока нет.</div>
            <?php endif; ?>
            <?php foreach ($categories as $cat): ?>
                <div class="shop-row">
                    <?php $slotLabel = $slotDefinitions[$cat['slot_number']]['label'] ?? 'Неизвестно'; ?>
                    <div class="shop-row-info">
                        <div class="shop-row-title"><?= htmlspecialchars($cat['name']) ?></div>
                        <div class="shop-row-meta">Слот #<?= $cat['slot_number'] ?> • <?= $slotLabel ?></div>
                    </div>
                    <div class="shop-row-status <?= $cat['status'] === 'hidden' ? 'hidden' : 'active' ?>">
                        <?= $cat['status'] === 'hidden' ? 'Скрыт' : 'Активен' ?>
                    </div>
                    <div class="shop-row-actions">
                        <form method="post" class="shop-inline-form">
                            <input type="hidden" name="edit_category" value="1">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <input type="text" name="category_name" value="<?= htmlspecialchars($cat['name']) ?>" required>
                            <select name="slot_number" required>
                                <?php foreach ($slotDefinitions as $slotNumber => $slotData): ?>
                                    <option value="<?= $slotNumber ?>" <?= (int)$cat['slot_number'] === $slotNumber ? 'selected' : '' ?>>#<?= $slotNumber ?> - <?= $slotData['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="category_status">
                                <option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Активный</option>
                                <option value="hidden" <?= $cat['status'] === 'hidden' ? 'selected' : '' ?>>Скрытый</option>
                            </select>
                            <button type="submit" class="shop-btn small">Сохранить</button>
                        </form>
                        <a class="shop-btn danger small" href="?page=shop&delete_category=<?= $cat['id'] ?>">Удалить</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="shop-panel">
            <h3>Добавить вещь</h3>
            <form method="post" class="shop-form" enctype="multipart/form-data">
                <input type="hidden" name="add_item" value="1">
                <input type="text" name="item_name" placeholder="Название предмета" required>
                <select name="item_category" required>
                    <option value="">Категория</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (слот #<?= $cat['slot_number'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="shop-grid">
                    <input type="number" name="required_exp" placeholder="Требуемый опыт" min="0" value="0">
                    <input type="number" name="item_price" placeholder="Стоимость" min="0" value="0">
                </div>
                <div class="shop-grid">
                    <input type="number" name="stat_health" placeholder="Здоровье" value="0">
                    <input type="number" name="stat_strength" placeholder="Сила" value="0">
                    <input type="number" name="stat_agility" placeholder="Ловкость" value="0">
                    <input type="number" name="stat_stamina" placeholder="Выносливость" value="0">
                    <input type="number" name="stat_perception" placeholder="Внимательность" value="0">
                    <input type="number" name="stat_cunning" placeholder="Хитрость" value="0">
                    <input type="number" name="stat_charisma" placeholder="Харизма" value="0">
                </div>
                <div class="shop-grid">
                    <input type="number" name="stat_str" placeholder="Бонус к урону" value="0">
                    <input type="number" name="stat_def" placeholder="Бонус к броне" value="0">
                    <input type="number" name="stat_hp" placeholder="Бонус к HP" value="0">
                </div>
                <input type="file" name="item_image" accept="image/*">
                <button type="submit" class="shop-btn">Добавить вещь</button>
            </form>

            <h3>Список вещей</h3>
            <?php if (empty($items)): ?>
                <div class="shop-empty">Вещей пока нет.</div>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <?php
                    $canBuy = ($item['category_status'] !== 'hidden')
                        && ((int)$currentUser['money'] >= (int)$item['price'])
                        && ((int)$currentUser['exp'] >= (int)$item['required_exp']);
                    $buyReason = '';
                    if ($item['category_status'] === 'hidden') {
                        $buyReason = 'Слот категории скрыт';
                    } elseif ((int)$currentUser['exp'] < (int)$item['required_exp']) {
                        $buyReason = 'Нужен опыт';
                    } elseif ((int)$currentUser['money'] < (int)$item['price']) {
                        $buyReason = 'Не хватает золота';
                    }
                ?>
                <div class="shop-item">
                    <div class="shop-item-preview">
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="item">
                        <?php else: ?>
                            <div class="shop-item-placeholder">?</div>
                        <?php endif; ?>
                    </div>
                    <div class="shop-item-info">
                        <div class="shop-item-title"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="shop-item-meta">
                            Категория: <?= htmlspecialchars($item['category_name'] ?? 'Без категории') ?> · Слот #<?= (int)($item['slot_number'] ?? 0) ?> ·
                            Опыт: <?= (int)$item['required_exp'] ?> · Цена: <?= (int)$item['price'] ?>
                        </div>
                        <div class="shop-item-stats">
                            +<?= (int)$item['stat_health'] ?> Здр · +<?= (int)$item['stat_strength'] ?> Сил · +<?= (int)$item['stat_agility'] ?> Лов ·
                            +<?= (int)$item['stat_stamina'] ?> Вын · +<?= (int)$item['stat_perception'] ?> Вним · +<?= (int)$item['stat_cunning'] ?> Хит ·
                            +<?= (int)$item['stat_charisma'] ?> Хар
                        </div>
                        <div class="shop-item-stats">
                            +<?= (int)$item['stat_str'] ?> Урон · +<?= (int)$item['stat_def'] ?> Броня · +<?= (int)$item['stat_hp'] ?> HP
                        </div>
                        <?php if (!$canBuy): ?>
                            <div class="shop-item-meta">Недоступно: <?= htmlspecialchars($buyReason) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="shop-item-actions">
                        <form method="post" class="shop-inline-form" enctype="multipart/form-data">
                            <input type="hidden" name="edit_item" value="1">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="text" name="item_name" value="<?= htmlspecialchars($item['name']) ?>" required>
                            <select name="item_category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (int)$item['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?> (слот #<?= $cat['slot_number'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="shop-grid">
                                <input type="number" name="required_exp" value="<?= (int)$item['required_exp'] ?>" min="0">
                                <input type="number" name="item_price" value="<?= (int)$item['price'] ?>" min="0">
                            </div>
                            <div class="shop-grid">
                                <input type="number" name="stat_health" value="<?= (int)$item['stat_health'] ?>">
                                <input type="number" name="stat_strength" value="<?= (int)$item['stat_strength'] ?>">
                                <input type="number" name="stat_agility" value="<?= (int)$item['stat_agility'] ?>">
                                <input type="number" name="stat_stamina" value="<?= (int)$item['stat_stamina'] ?>">
                                <input type="number" name="stat_perception" value="<?= (int)$item['stat_perception'] ?>">
                                <input type="number" name="stat_cunning" value="<?= (int)$item['stat_cunning'] ?>">
                                <input type="number" name="stat_charisma" value="<?= (int)$item['stat_charisma'] ?>">
                            </div>
                            <div class="shop-grid">
                                <input type="number" name="stat_str" value="<?= (int)$item['stat_str'] ?>">
                                <input type="number" name="stat_def" value="<?= (int)$item['stat_def'] ?>">
                                <input type="number" name="stat_hp" value="<?= (int)$item['stat_hp'] ?>">
                            </div>
                            <input type="file" name="item_image" accept="image/*">
                            <button type="submit" class="shop-btn small">Сохранить</button>
                        </form>
                        <a class="shop-btn danger small" href="?page=shop&delete_item=<?= $item['id'] ?>">Удалить</a>
                        <form method="post" class="shop-inline-form">
                            <button type="submit" name="buy_item" value="<?= $item['id'] ?>" class="shop-btn small" <?= $canBuy ? '' : 'disabled' ?>>Купить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
