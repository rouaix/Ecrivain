<?php
/* Registration form. Variables: $errors (array), $old (array) */
?>
<h2>Inscription</h2>
<?php if (!empty($errors)): ?>
    <div class="error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?php echo $base; ?>/register">
    <div class="form-group">
        <label for="username">Nom d’utilisateur *</label>
        <input type="text" id="username" name="username" value="<?php echo $old['username'] ?? ''; ?>" required>
    </div>
    <div class="form-group">
        <label for="email">Adresse e‑mail</label>
        <input type="text" id="email" name="email" value="<?php echo $old['email'] ?? ''; ?>">
    </div>
    <div class="form-group">
        <label for="password">Mot de passe *</label>
        <input type="password" id="password" name="password" required>
    </div>
    <input type="submit" value="Créer mon compte">
</form>
<p>Vous avez déjà un compte ? <a href="<?php echo $base; ?>/login">Connectez‑vous</a>.</p>