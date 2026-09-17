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
 * Drag-and-drop file input helper for the planning upload form.
 *
 * @module     local_exammanager/planning_dropzone
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function() {
            const $zone = $('.local-exammanager-dropzone');
            const $input = $zone.find('input[type=file]');
            if (!$zone.length || !$input.length) return;
            $zone.on('dragover', function(e) { e.preventDefault(); e.stopPropagation(); $zone.addClass('dragover'); });
            $zone.on('dragleave drop', function(e) { e.preventDefault(); e.stopPropagation(); $zone.removeClass('dragover'); });
            $zone.on('drop', function(e) {
                const files = e.originalEvent.dataTransfer.files;
                if (files && files.length > 0) $input[0].files = files;
            });
            $zone.on('click', function(e) {
                if (e.target.tagName.toLowerCase() !== 'input') $input.trigger('click');
            });
        }
    };
});