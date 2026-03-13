<?php
require_once __DIR__ . '/includes/auth_check.php';
$pageTitle = "Hareketler";

$sql = "
    SELECT 
        'Gelir' AS type,
        income.date,
        income.amount,
        income.description,
        income_categories.name AS category_name,
        projects.name AS project_name
    FROM income
    LEFT JOIN income_categories ON income.category_id = income_categories.id
    LEFT JOIN projects ON income.project_id = projects.id

    UNION ALL

    SELECT 
        'Gider' AS type,
        expense.date,
        expense.amount,
        expense.description,
        expense_categories.name AS category_name,
        NULL AS project_name
    FROM expense
    LEFT JOIN expense_categories ON expense.category_id = expense_categories.id

    ORDER BY date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Hareket Filtreleri</h2>
                <p class="text-muted mb-0">Gelir ve gider hareketlerini gelişmiş filtrelerle inceleyin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="filterType" class="form-label">Tür</label>
                        <select id="filterType" class="form-select">
                            <option value="">Hepsi</option>
                            <option value="Gelir">Gelir</option>
                            <option value="Gider">Gider</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="filterCategory" class="form-label">Kategori</label>
                        <select id="filterCategory" class="form-select">
                            <option value="">Hepsi</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="filterProject" class="form-label">Proje</label>
                        <select id="filterProject" class="form-select">
                            <option value="">Hepsi</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="filterDateStart" class="form-label">Tarih Başlangıç</label>
                        <input type="date" id="filterDateStart" class="form-control">
                    </div>

                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="filterDateEnd" class="form-label">Tarih Bitiş</label>
                        <input type="date" id="filterDateEnd" class="form-control">
                    </div>

                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filterMinAmount" class="form-label">Min Tutar</label>
                        <input type="number" id="filterMinAmount" step="0.01" class="form-control" placeholder="0">
                    </div>

                    <div class="col-6 col-md-3 col-xl-1">
                        <label for="filterMaxAmount" class="form-label">Max Tutar</label>
                        <input type="number" id="filterMaxAmount" step="0.01" class="form-control" placeholder="0">
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 pt-2">
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary">Temizle</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h2 class="h4 mb-1">Hareket Listesi</h2>
                <p class="text-muted mb-0">Tüm gelir ve gider hareketlerini görüntüleyin.</p>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table id="movementsTable" class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tür</th>
                                <th>Tarih</th>
                                <th>Kategori</th>
                                <th>Proje</th>
                                <th>Tutar</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hareketler as $hareket): ?>
                                <tr>
                                    <td>
                                        <?php if ($hareket['type'] === 'Gelir'): ?>
                                            <span class="badge bg-success">Gelir</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Gider</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-order="<?php echo htmlspecialchars($hareket['date'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($hareket['date'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($hareket['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['project_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td data-amount="<?php echo (float)$hareket['amount']; ?>" class="fw-semibold <?php echo $hareket['type'] === 'Gelir' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo htmlspecialchars(number_format((float)$hareket['amount'], 2, ',', '.') . ' TL', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($hareket['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '
<script src="https://cdn.datatables.net/2.3.7/js/dataTables.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const table = new DataTable("#movementsTable", {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, "desc"]],
        language: {
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
        }
    });

    const typeFilter = document.getElementById("filterType");
    const categoryFilter = document.getElementById("filterCategory");
    const projectFilter = document.getElementById("filterProject");
    const dateStartFilter = document.getElementById("filterDateStart");
    const dateEndFilter = document.getElementById("filterDateEnd");
    const minAmountFilter = document.getElementById("filterMinAmount");
    const maxAmountFilter = document.getElementById("filterMaxAmount");
    const clearBtn = document.getElementById("clearFilters");

    function fillSelectFromColumn(selectEl, columnIndex) {
        const values = table.column(columnIndex).data().toArray();
        const uniqueValues = [...new Set(values.filter(v => v !== null && v !== undefined && v !== "" && v !== "-"))].sort();

        uniqueValues.forEach(value => {
            const option = document.createElement("option");
            option.value = value.replace(/<[^>]*>/g, "").trim();
            option.textContent = value.replace(/<[^>]*>/g, "").trim();
            selectEl.appendChild(option);
        });
    }

    fillSelectFromColumn(categoryFilter, 2);
    fillSelectFromColumn(projectFilter, 3);

    typeFilter.addEventListener("change", function () {
        table.column(0).search(this.value).draw();
    });

    categoryFilter.addEventListener("change", function () {
        table.column(2).search(this.value).draw();
    });

    projectFilter.addEventListener("change", function () {
        table.column(3).search(this.value).draw();
    });

    DataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== "movementsTable") {
            return true;
        }

        const rowNode = table.row(dataIndex).node();
        const rowDate = data[1];
        const startDate = dateStartFilter.value;
        const endDate = dateEndFilter.value;

        const rowAmount = parseFloat(rowNode.cells[4].getAttribute("data-amount")) || 0;
        const minAmount = minAmountFilter.value !== "" ? parseFloat(minAmountFilter.value) : null;
        const maxAmount = maxAmountFilter.value !== "" ? parseFloat(maxAmountFilter.value) : null;

        if (startDate && rowDate < startDate) {
            return false;
        }

        if (endDate && rowDate > endDate) {
            return false;
        }

        if (minAmount !== null && rowAmount < minAmount) {
            return false;
        }

        if (maxAmount !== null && rowAmount > maxAmount) {
            return false;
        }

        return true;
    });

    dateStartFilter.addEventListener("change", function () {
        table.draw();
    });

    dateEndFilter.addEventListener("change", function () {
        table.draw();
    });

    minAmountFilter.addEventListener("input", function () {
        table.draw();
    });

    maxAmountFilter.addEventListener("input", function () {
        table.draw();
    });

    clearBtn.addEventListener("click", function () {
        typeFilter.value = "";
        categoryFilter.value = "";
        projectFilter.value = "";
        dateStartFilter.value = "";
        dateEndFilter.value = "";
        minAmountFilter.value = "";
        maxAmountFilter.value = "";

        table.columns().search("");
        table.search("");
        table.draw();
    });
});
</script>
';
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>