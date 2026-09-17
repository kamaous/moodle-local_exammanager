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
 * Loads the sections/activities of a course into each row of the bulk
 * activity restriction table, and toggles the restriction fields shown
 * for each row.
 *
 * @module     local_exammanager/activity_targets
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var strings = {};
    var targetsCache = {};

    /**
     * Load every UI string used by this module once, up front.
     *
     * @return {Promise}
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'activitytargets_entershortname', component: 'local_exammanager'},
            {key: 'searchloading', component: 'local_exammanager'},
            {key: 'shortnamenotfound', component: 'local_exammanager'},
            {key: 'activitytargets_choosesection', component: 'local_exammanager'},
            {key: 'activitytargets_chooseactivity', component: 'local_exammanager'},
            {key: 'activitytargets_notused', component: 'local_exammanager'},
            {key: 'importedvalue', component: 'local_exammanager'}
        ]).then(function(results) {
            strings = {
                entershortname: results[0],
                loading: results[1],
                coursenotfound: results[2],
                choosesection: results[3],
                chooseactivity: results[4],
                notused: results[5],
                importedvalue: results[6]
            };
            return strings;
        });
    }

    function addOption(select, value, label, dataset) {
        var option = document.createElement('option');
        option.value = value || '';
        option.textContent = label || value || '';
        if (dataset) {
            Object.keys(dataset).forEach(function(key) {
                option.dataset[key] = dataset[key];
            });
        }
        select.appendChild(option);
        return option;
    }

    function selectedOrCurrent(select) {
        return select.value || select.dataset.current || '';
    }

    function setSelectValue(select, value) {
        value = value || '';
        var exists = Array.prototype.some.call(select.options, function(option) {
            return option.value === value;
        });
        if (value !== '' && !exists) {
            addOption(select, value, strings.importedvalue.replace('{$a}', value), {imported: '1'});
        }
        select.value = value;
        select.dataset.current = value;
    }

    function sectionMatches(activity, sectionValue) {
        return !sectionValue || String(activity.sectionnum || '') === String(sectionValue);
    }

    function populateActivitySelect(row, data, preserveValue) {
        var activitySelect = row.querySelector('.exammanager-bulk-activity');
        var sectionSelect = row.querySelector('.exammanager-bulk-section');
        var targetSelect = row.querySelector('.exammanager-bulk-target-type');
        if (!activitySelect || !sectionSelect || !targetSelect) {
            return;
        }

        var current = preserveValue ? selectedOrCurrent(activitySelect) : '';
        var sectionValue = sectionSelect.value || '';
        activitySelect.innerHTML = '';
        addOption(activitySelect, '', targetSelect.value === 'activities' ? strings.chooseactivity : strings.notused);
        (data.activities || []).forEach(function(activity) {
            if (!sectionMatches(activity, sectionValue)) {
                return;
            }
            addOption(activitySelect, activity.value, activity.label, {sectionnum: activity.sectionnum || ''});
        });

        activitySelect.disabled = targetSelect.value !== 'activities';
        setSelectValue(activitySelect, targetSelect.value === 'activities' ? current : '');
    }

    function populateTargetSelects(row, data, preserveValues) {
        var sectionSelect = row.querySelector('.exammanager-bulk-section');
        var targetSelect = row.querySelector('.exammanager-bulk-target-type');
        if (!sectionSelect || !targetSelect) {
            return;
        }

        var currentSection = preserveValues ? selectedOrCurrent(sectionSelect) : '';
        sectionSelect.innerHTML = '';
        addOption(sectionSelect, '', strings.choosesection);
        (data.sections || []).forEach(function(section) {
            addOption(sectionSelect, section.value, section.label, {sectionnum: section.sectionnum || section.value || ''});
        });
        sectionSelect.disabled = false;
        setSelectValue(sectionSelect, currentSection);
        populateActivitySelect(row, data, preserveValues);
    }

    function setTargetsLoading(row, label) {
        var sectionSelect = row.querySelector('.exammanager-bulk-section');
        var activitySelect = row.querySelector('.exammanager-bulk-activity');
        if (sectionSelect) {
            sectionSelect.innerHTML = '';
            addOption(sectionSelect, '', label);
            sectionSelect.disabled = true;
        }
        if (activitySelect) {
            activitySelect.innerHTML = '';
            addOption(activitySelect, '', label);
            activitySelect.disabled = true;
        }
    }

    /**
     * Load the sections/activities of a course (by shortname) through the
     * local_exammanager_get_activity_targets external service.
     *
     * @param {Element} row Table row element.
     * @param {Boolean} preserveValues Whether to keep the current selections.
     */
    function loadTargets(row, preserveValues) {
        var courseInput = row.querySelector('.exammanager-bulk-course');
        if (!courseInput) {
            return;
        }

        var shortname = courseInput.value.trim();
        if (shortname === '') {
            setTargetsLoading(row, strings.entershortname);
            return;
        }

        if (targetsCache[shortname]) {
            populateTargetSelects(row, targetsCache[shortname], preserveValues);
            return;
        }

        setTargetsLoading(row, strings.loading);

        Ajax.call([{
            methodname: 'local_exammanager_get_activity_targets',
            args: {shortname: shortname}
        }])[0].done(function(data) {
            if (!data || !data.success) {
                setTargetsLoading(row, strings.coursenotfound);
                return;
            }
            if (courseInput.value.trim() !== shortname) {
                return;
            }
            targetsCache[shortname] = data;
            populateTargetSelects(row, data, preserveValues);
        }).fail(function() {
            setTargetsLoading(row, strings.coursenotfound);
        });
    }

    function setupTargetRow(row) {
        var courseInput = row.querySelector('.exammanager-bulk-course');
        var targetSelect = row.querySelector('.exammanager-bulk-target-type');
        var sectionSelect = row.querySelector('.exammanager-bulk-section');
        var typingTimer = null;

        if (courseInput) {
            courseInput.addEventListener('input', function() {
                window.clearTimeout(typingTimer);
                typingTimer = window.setTimeout(function() {
                    loadTargets(row, false);
                }, 350);
            });
            courseInput.addEventListener('change', function() {
                loadTargets(row, false);
            });
        }

        if (targetSelect) {
            targetSelect.addEventListener('change', function() {
                loadTargets(row, true);
            });
        }

        if (sectionSelect) {
            sectionSelect.addEventListener('change', function() {
                var course = courseInput ? courseInput.value.trim() : '';
                var data = targetsCache[course];
                if (data) {
                    populateActivitySelect(row, data, false);
                } else {
                    loadTargets(row, true);
                }
            });
        }

        loadTargets(row, true);
    }

    function initRestrictionToggles() {
        document.querySelectorAll('[data-bulk-restriction-row]').forEach(function(row) {
            var kind = row.querySelector('.exammanager-bulk-restriction-kind');
            var show = row.querySelector('[data-bulk-show]');
            var toggles = Array.prototype.slice.call(row.querySelectorAll('.exammanager-bulk-set-toggle'));

            function isSetChildVisible(key) {
                var toggle = row.querySelector('[data-bulk-set-toggle="' + key + '"]');
                return toggle && toggle.value === '1';
            }

            function syncRestrictionRow() {
                var value = kind ? kind.value : 'date';
                row.querySelectorAll('[data-bulk-group]').forEach(function(group) {
                    var key = group.dataset.bulkGroup;
                    var visible = key === value;

                    if (value === 'set' && (key === 'date' || key === 'grade' || key === 'profile')) {
                        visible = isSetChildVisible(key);
                    }

                    group.style.display = visible ? '' : 'none';
                });

                if (show) {
                    show.style.display = value === 'remove' ? 'none' : '';
                }
            }

            if (kind) {
                kind.addEventListener('change', syncRestrictionRow);
            }
            toggles.forEach(function(toggle) {
                toggle.addEventListener('change', syncRestrictionRow);
            });
            syncRestrictionRow();
        });
    }

    return {
        init: function() {
            loadStrings().then(function() {
                document.querySelectorAll('.exammanager-bulk-target-row').forEach(setupTargetRow);
                initRestrictionToggles();
                return null;
            }).catch(function() {
                return null;
            });
        }
    };
});
