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
 * Native course/quiz search autocomplete for the exam planning table.
 *
 * @module     local_exammanager/course_autocomplete
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/str'], function($, Ajax, Str) {

    var MIN_CHARS = 2;
    var PAGE_SIZE = 20;
    var SEARCH_DELAY = 250;

    var strings = {};

    /**
     * Load every UI string used by this module once, up front.
     *
     * @return {Promise}
     */
    function loadStrings() {
        return Str.get_strings([
            {key: 'searchnoresults', component: 'local_exammanager'},
            {key: 'searchloadmore', component: 'local_exammanager'},
            {key: 'searchloading', component: 'local_exammanager'},
            {key: 'searchinprogress', component: 'local_exammanager'},
            {key: 'searcherror', component: 'local_exammanager'},
            {key: 'quizplaceholder', component: 'local_exammanager'},
            {key: 'quiznotfound', component: 'local_exammanager'},
            {key: 'quizloaderror', component: 'local_exammanager'}
        ]).then(function(results) {
            strings = {
                noresults: results[0],
                loadmore: results[1],
                loading: results[2],
                searching: results[3],
                searcherror: results[4],
                quizplaceholder: results[5],
                quiznotfound: results[6],
                quizloaderror: results[7]
            };
            return strings;
        });
    }

    /**
     * Debounce a function call.
     *
     * @param {Function} fn Function to debounce.
     * @param {Number} delay Delay in milliseconds.
     * @return {Function}
     */
    function debounce(fn, delay) {
        var timer = null;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(context, args);
            }, delay);
        };
    }

    /**
     * Escape text for safe HTML insertion.
     *
     * @param {String} text Raw text.
     * @return {String}
     */
    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function closeAllDropdowns() {
        $('.local-exammanager-search-dropdown').hide();
    }

    function renderMessage($dropdown, message) {
        $dropdown.html('<div class="local-exammanager-search-item">' + escapeHtml(message) + '</div>').show();
    }

    function appendCourseItems($dropdown, items, state, $text, $hidden, $quiz) {
        if (!items || items.length === 0) {
            if (state.page === 0) {
                renderMessage($dropdown, strings.noresults);
            }
            return;
        }
        if (state.page === 0) {
            $dropdown.empty();
        }
        items.forEach(function(item) {
            var $item = $('<div>', {'class': 'local-exammanager-search-item', 'html': escapeHtml(item.label)});
            $item.on('click', function() {
                $text.val(item.label);
                $hidden.val(item.id);
                $dropdown.hide();
                loadQuizzes(item.id, $quiz);
            });
            $dropdown.append($item);
        });
        if (state.hasmore) {
            var $more = $('<div>', {
                'class': 'local-exammanager-search-item',
                'text': state.loadingMore ? strings.loading : strings.loadmore
            });
            $more.on('click', function() {
                if (state.loadingMore) {
                    return;
                }
                state.loadingMore = true;
                searchCourses(state, $text, $hidden, $dropdown, $quiz, true);
            });
            $dropdown.append($more);
        }
        $dropdown.show();
    }

    /**
     * Search courses through the local_exammanager_search_courses external
     * service. A monotonically increasing request id is used to discard
     * stale responses instead of aborting the underlying request.
     *
     * @param {Object} state Per-row search state.
     * @param {jQuery} $text Search text input.
     * @param {jQuery} $hidden Hidden course id input.
     * @param {jQuery} $dropdown Results dropdown container.
     * @param {jQuery} $quiz Quiz select for this row.
     * @param {Boolean} append Whether this is a "load more" continuation.
     */
    function searchCourses(state, $text, $hidden, $dropdown, $quiz, append) {
        var query = $.trim($text.val());
        if (query.length < MIN_CHARS) {
            $hidden.val('');
            $dropdown.hide();
            return;
        }
        if (!append) {
            state.page = 0;
            state.hasmore = false;
            state.loadingMore = false;
            renderMessage($dropdown, strings.searching);
        }

        var offset = state.page * PAGE_SIZE;
        var requestid = ++state.requestid;

        Ajax.call([{
            methodname: 'local_exammanager_search_courses',
            args: {query: query, limit: PAGE_SIZE, offset: offset}
        }])[0].done(function(data) {
            if (requestid !== state.requestid) {
                return;
            }
            var items = (data && data.results) || [];
            state.hasmore = !!(data && data.hasmore);
            appendCourseItems($dropdown, items, state, $text, $hidden, $quiz);
            if (items.length > 0) {
                state.page++;
            }
            state.loadingMore = false;
        }).fail(function() {
            if (requestid !== state.requestid) {
                return;
            }
            state.loadingMore = false;
            renderMessage($dropdown, strings.searcherror);
        });
    }

    /**
     * Load the quizzes of a course into its quiz select.
     *
     * @param {Number} courseid Course id.
     * @param {jQuery} $quiz Quiz select element.
     */
    function loadQuizzes(courseid, $quiz) {
        if (!courseid) {
            $quiz.html('<option value="">' + escapeHtml(strings.quizplaceholder) + '</option>');
            return;
        }
        $quiz.html('<option value="">' + escapeHtml(strings.loading) + '</option>');

        Ajax.call([{
            methodname: 'local_exammanager_get_quiz_list',
            args: {courseid: courseid}
        }])[0].done(function(data) {
            $quiz.html('<option value="">' + escapeHtml(strings.quizplaceholder) + '</option>');
            if (!data || data.length === 0) {
                $quiz.append('<option value="">' + escapeHtml(strings.quiznotfound) + '</option>');
                return;
            }
            data.forEach(function(q) {
                var quizid = parseInt(q.id, 10);
                var quizname = (q && typeof q.name !== 'undefined') ? String(q.name) : '';
                if (!Number.isInteger(quizid) || quizid <= 0) {
                    return;
                }
                var $option = $('<option>', {value: String(quizid), text: quizname});
                $quiz.append($option);
            });
        }).fail(function() {
            $quiz.html('<option value="">' + escapeHtml(strings.quizloaderror) + '</option>');
        });
    }

    function initRow($wrapper) {
        var row = $wrapper.data('row');
        var $text = $wrapper.find('.course-search-text');
        var $hidden = $wrapper.find('.course-id-hidden');
        var $dropdown = $wrapper.find('.local-exammanager-search-dropdown');
        var $quiz = $('#quiz-' + row);
        var state = {page: 0, hasmore: false, loadingMore: false, requestid: 0};
        var debouncedSearch = debounce(function() {
            $hidden.val('');
            $quiz.html('<option value="">' + escapeHtml(strings.quizplaceholder) + '</option>');
            searchCourses(state, $text, $hidden, $dropdown, $quiz, false);
        }, SEARCH_DELAY);

        $text.on('input', function() {
            var query = $.trim($text.val());
            if (query.length < MIN_CHARS) {
                $hidden.val('');
                $dropdown.hide();
                $quiz.html('<option value="">' + escapeHtml(strings.quizplaceholder) + '</option>');
                return;
            }
            debouncedSearch();
        });
        $text.on('focus', function() {
            var query = $.trim($text.val());
            if (query.length >= MIN_CHARS && $dropdown.children().length > 0) {
                $dropdown.show();
            }
        });
    }

    return {
        init: function() {
            loadStrings().then(function() {
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.local-exammanager-search-wrapper').length) {
                        closeAllDropdowns();
                    }
                });
                $('.local-exammanager-search-wrapper').each(function() {
                    initRow($(this));
                });
                return null;
            }).catch(function() {
                return null;
            });
        }
    };
});
