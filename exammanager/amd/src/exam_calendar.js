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
 * Initialises the FullCalendar view of scheduled exams.
 *
 * @module     local_exammanager/exam_calendar
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    /**
     * @param {String} elementid Id of the calendar container element.
     * @param {Array} events FullCalendar event objects.
     * @param {String} locale FullCalendar locale code (e.g. "en", "fr").
     */
    function init(elementid, events, locale) {
        Str.get_strings([
            {key: 'calendar_quiz', component: 'local_exammanager'},
            {key: 'calendar_start', component: 'local_exammanager'},
            {key: 'calendar_end', component: 'local_exammanager'},
            {key: 'calendar_course', component: 'local_exammanager'},
            {key: 'calendar_room', component: 'local_exammanager'},
            {key: 'calendar_teacher', component: 'local_exammanager'},
            {key: 'calendar_session', component: 'local_exammanager'}
        ]).then(function(results) {
            var strings = {
                quiz: results[0],
                start: results[1],
                end: results[2],
                course: results[3],
                room: results[4],
                teacher: results[5],
                session: results[6]
            };

            var calendarEl = document.getElementById(elementid);
            if (!calendarEl) {
                return null;
            }

            // eslint-disable-next-line no-undef
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: locale,
                height: 720,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: events,
                eventClick: function(info) {
                    var event = info.event;
                    var props = event.extendedProps || {};
                    var details = strings.quiz + ' : ' + event.title + '\n'
                        + strings.start + ' : ' + event.start.toLocaleString() + '\n';
                    if (event.end) {
                        details += strings.end + ' : ' + event.end.toLocaleString() + '\n';
                    }
                    details += strings.course + ' : ' + (props.course || '')
                        + '\n' + strings.room + ' : ' + (props.room || '')
                        + '\n' + strings.teacher + ' : ' + (props.teacher || '')
                        + '\n' + strings.session + ' : ' + (props.session || '');
                    // eslint-disable-next-line no-alert
                    window.alert(details);
                }
            });
            calendar.render();
            return null;
        }).catch(function() {
            return null;
        });
    }

    return {
        init: init
    };
});
