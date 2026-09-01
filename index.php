<?php
session_start();
$pesan = isset($_GET['pesan']) ? htmlspecialchars($_GET['pesan']) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absen Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --maroon-dark: #1a5d0e;
            --maroon-darker: #2e861e;
            --pink-mid: #4ed137;
            --pink-light: #a7eb9b;
            --pink-lighter: #c9f3c2;
        }

        body {
            background-image: url('bg-login/bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .login-wrapper {
            position: relative;
            width: 100%;
            max-width: 700px;
            margin: 2rem;
        }

        .login-card {
            display: flex;
            width: 100%;
            min-height: 460px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            background: #fff;
        }

        .side-panel {
            position: relative;
            width: 42%;
            background: var(--maroon-dark);
            overflow: hidden;
        }

        .side-panel .tri {
            position: absolute;
            width: 0;
            height: 0;
        }

        .side-panel .tri-1 {
            top: -10%;
            left: -20%;
            border-style: solid;
            border-width: 260px 0 260px 220px;
            border-color: transparent transparent transparent var(--pink-light);
            opacity: 0.9;
        }

        .side-panel .tri-2 {
            bottom: -15%;
            left: -25%;
            border-style: solid;
            border-width: 220px 0 220px 200px;
            border-color: transparent transparent transparent var(--pink-mid);
            opacity: 0.85;
        }

        .side-panel .tri-3 {
            top: 28%;
            left: -10%;
            border-style: solid;
            border-width: 100px 0 100px 130px;
            border-color: transparent transparent transparent var(--pink-lighter);
        }

        .tab-group {
            position: absolute;
            right: -1px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            z-index: 5;
        }

        .tab-item {
            padding: 0.7rem 1.2rem;
            font-weight: 900;
            font-size: 20px;
            letter-spacing: 0.05em;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .tab-item.active {
            background: #fff;
            color: #2e861e;
            border-radius: 30px 0 0 30px;
        }

        .tab-item.inactive {
            color: rgba(255, 255, 255, 0.85);
            background: transparent;
        }

        .form-panel {
            width: 58%;
            padding: 2.75rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .avatar-circle {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            margin: 0 auto 0.75rem auto;
            background: radial-gradient(circle at 35% 30%, var(--pink-mid), var(--maroon-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 16px rgba(109, 27, 78, 0.4);
        }

        .avatar-circle i {
            font-size: 2.1rem;
            color: #fff;
        }

        .form-logo {
            display: block;
            max-width: 220px;
            width: 50%;
            height: auto;
            margin: 0 auto 1.75rem auto;
        }

        .input-underline {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid #ccc;
            padding: 0.5rem 0.1rem;
            margin-bottom: 1.5rem;
        }

        .input-underline i {
            color: #9b9b9b;
            font-size: 1.1rem;
        }

        .input-underline input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.95rem;
            color: #333;
        }

        .input-underline input::placeholder {
            color: #adadad;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .btn-login-pill {
            background: linear-gradient(135deg, var(--pink-mid), var(--maroon-dark));
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            padding: 0.6rem 2.2rem;
            border-radius: 30px;
        }

        .btn-login-pill:hover {
            filter: brightness(1.05);
            color: #fff;
        }

        .alert-error {
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        @media (max-width: 576px) {
            .login-card { flex-direction: column; }
            .side-panel, .form-panel { width: 100%; }
            .side-panel { min-height: 120px; }
            .tab-group { display: none; }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">

            <div class="side-panel">
                <div class="tri tri-1"></div>
                <div class="tri tri-2"></div>
                <div class="tri tri-3"></div>

                <div class="tab-group">
                    <span class="tab-item active"><strong>BERKAH</strong></span>
                </div>
            </div>

            <div class="form-panel">
                <img src="bg-login/logo-berkah.png" alt="Logo Berkah Chicken" class="form-logo">

                <?php if ($pesan): ?>
                    <div class="text-danger alert-error"><?php echo $pesan; ?></div>
                <?php endif; ?>

                <form action="proses_login.php" method="POST">
                    <div class="input-underline">
                        <i class="bi bi-person-circle"></i>
                        <input type="text" name="username" placeholder="Username" required>
                    </div>

                    <div class="input-underline">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-login-pill">LOGIN</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>