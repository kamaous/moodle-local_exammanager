<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French language strings.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Soft manager by KAMA';
$string['title'] = 'Soft manager by KAMA';
$string['exammanager:manage'] = 'Gérer la programmation des examens dans Soft manager';
$string['privacy:metadata'] = 'Le plugin Soft manager ne stocke aucune donnée personnelle. Il se contente de lire les métadonnées existantes des cours et des quiz pour programmer en masse les dates d’ouverture/fermeture, la durée et les codes d’accès des quiz.';
$string['room'] = 'Salle';
$string['teacher'] = 'Surveillant';
$string['total'] = 'Total';
$string['noroomconflict'] = 'Aucun conflit de salle';
$string['noteacherconflict'] = 'Aucun conflit de surveillant';
$string['reports_hero_subtitle'] = 'Détection automatique des conflits d’examens';
$string['sessions_placeholder'] = 'Page sessions incluse. Créez et gérez vos sessions depuis cette page.';
$string['index_hero_title'] = 'Programmation des examens';
$string['index_hero_subtitle'] = 'Upload, prévisualisation et programmation complète';
$string['activities_hero_subtitle'] = 'Restrictions d’accès par shortname de cours';
$string['history_hero_subtitle'] = 'Vue d’historique filtrée côté SQL avec pagination, sans exposition des codes de sécurité.';
$string['calendar_hero_subtitle'] = 'Calendrier interactif des examens';
$string['calendar_quiz'] = 'Quiz';
$string['calendar_start'] = 'Début';
$string['calendar_end'] = 'Fin';
$string['calendar_course'] = 'Cours';
$string['calendar_room'] = 'Salle';
$string['calendar_teacher'] = 'Surveillant';
$string['calendar_session'] = 'Session';
$string['analyticschart'] = 'Graphique analytique';
$string['dashboard'] = 'Dashboard';
$string['primarynavlabel'] = 'Soft manager';
$string['planning'] = 'Programmation';
$string['activitiesplanning'] = 'Planifier des activités';
$string['calendar'] = 'Calendrier';
$string['history'] = 'Historique';
$string['sessions'] = 'Sessions';
$string['reports'] = 'Rapports';
$string['uploadlabel'] = 'Déposez un planning CSV / XLSX / XLS';
$string['dragdrophelp'] = 'Glissez-déposez votre fichier ici ou cliquez pour le sélectionner.';
$string['requiredcolumns'] = 'Colonnes obligatoires : open_time, close_time, time_limit.';
$string['optionalcolumns'] = 'Colonnes optionnelles : course_shortname, quiz_name, teacher, room, session, access_code_action, seb_action, generate_access_code, generate_seb_exit_code, force_new_codes.';
$string['helptext'] = 'Recherche native des cours, édition visuelle, calendrier interactif, détection de conflits, QR salles et surveillants.';
$string['sessiontitle'] = 'Sessions d’examens';
$string['sessionname'] = 'Nom de la session';
$string['sessionstart'] = 'Date de début';
$string['sessionend'] = 'Date de fin';
$string['sessionstatus'] = 'Statut';
$string['selectsession'] = 'Sélectionner une session';
$string['nosession'] = 'Sans session';
$string['create'] = 'Créer';
$string['sessioncreated'] = 'Session créée';
$string['previewplanning'] = 'Prévisualiser le planning';
$string['programexams'] = 'Programmer les examens';
$string['results'] = 'Résultats';
$string['readytoprogram'] = 'Prévisualisation terminée.';
$string['processingdone'] = 'Programmation terminée.';
$string['nofile'] = 'Aucun fichier chargé.';
$string['invalidfile'] = 'Format non supporté.';
$string['course'] = 'Cours';
$string['quiz'] = 'Quiz';
$string['open'] = 'Ouverture';
$string['close'] = 'Fermeture';
$string['duration'] = 'Durée';
$string['accesscode'] = 'Code accès';
$string['sebexitcode'] = 'Code sortie SEB';
$string['status'] = 'Statut';
$string['message'] = 'Message';
$string['downloadcsv'] = 'Télécharger CSV';
$string['downloadexcel'] = 'Télécharger Excel';
$string['downloadpdfroom'] = 'Télécharger PDF salles';
$string['downloadpdfteacher'] = 'Télécharger PDF surveillants';
$string['downloadlog'] = 'Télécharger journal';
$string['previewok'] = 'Prévisualisation OK';
$string['programok'] = 'Quiz mis à jour avec succès';
$string['allowsharedcodes'] = 'Autoriser les codes partagés';
$string['allowsharedcodes_help'] = 'Si coché, les quiz d’un même cours ayant la même date/heure d’ouverture reçoivent les mêmes codes d’accès et de sortie SEB. Sinon, chaque quiz reçoit ses propres codes.';
$string['totalquestions'] = 'Questions programmées (total)';
$string['enrolledstudents'] = 'Étudiants inscrits (cours concernés)';
$string['avgparticipants'] = 'Participants moyens par test';
$string['avgcourseparticipation'] = 'Participants moyens par cours (%)';
$string['avgtestduration'] = 'Durée moyenne par tentative';
$string['totlexams'] = 'Examens programmés';
$string['roomsused'] = 'Salles utilisées';
$string['teachersused'] = 'Surveillants impliqués';
$string['todaysexams'] = 'Examens aujourd’hui';
$string['rowsinerror'] = 'Lignes en erreur';
$string['recentexams'] = 'Examens récents';
$string['roomsummary'] = 'Résumé par salle';
$string['teachersummary'] = 'Résumé par surveillant';
$string['calendarview'] = 'Calendrier interactif';
$string['conflictrooms'] = 'Conflits de salle';
$string['conflictteachers'] = 'Conflits de surveillant';
$string['quickprogram'] = 'Programmer des examens';
$string['managehistory'] = 'Consulter l’historique';
$string['managesessions'] = 'Gérer les sessions';
$string['viewcalendar'] = 'Voir le calendrier';
$string['courseplaceholder'] = 'Rechercher un cours';
$string['shortnamenotfound'] = 'Shortname du cours introuvable.';
$string['activitytargets_entershortname'] = 'Renseigner le shortname';
$string['activitytargets_choosesection'] = 'Choisir une section / tuile';
$string['activitytargets_chooseactivity'] = 'Choisir une activité';
$string['activitytargets_notused'] = 'Non utilisé';
$string['importedvalue'] = 'Valeur importée : {$a}';
$string['activityapply_removeactivities'] = 'Enlever les restrictions des activités sélectionnées';
$string['activityapply_removesections'] = 'Enlever les restrictions des sections / tuiles sélectionnées';
$string['activityapply_applyactivities'] = 'Appliquer aux activités sélectionnées';
$string['activityapply_applysections'] = 'Appliquer aux sections / tuiles sélectionnées';
$string['quizplaceholder'] = 'Choisir un quiz';
$string['searchnoresults'] = 'Aucun cours trouvé';
$string['searchloadmore'] = 'Afficher plus';
$string['searchloading'] = 'Chargement...';
$string['searchinprogress'] = 'Recherche en cours...';
$string['searcherror'] = 'Erreur pendant la recherche';
$string['quiznotfound'] = 'Aucun quiz trouvé';
$string['quizloaderror'] = 'Erreur de chargement';
$string['codesbyroom'] = 'QR codes par salle';
$string['codesbyteacher'] = 'QR codes par surveillant';

$string['historycourseshortname'] = 'Shortname du cours';
$string['historycourselink'] = 'Lien du cours';

$string['historystatsrunning'] = 'En cours';
$string['historystatshidden'] = 'Masqués / non visibles';
$string['historystatsfinished'] = 'Terminés';
$string['historystatusscheduled'] = 'Programmé';
$string['historystatusrunning'] = 'En cours';
$string['historystatusfinished'] = 'Terminé';
$string['historystatushidden'] = 'Programmé mais caché';
$string['historystatuserror'] = 'Anomalie';
