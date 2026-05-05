<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings for component 'report_individualized', language 'fr'.
 *
 * @package   report_individualized
 * @copyright 2025 Ifrass
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']          = 'Rapport individualisé étudiant';
$string['individualized:view'] = 'Voir le rapport individualisé étudiant';
$string['allstudentsreport'] = 'Rapport de tous les étudiants';

// Filtres.
$string['selectuser']   = 'Sélectionner un étudiant';
$string['selectcourse'] = 'Sélectionner un cours';
$string['allusers']     = 'Tous les étudiants';
$string['allcourses']   = 'Tous les cours';

// Messages vides.
$string['noenrolments'] = 'Cet étudiant n\'est inscrit à aucun cours.';
$string['noresources']  = 'Aucune ressource dans ce cours.';
$string['noactivities'] = 'Aucune activité dans ce cours.';

// Sections.
$string['resources']  = 'Ressources';
$string['activities'] = 'Activités';

// -------------------------------------------------------------------------
// Colonnes — deux groupes de chaînes pour chaque colonne :
//  - La chaîne "simple" (ex. 'resourcename') est utilisée par settings.php
//    pour le label de la checkbox admin.
//  - Les chaînes "split" (ex. 'resourcename_type', 'resourcename_modality')
//    sont utilisées par index.php pour construire l'en-tête multi-lignes.
// -------------------------------------------------------------------------

// Colonne type/modalité.
$string['resourcename']          = 'Type et modalité pédagogique';
$string['resourcename_type']     = 'Type';
$string['resourcename_modality'] = 'Modalité pédagogique';
$string['activityname']          = 'Type et modalité pédagogique';
$string['activityname_type']     = 'Type';
$string['activityname_modality'] = 'Modalité pédagogique';
$string['columnheader_connector'] = ' et';

// Colonne date d'ouverture.
$string['availablefrom'] = 'Date d\'ouverture';

// Colonne consulté.
$string['viewed'] = 'Consulté';

// Colonne plage de consultation.
$string['viewrange'] = 'Plage de consultation';

// Colonne nombre de consultations — split pour en-tête multi-lignes.
$string['viewcount']       = 'Total consultations';
$string['viewcount_line1'] = 'Total';
$string['viewcount_line2'] = 'consultations';

// Colonne durée estimée — split pour en-tête multi-lignes.
$string['estimatedduration']       = 'Durée estimée';
$string['estimatedduration_line1'] = 'Durée';
$string['estimatedduration_line2'] = 'estimée';

// Colonnes activités uniquement.
$string['duedate']   = 'Date de fermeture';
$string['grade']     = 'Note';
$string['feedback']  = 'Feedback';
$string['completion'] = 'Complétion';
$string['opendate']  = 'Trace d\'ouverture';
$string['closedate'] = 'Trace de fermeture';

// Métriques récapitulatives (bandeau résumé section + cours).
$string['summary_completionrate']  = 'Complétion';
$string['summary_avggrade']        = 'Note moyenne';
$string['summary_resourcesviewed'] = 'Ressources vues';

// Badges de durée dans l'en-tête de section.
$string['profestimated']    = 'Estimation prof';
$string['studentestimated'] = 'Estimation étudiant';

// Export PDF.
$string['exportpdf']   = 'Exporter en PDF';
$string['reportfor']   = 'Rapport de';
$string['generatedon'] = 'Généré le';

// RGPD.
$string['privacy:metadata'] = 'Le plugin Rapport individualisé étudiant ne stocke aucune donnée personnelle. Il affiche uniquement les logs et données de notes existants dans Moodle.';

// Paramètres d\'affichage.
$string['unnamedsection'] = 'Section sans titre';
$string['columnsettings'] = 'Paramètres d\'affichage des rapports individualisés';

// Options avancées.
$string['advancedsettings']             = 'Options avancées';
$string['workshop_feedback_type']       = 'Atelier — lignes à afficher dans le rapport';
$string['workshop_feedback_type_desc']  = 'Choisissez quelles lignes afficher pour les activités de type Atelier. Par défaut les deux sont affichées.';
$string['workshop_feedback_both']       = 'Les deux (soumission + évaluation par pair)';
$string['workshop_feedback_submission'] = 'Soumission uniquement';
$string['workshop_feedback_assessment'] = 'Évaluation uniquement';
$string['categorydepth']      = 'Profondeur du chemin de catégorie';
$string['categorydepth_desc'] = 'Nombre maximum de niveaux affichés dans le chemin de catégorie. Mettre 0 pour tout afficher.';

// Filtres de date.
$string['datefrom']    = 'Du';
$string['dateto']      = 'Au';
$string['applyfilter'] = 'Appliquer';
$string['resetfilter'] = 'Réinitialiser';

// Filtre catégorie.
$string['selectcategory']   = 'Sélectionner une catégorie';
$string['allcategories']    = 'Toutes les catégories';
