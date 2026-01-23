<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach (($errors?:[]) as $err): ?>
                <li><?= ($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form class="login" method="post" action="<?= ($base) ?>/login">
    <h2>Connexion</h2>
    <input type="hidden" name="csrf_token" value="<?= ($csrfToken) ?>">
    <div class="form-group">
        <label for="username">Nom d’utilisateur</label>
        <input type="text" id="username" name="username" value="<?= ($old['username']) ?>" required>
    </div>
    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>
    </div>
    <input type="submit" value="Se connecter">
    <p>Vous n'avez pas encore de compte ? <a href="<?= ($base) ?>/register">Inscrivez‑vous</a>.</p>
</form>