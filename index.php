
<?php
session_start();
date_default_timezone_set("Asia/Jakarta");

// ===================================
// KONFIGURASI
// ===================================

$siswa = [
    1 => "Sabri",
    2 => "Aisyah",
    3 => "Amalia",
    4 => "Nanda",
    5 => "Ardhan",
    6 => "Aulia",
    7 => "Clarisa",
    8 => "Dapa",
    9 => "Dzaki Satya",
    10 => "Galih Chyo"
];

$pilihan = ["Hadir", "Izin", "Sakit", "Alfa"];

$file = __DIR__ . "/absensi.json";

// Keamanan output
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Membaca file JSON
function bacaData($file) {
    if (!file_exists($file)) {
        return [];
    }

    $isi = file_get_contents($file);
    $data = json_decode($isi, true);

    return is_array($data) ? $data : [];
}

// Token keamanan form
if (empty($_SESSION["token"])) {
    $_SESSION["token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token"];

// Tanggal yang dipilih
$tanggal = $_GET["tanggal"] ?? date("Y-m-d");

$dt = DateTimeImmutable::createFromFormat(
    "!Y-m-d",
    $tanggal
);

if (!$dt || $dt->format("Y-m-d") !== $tanggal) {
    $tanggal = date("Y-m-d");
    $dt = new DateTimeImmutable($tanggal);
}

// ===================================
// PROSES PENYIMPANAN
// ===================================

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["token"]) ||
        !hash_equals($token, $_POST["token"])
    ) {
        http_response_code(403);
        exit("Permintaan tidak valid.");
    }

    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "simpan" || $aksi === "reset") {

        $absensiBaru = [];
        $valid = true;

        if ($aksi === "simpan") {

            foreach ($siswa as $no => $nama) {

                $status = $_POST["status"][$no] ?? "";

                if (!in_array($status, $pilihan, true)) {
                    $valid = false;
                    break;
                }

                $absensiBaru[$no] = $status;
            }
        }

        if (!$valid) {
            $error = "Harap isi absensi seluruh siswa.";
        } else {

            // Membuka file dengan penguncian
            $fp = fopen($file, "c+");

            if ($fp && flock($fp, LOCK_EX)) {

                rewind($fp);

                $isi = stream_get_contents($fp);
                $semuaData = json_decode($isi, true);

                if (!is_array($semuaData)) {
                    $semuaData = [];
                }

                if ($aksi === "reset") {

                    unset($semuaData[$tanggal]);

                } else {

                    $semuaData[$tanggal] = [
                        "absensi" => $absensiBaru,
                        "waktu" => date("H:i:s")
                    ];
                }

                $json = json_encode(
                    $semuaData,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE
                );

                rewind($fp);

                $berhasil = $json !== false
                    && fwrite($fp, $json) === strlen($json);

                if ($berhasil) {
                    fflush($fp);
                    ftruncate($fp, strlen($json));
                }

                flock($fp, LOCK_UN);
                fclose($fp);

                if ($berhasil) {

                    $pesan = $aksi === "reset"
                        ? "reset"
                        : "success";

                    header(
                        "Location: index.php?tanggal="
                        . urlencode($tanggal)
                        . "&" . $pesan . "=1"
                    );

                    exit;
                }

                $error = "Gagal menyimpan data.";

            } else {

                if ($fp) fclose($fp);

                $error = "File tidak dapat dibuka.";
            }
        }
    }
}

// ===================================
// MENGAMBIL DATA
// ===================================

$semuaData = bacaData($file);

$data = $semuaData[$tanggal]["absensi"] ?? [];

$waktu = $semuaData[$tanggal]["waktu"] ?? "-";

// Rekap
$rekap = [
    "Hadir" => 0,
    "Izin" => 0,
    "Sakit" => 0,
    "Alfa" => 0
];

foreach ($data as $status) {
    if (isset($rekap[$status])) {
        $rekap[$status]++;
    }
}

$total = count($siswa);
$terisi = count($data);

$persen = $total > 0
    ? round(($rekap["Hadir"] / $total) * 100)
    : 0;

$kemarin = $dt->modify("-1 day")->format("Y-m-d");
$besok = $dt->modify("+1 day")->format("Y-m-d");

?>

<!DOCTYPE html>
<html lang="id">

<head>
<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Attendify | Absensi X RPL 2</title>

<style>

/* ===============================
   GLOBAL
================================ */

:root {
    --navy: #172b49;
    --gold: #c5a363;
    --bg: #f5f3ed;
    --white: #ffffff;
    --text: #26364a;
    --muted: #788396;
    --border: #e8e8e8;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
}

