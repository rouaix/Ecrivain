<?php
// Main layout template. Expects variables $title and $content.
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Assistant'); ?></title>
    <link rel="manifest" href="<?php echo $base; ?>/public/manifest.json">
    <meta name="theme-color" content="#3f51b5">
    <script src="https://cdn.tiny.cloud/1/4c2e77otz0bu6nml5zzxiszsp8ax7m4nx2u4egj2zaus9anz/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <?php
    // Determine user‑selected theme stored in a cookie. Default is "default".
    $selectedTheme = $_COOKIE['theme'] ?? 'default';
    ?>
    <link rel="stylesheet" href="<?php echo $base; ?>/style.css">
    <link rel="stylesheet" href="<?php echo $base; ?>/public/theme-<?php echo htmlspecialchars($selectedTheme); ?>.css">
    <style>
        :root {
            /* Default color variables used by the layout. These values correspond to the "default" theme. */
            --body-bg: #f6f6f6;
            --header-bg: #3f51b5;
            --header-text: #ffffff;
            --footer-bg: #eeeeee;
            --footer-text: #666666;
            --button-bg: #4caf50;
            --button-delete-bg: #f44336;
            --button-primary-bg: #3f51b5;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--body-bg);
        }

        header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        header h1 {
            margin: 0;
            font-size: 1.4em;
        }

        nav a {
            color: var(--header-text);
            margin-right: 15px;
            text-decoration: none;
        }

        nav a:last-child {
            margin-right: 0;
        }

        main {
            padding: 20px;
        }

        footer {
            background: var(--footer-bg);
            color: var(--footer-text);
            text-align: center;
            padding: 10px;
            font-size: 0.9em;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .error {
            color: red;
        }

        .form-group {
            margin-bottom: 10px;
        }

        label {
            display: block;
            margin-bottom: 4px;
        }

        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
        }

        input[type="submit"],
        button {
            background: var(--button-primary-bg);
            color: #fff;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f0f0f0;
        }

        a.button {
            display: inline-block;
            background: var(--button-bg);
            color: #fff;
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 3px;
            margin-right: 5px;
        }

        a.button.delete {
            background: var(--button-delete-bg);
        }

        a.button.secondary {
            background: #9e9e9e;
        }
    </style>
</head>

<body class="theme-<?php echo htmlspecialchars($selectedTheme); ?>">
    <header>
        <h1>Assistant</h1>
        <nav style="display:flex; align-items:center;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $base; ?>/dashboard">Tableau de bord</a>
                <a href="<?php echo $base; ?>/logout">Déconnexion</a>
            <?php else: ?>
                <a href="<?php echo $base; ?>/">Accueil</a>
                <a href="<?php echo $base; ?>/login">Connexion</a>
                <a href="<?php echo $base; ?>/register">Inscription</a>
            <?php endif; ?>
            <!-- Theme selector -->
            <form id="themeForm" method="post" action="<?php echo $base; ?>/theme" style="margin-left:20px;">
                <label for="themeSelect" style="color: var(--header-text); margin-right:5px;">Thème:</label>
                <select name="theme" id="themeSelect" onchange="document.getElementById('themeForm').submit();">
                    <?php $themes = ['default' => 'Classique', 'dark' => 'Sombre', 'modern' => 'Moderne'];
                    foreach ($themes as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $selectedTheme === $key ? 'selected' : ''; ?>>
                        
                                <?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </nav>
    </header>
    <main>
        <div class="container">
            <?php echo $content; ?>
        </div>
    </main>
    <footer>
        &copy; <?php echo date('Y'); ?> Assistant - Ce logiciel est un exemple pédagogique.
    </footer>

    <!-- Register service worker for PWA support -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?php echo $base; ?>/public/service-worker.js').catch(function (err) {
                    console.error('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>

</html>