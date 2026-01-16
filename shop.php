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
    <div class="page-header">
        <div class="page-title">Магазин</div>
        <div class="page-actions">
            <button type="button" class="ui-btn ui-btn--secondary" data-open-modal="modal-add-category">Добавить категорию</button>
            <button type="button" class="ui-btn" data-open-modal="modal-add-item">Добавить вещь</button>
        </div>
    </div>

    <?php if ($shopMessage): ?>
        <div class="shop-message <?= $shopMessageType ?>"><?= htmlspecialchars($shopMessage) ?></div>
    <?php endif; ?>

    <div class="shop-panels">
        <div class="shop-panel">
            <h3>Категории слотов</h3>
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
                        <button type="button"
                                class="ui-btn ui-btn--ghost"
                                data-edit-category
                                data-category-id="<?= $cat['id'] ?>"
                                data-category-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>"
                                data-slot-number="<?= (int)$cat['slot_number'] ?>"
                                data-status="<?= $cat['status'] ?>">
                            Редактировать
                        </button>
                        <a class="ui-btn ui-btn--danger"
                           href="?page=shop&delete_category=<?= $cat['id'] ?>"
                           onclick="return confirm('Удалить категорию и все предметы внутри?');">
                           Удалить
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="shop-panel">
            <h3>Витрина вещей</h3>
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
                        <button type="button"
                                class="ui-btn ui-btn--ghost"
                                data-edit-item
                                data-item-id="<?= $item['id'] ?>"
                                data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                                data-item-category="<?= (int)$item['category_id'] ?>"
                                data-item-exp="<?= (int)$item['required_exp'] ?>"
                                data-item-price="<?= (int)$item['price'] ?>"
                                data-stat-health="<?= (int)$item['stat_health'] ?>"
                                data-stat-strength="<?= (int)$item['stat_strength'] ?>"
                                data-stat-agility="<?= (int)$item['stat_agility'] ?>"
                                data-stat-stamina="<?= (int)$item['stat_stamina'] ?>"
                                data-stat-perception="<?= (int)$item['stat_perception'] ?>"
                                data-stat-cunning="<?= (int)$item['stat_cunning'] ?>"
                                data-stat-charisma="<?= (int)$item['stat_charisma'] ?>"
                                data-stat-str="<?= (int)$item['stat_str'] ?>"
                                data-stat-def="<?= (int)$item['stat_def'] ?>"
                                data-stat-hp="<?= (int)$item['stat_hp'] ?>"
                                data-item-image="<?= htmlspecialchars($item['image'] ?? '', ENT_QUOTES) ?>">
                            Редактировать
                        </button>
                        <a class="ui-btn ui-btn--danger"
                           href="?page=shop&delete_item=<?= $item['id'] ?>"
                           onclick="return confirm('Удалить предмет?');">
                           Удалить
                        </a>
                        <form method="post">
                            <button type="submit" name="buy_item" value="<?= $item['id'] ?>" class="ui-btn ui-btn--secondary" <?= $canBuy ? '' : 'disabled' ?>>Купить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal" id="modal-add-category" aria-hidden="true">
    <div class="modal-overlay" data-close-modal></div>
    <div class="modal-card light">
        <button class="modal-close" type="button" data-close-modal>×</button>
        <div class="modal-title">Новая категория</div>
        <form method="post" class="form-grid">
            <input type="hidden" name="add_category" value="1">
            <input class="ui-input" type="text" name="category_name" placeholder="Название категории" required>
            <select class="ui-select" name="slot_number" required>
                <option value="">Слот</option>
                <?php foreach ($slotDefinitions as $slotNumber => $slotData): ?>
                    <option value="<?= $slotNumber ?>">#<?= $slotNumber ?> - <?= $slotData['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <select class="ui-select" name="category_status">
                <option value="active">Активный</option>
                <option value="hidden">Скрытый</option>
            </select>
            <button type="submit" class="ui-btn ui-btn--secondary">Сохранить</button>
        </form>
    </div>
</div>

<div class="modal" id="modal-edit-category" aria-hidden="true">
    <div class="modal-overlay" data-close-modal></div>
    <div class="modal-card light">
        <button class="modal-close" type="button" data-close-modal>×</button>
        <div class="modal-title">Редактировать категорию</div>
        <form method="post" class="form-grid" id="edit-category-form">
            <input type="hidden" name="edit_category" value="1">
            <input type="hidden" name="category_id" id="edit-category-id">
            <input class="ui-input" type="text" name="category_name" id="edit-category-name" required>
            <select class="ui-select" name="slot_number" id="edit-category-slot" required>
                <?php foreach ($slotDefinitions as $slotNumber => $slotData): ?>
                    <option value="<?= $slotNumber ?>">#<?= $slotNumber ?> - <?= $slotData['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <select class="ui-select" name="category_status" id="edit-category-status">
                <option value="active">Активный</option>
                <option value="hidden">Скрытый</option>
            </select>
            <button type="submit" class="ui-btn ui-btn--secondary">Сохранить</button>
        </form>
    </div>
</div>

