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
 * Client-side behaviour for the "restriction to apply" form on the
 * activity planning page: target type switch, restriction kind switch,
 * restriction set toggles and select-all/none helpers.
 *
 * @module     local_exammanager/activity_apply
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    var strings = {};

    /**
     * Load every UI string used by this module once, up front.
     *
     * @return {Promise}
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'activityapply_removeactivities', component: 'local_exammanager'},
            {key: 'activityapply_removesections', component: 'local_exammanager'},
            {key: 'activityapply_applyactivities', component: 'local_exammanager'},
            {key: 'activityapply_applysections', component: 'local_exammanager'}
        ]).then(function(results) {
            strings = {
                removeactivities: results[0],
                removesections: results[1],
                applyactivities: results[2],
                applysections: results[3]
            };
            return strings;
        });
    }

    return {
        init: function() {
            loadStrings().then(function() {
                var form = document.getElementById('exammanager-activities-form');
                if (!form) {
                    return null;
                }

                var sectionTargets = Array.prototype.slice.call(form.querySelectorAll('.exammanager-section-target'));
                var activityTargets = Array.prototype.slice.call(form.querySelectorAll('.exammanager-activity-target'));
                var selectAll = document.getElementById('exammanager-select-all');
                var selectNone = document.getElementById('exammanager-select-none');
                var targetType = document.getElementById('exammanager-target-type');
                var restrictionKind = document.getElementById('exammanager-restriction-kind');
                var applyButton = document.getElementById('exammanager-apply-activities-btn');
                var showLine = form.querySelector('.local-exammanager-showline');

                function currentTargets() {
                    return targetType && targetType.value === 'activities' ? activityTargets : sectionTargets;
                }

                function setCurrentTargets(checked) {
                    currentTargets().forEach(function(checkbox) {
                        checkbox.checked = checked;
                    });
                }

                if (selectAll) {
                    selectAll.addEventListener('click', function() {
                        setCurrentTargets(true);
                    });
                }
                if (selectNone) {
                    selectNone.addEventListener('click', function() {
                        setCurrentTargets(false);
                    });
                }

                function syncApplyButton() {
                    if (!applyButton) {
                        return;
                    }

                    var targetValue = targetType ? targetType.value : 'sections';
                    var restrictionValue = restrictionKind ? restrictionKind.value : 'date';
                    if (restrictionValue === 'remove') {
                        applyButton.textContent = targetValue === 'activities'
                            ? strings.removeactivities
                            : strings.removesections;
                        return;
                    }

                    applyButton.textContent = targetValue === 'activities'
                        ? strings.applyactivities
                        : strings.applysections;
                }

                function syncTargetPanels() {
                    var value = targetType ? targetType.value : 'sections';
                    form.querySelectorAll('[data-target-panel]').forEach(function(panel) {
                        var active = panel.dataset.targetPanel === value;
                        panel.style.display = active ? '' : 'none';
                        panel.querySelectorAll('input[type=checkbox]').forEach(function(input) {
                            input.disabled = !active;
                            if (!active) {
                                input.checked = false;
                            }
                        });
                    });

                    syncApplyButton();
                }

                function syncRestrictionFields() {
                    var value = restrictionKind ? restrictionKind.value : 'date';
                    form.querySelectorAll('[data-restriction-fields]').forEach(function(panel) {
                        panel.style.display = panel.dataset.restrictionFields === value ? '' : 'none';
                    });
                    if (showLine) {
                        showLine.style.display = value === 'remove' ? 'none' : '';
                    }
                    syncApplyButton();
                }

                function syncSetFields() {
                    form.querySelectorAll('.exammanager-set-toggle input').forEach(function(toggle) {
                        var key = toggle.name.replace('set_', '');
                        var panel = form.querySelector('[data-set-fields="' + key + '"]');
                        if (panel) {
                            panel.style.display = toggle.checked ? '' : 'none';
                        }
                    });
                }

                function syncProfileValues() {
                    form.querySelectorAll('.exammanager-profile-operator').forEach(function(operator) {
                        var wrap = operator.closest('.local-exammanager-formrow');
                        var value = wrap ? wrap.querySelector('.exammanager-profile-value') : null;
                        if (!value) {
                            return;
                        }
                        var disabled = operator.value === 'isempty' || operator.value === 'isnotempty';
                        value.disabled = disabled;
                        if (disabled) {
                            value.value = '';
                        }
                    });
                }

                if (restrictionKind) {
                    restrictionKind.addEventListener('change', syncRestrictionFields);
                }
                if (targetType) {
                    targetType.addEventListener('change', syncTargetPanels);
                }
                form.querySelectorAll('.exammanager-set-toggle input').forEach(function(toggle) {
                    toggle.addEventListener('change', syncSetFields);
                });
                form.querySelectorAll('.exammanager-profile-operator').forEach(function(operator) {
                    operator.addEventListener('change', syncProfileValues);
                });

                syncTargetPanels();
                syncRestrictionFields();
                syncSetFields();
                syncProfileValues();
                return null;
            }).catch(function() {
                return null;
            });
        }
    };
});
