<?php
// --- LOGIKA BACKEND (JANGAN DIHAPUS) ---
session_start();
// Jika sudah login, lempar ke dashboard
if (isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'koneksi.php';

// 1. AMBIL DATA SEKOLAH
$nama_sekolah = 'Aplikasi Rapor Digital';
$logo_path = 'assets/img/logo_default.png'; 

// Cek koneksi sebelum query
if (isset($koneksi)) {
    $q_sekolah = mysqli_query($koneksi, "SELECT nama_sekolah, logo_sekolah FROM sekolah LIMIT 1");
    if ($q_sekolah && mysqli_num_rows($q_sekolah) > 0) {
        $d_sekolah = mysqli_fetch_assoc($q_sekolah);
        $nama_sekolah = $d_sekolah['nama_sekolah'];
        if (!empty($d_sekolah['logo_sekolah']) && file_exists('uploads/'.$d_sekolah['logo_sekolah'])) {
            $logo_path = 'uploads/'.$d_sekolah['logo_sekolah'];
        }
    }

    // 2. AMBIL DATA STATISTIK SEDERHANA (Untuk Tampilan Hero)
    $count_siswa = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM siswa WHERE status_siswa='Aktif'"))['c'] ?? 0;
    $count_guru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM guru"))['c'] ?? 0;
    $count_kelas = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM kelas"))['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Rapor Digital | <?php echo htmlspecialchars($nama_sekolah); ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"> <!-- Animate On Scroll -->
    <link rel="stylesheet" href="assets/css/sweetalert2.min.css">

    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4cc9f0;
            --text-dark: #0b090a;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --glass: rgba(255, 255, 255, 0.8);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
            background-color: #fff;
        }

        /* Navbar Transparent to Sticky */
        .navbar {
            transition: all 0.4s ease;
            padding: 1.5rem 0;
        }
        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 1rem 0;
        }
        .navbar-brand img {
            height: 40px;
            transition: transform 0.3s;
        }
        .navbar-brand span {
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.5px;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 160px 0 100px;
            background: radial-gradient(circle at top right, #eef2ff 0%, #fff 40%);
            overflow: hidden;
        }
        /* Blob Background Animation */
        .hero-blob {
            position: absolute;
            top: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            filter: blur(80px);
            opacity: 0.2;
            border-radius: 50%;
            z-index: 0;
            animation: float 10s infinite ease-in-out;
        }
        .hero-blob-2 {
            position: absolute;
            bottom: 10%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #f72585 0%, #7209b7 100%);
            filter: blur(90px);
            opacity: 0.15;
            border-radius: 50%;
            z-index: 0;
            animation: float 15s infinite ease-in-out reverse;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }
        .hero-title span {
            background: linear-gradient(120deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        /* Buttons */
        .btn-grad {
            background-image: linear-gradient(to right, #4361ee 0%, #3f37c9 51%, #4361ee 100%);
            padding: 15px 35px;
            text-align: center;
            text-transform: uppercase;
            transition: 0.5s;
            background-size: 200% auto;
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 0.9rem;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        .btn-grad:hover {
            background-position: right center;
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(67, 97, 238, 0.4);
        }

        /* 3D Illustration Placeholder using CSS & Icons */
        .hero-image-wrapper {
            position: relative;
            padding: 2rem;
        }
        .floating-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.8);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            position: absolute;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: float-card 6s ease-in-out infinite;
        }
        .card-1 { top: 10%; right: 10%; z-index: 2; }
        .card-2 { bottom: 20%; left: 5%; z-index: 3; animation-delay: 2s; }
        .card-3 { top: 40%; right: -5%; z-index: 1; animation-delay: 1s; }

        .icon-box {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
        }
        
        /* Features Section */
        .features-section {
            padding: 100px 0;
            position: relative;
        }
        .feature-box {
            padding: 40px 30px;
            border-radius: 24px;
            background: white;
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            height: 100%;
        }
        .feature-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            border-color: transparent;
        }
        .feature-icon {
            width: 70px; height: 70px;
            border-radius: 20px;
            background: #f8f9fa;
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .feature-box:hover .feature-icon {
            background: var(--primary);
            color: white;
            transform: rotate(-10deg);
        }

        /* Stats Section */
        .stats-section {
            background: var(--primary);
            color: white;
            padding: 60px 0;
            border-radius: 30px;
            margin: 0 20px;
        }

        /* Footer */
        footer {
            background-color: #fff;
            padding: 50px 0 20px;
            border-top: 1px solid #eee;
        }

        /* Modal Login Custom */
        .modal-content {
            border-radius: 24px;
            border: none;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #4361ee, #4cc9f0);
            color: white;
            border: none;
            padding: 2rem 2rem 1rem;
        }
        .modal-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #f1f5f9;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: none;
            background-color: #fff;
        }

        /* Animations */
        @keyframes float {
            0% { transform: translate(0, 0); }
            50% { transform: translate(30px, 50px); }
            100% { transform: translate(0, 0); }
        }
        @keyframes float-card {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .hero-title { font-size: 2.5rem; }
            .hero-image-wrapper { display: none; } /* Hide complex illustration on mobile */
            .stats-section { margin: 0; border-radius: 0; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="<?php echo $logo_path; ?>" alt="Logo" onerror="this.style.display='none'">
                <span>Radig<span style="color:var(--primary)">.</span></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link" href="#alur">Alur Kerja</a></li>
                    <li class="nav-item">
                        <button class="btn btn-grad px-4 py-2" style="font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#loginModal">
                            Masuk Aplikasi
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section d-flex align-items-center">
        <div class="hero-blob"></div>
        <div class="hero-blob-2"></div>
        
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right" data-aos-duration="1000">
                    <div class="d-inline-block px-3 py-1 rounded-pill bg-primary bg-opacity-10 text-primary fw-bold mb-3" style="font-size: 0.85rem;">
                        🚀 Platform Rapor Digital Modern v2.0.1 rev
                    </div>
                    <h1 class="hero-title">
                        Digitalisasi Akademik <br>
                        <span>Lebih Cerdas.</span>
                    </h1>
                    <p class="hero-subtitle">
                        Solusi terintegrasi untuk manajemen penilaian, absensi, dan pelaporan hasil belajar di <?php echo htmlspecialchars($nama_sekolah); ?>. Akses data akademik secara real-time, dimana saja.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-grad shadow-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Login Sekarang
                        </button>
                        <a href="#fitur" class="btn btn-light px-4 py-3 rounded-pill fw-bold text-dark border">
                            Pelajari Dulu <i class="bi bi-arrow-down ms-2"></i>
                        </a>
                    </div>
                    
                    <div class="mt-5 d-flex gap-4 text-muted">
                        <div>
                            <h4 class="fw-bold text-dark mb-0"><?php echo $count_siswa; ?>+</h4>
                            <small>Siswa Aktif</small>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0"><?php echo $count_guru; ?>+</h4>
                            <small>Guru Pengajar</small>
                        </div>
                        <div class="vr"></div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0"><?php echo $count_kelas; ?></h4>
                            <small>Rombel Kelas</small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block position-relative" style="height: 500px;" data-aos="fade-left" data-aos-duration="1200">
                    <!-- Abstract Composition / Illustration -->
                    <img src="assets/img/logo_default.png" alt="School Logo" class="position-absolute top-50 start-50 translate-middle" style="width: 300px; opacity: 0.05; z-index: 0;">
                    
                    <!-- Floating Card 1: Nilai -->
                    <div class="floating-card card-1">
                        <div class="icon-box bg-success">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Input Nilai</h6>
                            <small class="text-muted">Cepat & Akurat</small>
                        </div>
                    </div>

                    <!-- Floating Card 2: Rapor -->
                    <div class="floating-card card-2">
                        <div class="icon-box bg-primary">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Cetak Rapor</h6>
                            <small class="text-muted">Otomatis PDF</small>
                        </div>
                    </div>

                    <!-- Floating Card 3: User -->
                    <div class="floating-card card-3">
                        <div class="icon-box bg-warning text-dark">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Wali Kelas</h6>
                            <small class="text-muted">Monitoring Siswa</small>
                        </div>
                    </div>
                    
                    <!-- Central Illustration Placeholder (bisa diganti gambar vektor 3D jika punya) -->
                    <div class="position-absolute top-50 start-50 translate-middle">
                        <div style="width: 400px; height: 400px; background: url('https://img.freepik.com/free-vector/gradient-online-test-illustration_23-2150235125.jpg?w=740&t=st=1709783000~exp=1709783600~hmac=fakehash') center/contain no-repeat; border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.1);"></div>
                         <!-- Note: Gambar di atas adalah placeholder freeware, jika tidak muncul akan blank tapi layout tetap aman -->
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="features-section">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-6 text-center" data-aos="fade-up">
                    <span class="text-primary fw-bold text-uppercase letter-spacing-2">Fitur Unggulan</span>
                    <h2 class="fw-bold mt-2">Semua Kebutuhan Rapor <br>Dalam Satu Genggaman</h2>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <h4>Role Guru & Walas</h4>
                        <p class="text-muted">Pembagian tugas yang jelas antara Guru Mapel (Input Nilai) dan Wali Kelas (Leger & Rapor).</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="bi bi-grid-1x2"></i>
                        </div>
                        <h4>Kurikulum Merdeka</h4>
                        <p class="text-muted">Mendukung penilaian Sumatif, Formatif, dan Projek (P5) sesuai standar kurikulum terbaru.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="bi bi-phone"></i>
                        </div>
                        <h4>Akses Siswa</h4>
                        <p class="text-muted">Siswa dapat login untuk melihat transkrip nilai sementara dan mengunduh rapor digital.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow / Alur -->
    <section id="alur" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-5 mb-4 mb-lg-0" data-aos="fade-right">
                    <h2 class="fw-bold mb-3">Alur Kerja Sederhana</h2>
                    <p class="text-muted mb-4">Sistem dirancang untuk meminimalisir kesalahan input dan mempercepat proses pembagian rapor.</p>
                    
                    <div class="d-flex gap-3 mb-3">
                        <div class="bg-white p-3 rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                            <span class="fw-bold text-primary">1</span>
                        </div>
                        <div>
                            <h6 class="fw-bold">Admin Sekolah</h6>
                            <p class="small text-muted">Mengatur Data Master (Siswa, Guru, Kelas, Mapel).</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="bg-white p-3 rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                            <span class="fw-bold text-primary">2</span>
                        </div>
                        <div>
                            <h6 class="fw-bold">Guru Mapel</h6>
                            <p class="small text-muted">Membuat Tujuan Pembelajaran (TP) & Input Nilai.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="bg-white p-3 rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                            <span class="fw-bold text-primary">3</span>
                        </div>
                        <div>
                            <h6 class="fw-bold">Wali Kelas</h6>
                            <p class="small text-muted">Cek Kelengkapan, Input Ekstrakurikuler, Cetak Rapor.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 ms-auto" data-aos="fade-left">
                    <div class="bg-white p-4 rounded-4 shadow-lg position-relative border">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:12px; height:12px; background:#fc5c65; border-radius:50%;"></div>
                                <div style="width:12px; height:12px; background:#fd9644; border-radius:50%;"></div>
                                <div style="width:12px; height:12px; background:#26de81; border-radius:50%;"></div>
                            </div>
                            <small class="text-muted">Preview Dashboard</small>
                        </div>
                        <!-- Mockup Tabel Sederhana -->
                        <table class="table table-hover text-center small">
                            <thead class="table-light">
                                <tr><th>Mapel</th><th>TP 1</th><th>TP 2</th><th>Nilai Akhir</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="text-start fw-bold">Matematika</td><td>85</td><td>90</td><td><span class="badge bg-success">88</span></td></tr>
                                <tr><td class="text-start fw-bold">B. Indonesia</td><td>88</td><td>85</td><td><span class="badge bg-success">87</span></td></tr>
                                <tr><td class="text-start fw-bold">IPA</td><td>80</td><td>82</td><td><span class="badge bg-success">81</span></td></tr>
                            </tbody>
                        </table>
                        <div class="mt-3 text-center">
                            <button class="btn btn-primary btn-sm w-100 rounded-pill">Cetak Rapor PDF</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-md-4 mb-4">
                    <a class="d-flex align-items-center gap-2 text-decoration-none text-dark mb-3" href="#">
                        <img src="<?php echo $logo_path; ?>" alt="Logo" height="30">
                        <span class="fw-bold fs-5">Radig.</span>
                    </a>
                    <p class="text-muted small">
                        Sistem Informasi Akademik dan Rapor Digital untuk <?php echo htmlspecialchars($nama_sekolah); ?>.
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($nama_sekolah); ?>. All rights reserved.</p>
                    <p class="text-muted small">Version 2.0.1</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modal Login Modern -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-0 justify-content-center text-center pb-0">
                    <div>
                        <div class="bg-white p-2 rounded-circle d-inline-block mb-2 shadow-sm">
                            <img src="<?php echo $logo_path; ?>" width="50" alt="Logo">
                        </div>
                        <h5 class="modal-title fw-bold">Selamat Datang Kembali</h5>
                        <p class="text-white-50 small mb-0">Silakan login untuk mengakses dashboard.</p>
                    </div>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3 btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <form action="proses_login.php" method="POST">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" placeholder="NIP/NISN" required>
                            <label for="username">Username (NIP / NISN)</label>
                        </div>
                        <div class="form-floating mb-4">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-grad btn-lg shadow-sm">Masuk Aplikasi</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center border-0 bg-light py-3">
                    <small class="text-muted">Lupa password? Hubungi Admin Sekolah.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/sweetalert2.min.js"></script>
    <script>
        // Init Animation
        AOS.init({
            once: true,
            offset: 50,
            duration: 800,
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.navbar').classList.add('scrolled');
            } else {
                document.querySelector('.navbar').classList.remove('scrolled');
            }
        });

        // SweetAlert Logic dari URL Param
        const urlParams = new URLSearchParams(window.location.search);
        const pesan = urlParams.get('pesan');
        if (pesan) {
            let title = '', text = '', icon = '';
            if (pesan === 'gagal') {
                title = 'Login Gagal'; text = 'Username atau password salah.'; icon = 'error';
                // Auto open modal jika gagal login
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            } else if (pesan === 'logout') {
                title = 'Berhasil Logout'; text = 'Sampai jumpa kembali!'; icon = 'success';
            } else if (pesan === 'belum_login') {
                title = 'Akses Ditolak'; text = 'Silakan login terlebih dahulu.'; icon = 'warning';
            }

            if(title) {
                Swal.fire({ icon: icon, title: title, text: text, confirmButtonColor: '#4361ee' });
                window.history.replaceState(null, null, window.location.pathname);
            }
        }
    </script>
</body>
</html>