<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$notice = "";

// ---------- Add a new item ----------
if (isset($_POST['add_item'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $category = $_POST['category'];
    $condition = $_POST['item_condition'];

    if ($title !== "") {
        $stmt = $conn->prepare("INSERT INTO items(owner_id, title, description, category, item_condition) VALUES(?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $desc, $category, $condition]);
        $notice = "Added \"$title\" to the shed!";
    }
}

// ---------- Delete an item ----------
if (isset($_GET['delete_item'])) {
    $item_id = $_GET['delete_item'];
    $stmt = $conn->prepare("DELETE FROM items WHERE id = ? AND owner_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $notice = "Item removed.";
}

// ---------- Request to borrow an item ----------
if (isset($_GET['request'])) {
    $item_id = $_GET['request'];

    // make sure the item exists, is available, and isn't the user's own item
    $check = $conn->prepare("SELECT * FROM items WHERE id = ? AND status = 'available' AND owner_id != ?");
    $check->execute([$item_id, $user_id]);

    if ($check->fetch()) {
        // don't let someone spam the same request twice
        $dup = $conn->prepare("SELECT id FROM borrow_requests WHERE item_id = ? AND borrower_id = ? AND status = 'pending'");
        $dup->execute([$item_id, $user_id]);

        if (!$dup->fetch()) {
            $stmt = $conn->prepare("INSERT INTO borrow_requests(item_id, borrower_id) VALUES(?, ?)");
            $stmt->execute([$item_id, $user_id]);
            $notice = "Request sent! The owner will need to approve it.";
        } else {
            $notice = "You already asked for this one - just waiting on the owner.";
        }
    }
}

// ---------- Owner approves a request ----------
if (isset($_GET['approve'])) {
    $req_id = $_GET['approve'];

    // only allow this if the request is for an item this user owns
    $stmt = $conn->prepare("SELECT br.id, br.item_id FROM borrow_requests br
                             JOIN items i ON br.item_id = i.id
                             WHERE br.id = ? AND i.owner_id = ? AND br.status = 'pending'");
    $stmt->execute([$req_id, $user_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req) {
        $conn->prepare("UPDATE borrow_requests SET status = 'approved' WHERE id = ?")->execute([$req_id]);
        $conn->prepare("UPDATE items SET status = 'borrowed' WHERE id = ?")->execute([$req['item_id']]);
        $notice = "Request approved - happy sharing!";
    }
}

// ---------- Owner declines a request ----------
if (isset($_GET['decline'])) {
    $req_id = $_GET['decline'];
    $stmt = $conn->prepare("UPDATE borrow_requests br
                             JOIN items i ON br.item_id = i.id
                             SET br.status = 'declined'
                             WHERE br.id = ? AND i.owner_id = ? AND br.status = 'pending'");
    $stmt->execute([$req_id, $user_id]);
    $notice = "Request declined.";
}

// ---------- Mark an approved borrow as returned ----------
if (isset($_GET['return'])) {
    $req_id = $_GET['return'];
    // either the owner or the borrower should be able to close the loop
    $stmt = $conn->prepare("SELECT br.id, br.item_id FROM borrow_requests br
                             JOIN items i ON br.item_id = i.id
                             WHERE br.id = ? AND br.status = 'approved'
                             AND (i.owner_id = ? OR br.borrower_id = ?)");
    $stmt->execute([$req_id, $user_id, $user_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req) {
        $conn->prepare("UPDATE borrow_requests SET status = 'returned' WHERE id = ?")->execute([$req_id]);
        $conn->prepare("UPDATE items SET status = 'available' WHERE id = ?")->execute([$req['item_id']]);
        $notice = "Nice, item is back in the shed and available again.";
    }
}

// ---------- Data for the page ----------

// items available from other people
$browse = $conn->prepare("SELECT items.*, users.username AS owner_name
                           FROM items JOIN users ON items.owner_id = users.id
                           WHERE items.status = 'available' AND items.owner_id != ?
                           ORDER BY items.created_at DESC");
$browse->execute([$user_id]);
$browse_items = $browse->fetchAll(PDO::FETCH_ASSOC);

// my own listed items
$mine = $conn->prepare("SELECT * FROM items WHERE owner_id = ? ORDER BY created_at DESC");
$mine->execute([$user_id]);
$my_items = $mine->fetchAll(PDO::FETCH_ASSOC);

// requests other people have sent me for my items
$incoming = $conn->prepare("SELECT br.id, br.status, br.requested_at, items.title, users.username AS borrower_name
                             FROM borrow_requests br
                             JOIN items ON br.item_id = items.id
                             JOIN users ON br.borrower_id = users.id
                             WHERE items.owner_id = ? AND br.status = 'pending'
                             ORDER BY br.requested_at DESC");
$incoming->execute([$user_id]);
$incoming_requests = $incoming->fetchAll(PDO::FETCH_ASSOC);

// stuff I'm currently borrowing / have requested
$my_borrows = $conn->prepare("SELECT br.id, br.status, br.requested_at, items.title, users.username AS owner_name
                               FROM borrow_requests br
                               JOIN items ON br.item_id = items.id
                               JOIN users ON items.owner_id = users.id
                               WHERE br.borrower_id = ? AND br.status IN ('pending','approved')
                               ORDER BY br.requested_at DESC");
$my_borrows->execute([$user_id]);
$my_borrow_list = $my_borrows->fetchAll(PDO::FETCH_ASSOC);

$categories = ['Electronics', 'Lab Tools', 'Textbooks', 'Sports Gear', 'Camera Equipment', 'Other'];
$category_emoji = [
    'Electronics' => '🔌',
    'Lab Tools' => '🔧',
    'Textbooks' => '📚',
    'Sports Gear' => '⚽',
    'Camera Equipment' => '📷',
    'Other' => '📦'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Shed - ToolShed</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="topbar">
        <h1>🧰 ToolShed</h1>
        <div class="who">
            Hi, <strong><?= htmlspecialchars($username) ?></strong> 👋
            <a href="logout.php" class="logout-link">Log out</a>
        </div>
    </header>

    <div class="wrap wide">

        <?php if ($notice): ?>
            <p class="msg msg-success">✅ <?= htmlspecialchars($notice) ?></p>
        <?php endif; ?>

        <?php if ($incoming_requests): ?>
        <section class="card">
            <h2>📥 Requests waiting on you</h2>
            <ul class="req-list">
                <?php foreach ($incoming_requests as $r): ?>
                <li>
                    <span><strong><?= htmlspecialchars($r['borrower_name']) ?></strong> wants to borrow <strong><?= htmlspecialchars($r['title']) ?></strong></span>
                    <span class="actions">
                        <a class="btn btn-yes" href="?approve=<?= (int)$r['id'] ?>">Approve</a>
                        <a class="btn btn-no" href="?decline=<?= (int)$r['id'] ?>">Decline</a>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2>➕ Add something to lend</h2>
            <form method="POST" class="item-form">
                <input type="hidden" name="add_item" value="1">
                <input type="text" name="title" placeholder="What are you sharing? e.g. Soldering Iron" required maxlength="100">
                <textarea name="description" placeholder="Any details other students should know (condition, how to pick up, etc.)"></textarea>
                <div class="row">
                    <select name="category" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= $category_emoji[$c] ?> <?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="item_condition" required>
                        <option>New</option>
                        <option selected>Good</option>
                        <option>Fair</option>
                        <option>Well-loved</option>
                    </select>
                    <button type="submit">Add to shed</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>🔎 Browse what's available</h2>
            <?php if (!$browse_items): ?>
                <p class="empty">Nothing here yet - be the first to lend something above!</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($browse_items as $item): ?>
                    <div class="item-tile">
                        <div class="item-emoji"><?= $category_emoji[$item['category']] ?? '📦' ?></div>
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="item-desc"><?= htmlspecialchars($item['description']) ?></p>
                        <p class="item-meta"><?= htmlspecialchars($item['category']) ?> · Condition: <?= htmlspecialchars($item['item_condition']) ?></p>
                        <p class="item-meta">Owner: <?= htmlspecialchars($item['owner_name']) ?></p>
                        <a class="btn btn-yes full" href="?request=<?= (int)$item['id'] ?>">Ask to borrow</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>📦 My listings</h2>
            <?php if (!$my_items): ?>
                <p class="empty">You haven't listed anything yet.</p>
            <?php else: ?>
                <ul class="mine-list">
                    <?php foreach ($my_items as $item): ?>
                    <li>
                        <span><?= $category_emoji[$item['category']] ?? '📦' ?> <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <span class="tag tag-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></span>
                        <a class="btn btn-no" href="?delete_item=<?= (int)$item['id'] ?>" onclick="return confirm('Remove this item from the shed?');">Delete</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>🙋 Stuff I'm borrowing / waiting on</h2>
            <?php if (!$my_borrow_list): ?>
                <p class="empty">You're not borrowing anything right now.</p>
            <?php else: ?>
                <ul class="req-list">
                    <?php foreach ($my_borrow_list as $r): ?>
                    <li>
                        <span><strong><?= htmlspecialchars($r['title']) ?></strong> from <?= htmlspecialchars($r['owner_name']) ?>
                        <span class="tag tag-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></span>
                        <?php if ($r['status'] === 'approved'): ?>
                            <a class="btn btn-yes" href="?return=<?= (int)$r['id'] ?>">Mark returned</a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

    
</body>
</html>
