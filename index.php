<?php
require_once 'db.php';

// Fetch products
$result = $conn->query("SELECT id, name, price, created_at FROM products ORDER BY id DESC");
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Mini E-Commerce Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1>Mini E-Commerce Dashboard</h1>
    <p class="subtitle">Add and manage products (PHP + MySQL)</p>

    <form class="card form" action="add_product.php" method="POST">
      <div class="row">
        <label>Product name</label>
        <input type="text" name="name" placeholder="e.g., Hoodie ROOTED" required maxlength="255">
      </div>

      <div class="row">
        <label>Price (€)</label>
        <input type="number" name="price" placeholder="e.g., 39.99" required step="0.01" min="0">
      </div>

      <button type="submit">Add product</button>
    </form>

    <div class="card">
      <h2>Products</h2>

      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th class="right">Price</th>
            <th>Created</th>
            <th class="right">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr>
              <td colspan="4" class="empty">No products yet. Add your first one above 👆</td>
            </tr>
          <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td class="right"><?= number_format((float)$row['price'], 2) ?> €</td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="right">
                  <a class="danger" href="delete_product.php?id=<?= (int)$row['id'] ?>"
                     onclick="return confirm('Delete this product?');">
                    Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <footer>
      <span>Built by Weam • GitHub Portfolio Project</span>
    </footer>
  </div>
</body>
</html>