.container {
    width: 92%;
    max-width: 1100px;
    margin: 35px auto;
}

/* HEADER */

.header {
    background: var(--navy);
    color: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
}

.brand {
    color: var(--gold);
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 25px;
}

.brand span {
    color: white;
}

.header h1 {
    font-size: 29px;
    margin-bottom: 8px;
}

.header p {
    color: #c4cfdf;
    font-size: 14px;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tag {
    background: #344661;
    color: #f0d59e;
    padding: 7px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: bold;
}

/* SUMMARY */

.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat {
    background: white;
    padding: 22px;
    border-radius: 13px;
    border: 1px solid var(--border);
    border-left: 4px solid;
}

.stat h2 {
    font-size: 32px;
}

.stat p {
    color: var(--muted);
    font-size: 13px;
}

.hadir {
    border-color: #29805b;
}

.hadir h2 {
    color: #29805b;
}

.izin {
    border-color: #c59a45;
}

.izin h2 {
    color: #b58b36;
}

.sakit {
    border-color: #4384b6;
}

.sakit h2 {
    color: #4384b6;
}

.alfa {
    border-color: #bd5555;
}

.alfa h2 {
    color: #bd5555;
}

/* MAIN CARD */

.card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    border: 1px solid var(--border);
    margin-bottom: 25px;
}

.card-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.card-title h2 {
    font-size: 19px;
    color: var(--navy);
}

.small {
    color: var(--muted);
    font-size: 13px;
}

/* DATE NAVIGATION */

.date-nav {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.date-nav a {
    text-decoration: none;
    background: #eef0f3;
    color: var(--navy);
    padding: 9px 15px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: bold;
}

.date-nav a:hover {
    background: var(--navy);
    color: white;
}

.date-nav input {
    padding: 9px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-family: inherit;
}

.date-nav button {
    background: var(--gold);
    border: none;
    color: #172b49;
    padding: 10px 15px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

/* TABLE */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f1f3f6;
    padding: 15px;
    color: #536075;
    font-size: 12px;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 15px;
    border-bottom: 1px solid #edf0f2;
    font-size: 14px;
}

tbody tr:hover {
    background: #faf9f5;
}

.number {
    color: var(--muted);
    font-weight: bold;
}

.student {
    font-weight: 600;
}

.avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: #e9edf3;
    border-radius: 50%;
    margin-right: 10px;
    color: var(--navy);
    font-size: 12px;
    font-weight: bold;
}

/* SELECT */

select {
    width: 100%;
    min-width: 120px;
    padding: 11px;
    border: 1px solid #dce1e7;
    border-radius: 7px;
    background: white;
    color: var(--text);
    font-size: 13px;
    cursor: pointer;
}

select:focus {
    outline: 2px solid var(--gold);
    border-color: var(--gold);
}

/* BUTTON */

.actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.btn {
    padding: 13px 22px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.save {
    background: var(--navy);
    color: white;
    flex: 1;
}

.save:hover {
    background: #294c78;
}

.reset {
    background: #fbeaea;
    color: #b34e4e;
    width: 100%;
}

.reset:hover {
    background: #f5d6d6;
}

.reset-form {
    margin-top: 12px;
}

/* ALERT */

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.success {
    background: #e2f3e9;
    color: #20754a;
}

.error {
    background: #fde8e8;
    color: #a63c3c;
}

/* PROGRESS */

.progress {
    width: 100%;
    height: 10px;
    background: #e9edf2;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 12px;
}

.progress-bar {
    height: 100%;
    background: #29805b;
    border-radius: 20px;
}

/* FOOTER */

footer {
    text-align: center;
    color: #88909b;
    font-size: 12px;
    padding: 20px;
}

/* RESPONSIVE */

@media(max-width: 700px) {

    .container {
        width: 94%;
        margin: 15px auto;
    }

    .header {
        padding: 23px 20px;
    }

    .header h1 {
        font-size: 23px;
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .stat {
        padding: 17px;
    }

    .stat h2 {
        font-size: 27px;
    }

    .card {
        padding: 15px;
    }

    .card-title {
        align-items: flex-start;
        flex-direction: column;
    }

    th, td {
        padding: 12px 8px;
        font-size: 12px;
    }

    .avatar {
        display: none;
    }

    .actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .date-nav {
        gap: 7px;
    }
}

</style>

</head>

<body>

<div class="container">

<!-- HEADER -->

<header class="header">

    <div class="header-top">

        <div class="brand">
            Attendify<span>.</span>
        </div>

        <span class="tag">X RPL 2</span>

    </div>

    <h1>Dashboard Absensi</h1>

    <p>
        Sistem pengelolaan kehadiran siswa
        yang praktis dan terorganisir.
    </p>

</header>

<!-- SUMMARY -->

<section class="summary">

    <div class="stat hadir">
        <h2><?= $rekap["Hadir"] ?></h2>
        <p>● Hadir</p>
    </div>

    <div class="stat izin">
        <h2><?= $rekap["Izin"] ?></h2>
        <p>● Izin</p>
    </div>

    <div class="stat sakit">
        <h2><?= $rekap["Sakit"] ?></h2>
        <p>● Sakit</p>
    </div>

    <div class="stat alfa">
        <h2><?= $rekap["Alfa"] ?></h2>
        <p>● Alfa</p>
    </div>

</section>

<!-- MAIN CONTENT -->

<main class="card">

    <div class="card-title">

        <div>
            <h2>Daftar Kehadiran Siswa</h2>
            <p class="small">
                Kelola absensi berdasarkan tanggal.
            </p>
        </div>

        <span class="small">
            <?= $total ?> Siswa
        </span>

    </div>

    <!-- NAVIGASI TANGGAL -->

    <form method="GET" class="date-nav">

        <a href="?tanggal=<?= e($kemarin) ?>">
            &larr;
        </a>

        <input
            type="date"
            name="tanggal"
            value="<?= e($tanggal) ?>"
            required
        >

        <button type="submit">
            Tampilkan
        </button>

        <a href="?tanggal=<?= e($besok) ?>">
            &rarr;
        </a>

        <a href="index.php">
            Hari Ini
        </a>

    </form>

    <?php if (isset($_GET["success"])): ?>

        <div class="alert success">
            ✓ Absensi berhasil disimpan.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["reset"])): ?>

        <div class="alert success">
            Data absensi berhasil direset.
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- FORM ABSENSI -->

    <form method="POST"
          action="?tanggal=<?= e($tanggal) ?>">

        <input type="hidden"
               name="token"
               value="<?= e($token) ?>">

        <input type="hidden"
               name="aksi"
               value="simpan">

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Siswa</th>
                        <th>Status Kehadiran</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($siswa as $no => $nama): ?>

                    <tr>

                        <td class="number">
                            <?= sprintf("%02d", $no) ?>
                        </td>

                        <td class="student">

                            <span class="avatar">
                                <?= e(strtoupper(substr($nama, 0, 1))) ?>
                            </span>

                            <?= e($nama) ?>

                        </td>

                        <td>

                            <select
                                name="status[<?= $no ?>]"
                                required
                            >

                                <option value="">
                                    Pilih Status
                                </option>

                                <?php foreach ($pilihan as $status): ?>

                                    <option
                                        value="<?= e($status) ?>"
                                        <?= (($data[$no] ?? "") === $status)
                                            ? "selected" : "" ?>
                                    >
                                        <?= e($status) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <div class="actions">

            <button type="submit" class="btn save">
                ✓ Simpan Absensi
            </button>

        </div>

    </form>

    <!-- RESET -->

    <?php if (!empty($data)): ?>

        <form
            method="POST"
            class="reset-form"
            action="?tanggal=<?= e($tanggal) ?>"
        >

            <input type="hidden"
                   name="token"
                   value="<?= e($token) ?>">

            <input type="hidden"
                   name="aksi"
                   value="reset">

            <button type="submit" class="btn reset">
                Reset Absensi Tanggal Ini
            </button>

        </form>

    <?php endif; ?>

</main>

<!-- STATISTIK -->

<section class="card">

    <div class="card-title">

        <h2>Statistik Kehadiran</h2>

        <span class="small">
            <?= $persen ?>% Hadir
        </span>

    </div>

    <p class="small">
        <?= $rekap["Hadir"] ?> dari
        <?= $total ?> siswa hadir.
    </p>

    <div class="progress">

        <div
            class="progress-bar"
            style="width: <?= $persen ?>%">
        </div>

    </div>

    <p class="small" style="margin-top:15px;">

        Absensi terisi:
        <?= $terisi ?> / <?= $total ?>

        <br>

        Terakhir disimpan:
        <?= e($waktu) ?> WIB

    </p>

</section>

<footer>

    Attendify © <?= date("Y") ?>
    | Sistem Absensi X RPL 2

    <br>

    Developed with PHP

</footer>

</div>

</body>
</html>
