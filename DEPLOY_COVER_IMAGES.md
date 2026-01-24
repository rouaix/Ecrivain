# Déploiement : Correction de l'upload et affichage des images de couverture

## Problème résolu

Les images de couverture ne s'uploadaient pas et ne s'affichaient pas correctement en production à cause de :

1. **Chemin d'upload inaccessible** : Les images étaient uploadées dans `data/$email/projects/$pid/` qui est protégé par `.htaccess`
2. **CSS incomplet** : L'image avait `width: auto` et `height: auto` sans dimensions maximales
3. **Permissions restrictives** : `mkdir($uploadDir, 0777)` échouait en production

## Modifications apportées

### 1. ProjectController.php

#### Méthode `update()` (lignes 485-521)
- ✅ Upload maintenant dans `public/uploads/covers/` au lieu de `data/`
- ✅ Permissions changées de `0777` à `0755` (plus sécurisé)
- ✅ Ajout de logs de débogage pour tracer les erreurs
- ✅ Meilleure gestion des erreurs d'upload
- ✅ Nom de fichier : `project_{$pid}_couverture_{timestamp}.{ext}`

#### Méthode `cover()` (lignes 562-601)
- ✅ Chemin modifié pour servir depuis `public/uploads/covers/`
- ✅ Ajout de logs pour déboguer les problèmes

### 2. style.css (lignes 2421-2431)

```css
img.project-cover{
    position: absolute;
    top: 0;
    right: 0;
    max-width: 200px;        /* AJOUTÉ */
    max-height: 120px;       /* AJOUTÉ */
    width: auto;
    height: auto;
    object-fit: contain;     /* AJOUTÉ */
    border-radius: 8px;      /* AJOUTÉ */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* AJOUTÉ */
}
```

## Procédure de déploiement

### Étape 1 : Déployer les fichiers
```bash
# Sur le serveur de production
git pull origin main
```

### Étape 2 : Créer le répertoire d'upload
```bash
mkdir -p public/uploads/covers
chmod 755 public/uploads/covers
```

### Étape 3 : Migrer les images existantes
```bash
php migrate_cover_images.php
```

Le script va :
- ✅ Chercher toutes les images dans `data/*/projects/*/`
- ✅ Les copier vers `public/uploads/covers/`
- ✅ Mettre à jour la base de données
- ✅ Conserver les anciennes images (à supprimer manuellement après vérification)

### Étape 4 : Vérifier les logs
```bash
# Vérifier que l'upload fonctionne
tail -f data/auth_debug.log

# Les logs contiendront :
# - "Cover upload attempt - Type: ..., Ext: ..., Size: ..."
# - "Cover uploaded successfully: ..."
# - Ou les erreurs éventuelles
```

### Étape 5 : Tester
1. Aller sur `/project/{id}/edit`
2. Uploader une nouvelle image de couverture
3. Vérifier qu'elle s'affiche correctement
4. Vérifier dans `public/uploads/covers/` que le fichier existe

## Compatibilité

- ✅ Compatible avec l'ancien système (images dans `data/` continuent de fonctionner via `cover()`)
- ✅ Les nouvelles images iront dans `public/uploads/covers/`
- ✅ Le script de migration peut être exécuté plusieurs fois sans risque

## Rollback

Si besoin de revenir en arrière :
1. Restaurer l'ancienne version de `ProjectController.php`
2. Restaurer l'ancienne version de `style.css`
3. Les anciennes images dans `data/` sont toujours présentes

## Sécurité

- ✅ Validation stricte des types MIME et extensions
- ✅ Permissions `0755` au lieu de `0777`
- ✅ Noms de fichiers générés automatiquement (évite les collisions)
- ✅ Suppression automatique de l'ancienne image lors du remplacement

## Support

En cas de problème :
1. Vérifier les logs : `data/auth_debug.log`
2. Vérifier les permissions : `ls -la public/uploads/covers/`
3. Vérifier la base de données : `SELECT id, cover_image FROM projects WHERE cover_image IS NOT NULL`
