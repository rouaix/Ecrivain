<?php
/* Login form view. Variables: $errors (array), $old (array) */
?>
<h2>Connexion</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/login">
    <div class="form-group">
        <label for="username">Nom d’utilisateur</label>
        <input type="text" id="username" name="username" value="<?php echo $old['username'] ?? ''; ?>" required>
    </div>
    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required>
    </div>
    <input type="submit" value="Se connecter">
</form>
<p>Vous n'avez pas encore de compte ? <a href="<?php echo $base; ?>/register">Inscrivez‑vous</a>.</p>