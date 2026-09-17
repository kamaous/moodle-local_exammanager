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
 * Draws the dashboard analytics bar chart.
 *
 * @module     local_exammanager/dashboard_chart
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * @param {String} canvasid Id of the <canvas> element.
     * @param {Array} labels Already-translated bar labels.
     * @param {Array} data Numeric values, one per label.
     */
    function init(canvasid, labels, data) {
        var canvas = document.getElementById(canvasid);
        if (!canvas) {
            return;
        }

        // eslint-disable-next-line no-undef
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ExamManager',
                    data: data
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {display: false}
                }
            }
        });
    }

    return {
        init: init
    };
});
