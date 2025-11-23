<?php
include 'header.php';
include 'koneksi.php';

if ($_SESSION['role'] != 'admin') {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang untuk mengakses halaman ini.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

// --- LANGKAH 1: DETEKSI OTOMATIS JENJANG SEKOLAH ---
$query_sekolah = mysqli_query($koneksi, "SELECT jenjang FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($query_sekolah);
$jenjang_sekolah = $sekolah['jenjang'] ?? 'SMP'; 

// [MODIFIKASI] Kita ingin halaman ini SELALU menampilkan Mapel, baik untuk SD maupun SMP.
// Jadi kita buat variabel penanda agar logika di bawah menganggap ini mode 'Mapel'
$tampilkan_mode_mapel = true; 
?>

<style>
    /* Style umum */
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem; border-radius: 0.75rem; color: white;
    }
    .page-header h1 { font-weight: 700; }
    .page-header .btn { box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: 600; }
    #search-input { max-width: 400px; }

    /* Style untuk Kartu Mata Pelajaran */
    .mapel-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: 0; border-radius: 0.75rem;
    }
    .mapel-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    }
    .mapel-card-header {
        border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem;
        color: white; padding: 1.5rem; position: relative; overflow: hidden;
    }
    .mapel-card-header .mapel-icon {
        font-size: 4rem; position: absolute; right: -1rem; bottom: -1.5rem;
        opacity: 0.2; transform: rotate(-15deg);
    }
    .mapel-card-header h4 { font-weight: 700; margin-bottom: 0.25rem; }
    .mapel-card-header p { margin: 0; opacity: 0.8; }
    .mapel-card .list-group-item { border-color: rgba(0,0,0,0.08); }
    .mapel-card .guru-list { padding-left: 1.2rem; font-size: 0.9rem; }
    .mapel-card .guru-list li { margin-bottom: 0.25rem; }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center">
            <div>
                <!-- [MODIFIKASI] Judul selalu Manajemen Mata Pelajaran -->
                <h1 class="mb-1">Manajemen Mata Pelajaran</h1>
                <p class="lead mb-0 opacity-75">Kelola daftar mata pelajaran (Master Data) untuk sekolah.</p>
            </div>
            <!-- [MODIFIKASI] Tombol Tambah Mapel Selalu Muncul -->
            <a href="mapel_tambah.php" class="btn btn-outline-light mt-3 mt-sm-0">
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Mata Pelajaran
            </a>
        </div>
    </div>

    <div class="mb-4 d-flex justify-content-between">
        <div class="input-group" style="max-width: 400px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="search-input" class="form-control" placeholder="Cari nama mata pelajaran atau kode...">
        </div>
        
        <div>
            <a href="mapel_urutkan.php" class="btn btn-primary">
                <i class="bi bi-list-ol me-2"></i>Atur Urutan Mapel
            </a>
        </div>
    </div>


    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="card-list">
        <?php 
        // [MODIFIKASI] Query Mata Pelajaran dijalankan untuk SEMUA jenjang
        $query = mysqli_query($koneksi, "
            SELECT 
                mp.id_mapel, mp.nama_mapel, mp.kode_mapel,
                (SELECT COUNT(tp.id_tp) FROM tujuan_pembelajaran tp WHERE tp.id_mapel = mp.id_mapel) as jumlah_tp,
                (SELECT COUNT(DISTINCT gm.id_guru) FROM guru_mengajar gm WHERE gm.id_mapel = mp.id_mapel) as jumlah_guru,
                (SELECT GROUP_CONCAT(DISTINCT g.nama_guru SEPARATOR '</li><li>') FROM guru_mengajar gm JOIN guru g ON gm.id_guru = g.id_guru WHERE gm.id_mapel = mp.id_mapel LIMIT 3) as guru_pengampu
            FROM mata_pelajaran mp ORDER BY mp.urutan ASC, mp.nama_mapel ASC
        ");
        
        $colors = ['#0d6efd', '#6f42c1', '#d63384', '#fd7e14', '#198754', '#0dcaf0', '#6610f2'];
        $color_index = 0;

        if (mysqli_num_rows($query) > 0) {
            while ($data = mysqli_fetch_assoc($query)) {
                $bg_color = $colors[$color_index % count($colors)];
                $color_index++;
        ?>
            <div class="col searchable-card">
                <div class="card shadow-sm mapel-card h-100">
                    <div class="mapel-card-header" style="background-color: <?php echo $bg_color; ?>;">
                        <i class="bi bi-book-half mapel-icon"></i>
                        <h4 class="searchable-name"><?php echo htmlspecialchars($data['nama_mapel']); ?></h4>
                        <p class="searchable-code">Kode: <?php echo htmlspecialchars($data['kode_mapel']); ?></p>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-around text-center mb-3">
                            <div><h4 class="mb-0 fw-bold"><?php echo $data['jumlah_guru']; ?></h4><small class="text-muted">Guru Pengampu</small></div>
                            <div><h4 class="mb-0 fw-bold"><?php echo $data['jumlah_tp']; ?></h4><small class="text-muted">Tujuan Pembelajaran</small></div>
                        </div>
                        <div class="list-group list-group-flush"><div class="list-group-item">
                            <h6 class="mb-2 small text-muted">Guru Pengampu:</h6>
                            <ol class="guru-list mb-0 searchable-guru">
                                <?php if (!empty($data['guru_pengampu'])) {
                                    echo '<li>' . $data['guru_pengampu'] . '</li>'; 
                                    if ($data['jumlah_guru'] > 3) { echo '<li class="text-muted small">...' . ($data['jumlah_guru'] - 3) . ' guru lainnya</li>'; }
                                } else { echo '<li class="text-danger" style="list-style: none;">Belum ada guru</li>'; } ?>
                            </ol>
                        </div></div>
                    </div>
                    
                    <div class="card-footer bg-white">
                        <a href="tp_tampil.php?id_mapel=<?php echo $data['id_mapel']; ?>" class="btn btn-primary d-block w-100 mb-2">
                            <i class="bi bi-card-list me-2"></i>Kelola Tujuan Pembelajaran
                        </a>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="mapel_edit.php?id=<?php echo $data['id_mapel']; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil-fill me-1"></i> Edit
                            </a>
                            <a href="mapel_aksi.php?aksi=hapus&id=<?php echo $data['id_mapel']; ?>" class="btn btn-outline-danger btn-sm btn-hapus">
                                <i class="bi bi-trash-fill me-1"></i> Hapus
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        <?php } } else { ?>
            <div class="col-12 text-center">
                <p>Belum ada mata pelajaran.</p>
            </div>
        <?php } ?>
    </div>

    <div id="no-results" class="text-center py-5" style="display: none;">
        <i class="bi bi-search fs-1 text-muted"></i>
        <h4 class="mt-3">Data tidak ditemukan</h4>
        <p class="text-muted">Tidak ada data yang cocok dengan kata kunci pencarian Anda.</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function(){
    // Skrip Pencarian Universal
    $("#search-input").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        var found = false;
        $("#card-list .searchable-card").filter(function() {
            // Mengambil teks dari elemen-elemen spesifik untuk pencarian
            var cardText = $(this).find('.searchable-name').text().toLowerCase() +
                           $(this).find('.searchable-code').text().toLowerCase() +
                           $(this).find('.searchable-guru').text().toLowerCase();
            
            var isVisible = cardText.indexOf(value) > -1;
            $(this).toggle(isVisible);
            if(isVisible) {
                found = true;
            }
        });
        $("#no-results").toggle(!found);
    });

    // --- TAMBAHAN: SKRIP KONFIRMASI HAPUS ---
    $(document).on('click', '.btn-hapus', function(e) {
        e.preventDefault(); // Mencegah link langsung dieksekusi
        var href = $(this).attr('href'); // Ambil URL hapus dari link

        Swal.fire({
            title: 'Anda yakin?',
            text: "Data mata pelajaran ini akan dihapus. Anda tidak dapat mengembalikannya!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Jika dikonfirmasi, alihkan ke URL hapus
                window.location.href = href;
            }
        });
    });
    // --- AKHIR SKRIP TAMBAHAN ---
});
</script>

<?php
// Notifikasi Sukses
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!','" . addslashes($_SESSION['pesan']) . "','success');</script>";
    unset($_SESSION['pesan']);
}
// Notifikasi Error (PENTING UNTUK HAPUS)
if (isset($_SESSION['pesan_error'])) {
    echo "<script>Swal.fire('Gagal!','" . addslashes($_SESSION['pesan_error']) . "','error');</script>";
    unset($_SESSION['pesan_error']);
}
include 'footer.php';
?>