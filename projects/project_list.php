<?php
require_once __DIR__ . "/../includes/auth_check.php";
$pageTitle = "Proje Listesi";

$where = [];
$params = [];
$q = trim($_GET['q'] ?? '');
$customer = trim($_GET['customer'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'id_desc';

if ($q !== '') {
    $where[] = "projects.name LIKE :q";
    $params['q'] = "%{$q}%";
}

if ($customer !== '') {
    $where[] = "cari_accounts.name LIKE :customer";
    $params['customer'] = "%{$customer}%";
}

if ($status !== '' && ($status === '1' || $status === '0')) {
    $where[] = "projects.is_active = :status";
    $params['status'] = (int)$status;
}

$sql = "SELECT projects.*, cari_accounts.name AS customer_name
        FROM projects
        LEFT JOIN cari_accounts ON projects.customer_id = cari_accounts.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$orderBy = "projects.id DESC";

if ($sort === 'id_asc') {
    $orderBy = "projects.id ASC";
} elseif ($sort === 'amount_asc') {
    $orderBy = "projects.total_amount ASC";
} elseif ($sort === 'amount_desc') {
    $orderBy = "projects.total_amount DESC";
} elseif ($sort === 'date_asc') {
    $orderBy = "projects.start_date ASC";
} elseif ($sort === 'date_desc') {
    $orderBy = "projects.start_date DESC";
}

$sql .= " ORDER BY " . $orderBy;

$sorgu = $pdo->prepare($sql);
$sorgu->execute($params);
$projeler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../includes/header.php";
?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" role="alert">
        Proje silindi.
    </div>
<?php endif; ?>

<?php if (isset($_GET['delete_error'])): ?>
    <div class="alert alert-danger" role="alert">
        Bu projeye ait ödeme planı olduğu için silinemez.
    </div>
<?php endif; ?>

<?php if (isset($_GET['toggled'])): ?>
    <div class="alert alert-success" role="alert">
        Proje durumu güncellendi.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Proje Filtreleri</h2>
                <p class="text-muted mb-0">Projeleri isim, müşteri, durum ve sıralamaya göre filtreleyin.</p>
            </div>

            <div class="card-body pt-4">
                <form method="GET" action="project_list.php">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-3">
                            <label for="q" class="form-label">Proje Adı</label>
                            <input
                                type="text"
                                id="q"
                                name="q"
                                class="form-control"
                                value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label for="customer" class="form-label">Müşteri</label>
                            <input
                                type="text"
                                id="customer"
                                name="customer"
                                class="form-control"
                                value="<?php echo htmlspecialchars($customer, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label for="status" class="form-label">Durum</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">Hepsi</option>
                                <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label for="sort" class="form-label">Sırala</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="id_desc" <?php echo ($sort === 'id_desc') ? 'selected' : ''; ?>>En Yeni</option>
                                <option value="id_asc" <?php echo ($sort === 'id_asc') ? 'selected' : ''; ?>>En Eski</option>
                                <option value="amount_asc" <?php echo ($sort === 'amount_asc') ? 'selected' : ''; ?>>Tutar Artan</option>
                                <option value="amount_desc" <?php echo ($sort === 'amount_desc') ? 'selected' : ''; ?>>Tutar Azalan</option>
                                <option value="date_asc" <?php echo ($sort === 'date_asc') ? 'selected' : ''; ?>>Başlangıç Tarihi Artan</option>
                                <option value="date_desc" <?php echo ($sort === 'date_desc') ? 'selected' : ''; ?>>Başlangıç Tarihi Azalan</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2 pt-2">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="project_list.php" class="btn btn-outline-secondary">Temizle</a>
                                <a href="project_add.php" class="btn btn-success">Proje Ekle</a>
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
                <h2 class="h4 mb-1">Proje Listesi</h2>
                <p class="text-muted mb-0">Tüm projeleri görüntüleyin ve yönetin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table id="projectTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Sıra</th>
                                <th>Proje Adı</th>
                                <th>Müşteri</th>
                                <th>KDV Dahil Toplam</th>
                                <th>Taksit Sayısı</th>
                                <th>Başlangıç Tarihi</th>
                                <th>Durum</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projeler)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Kayıt bulunamadı.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projeler as $proje): ?>
                                    <?php
                                    $toplamTutar = !empty($proje['grand_total'])
                                        ? (float)$proje['grand_total']
                                        : (float)$proje['total_amount'];
                                    ?>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <a href="project_detail.php?id=<?php echo (int)$proje['id']; ?>" class="text-decoration-none fw-semibold">
                                                <?php echo htmlspecialchars($proje['name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($proje['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold text-primary" data-order="<?php echo $toplamTutar; ?>">
                                            <?php echo number_format($toplamTutar, 2, ',', '.'); ?> TL
                                        </td>
                                        <td data-order="<?php echo (int)$proje['installment_count']; ?>">
                                            <?php echo htmlspecialchars($proje['installment_count'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td data-order="<?php echo htmlspecialchars($proje['start_date'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($proje['start_date'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td data-order="<?php echo (int)$proje['is_active']; ?>">
                                            <?php if ((int)$proje['is_active'] === 1): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                                <a href="project_detail.php?id=<?php echo (int)$proje['id']; ?>&edit=1" class="btn btn-sm btn-outline-primary">
                                                    Düzenle
                                                </a>

                                                <a href="../invoice/invoice.php?id=<?php echo (int)$proje['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                                    Fatura
                                                </a>

                                                <a href="project_delete.php?id=<?php echo (int)$proje['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Bu projeyi silmek istediğinize emin misiniz?');">
                                                    Sil
                                                </a>

                                                <form method="POST" action="project_toggle.php" class="m-0">
                                                    <input type="hidden" name="id" value="<?php echo (int)$proje['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        <?php echo ((int)$proje['is_active'] === 1) ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                                    </button>
                                                </form>
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
    var table = $("#projectTable").DataTable({
        pageLength: 10,
        ordering: true,
        searching: true,
        info: true,
        lengthMenu: [10, 25, 50, 100],
        order: [[5, "desc"]],
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
                targets: 7
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