<?php
// Aktifkan pelaporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi
session_start();

// CEK AUTENTIKASI ADMIN
if (!isset($_SESSION['admin_id'])) {
    if (isset($_COOKIE['admin_id']) && isset($_COOKIE['username'])) {
        $_SESSION['admin_id'] = $_COOKIE['admin_id'];
        $_SESSION['username'] = $_COOKIE['username'];
    } else {
        header("Location: ../Admin/index.php");
        exit;
    }
}

require_once '../include/sidebar.php';
require_once '../Data/db_connect.php';

// CEK STATUS PIN
$pinVerified = $_SESSION['pin_verified_report'] ?? false; // Gunakan variabel spesifik halaman

// AMBIL DATA TAHUN
$years = [];
$sql_years = "SELECT YEAR(order_date) AS year FROM orders GROUP BY year ORDER BY year DESC";
$result_years = $conn->query($sql_years);
while ($row = $result_years->fetch_assoc()) {
    $years[] = $row['year'];
}

// AMBIL DATA BULAN
$months = [];
$sql_months = "SELECT MONTH(order_date) AS month FROM orders GROUP BY month ORDER BY month ASC";
$result_months = $conn->query($sql_months);
while ($row = $result_months->fetch_assoc()) {
    $months[] = $row['month'];
}

// PROSES FILTER
$selected_year = date('Y');
$selected_month = date('n');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $selected_month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
}

// AMBIL DATA PENJUALAN SESUAI TABEL ORDERS
$sales_data = [];
$labels = [];
$sql_sales = "SELECT 
                DAY(order_date) AS day,
                SUM(total_amount) AS total 
              FROM orders 
              WHERE MONTH(order_date) = ? 
                AND YEAR(order_date) = ?
                AND status != 'cancelled' /* Exclude cancelled orders */
              GROUP BY day 
              ORDER BY day ASC";

$stmt = $conn->prepare($sql_sales);
$stmt->bind_param("ii", $selected_month, $selected_year);
$stmt->execute();
$result_sales = $stmt->get_result();

// Inisialisasi array untuk semua hari dalam bulan
$numberOfDays = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$daily_sales = array_fill(1, $numberOfDays, 0);

// Isi data dari database
while ($row = $result_sales->fetch_assoc()) {
    $day = (int)$row['day'];
    $daily_sales[$day] = (float)$row['total'];
}

// Buat labels dan data untuk chart
for ($i = 1; $i <= $numberOfDays; $i++) {
    $labels[] = 'Hari ' . $i;
    $sales_data[] = $daily_sales[$i];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Penjualan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .main-content {
            margin-left: 210px;
            padding: 1.5rem;
            padding-top: 90px;
            min-height: 100vh;
            width: calc(100% - 210px);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 80px 1rem 1rem;
            }
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .pin-overlay {
            position: fixed;
            top: 0;
            left: 210px;
            width: calc(100% - 210px);
            height: 100%;
            backdrop-filter: blur(8px);
            background: rgba(255,255,255,0.7);
            z-index: 999;
            display: <?= $pinVerified ? 'none' : 'block' ?>;
        }

        @media (max-width: 768px) {
            .pin-overlay {
                left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="pin-overlay" style="display: <?= $pinVerified ? 'none' : 'block' ?>">
    <div class="modal fade" id="pinModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verifikasi PIN Admin</h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="pinInput" class="form-label">Masukkan 6 Digit PIN</label>
                    <input type="password" class="form-control form-control-lg" id="pinInput" 
                           placeholder="••••••" maxlength="6" inputmode="numeric" pattern="\d{6}">
                </div>
                <div id="pinError" class="text-danger d-none">PIN salah! Silakan coba lagi.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="verifyPin()">Verifikasi</button>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="main-content" style="<?= !$pinVerified ? 'display: none;' : '' ?>">
    <h4 class="mb-4">Grafik Penjualan</h4>

    <!-- Perbarui bagian form filter -->
    <form method="POST" class="row g-3 mb-4 align-items-center">
        <div class="col-md-3">
            <select class="form-select" name="year">
                <option value="">Pilih Tahun</option>
                <?php foreach ($years as $year): ?>
                <option value="<?= $year ?>" <?= $year == $selected_year ? 'selected' : '' ?>>
                    <?= $year ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-3">
            <select class="form-select" name="month">
                <option value="">Pilih Bulan</option>
                <?php foreach ($months as $month): ?>
                <option value="<?= $month ?>" <?= $month == $selected_month ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $month, 1)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
        
        <div class="col-md-2">
            <button type="button" class="btn btn-success" onclick="downloadPDF()">
                <i class="bi bi-download"></i> Download PDF
            </button>
        </div>
    </form>

    <!-- Chart -->
    <div class="chart-container">
        <canvas id="salesChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Inisialisasi Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Total Penjualan Harian (Rp)',
            data: <?= json_encode($sales_data) ?>,
            borderColor: '#4e73df',
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) label += ': ';
                        if (context.parsed.y !== null) {
                            label += 'Rp' + context.parsed.y.toLocaleString();
                        }
                        return label;
                    }
                }
            }
        }
    }
});

const correctPin = '123456'; // Ganti dengan PIN yang sebenarnya dari database

function verifyPin() {
    const enteredPin = document.getElementById('pinInput').value;
    const errorElement = document.getElementById('pinError');
    
    if(enteredPin === correctPin) {
        // Kirim PIN ke server untuk validasi session
        fetch('verify_pin.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json' 
            },
            body: JSON.stringify({ 
                pin: enteredPin, 
                page: 'report' // Tambahkan parameter page
            })
        }) // Hapus titik koma disini
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                window.location.reload();
            } else {
                errorElement.classList.remove('d-none');
                document.getElementById('pinInput').classList.add('is-invalid');
            }
        });
    } else {
        errorElement.classList.remove('d-none');
        document.getElementById('pinInput').classList.add('is-invalid');
    }
}

// Inisialisasi modal
const pinModal = new bootstrap.Modal(document.getElementById('pinModal'), {
    backdrop: false,
    keyboard: false
});

<?php if(!$pinVerified): ?>
document.addEventListener('DOMContentLoaded', function() {
    pinModal.show();
});
<?php endif; ?>

// Tambahkan di bagian paling bawah script
function downloadPDF() {
    // Ambil nilai bulan dan tahun yang dipilih
    const selectedMonth = document.querySelector('select[name="month"]').value;
    const selectedYear = document.querySelector('select[name="year"]').value;
    
    // Konversi angka bulan ke nama bulan
    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const monthName = monthNames[selectedMonth - 1] || '';

    // Dapatkan gambar dari chart
    const chartCanvas = document.getElementById('salesChart');
    const chartImage = chartCanvas.toDataURL('image/png', 1.0);
    
    // Buat PDF
    const pdf = new jspdf.jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: 'a4'
    });

    // Judul laporan
    const title = `Laporan Penjualan Bulan ${monthName} ${selectedYear}`;
    
    // Tambahkan konten ke PDF
    pdf.setFontSize(18);
    pdf.text(title, 15, 20);
    
    // Tambahkan gambar chart
    const imgWidth = 280; // Lebar maksimum untuk landscape A4
    const imgHeight = (chartCanvas.height * imgWidth) / chartCanvas.width;
    pdf.addImage(chartImage, 'PNG', 15, 30, imgWidth, imgHeight);

    // Download PDF
    pdf.save(`Laporan_Penjualan_${monthName}_${selectedYear}.pdf`);
}
</script>

</body>
</html>