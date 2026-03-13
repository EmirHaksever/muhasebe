<?php
include_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Gider Listesi";
include_once __DIR__ . '/../includes/header.php';

$sorguKat = $pdo->prepare("SELECT id, name FROM expense_categories WHERE is_active = 1 ORDER BY name ASC");
$sorguKat->execute();
$kategoriler = $sorguKat->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$min = $_GET['min'] ?? '';
$max = $_GET['max'] ?? '';
$q = trim($_GET['q'] ?? '');

if ($start !== '') {
    $where[] = "expense.date >= :start";
    $params['start'] = $start;
}
if ($end !== '') {
    $where[] = "expense.date <= :end";
    $params['end'] = $end;
}

if ($category_id !== '' && is_numeric($category_id)) {
    $where[] = "expense.category_id = :category_id";
    $params['category_id'] = (int)$category_id;
}

if ($min !== '' && is_numeric($min)) {
    $where[] = "expense.amount >= :min";
    $params['min'] = (float)$min;
}
if ($max !== '' && is_numeric($max)) {
    $where[] = "expense.amount <= :max";
    $params['max'] = (float)$max;
}

if ($q !== '') {
    $where[] = "expense.description LIKE :q";
    $params['q'] = "%{$q}%";
}

$sql = "SELECT expense.id, expense.date, expense.amount, expense.description,
               expense_categories.name AS category_name
        FROM expense
        LEFT JOIN expense_categories ON expense.category_id = expense_categories.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY expense.date DESC";

$sorgu = $pdo->prepare($sql);
$sorgu->execute($params);
$giderler = $sorgu->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gider Filtreleri</h2>
                <p class="text-muted mb-0">Gider kayıtlarını tarih, kategori, tutar ve açıklamaya göre filtreleyin.</p>
            </div>

            <div class="card-body pt-4">
                <form method="GET" action="expense_list.php">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-2">
                            <label for="start" class="form-label">Başlangıç</label>
                            <input
                                type="date"
                                id="start"
                                name="start"
                                class="form-control"
                                value="<?php echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-6 col-xl-2">
                            <label for="end" class="form-label">Bitiş</label>
                            <input
                                type="date"
                                id="end"
                                name="end"
                                class="form-control"
                                value="<?php echo htmlspecialchars($end, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-6 col-xl-2">
                            <label for="category_id" class="form-label">Kategori</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="">Hepsi</option>
                                <?php foreach ($kategoriler as $k): ?>
                                    <option value="<?php echo (int)$k['id']; ?>"
                                        <?php echo ($category_id == $k['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($k['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="min" class="form-label">Min Tutar</label>
                            <input
                                type="text"
                                id="min"
                                name="min"
                                class="form-control"
                                value="<?php echo htmlspecialchars($min, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-6 col-md-3 col-xl-2">
                            <label for="max" class="form-label">Max Tutar</label>
                            <input
                                type="text"
                                id="max"
                                name="max"
                                class="form-control"
                                value="<?php echo htmlspecialchars($max, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-xl-2">
                            <label for="q" class="form-label">Açıklama</label>
                            <input
                                type="text"
                                id="q"
                                name="q"
                                class="form-control"
                                value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2 pt-2">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="expense_list.php" class="btn btn-outline-secondary">Temizle</a>
                                <a href="expense_add.php" class="btn btn-success">Gider Ekle</a>
                                <a href="../expense_categories/expense_category.php" class="btn btn-outline-dark">Gider Kategori Listesi</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gider Listesi</h2>
                <p class="text-muted mb-0">Tüm gider kayıtlarını görüntüleyin ve yönetin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table id="expenseTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sıra</th>
                                <th>Kategori</th>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Açıklama</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($giderler)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Kayıt bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($giderler as $gider): ?>
                                    <tr>
                                        <td></td>
                                        <td><?php echo htmlspecialchars($gider['category_name'] ?? 'Kategori Silinmiş', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-order="<?php echo htmlspecialchars($gider['date'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($gider['date'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="fw-semibold text-danger" data-order="<?php echo (float)$gider['amount']; ?>">
                                            <?php echo number_format((float)$gider['amount'], 2, ',', '.'); ?> TL
                                        </td>
                                        <td><?php echo htmlspecialchars($gider['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <a href="expense_edit.php?id=<?php echo (int)$gider['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    Düzenle
                                                </a>
                                                <a href="expense_delete.php?id=<?php echo (int)$gider['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Bu gider kaydını silmek istediğinize emin misiniz?');">
                                                    Sil
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '
<script>
$(document).ready(function() {
    var table = $("#expenseTable").DataTable({
        pageLength: 10,
        ordering: true,
        searching: true,
        info: true,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, "desc"]],
        language: {
            decimal: ",",
            thousands: ".",
            search: "Ara:",
            lengthMenu: "_MENU_ kayıt göster",
            info: "_TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor",
            infoEmpty: "Kayıt yok",
            zeroRecords: "Eşleşen kayıt bulunamadı",
            paginate: {
                first: "İlk",
                last: "Son",
                next: "Sonraki",
                previous: "Önceki"
            }
        },
        columnDefs: [
            {
                orderable: false,
                searchable: false,
                targets: 0
            },
            {
                orderable: false,
                targets: 5
            }
        ]
    });

    table.on("order.dt search.dt draw.dt", function() {
        let i = 1;
        table.column(0, { search: "applied", order: "applied", page: "current" }).nodes().each(function(cell) {
            cell.innerHTML = i++;
        });
    }).draw();
});
</script>
';

require_once __DIR__ . '/../includes/footer.php';
?>