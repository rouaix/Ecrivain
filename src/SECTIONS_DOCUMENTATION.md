# Nouvelles Rubriques de Livre - Documentation

## Vue d'ensemble

J'ai ajouté un système complet de gestion des sections de livre à votre application Ecrivain. Vous pouvez maintenant créer et gérer les rubriques suivantes :

### Sections avant les chapitres (dans cet ordre) :
1. **Couverture** - Avec support d'image
2. **Préface** - Texte d'introduction
3. **Introduction** - Présentation du contenu
4. **Prologue** - Début de l'histoire

### Sections après les chapitres (dans cet ordre) :
5. **Postface** - Conclusion ou réflexions finales
6. **Annexes** - Documents supplémentaires
7. **Notes** - Notes de l'auteur ou références
8. **Dos du livre** - Résumé ou quatrième de couverture (avec support d'image)

## Fonctionnalités implémentées

### 1. Nouveau modèle `Section.php`
- Gestion complète des sections spéciales du livre
- Support d'images pour la couverture et le dos du livre
- Ordre prédéfini des sections
- Une seule section de chaque type par projet

### 2. Base de données
- Nouvelle table `sections` créée automatiquement
- Stockage du contenu textuel et du chemin d'image
- Contrainte d'unicité : une seule section de chaque type par projet

### 3. Interface utilisateur
- **Page du projet** : Affichage de toutes les sections disponibles
  - Sections avant les chapitres affichées en premier
  - Chapitres au milieu
  - Sections après les chapitres à la fin
- Boutons "Créer" ou "Modifier" pour chaque type de section
- Bouton "Supprimer" pour les sections existantes

### 4. Éditeur de sections
- Formulaire simple avec :
  - Titre (optionnel, utilise le nom par défaut si vide)
  - Upload d'image (pour Couverture et Dos du livre)
  - Éditeur de contenu avec compteur de mots
- Prévisualisation de l'image actuelle si elle existe

### 5. Export EPUB
- Les sections sont maintenant incluses dans l'export EPUB
- Ordre correct : sections avant → chapitres → sections après
- Les images ne sont pas encore incluses dans l'EPUB (amélioration future possible)

## Comment utiliser

### Créer une section

1. Allez sur la page de votre projet
2. Dans la section "Sections avant les chapitres" ou "Sections après les chapitres", cliquez sur "Créer" pour le type de section souhaité
3. Remplissez le formulaire :
   - **Titre** : Optionnel, laissez vide pour utiliser le nom par défaut
   - **Image** : Pour la couverture ou le dos du livre uniquement
   - **Contenu** : Le texte de la section
4. Cliquez sur "Enregistrer"

### Modifier une section

1. Sur la page du projet, cliquez sur "Modifier" à côté de la section
2. Modifiez le contenu
3. Pour changer l'image, sélectionnez une nouvelle image
4. Cliquez sur "Enregistrer"

### Supprimer une section

1. Sur la page du projet, cliquez sur "Supprimer" à côté de la section
2. Confirmez la suppression

### Exporter avec les sections

1. Sur la page du projet, cliquez sur "Exporter en EPUB"
2. Le fichier EPUB contiendra toutes vos sections dans le bon ordre

## Structure des fichiers créés/modifiés

### Nouveaux fichiers :
- `app/models/Section.php` - Modèle pour gérer les sections
- `app/views/section/edit.php` - Vue pour créer/modifier une section

### Fichiers modifiés :
- `index.php` - Ajout des routes pour les sections et mise à jour de l'export EPUB
- `app/views/project/show.php` - Affichage des sections avant et après les chapitres

### Dossier créé automatiquement :
- `public/uploads/` - Stockage des images uploadées

## Notes techniques

- Les images sont stockées dans `public/uploads/` avec un nom unique
- La table `sections` a une contrainte d'unicité sur `(project_id, type)`
- Si vous essayez de créer une section qui existe déjà, elle sera mise à jour au lieu d'être dupliquée
- Les sections sont triées automatiquement selon leur ordre prédéfini

## Améliorations futures possibles

1. Support des images dans l'export EPUB
2. Éditeur WYSIWYG pour le contenu des sections
3. Possibilité de réordonner les sections par glisser-déposer
4. Prévisualisation de la couverture sur la page du projet
5. Templates prédéfinis pour chaque type de section
