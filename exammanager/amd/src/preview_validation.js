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
 * Client-side validation of the exam planning preview table, before the
 * "Program exams" button is enabled.
 *
 * @module     local_exammanager/preview_validation
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    var strings = {};

    function loadStrings() {
        return Str.get_strings([
            {key: 'shortnamenotfound', component: 'local_exammanager'},
            {key: 'validation_datesrequired', component: 'local_exammanager'},
            {key: 'validation_dateinvalid', component: 'local_exammanager'},
            {key: 'validation_closeafteropen', component: 'local_exammanager'},
            {key: 'validation_durationrequired', component: 'local_exammanager'},
            {key: 'validation_quiznotselected', component: 'local_exammanager'},
            {key: 'validation_correctionneeded', component: 'local_exammanager'}
        ]).then(function(results) {
            strings = {
                shortnamenotfound: results[0],
                datesrequired: results[1],
                dateinvalid: results[2],
                closeafteropen: results[3],
                durationrequired: results[4],
                quiznotselected: results[5],
                correctionneeded: results[6]
            };
            return strings;
        });
    }

    function isValidDateTimeLocal(value) {
        if (!value) {
            return false;
        }
        return !Number.isNaN(new Date(value).getTime());
    }

    function validateRow(row) {
        var errors = [];
        var courseValid = row.dataset.courseValid || 'missing';
        var quizRequired = row.dataset.quizRequired || '1';
        var openInput = row.querySelector('.exammanager-open-input');
        var closeInput = row.querySelector('.exammanager-close-input');
        var durationInput = row.querySelector('.exammanager-duration-input');
        var quizSelect = row.querySelector('.exammanager-quiz-select');
        var statusText = row.querySelector('.exammanager-status-text');
        var messageText = row.querySelector('.exammanager-message-text');
        var inlineErrors = row.querySelector('.exammanager-inline-errors');

        var openValue = openInput ? openInput.value.trim() : '';
        var closeValue = closeInput ? closeInput.value.trim() : '';
        var durationValue = durationInput ? durationInput.value.trim() : '';

        if (courseValid === 'invalid') {
            errors.push(strings.shortnamenotfound);
        }

        if (!openValue || !closeValue) {
            errors.push(strings.datesrequired);
        } else if (!isValidDateTimeLocal(openValue) || !isValidDateTimeLocal(closeValue)) {
            errors.push(strings.dateinvalid);
        } else if (new Date(closeValue).getTime() <= new Date(openValue).getTime()) {
            errors.push(strings.closeafteropen);
        }

        if (durationValue === '' || Number(durationValue) <= 0) {
            errors.push(strings.durationrequired);
        }

        if (quizRequired === '1' && (!quizSelect || !quizSelect.value)) {
            errors.push(strings.quiznotselected);
        }

        row.classList.toggle('table-danger', errors.length > 0);
        if (inlineErrors) {
            inlineErrors.innerHTML = errors.map(function(error) {
                return '<div>' + error + '</div>';
            }).join('');
        }

        if (statusText && messageText && errors.length > 0) {
            statusText.textContent = 'ERROR';
            messageText.textContent = strings.correctionneeded;
        }

        return errors;
    }

    return {
        init: function() {
            var form = document.getElementById('exammanager-preview-form');
            var programBtn = document.getElementById('exammanager-program-btn');
            if (!form || !programBtn) {
                return;
            }

            loadStrings().then(function() {
                function validateAllRows() {
                    var rows = form.querySelectorAll('.exammanager-preview-row');
                    var totalErrors = 0;
                    rows.forEach(function(row) {
                        totalErrors += validateRow(row).length;
                    });
                    programBtn.disabled = totalErrors > 0;
                }

                form.addEventListener('input', function(e) {
                    if (e.target.closest('.exammanager-preview-row')) {
                        validateAllRows();
                    }
                });
                form.addEventListener('change', function(e) {
                    if (e.target.closest('.exammanager-preview-row')) {
                        validateAllRows();
                    }
                });
                validateAllRows();
                return null;
            }).catch(function() {
                return null;
            });
        }
    };
});
