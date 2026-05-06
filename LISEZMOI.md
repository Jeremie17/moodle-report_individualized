# report_individualized

Plugin de rapport individualisé étudiant pour Moodle 5.

Génère un rapport détaillé par étudiant de son activité au sein d'un cours — exportable en PDF — destiné à la soumission aux organismes financeurs OPCO.

Développé par Ifrass (2025–2026).

---

## Prérequis

| Dépendance | Version |
|---|---|
| Moodle | 5.0 ou supérieur (build 2025041400+) |
| PHP | 8.2 ou supérieur |
| Node.js | 22 (exécution) — voir [Limitations connues](#limitations-connues) pour la compilation AMD |

---

## Installation

1. Copier le dossier `report/individualized/` dans votre installation Moodle sous `{racine_moodle}/report/individualized/`.
2. Se connecter en tant qu'administrateur et aller dans **Administration du site → Notifications** pour déclencher l'installation automatique.
3. Confirmer l'installation du plugin.
4. Vider tous les caches : **Administration du site → Développement → Purger tous les caches**.

---

## Champs personnalisés (configuration requise)

Ce plugin lit deux champs personnalisés qui doivent être créés par un administrateur avant que le plugin puisse afficher toutes les colonnes.

Aller dans **Administration du site → Cours → Champs personnalisés de cours** et créer une catégorie de champ (par exemple « Formation »), puis ajouter les champs suivants :

### `modalite` — Modalité pédagogique

| Paramètre | Valeur |
|---|---|
| Type de champ | Menu déroulant |
| Nom court | `modalite` |
| Options | Une option par ligne, ex. : `Recherche personnelle`, `Débat`, `Évaluation par les pairs`, `Travail de groupe`, `Synthèse de document`, `Auto-évaluation` |

Ce champ est renseigné par activité par le formateur dans les paramètres de l'activité sous **Formation → Modalité pédagogique**.

### `duree_estimee` — Durée estimée

| Paramètre | Valeur |
|---|---|
| Type de champ | Texte court |
| Nom court | `duree_estimee` |

Le formateur saisit une durée en **minutes** (entier). Exemple : `30` pour 30 minutes.

Ce champ est renseigné par activité par le formateur dans les paramètres de l'activité sous **Formation → Durée estimée**.

### Feedback TIME — Durée déclarée par l'étudiant

Pour permettre aux étudiants de déclarer le temps passé sur une section, le formateur crée une activité **Feedback** dont le **Numéro d'identification** commence par `TIME` (insensible à la casse, espaces de début ignorés). La première réponse numérique de l'étudiant à n'importe quelle question de ce feedback est utilisée comme durée déclarée en minutes.

---

## Fonctionnalités

- Filtrage par étudiant, cours, catégorie et plage de dates
- Rapport organisé par section de cours
- Deux tableaux par section : **Ressources** et **Activités**
- Badges récapitulatifs par section et par cours (durée estimée, durée déclarée étudiant, taux de complétion, note moyenne, ressources consultées)
- Prise en charge spécialisée du **Workshop** (2 lignes : soumission + évaluation par les pairs, avec durée fixe de 10 minutes pour la ligne d'évaluation)
- Prise en charge spécialisée des activités **H5P** (Flashcards, Vidéo interactive avec quiz) — voir [Prise en charge H5P](#prise-en-charge-h5p) ci-dessous
- Feedback spécialisé par type d'activité : devoir, quiz, workshop, H5P, fallback gradebook
- Colonnes dynamiques : l'administrateur peut afficher/masquer les colonnes depuis **Administration du site → Rapports → Paramètres du rapport individualisé**
- Export PDF : rapport complet, par cours, par section, par tableau (ressources ou activités)
- Filtres AJAX : pas de rechargement de page lors du changement d'étudiant, de cours ou de plage de dates
- Support complet des langues anglaise et française

---

## Prise en charge H5P

Toutes les activités H5P apparaissent dans le tableau **Activités**, quel que soit leur type de contenu.

### Ce que le rapport peut afficher

| Type de contenu H5P | Complétion | Trace de fermeture | Remarques |
|---|---|---|---|
| Flashcard | ✓ quand toutes les cartes répondues | ✓ | Entièrement pris en charge |
| Vidéo interactive avec quiz | ✓ après soumission | ✓ | L'étudiant doit cliquer sur **Envoyer les réponses** à la fin |
| Vidéo simple (sans quiz) | ✗ toujours | — toujours | Voir limitation ci-dessous |

### Dates d'ouverture et de fermeture

Les activités H5P ne disposent pas de paramètres de disponibilité natifs. Le plugin lit les dates depuis les **restrictions d'accès** configurées sur l'activité. Si aucune restriction n'est configurée, la chaîne de fallback s'applique : date de la note → trace de soumission de l'étudiant.

### Limitation connue H5P

Les vidéos H5P simples (YouTube ou fichier sans quiz intégrés) n'envoient aucun statement xAPI à Moodle lors de la lecture. La complétion affichera toujours ✗ et la trace de fermeture sera toujours vide (—). Il s'agit d'une limitation de H5P lui-même.

**Recommandation** : utiliser le format H5P Vidéo interactive avec au moins un quiz intégré, et informer les étudiants qu'ils doivent cliquer sur **Envoyer les réponses** à la fin pour que leur progression soit enregistrée.

---

## Permissions

| Capacité | Rôle par défaut |
|---|---|
| `report/individualized:view` | Gestionnaire, Enseignant, Enseignant non éditeur |

Pour accorder l'accès à d'autres rôles, aller dans **Administration du site → Utilisateurs → Permissions → Définir les rôles**.

---

## Déploiement

### Mode développement (local)

En développement, ajouter la ligne suivante dans `config.php` pour servir les modules AMD directement sans compilation :

```php
$CFG->cachejs = false;
```

### Déploiement en production

La compilation AMD via le Gruntfile de Moodle est actuellement bloquée par une incompatibilité entre Moodle 5.1.x et Node.js 22. Les fichiers de build sont pré-compilés avec terser en attendant que Moodle corrige ce problème en amont. Voir [Limitations connues](#limitations-connues) pour plus de détails.

---

## Lancer les tests

Des tests PHPUnit sont inclus pour les classes utilitaires principales. Pour les lancer en local :

```bash
# Initialiser l'environnement PHPUnit (une seule fois)
php admin/tool/phpunit/cli/init.php

# Lancer tous les tests du plugin
cd report/individualized
php /chemin/vers/moodle/vendor/bin/phpunit \
    --bootstrap /chemin/vers/moodle/public/lib/phpunit/bootstrap.php \
    --testdox
```

Les tests couvrent : `duration_util`, `date_util`, `completion_util`, `summary_util`.

---

## Structure des fichiers

```
report/individualized/
├── version.php                          Métadonnées et version du plugin
├── settings.php                         Paramètres admin (colonnes visibles)
├── lib.php                              Hooks Moodle + shell du callback fragment
├── index.php                            Page principale du rapport
├── export_pdf.php                       Export PDF
├── styles.css                           CSS personnalisé
├── phpunit.xml                          Configuration de la suite de tests PHPUnit
├── amd/
│   ├── src/filters.js                   Module AMD (filtres AJAX, source)
│   └── build/filters.min.js            Module AMD (compilé — requis en production)
├── classes/
│   ├── external/
│   │   └── get_filter_options.php       Fonction externe (options des filtres AJAX)
│   ├── output/
│   │   └── report_fragment.php          Renderer de fragment (contenu rapport AJAX)
│   └── util/
│       ├── date_util.php                Formatage et récupération des dates
│       ├── view_stats_util.php          Statistiques de consultation et libellés d'activités
│       ├── completion_util.php          Icônes et statut de complétion
│       ├── duration_util.php            Formatage et récupération des durées
│       ├── feedback_util.php            Récupération du feedback par type de module
│       ├── workshop_util.php            Données spécifiques Workshop (2 lignes)
│       ├── summary_util.php             Métriques récapitulatives (taux de complétion, note moy…)
│       └── category_util.php            Chemin de catégorie et filtrage
├── db/
│   ├── access.php                       Définitions des capacités
│   └── services.php                     Déclarations des fonctions externes
├── tests/
│   └── util/                            Fichiers de tests PHPUnit
└── lang/
    ├── en/report_individualized.php     Chaînes en anglais
    └── fr/report_individualized.php     Chaînes en français
```

---

## Limitations connues

- La pagination n'est pas implémentée. Avec `flexible_table` et l'accumulation de données côté PHP, la pagination native Moodle nécessite des requêtes pilotées par SQL. Ceci est prévu pour une version future.
- L'engagement sur les ressources ne peut pas être vérifié au-delà de l'événement de log `viewed`. Moodle ne trace pas le temps passé sur les fichiers ou les URL externes.
- Les vidéos H5P simples (sans quiz intégrés) ne génèrent pas de statements xAPI — la complétion et la trace de fermeture sont indisponibles pour ce type de contenu.
- La compilation AMD via le Gruntfile de Moodle est actuellement bloquée par une incompatibilité entre Moodle 5.1.x et Node.js 22. Les fichiers de build sont pré-compilés avec terser en attendant que Moodle corrige ce problème en amont.

---

## Licence

GNU General Public License v3 ou ultérieure — voir [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)