<div class="modal" id="modal-add-item" aria-hidden="true">
    <div class="modal-overlay" data-close-modal></div>
    <div class="modal-card light">
        <button class="modal-close" type="button" data-close-modal>×</button>
        <div class="modal-title">Новая вещь</div>
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="add_item" value="1">
            <input class="ui-input" type="text" name="item_name" placeholder="Название предмета" required>
            <select class="ui-select" name="item_category" required>
                <option value="">Категория</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (слот #<?= $cat['slot_number'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="form-grid two">
                <input class="ui-input" type="number" name="required_exp" placeholder="Требуемый опыт" min="0" value="0">
                <input class="ui-input" type="number" name="item_price" placeholder="Стоимость" min="0" value="0">
            </div>
            <div class="form-grid three">
                <input class="ui-input" type="number" name="stat_health" placeholder="Здоровье" value="0">
                <input class="ui-input" type="number" name="stat_strength" placeholder="Сила" value="0">
                <input class="ui-input" type="number" name="stat_agility" placeholder="Ловкость" value="0">
                <input class="ui-input" type="number" name="stat_stamina" placeholder="Выносливость" value="0">
                <input class="ui-input" type="number" name="stat_perception" placeholder="Внимательность" value="0">
                <input class="ui-input" type="number" name="stat_cunning" placeholder="Хитрость" value="0">
                <input class="ui-input" type="number" name="stat_charisma" placeholder="Харизма" value="0">
            </div>
            <div class="form-grid three">
                <input class="ui-input" type="number" name="stat_str" placeholder="Бонус к урону" value="0">
                <input class="ui-input" type="number" name="stat_def" placeholder="Бонус к броне" value="0">
                <input class="ui-input" type="number" name="stat_hp" placeholder="Бонус к HP" value="0">
            </div>
            <input class="ui-input" type="file" name="item_image" accept="image/*">
            <button type="submit" class="ui-btn ui-btn--secondary">Сохранить</button>
        </form>
    </div>
</div>

<div class="modal" id="modal-edit-item" aria-hidden="true">
    <div class="modal-overlay" data-close-modal></div>
    <div class="modal-card light">
        <button class="modal-close" type="button" data-close-modal>×</button>
        <div class="modal-title">Редактировать вещь</div>
        <div class="shop-modal-preview">
            <img id="edit-item-preview" src="" alt="item">
        </div>
        <form method="post" class="form-grid" enctype="multipart/form-data" id="edit-item-form">
            <input type="hidden" name="edit_item" value="1">
            <input type="hidden" name="item_id" id="edit-item-id">
            <input class="ui-input" type="text" name="item_name" id="edit-item-name" required>
            <select class="ui-select" name="item_category" id="edit-item-category" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?> (слот #<?= $cat['slot_number'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="form-grid two">
                <input class="ui-input" type="number" name="required_exp" id="edit-item-exp" min="0">
                <input class="ui-input" type="number" name="item_price" id="edit-item-price" min="0">
            </div>
            <div class="form-grid three">
                <input class="ui-input" type="number" name="stat_health" id="edit-item-health">
                <input class="ui-input" type="number" name="stat_strength" id="edit-item-strength">
                <input class="ui-input" type="number" name="stat_agility" id="edit-item-agility">
                <input class="ui-input" type="number" name="stat_stamina" id="edit-item-stamina">
                <input class="ui-input" type="number" name="stat_perception" id="edit-item-perception">
                <input class="ui-input" type="number" name="stat_cunning" id="edit-item-cunning">
                <input class="ui-input" type="number" name="stat_charisma" id="edit-item-charisma">
            </div>
            <div class="form-grid three">
                <input class="ui-input" type="number" name="stat_str" id="edit-item-stat-str">
                <input class="ui-input" type="number" name="stat_def" id="edit-item-stat-def">
                <input class="ui-input" type="number" name="stat_hp" id="edit-item-stat-hp">
            </div>
            <input class="ui-input" type="file" name="item_image" accept="image/*">
            <button type="submit" class="ui-btn ui-btn--secondary">Сохранить</button>
        </form>
    </div>
</div>

<script>
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.classList.add('modal-open');
    };

    const closeModal = (modal) => {
        modal.classList.remove('is-open');
        document.body.classList.remove('modal-open');
    };

    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn.dataset.openModal));
    });

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal');
            if (modal) closeModal(modal);
        });
    });

    document.querySelectorAll('[data-edit-category]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-category-id').value = btn.dataset.categoryId;
            document.getElementById('edit-category-name').value = btn.dataset.categoryName;
            document.getElementById('edit-category-slot').value = btn.dataset.slotNumber;
            document.getElementById('edit-category-status').value = btn.dataset.status;
            openModal('modal-edit-category');
        });
    });

    document.querySelectorAll('[data-edit-item]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-item-id').value = btn.dataset.itemId;
            document.getElementById('edit-item-name').value = btn.dataset.itemName;
            document.getElementById('edit-item-category').value = btn.dataset.itemCategory;
            document.getElementById('edit-item-exp').value = btn.dataset.itemExp;
            document.getElementById('edit-item-price').value = btn.dataset.itemPrice;
            document.getElementById('edit-item-health').value = btn.dataset.statHealth;
            document.getElementById('edit-item-strength').value = btn.dataset.statStrength;
            document.getElementById('edit-item-agility').value = btn.dataset.statAgility;
            document.getElementById('edit-item-stamina').value = btn.dataset.statStamina;
            document.getElementById('edit-item-perception').value = btn.dataset.statPerception;
            document.getElementById('edit-item-cunning').value = btn.dataset.statCunning;
            document.getElementById('edit-item-charisma').value = btn.dataset.statCharisma;
            document.getElementById('edit-item-stat-str').value = btn.dataset.statStr;
            document.getElementById('edit-item-stat-def').value = btn.dataset.statDef;
            document.getElementById('edit-item-stat-hp').value = btn.dataset.statHp;

            const preview = document.getElementById('edit-item-preview');
            const img = btn.dataset.itemImage;
            preview.src = img ? img : '';
            preview.style.display = img ? 'block' : 'none';
            openModal('modal-edit-item');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.is-open').forEach((modal) => closeModal(modal));
        }
    });
</script>
