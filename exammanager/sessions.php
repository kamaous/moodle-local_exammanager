<?php
require('../../config.php');
require_login();
$context = context_system::instance();
require_capability('local/exammanager:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/exammanager/sessions.php'));
$PAGE->set_title(get_string('sessions', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));
echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('sessions');
echo '<div class="local-exammanager-panel"><p>Page sessions incluse. Créez et gérez vos sessions depuis cette page.</p></div>';
echo html_writer::end_div();
echo $OUTPUT->footer();
