<?php
include_once __DIR__ . "/includes/auth_check.php";
$pageTitle = "Raporlar";

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$whereIncome = [];
$paramsIncome = [];

$whereExpense = [];
$paramsExpense = [];

if ($start !== '') {
    $whereIncome[] = "income.date >= :start";
    $paramsIncome['start'] = $start;

    $whereExpense[] = "expense.date >= :start";
    $paramsExpense['start'] = $start;
}

if ($end !== '') {
    $whereIncome[] = "income.date <= :end";
    $paramsIncome['end'] = $end;

    $whereExpense[] = "expense.date <= :end";
    $paramsExpense['end'] = $end;
}

$gelirSql = "SELECT COALESCE(SUM(amount),0) FROM income";

if (!empty($whereIncome)) {
    $gelirSql .= " WHERE " . implode(" AND ", $whereIncome);
}

$stmt = $pdo->prepare($gelirSql);
$stmt->execute($paramsIncome);
$toplamGelir = (float)$stmt->fetchColumn();

$giderSql = "SELECT COALESCE(SUM(amount),0) FROM expense";

if (!empty($whereExpense)) {
    $giderSql .= " WHERE " . implode(" AND ", $whereExpense);
}

$stmt = $pdo->prepare($giderSql);
$stmt->execute($paramsExpense);
$toplamGider = (float)$stmt->fetchColumn();

$netKazanc = $toplamGelir - $toplamGider;

$gelirKategoriSql = "
    SELECT 
        income_categories.name AS category_name,
        COALESCE(SUM(income.amount),0) AS total
    FROM income
    LEFT JOIN income_categories ON income.category_id = income_categories.id
";

if (!empty($whereIncome)) {
    $gelirKategoriSql .= " WHERE " . implode(" AND ", $whereIncome);
}
$gelirKategoriSql .= " GROUP BY income.category_id, income_categories.name";
$gelirKategoriSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($gelirKategoriSql);
$stmt->execute($paramsIncome);
$gelirKategoriToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$giderKategoriSql = "
    SELECT 
        expense_categories.name AS category_name,
        COALESCE(SUM(expense.amount),0) AS total
    FROM expense
    LEFT JOIN expense_categories ON expense.category_id = expense_categories.id
";

if (!empty($whereExpense)) {
    $giderKategoriSql .= " WHERE " . implode(" AND ", $whereExpense);
}

$giderKategoriSql .= " GROUP BY expense.category_id, expense_categories.name";
$giderKategoriSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($giderKategoriSql);
$stmt->execute($paramsExpense);
$giderKategoriToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$projeTahsilatSql = "
    SELECT 
        projects.name AS project_name,
        COALESCE(SUM(income.amount),0) AS total
    FROM income
    INNER JOIN projects ON income.project_id = projects.id
";

$projeWhere = ["income.project_id IS NOT NULL"];

if (!empty($whereIncome)) {
    $projeWhere = array_merge($projeWhere, $whereIncome);
}

if (!empty($projeWhere)) {
    $projeTahsilatSql .= " WHERE " . implode(" AND ", $projeWhere);
}

$projeTahsilatSql .= " GROUP BY income.project_id, projects.name";
$projeTahsilatSql .= " ORDER BY total DESC";

$stmt = $pdo->prepare($projeTahsilatSql);
$stmt->execute($paramsIncome);
$projeTahsilatToplamlari = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enCokGelirKategori = $gelirKategoriToplamlari[0] ?? null;
$enCokGiderKategori = $giderKategoriToplamlari[0] ?? null;
$enCokTahsilatProje = $projeTahsilatToplamlari[0] ?? null;

include_once __DIR__ . "/includes/header.php";

function tl($v)
{
    return number_format((float)$v, 2, ',', '.') . " TL";
}
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Rapor Filtreleri</h2>
                <p class="text-muted mb-0">Raporları tarih aralığına göre filtreleyin.</p>
            </div>

            <div class="card-body pt-4">
                <form method="GET" action="reports.php">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="start" class="form-label">Başlangıç Tarihi</label>
                            <input
                                type="date"
                                name="start"
                                id="start"
                                class="form-control"
                                value="<?php echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="end" class="form-label">Bitiş Tarihi</label>
                            <input
                                type="date"
                                name="end"
                                id="end"
                                class="form-control"
                                value="<?php echo htmlspecialchars($end, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="reports.php" class="btn btn-outline-secondary">Temizle</a>
                                <a href="reports_print.php?start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>"
                                    target="_blank"
                                    class="btn btn-outline-dark">
                                    Raporu Yazdır / PDF Al
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Toplam Gelir</small>
                        <div class="fs-4 fw-bold text-success"><?php echo tl($toplamGelir); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Toplam Gider</small>
                        <div class="fs-4 fw-bold text-danger"><?php echo tl($toplamGider); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Net Kazanç</small>
                        <div class="fs-4 fw-bold <?php echo ($netKazanc >= 0) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo tl($netKazanc); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">En Çok Gelir Getiren Kategori</small>
                        <div class="fw-semibold mb-2">
                            <?php echo htmlspecialchars($enCokGelirKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="fs-5 fw-bold text-success">
                            <?php echo tl($enCokGelirKategori['total'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">En Çok Gider Olan Kategori</small>
                        <div class="fw-semibold mb-2">
                            <?php echo htmlspecialchars($enCokGiderKategori['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="fs-5 fw-bold text-danger">
                            <?php echo tl($enCokGiderKategori['total'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">En Çok Tahsilat Gelen Proje</small>
                        <div class="fw-semibold mb-2">
                            <?php echo htmlspecialchars($enCokTahsilatProje['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="fs-5 fw-bold text-primary">
                            <?php echo tl($enCokTahsilatProje['total'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gelir Kategorilerine Göre Toplamlar</h2>
                <p class="text-muted mb-0">Gelirlerin kategori bazlı dağılımı.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($gelirKategoriToplamlari)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($gelirKategoriToplamlari as $kategori): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kategori['category_name'] ?? 'Kategori Silinmiş', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold text-success"><?php echo tl($kategori['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Gider Kategorilerine Göre Toplamlar</h2>
                <p class="text-muted mb-0">Giderlerin kategori bazlı dağılımı.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($giderKategoriToplamlari)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($giderKategoriToplamlari as $kategori): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kategori['category_name'] ?? 'Kategori Silinmiş', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold text-danger"><?php echo tl($kategori['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Projelere Göre Tahsilat Toplamları</h2>
                <p class="text-muted mb-0">Projelerin tahsilat bazlı dağılımı.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Proje</th>
                                <th>Toplam Tahsilat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($projeTahsilatToplamlari)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($projeTahsilatToplamlari as $proje): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proje['project_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="fw-semibold text-primary"><?php echo tl($proje['total']); ?></td>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>